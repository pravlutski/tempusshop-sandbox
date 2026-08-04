<?php
error_reporting(0);
$token = trim(file_get_contents('token.txt'));

$scraped_products_map = [];

function refreshToken() {
    global $token;
    echo "\n⚠ Токен устарел или невалиден. Запускаем get_token.py...\n";
    
    exec('python get_token.py'); 
    
    sleep(1);
    
    if (file_exists('token.txt')) {
        $token = trim(file_get_contents('token.txt'));
        echo "✓ Токен обновлен: " . substr($token, 0, 15) . "...\n";
        return true;
    } else {
        echo "❌ Ошибка: файл token.txt не найден - ошибка в правах.\n";
        return false;
    }
}

function fetchPageWithRetry($page, $max_retries = 3) {
    global $token;
    $attempt = 0;
    
    while ($attempt < $max_retries) {
        $attempt++;
        echo "Страница $page, попытка $attempt... ";
        
        $ch = curl_init();
        
        $url = "https://www.wildberries.ru/__internal/u-catalog/sellers/v4/catalog?ab_testing=false&appType=1&curr=rub&dest=-1257786&fbrand=172&hide_dtype=9&hide_vflags=4294967296&inheritFilters=false&lang=ru&page=$page&sort=popular&spp=30&supplier=60227";
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER,[
            'accept: */*',
            'accept-language: ru,en;q=0.9,de;q=0.8',
            'Cookie: x_wbaas_token='.$token.';  _cp=1',
            'priority: u=1, i',
            'referer: https://www.wildberries.ru/seller/60227?sort=popular&page=2&fbrand=172',
            'sec-ch-ua: "Chromium";v="142", "YaBrowser";v="25.12", "Not_A Brand";v="99", "Yowser";v="2.5"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 YaBrowser/25.12.0.0 Safari/537.36',
            'x-requested-with: XMLHttpRequest',
            'x-spa-version: 13.19.4',
        ]);
        
        $response = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        // ОБРАБОТКА ИСТЕКШЕГО ТОКЕНА
        if ($http_code == 401 OR $http_code == 498 ) {
            if (refreshToken()) {
                $attempt--;
                continue;
            } else {
                return false;
            }
        }
        
        // Проверка ошибок curl
        if ($curl_errno !== 0) {
            echo "ОШИБКА CURL ($curl_errno): $curl_error\n";
            if ($attempt < $max_retries) {
                sleep(2 * $attempt);
                continue;
            }
            return false;
        }
        
        // Проверка HTTP кода
        if ($http_code !== 200) {
            echo "ОШИБКА HTTP: код $http_code\n";
            if ($attempt < $max_retries) {
                sleep(2 * $attempt);
                continue;
            }
            return false;
        }
        
        // Проверка пустого ответа
        if (empty($response)) {
            echo "ПУСТОЙ ОТВЕТ\n";
            if ($attempt < $max_retries) {
                sleep(2 * $attempt);
                continue;
            }
            return false;
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "ОШИБКА JSON: " . json_last_error_msg() . "\n";
            if ($attempt < $max_retries) {
                sleep(2 * $attempt);
                continue;
            }
            return false;
        }
        
        if (!isset($data['products']) || !is_array($data['products'])) {
            echo "НЕКОРРЕКТНАЯ СТРУКТУРА ДАННЫХ\n";
            if ($attempt < $max_retries) {
                sleep(2 * $attempt);
                continue;
            }
            return false;
        }
        
        echo "OK\n";
        return $data;
    }
    
    return false;
}

$fp_all = fopen('all_product.csv', 'w');
fprintf($fp_all, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($fp_all, ['Ссылка', 'Название', 'Цена', 'Цена без скидки', 'Остаток'], ';');

$page = 1;
$total_products = 0;
$failed_pages = [];

echo "=== Старт парсинга ===\n";

do {
    $data = fetchPageWithRetry($page);
    
    if ($data === false) {
        echo "❌ Страница $page не удалась после всех попыток!\n";
        $failed_pages[] = $page;
        $page++;
        if (count($failed_pages) > 5) break; 
        continue;
    }
    
    if ($page == 1) {
        $total_products = $data['total'];
        echo "Всего товаров в каталоге: $total_products\n\n";
    }
    
    foreach ($data['products'] as $product) {
        $id = (string)$product['id'];
        $link = 'https://www.wildberries.ru/catalog/' . $id . '/detail.aspx';
        $name = $product['name'];
        // Цена текущая
        $price = isset($product['sizes'][0]['price']['product']) 
            ? $product['sizes'][0]['price']['product'] / 100 
            : 0;
        
        // Цена базовая
        $priceBasic = isset($product['sizes'][0]['price']['basic']) 
            ? $product['sizes'][0]['price']['basic'] / 100 
            : 0;
            
        $quantity = $product['totalQuantity'];
        
        fputcsv($fp_all, [$link, $name, $price, $priceBasic, $quantity], ';');
        
        $scraped_products_map[$id] = [
            'price' => $price,       // Текущая цена
            'old_price' => $priceBasic, // Цена до скидки
            'qty' => $quantity       // Остаток
        ];
    }
    
    if (empty($data['products'])) {
        break;
    }
    
    $page++;
    sleep(1); 
    
} while (true);

fclose($fp_all);

echo "\n✓ Сбор данных завершен. Найдено товаров: " . count($scraped_products_map) . "\n";
echo "Файл с полным списком: all_product.csv\n";

echo "\n=== Обновление файла цен (price_product.csv) ===\n";

$inputFile = 'file_price.csv';
$outputFile = 'price_product.csv';

if (!file_exists($inputFile)) {
    echo "❌ Файл $inputFile не найден!\n";
    exit;
}

$handleIn = fopen($inputFile, "r");
$handleOut = fopen($outputFile, "w");

fprintf($handleOut, chr(0xEF).chr(0xBB).chr(0xBF));

$rowNumber = 0;
while (($row = fgetcsv($handleIn, 0, ";")) !== FALSE) {
    $rowNumber++;

    if ($rowNumber == 1) {
        $newHeader = [];
        $newHeader[] = $row[0];
        $newHeader[] = "Текущая цена";
        $newHeader[] = "Цена без скидки";
        $newHeader[] = "Остаток";
        
        for ($i = 1; $i < count($row); $i++) {
            $newHeader[] = $row[$i];
        }
        
        fputcsv($handleOut, $newHeader, ";");
        continue;
    }
    

    $article = (string)$row[0];
    
    $insertPrice = "";
    $insertOldPrice = "";
    $insertQty = "";
    
    if (isset($scraped_products_map[$article])) {
        $insertPrice = $scraped_products_map[$article]['price'];
        $insertOldPrice = $scraped_products_map[$article]['old_price'];
        $insertQty = $scraped_products_map[$article]['qty'];
    }
    
    $newRow = [];
    $newRow[] = $row[0];
    $newRow[] = $insertPrice;
    $newRow[] = $insertOldPrice;
    $newRow[] = $insertQty;
    
    for ($i = 1; $i < count($row); $i++) {
        $newRow[] = $row[$i];
    }
    
    fputcsv($handleOut, $newRow, ";");
}

fclose($handleIn);
fclose($handleOut);

echo "✓ Файл price_product.csv успешно создан!\n";
?>