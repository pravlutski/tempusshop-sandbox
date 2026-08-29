<?php
#!/usr/bin/php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
	
set_time_limit(3600);
error_reporting(3600);

class CompetitorPriceParser
{
    private $db;
    private $uploadPath = '/upload/competitor_prices/';
    private $sshConfig = [
        'host' => '45.135.234.186',
        'user' => 'developer',
        'key_path' => '/var/www/bitrix/developer_key',
        'remote_path' => '/home/developer/upload/'
    ];
	
    public function __construct()
    {
		global $DB;
        $this->db = $DB;
    }
	
    private function downloadFilesViaSSH($arFiles)
    {
        $downloadedFiles = [];
        foreach ($arFiles as $file) {
			// проверяем путь к файлу. если локальный берем его сразу
			if (str_starts_with($file, $this->uploadPath)) {
				$filename = basename($file);
				$downloadedFiles[] = $filename;
				continue;
			}
			
            $remoteFile = "developer@45.135.234.186:/home/developer/upload/{$file}";
            $localFile = $_SERVER['DOCUMENT_ROOT'] . $this->uploadPath . $file;
            
            // Проверяем существует ли уже файл
            if (file_exists($localFile)) {
                unlink($localFile); // Удаляем старый файл
            }
            
            $command = "scp -i " . escapeshellarg($this->sshConfig['key_path']) . 
                       " -o StrictHostKeyChecking=no" .
                       " -o ConnectTimeout=30" .
                       " -o BatchMode=yes" .
                       " " . escapeshellarg($remoteFile) . 
                       " " . escapeshellarg($localFile) . 
                       " 2>&1";
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($localFile)) {
                $downloadedFiles[] = $file;
                $fileSize = filesize($localFile);
                $this->log("✅ Файл загружен: $file ($fileSize байт)");
            } else {
                $error = implode("; ", $output);
                $this->log("❌ Ошибка загрузки $file: $error (код: $returnCode)");
            }
        }
        
        return $downloadedFiles;
    }
	
    public function parseAllCompetitors()
    {
        $competitors = $this->getCompetitorsFromDB();
		
		$arFiles = array_column($competitors, 'PARSING_FILENAME');
		
        $this->log("Начало загрузки файлов по SSH...");
        $downloadedFiles = $this->downloadFilesViaSSH($arFiles);
        $this->log("Загружено файлов: " . count($downloadedFiles));
		
        $results = [];

        foreach ($competitors as $competitor) {
            if (empty($competitor['PARSING_FILENAME']) || $competitor['AUTO_PARSE'] != 'Y') {
                continue;
            }

            try {
                $result = $this->parseCompetitorFile($competitor);
                $results[$competitor['NAME']] = $result;
                
                $this->log("Успешно обработан: {$competitor['NAME']} - найдено " . count($result) . " цен");
                
            } catch (Exception $e) {
                $error = "Ошибка обработки {$competitor['NAME']}: " . $e->getMessage();
                $results[$competitor['NAME']] = ['error' => $error];
                $this->log($error);
            }
        }

        return $results;
    }

    public function parseSingleCompetitor($competitorId)
    {
        $competitor = $this->getCompetitorById($competitorId);
		
		$arFiles = [$competitor['PARSING_FILENAME']];
        $this->log("Начало загрузки файл {$competitor['PARSING_FILENAME']} по SSH.");
        $downloadedFiles = $this->downloadFilesViaSSH($arFiles);
        $this->log("Загружено файлов: " . count($downloadedFiles));
		
        if (!$competitor) {
            throw new Exception("Конкурент с ID {$competitorId} не найден");
        }

        if (empty($competitor['PARSING_FILENAME'])) {
            throw new Exception("Для конкурента не указан файл для парсинга");
        }

        $result = $this->parseCompetitorFile($competitor);
        $this->log("Обработан конкурент {$competitor['NAME']} - найдено " . count($result) . " цен");
        
        return $result;
    }

    private function getCompetitorsFromDB()
    {
        $sql = "SELECT * FROM ci_competitors WHERE PARSING_FILENAME != '' ORDER BY NAME";
        $result = $this->db->Query($sql);
        
        $competitors = [];
        while ($row = $result->Fetch()) {
            $competitors[] = [
                'ID' => $row['ID'],
                'AUTO_PARSE' => $row['AUTO_PARSE'],
                'NAME' => $row['NAME'],
                'PRICE_TYPE' => $row['PRICE_TYPE'],
                'MAPPING' => $row['MAPPING'] ? json_decode($row['MAPPING'], true) : [],
                'PARSING_FILENAME' => $row['PARSING_FILENAME'],
				'SETTINGS' => $row['SETTINGS'] ? json_decode($row['SETTINGS'], true) : [],
            ];
        }
        
        return $competitors;
    }

    private function getCompetitorById($competitorId)
    {
        $sql = "SELECT * FROM ci_competitors WHERE ID = " . intval($competitorId);
        $result = $this->db->Query($sql);
        
        if ($row = $result->Fetch()) {
            return [
                'ID' => $row['ID'],
                'NAME' => $row['NAME'],
                'PRICE_TYPE' => $row['PRICE_TYPE'],
                'MAPPING' => $row['MAPPING'] ? json_decode($row['MAPPING'], true) : [],
                'PARSING_FILENAME' => $row['PARSING_FILENAME'],
				'SETTINGS' => $row['SETTINGS'] ? json_decode($row['SETTINGS'], true) : [],
            ];
        }
        
        return null;
    }

	private function parseCompetitorFile($competitor)
	{
		$filename = $competitor['PARSING_FILENAME'];
		if (str_starts_with($filename, $this->uploadPath)) {
			$filename = basename($filename);
		}
		$filePath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadPath . $filename;

		if (!file_exists($filePath)) {
			throw new Exception("Файл {$filename} не найден");
		}

		$content = file_get_contents($filePath);
		
		if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
			$content = substr($content, 3);
			$this->log("Удален UTF-8 BOM из файла {$filename}");
		}
		
		$lines = explode("\n", $content);
		
		$prices = [];
		$mapping = $this->prepareMapping($competitor['MAPPING']);
		$alternativeArticle = $this->getAlternativeArticle();
		$articlesBX = $this->getArticlesBX();
		
		$arFail = [];
		foreach ($lines as $lineNumber => $line) {
			$line = trim($line);
			if (empty($line)) continue;

			$parts = explode(';', $line);
			if (count($parts) < 2) {
				$this->log("Пропущена строка {$lineNumber}: неверный формат");
				continue;
			}

			$competitorArticle = (string)trim($parts[0]);
			$price = trim($parts[1]);
			$productUrl = isset($parts[2]) ? trim($parts[2]) : '';
			
			//$hex = bin2hex($competitorArticle);
			//$this->log("Строка {$competitorArticle} {$lineNumber}: Артикул в hex = {$hex}");
			// артикул по маппингу
			$ourArticle = $this->findOurArticle($competitorArticle, $mapping);
			
			if (!$ourArticle && $alternativeArticle[$competitorArticle]) {
				$ourArticle = $alternativeArticle[$competitorArticle];
			}
			if (!$ourArticle && $articlesBX[$competitorArticle]) {
				$ourArticle = $competitorArticle;
			}

			//prent($mapping[$competitorArticle]);prent($competitorArticle);prent($mapping['465661387']);die;
			if ($ourArticle) {
				$cleanPrice = $this->cleanPrice($price);
				if ($cleanPrice > 0) {
					$prices[] = [
						'our_article' => $ourArticle,
						'price' => $cleanPrice,
						'brand_id' => 0,
						'competitor_article' => $competitorArticle,
						'competitor_name' => $competitor['NAME'],
						'price_type' => $competitor['PRICE_TYPE'],
						'product_url' => $productUrl,
					];
				}
			} else {
				$arFail[] = $competitorArticle;
			}
		}
		
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/dev/competitor.log', print_r($prices, true));
		file_put_contents("/var/www/bitrix_logs/debug/competitor/.last_parse_{$competitor['ID']}.log", print_r([$prices, $arFail], true));
		
		$prices = $this->cleanPrices($prices, $competitor['SETTINGS']['excluded_brands']);
		
		$stats = $this->savePricesToDB($prices, $competitor['NAME'], $competitor['PRICE_TYPE']);
		
		$this->writeShortLog($competitor['NAME'], $stats['added'], $stats['deleted']);
		
		$this->log("Не слинкованных: " . count($arFail) . "\r\n" . implode("\r\n", $arFail));
		
		return $prices;
	}
	
	private function getAlternativeArticle() {	
		global $DB;
		$strSql = "SELECT * FROM ci_catalog_artnumbers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$arAlternative[$row["alternative"]] = $row["artnumber"];
		}
		return $arAlternative;
	}
	
    private function getArticlesBX()
    {
		global $DB;
        $strSql = "
            SELECT 
                be.ID as PRODUCT_ID,
                bep.PROPERTY_123 as ARTICLE
            FROM b_iblock_element be
            INNER JOIN b_iblock_element_prop_s16 bep ON be.ID = bep.IBLOCK_ELEMENT_ID
            WHERE be.IBLOCK_ID = 16 
                AND be.IBLOCK_ID = 16
        ";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arArticle = array();
		while ($row = $results->Fetch()){
			$arArticle[$row["ARTICLE"]] = $row["PRODUCT_ID"];
		}
		return $arArticle;
    }
	
	private function cleanPrices($prices, $excludedBrands = []) {
		if (!$prices) return [];
		
		$arArticle = array_column($prices, 'our_article');
		
		$arFilter = [
			'IBLOCK_ID' => 16, 
			'PROPERTY_CML2_ARTICLE' => $arArticle
		];
		$rs = CIBlockElement::GetList(['NAME' => 'ASC'], $arFilter, false, false, ['ID', 'PROPERTY_BRAND', 'PROPERTY_CML2_ARTICLE']);
		$matchBrand = [];
		while($ar = $rs->GetNext()){
			$matchBrand[$ar['PROPERTY_CML2_ARTICLE_VALUE']] = $ar['PROPERTY_BRAND_VALUE'];
		}

		if (is_array($excludedBrands) && count($excludedBrands) > 0) {
			$priceNew = [];
			
			foreach ($prices as &$item) {
				if ($matchBrand[$item['our_article']] && !in_array($matchBrand[$item['our_article']], $excludedBrands)) {
					$item['brand_id'] = $matchBrand[$item['our_article']];
					$priceNew[] = $item;
				}
			}
			unset($item);
			
			return $priceNew;
		}
		
		foreach ($prices as &$item) {
			if ($matchBrand[$item['our_article']]) {
				$item['brand_id'] = $matchBrand[$item['our_article']];
			}
		}
		unset($item);
		
		return $prices;
	}
	
    private function prepareMapping($mappingData)
    {
        $mapping = [];
        
        if (is_array($mappingData)) {
            foreach ($mappingData as $item) {
                if (strpos($item, ';') !== false) {
                    list($our, $competitor) = explode(';', $item, 2);
                    $mapping[(string)trim($competitor)] = (string)trim($our);
                }
            }
        }
        
        return $mapping;
    }

    private function findOurArticle($competitorArticle, $mapping)
    {
        if (isset($mapping[$competitorArticle])) {
            return $mapping[$competitorArticle];
        }

        return null;
    }

    private function cleanPrice($price)
    {
        $clean = preg_replace('/[^\d,\.]/', '', $price);
        $clean = str_replace(',', '.', $clean);
        
        return floatval($clean);
    }

	private function savePricesToDB($prices, $competitorName, $priceType)
	{
		$stats = [
			'added' => 0,
			'deleted' => 0,
			'updated' => 0
		];
		
		if (empty($prices)) {
			$stats['deleted'] = $this->cleanupOldPrices($competitorName, $priceType);
			return $stats;
		}

		$timestamp = date('Y-m-d H:i:s');
		
		$ourArticles = array_column($prices, 'our_article');
		
		$existingPrice = $this->getExistingPrice($ourArticles, $competitorName, $priceType);
		$existIds = array_column($existingPrice, 'ID');
		
		$coInvest = 0;
		if ($priceType == 'wb') {
			$coInvest = (float) COption::GetOptionString("panel.manager", "PRICEUPDATE_CO_INVEST_WB");
		}
		
		foreach ($prices as $priceData) {
			$ourArticle = $priceData['our_article'];
			$newPrice = floatval($priceData['price']);
			$productUrl = $priceData['product_url'] ?? '';
			
			if ($coInvest > 0) {
				$newPrice = $newPrice / (1 - $coInvest / 100);
			}
			
			if ($existingPrice[$ourArticle]) {
				$previousPrice = floatval($existingPrice[$ourArticle]['PRICE']);
				$previousUrl = $existingPrice[$ourArticle]['PRODUCT_URL'];
				
				$updateData = [
					'PRICE' => $newPrice,
					'DATE_UPDATE' => $timestamp,
				];
				
				if (!empty($productUrl) && $previousUrl != $productUrl) {
					$updateData['PRODUCT_URL'] = $productUrl;
				}
				
				if ($newPrice != $previousPrice) {
					$updateData['PREVIOUS_PRICE'] = $previousPrice;
					$stats['updated']++;
				} else {
					continue;
				}
				
				$this->updatePrice($ourArticle, $competitorName, $priceType, $updateData);
			} else {
				
				$id = $this->insertPrice([
					'ARTICLE' => $ourArticle,
					'BRAND_ID' => $priceData['brand_id'],
					'PRICE_TYPE' => $priceType,
					'COMPETITOR_NAME' => $competitorName,
					'PRICE' => $newPrice,
					'PREVIOUS_PRICE' => 0,
					'PRODUCT_URL' => $productUrl,
					'DATE_CREATE' => $timestamp,
					'DATE_UPDATE' => $timestamp
				]);
				
				if ($id) {
					$stats['added']++;
					$existIds[] = $id;
				}
			}
		}
		//prent($existIds);
		
		$stats['deleted'] = $this->cleanupMissingPrices($competitorName, $priceType, $existIds);
		
		return $stats;
	}

	private function getExistingPrice($arArticle, $competitorName, $priceType)
	{
		$ar = [];
		$sql = "SELECT ID, ARTICLE, PRICE, PREVIOUS_PRICE, PRODUCT_URL 
				FROM ci_price_competitor 
				WHERE ARTICLE IN ('" . implode("','", $arArticle) . "') 
				AND COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
				AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "'";
        
		$result = $this->db->Query($sql);
		
		while ($row = $result->Fetch()) {
            $ar[$row['ARTICLE']] = [
				'ID' => $row['ID'],
				'ARTICLE' => $row['ARTICLE'],
				'PRICE' => $row['PRICE'],
				'PREVIOUS_PRICE' => $row['PREVIOUS_PRICE'],
				'PRODUCT_URL' => $row['PRODUCT_URL'],
			];
        }
		
		return $ar;
	}

	private function updatePrice($article, $competitorName, $priceType, $updateData)
	{
		$updates = [];
		foreach ($updateData as $field => $value) {
			if (is_string($value)) {
				$updates[] = $field . " = '" . $this->db->ForSql($value) . "'";
			} else {
				$updates[] = $field . " = " . floatval($value);
			}
		}
		
		$sql = "UPDATE ci_price_competitor 
				SET " . implode(', ', $updates) . "
				WHERE ARTICLE = '" . $this->db->ForSql($article) . "' 
				AND COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
				AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "'";
		
		return $this->db->Query($sql);
	}
	private function insertPrice($data)
	{
		$fields = [];
		$values = [];
		
		foreach ($data as $field => $value) {
			if (is_string($value)) {
				$value = "'" . $this->db->ForSql($value) . "'";
			} else {
				$value = floatval($value);
			}
			$fields[$field] = $value;
		}
		
		return $this->db->Insert("ci_price_competitor", $fields, $err_mess.__LINE__);
	}

	private function cleanupMissingPrices($competitorName, $priceType, $existIds)
	{
		if (empty($existIds)) {
			return $this->cleanupOldPrices($competitorName, $priceType);
		}

		// Получаем количество записей для удаления
		$countSql = "SELECT COUNT(*) as CNT FROM ci_price_competitor 
					WHERE COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
					AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "' 
					AND ID NOT IN ('" . implode("','", $existIds) . "')";
		
		$countResult = $this->db->Query($countSql);
		$countRow = $countResult->Fetch();
		$deletedCount = $countRow['CNT'];

		$sql = "DELETE FROM ci_price_competitor 
				WHERE COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
				AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "' 
				AND ID NOT IN ('" . implode("','", $existIds) . "')";

		$result = $this->db->Query($sql);
		
		$this->log("Удалено устаревших позиций для {$competitorName}: {$deletedCount}");
		
		return $deletedCount;
	}

	private function cleanupOldPrices($competitorName, $priceType)
	{
		$countSql = "SELECT COUNT(*) as CNT FROM ci_price_competitor 
					WHERE COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
					AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "'";
		
		$countResult = $this->db->Query($countSql);
		$countRow = $countResult->Fetch();
		$deletedCount = $countRow['CNT'];

		$sql = "DELETE FROM ci_price_competitor 
				WHERE COMPETITOR_NAME = '" . $this->db->ForSql($competitorName) . "' 
				AND PRICE_TYPE = '" . $this->db->ForSql($priceType) . "'";
		
		$result = $this->db->Query($sql);

		$this->log("Удалены все позиции для {$competitorName} (файл пустой или не найден): {$deletedCount} записей");
		
		return $deletedCount;
	}

    private function log($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/competitor_prices/parser.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage; // Для вывода в консоль
    }
	
	private function writeShortLog($competitorName, $added, $deleted)
	{
		$logFile = $_SERVER['DOCUMENT_ROOT'] . "/upload/competitor_prices/{$competitorName}.log";
		$timestamp = date('d.m.Y H:i:s');
		$logMessage = "{$timestamp} {$competitorName} Удалено {$deleted}, добавлено {$added}\n";
		
		file_put_contents($logFile, $logMessage, LOCK_EX);
	}
}

if (php_sapi_name() === 'cli') {
    $parser = new CompetitorPriceParser();
    
    $competitorId = null;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $competitorId = intval($argv[1]);
    }
    
    try {
        if ($competitorId) {
            echo "Запуск парсинга для конкурента ID: {$competitorId}\n";
            $result = $parser->parseSingleCompetitor($competitorId);
            echo "Успешно обработано: " . count($result) . " позиций\n";
        } else {
            echo "Запуск парсинга всех конкурентов\n";
            $results = $parser->parseAllCompetitors();
            $total = 0;
            foreach ($results as $competitor => $data) {
                if (!isset($data['error'])) {
                    $total += count($data);
                }
            }
            echo "Всего обработано позиций: {$total}\n";
        }
    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
        exit(1);
    }
    
} else {
    header('Content-Type: text/plain; charset=utf-8');
    
    $parser = new CompetitorPriceParser();
    $competitorId = isset($_GET['competitor_id']) ? intval($_GET['competitor_id']) : null;
    
    try {
        if ($competitorId) {
            echo "Запуск парсинга для конкурента ID: {$competitorId}\n";
            echo "=========================================\n";
            $result = $parser->parseSingleCompetitor($competitorId);
            echo "✅ Успешно обработано: " . count($result) . " позиций\n";
            
            if (!empty($result)) {
                echo "\nОбработанные позиции:\n";
                foreach ($result as $item) {
                    echo " - {$item['our_article']}: {$item['price']} руб.\n";
                }
            }
        } else {
            echo "Запуск парсинга всех конкурентов\n";
            echo "================================\n";
            $results = $parser->parseAllCompetitors();
            $total = 0;
            
            foreach ($results as $competitor => $data) {
                if (isset($data['error'])) {
                    echo "❌ {$competitor}: {$data['error']}\n";
                } else {
                    $count = count($data);
                    $total += $count;
                    echo "✅ {$competitor}: {$count} позиций\n";
                }
            }
            echo "\n📊 Всего обработано позиций: {$total}\n";
        }
    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        http_response_code(500);
    }
}
?>