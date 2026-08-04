<?php


require '/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_implicit_flush( true );

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class SpreadsheetHandler
{
    private $spreadsheet;
    private $filePath;
    private $savePath;
    private $logPath;
    private $maxEmptyRows;

    public function __construct( $name = 'fasion' )
    {
        $this->savePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/images/{$name}/";
        $this->logPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/logs/{$name}.log";
        $this->outputDoc = "/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/{$name}".date('Y_m_d').'.xlsx';
        $this->maxEmptyRows = 3;

        $this->filePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/price_{$name}.xlsx";
        $this->spreadsheet = IOFactory::load( $this->filePath );
    }

    public function run()
    {
      $watchData = $this->getArticles( 'A','B', 6 );
      foreach ( $watchData as $brand => $articles ) {
        if ( $brand == 'Armani Exchange'){
          $brandF = 'armaniExchange';
        }else{
          $brandF = str_replace( ' ', '-', mb_strtolower($brand) );
        }

        $this->getImagesAllTime( $articles );
        $this->getImagesKronos( $brandF, $articles );
        $this->getImagesWatchard( $brandF, $articles );

      }
      $this->saveImages( 6, 1096, 'A','B', $this->outputDoc );
    }

    private function writeLog( $message )
    {
      file_put_contents( $this->logPath, print_r($message, true) . PHP_EOL, FILE_APPEND | LOCK_EX );
    }

    public function saveImages($startRow = 2, $maxRows = 15, $column = 'B', $articleColumn , $savePath)
    {

        $sheet = $this->spreadsheet->getActiveSheet();
        $row = $startRow;
        $processedRows = 0;
        $emptyCounter = 0;

        while ($processedRows < $maxRows and $row < 1100) {
            $value = $sheet->getCell("$articleColumn$row")->getValue();
            if ( empty($value) && $emptyCounter >= $this->maxEmptyRows) {
                echo "Прервано: найдено {$this->maxEmptyRows} пустых строк подряд";
                break;
            }
            if ( empty($value) ) {
                $processedRows++;
                $row++;
                continue;
            }
            $emptyCounter = 0;
            $imagePath = $this->savePath . $value . '.png';

            $this->writeLog('###');
            $this->writeLog( $value );
            $this->writeLog( $imagePath );

            if (file_exists($imagePath)) {
              $this->writeLog( 'Вставлено' );
              echo "Проверяем изображение: $value\n";

              try {
                // Проверка, что файл - изображение
                  $imageInfo = getimagesize($imagePath);
                  if ($imageInfo === false) {
                      throw new Exception("Файл $imagePath не является допустимым изображением.");
                  }

                  // Создаем менеджер изображений (можно выбрать GD или Imagick)
                  $manager = new ImageManager(new Driver()); // или new \Intervention\Image\Drivers\Imagick\Driver()

                  // Загружаем изображение и обрезаем пустые поля
                  $image = $manager->read( $imagePath );

                  // Тримминг: убираем прозрачные/белые края
                  // $image->trim(); // Раздувает картинку в 8 раз от исходного размера
                  // threshold: 0 — учитывает прозрачность
                  // tolerance: 0.1 — допуск для схожих цветов (можно настроить)
                  $image->toJpeg(75);
                  // Сохраняем во временный файл
                  $trimmedSavePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/test/exel/docs/img/trimmed_'.$value.'.jpg';
                  // $trimmedImagePath = tempnam(sys_get_temp_dir(), 'trimmed_') . '.png';
                  // $image->save($trimmedImagePath);
                  try {
                      $image->save($trimmedSavePath);
                      echo "Файл успешно сохранен: " . $trimmedSavePath . "\n";
                  } catch (Exception $e) {
                      echo "Ошибка при сохранении: " . $e->getMessage() . "\n";
                  }

                  // Вставляем обрезанное изображение в Excel
                  $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                  $drawing->setPath($trimmedSavePath);
                  $drawing->setHeight(140);
                  $drawing->setCoordinates("$column$row");

                  // Настраиваем размеры ячейки
                  $sheet->getRowDimension($row)->setRowHeight(120);
                  $sheet->getColumnDimension($column)->setWidth(16);

                  // Центрируем изображение
                  $drawing->setOffsetX((int)(($sheet->getColumnDimension($column)->getWidth() * 7.5 - $drawing->getWidth()) / 2));
                  $drawing->setOffsetY(10);
                  $drawing->setWorksheet($sheet);

                  // Удаляем временный файл
                  // unlink($trimmedSavePath);


              } catch (Exception $e) {
                  echo "Ошибка при обработке изображения $value: " . $e->getMessage() . "\n";
              }

            }
            $row++;
            $processedRows++;

        }

        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($savePath);

        echo 'Готово! Изображения добавлены и файл сохранен.';
    }

    public function getArticles($columnBrand = 'A', $columnArt = 'B', $startRow = 5)
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $articles = [];
        $row = $startRow;

        $emptyCounter = 0; // В прайсе есть строки, в которых только название группы (бренда)

        while (true) {

            $article = $sheet->getCell("$columnArt$row")->getValue();
            $brand = $sheet->getCell("$columnBrand$row")->getValue();

            if ( empty($article) && $emptyCounter >= $this->maxEmptyRows ) {
                break;
            }

            if ( empty($article) ) {
              $emptyCounter++;
              $row++;
              continue;
            }

            $data[ $brand ][] = $article;
            $row++;
            $emptyCounter = 0;
        }

        return $data;
    }

    public function getImagesWatchard( $brand, $articles = array())
    {
      if ( stripos($brand, ' ') !== false ){
        $arBrand = explode( ' ', mb_strtolower($brand) );
        $brandF = array_shift( $arBrand );
        foreach ( $arBrand as $chunk ){
          $brandFormatted .= ucfirst( $chunk );
        }
      }else{
        $brandFormatted = ucfirst($brand);
      }
      foreach ($articles as $article) {
          $imagePath = $this->savePath . $article . '.png';
          if (!file_exists($imagePath)) {
              $img_src = '';

              $url = 'https://watchard.com/search/'. $article;

              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL, $url);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
              curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
              curl_setopt($ch, CURLOPT_TIMEOUT, 10);

              $html = curl_exec($ch);
              if ($html === false) {
                  echo 'Ошибка cURL: ' . curl_error($ch) . '\n';
                  curl_close($ch);
                  continue; // Пропуск итерации
              }
              curl_close($ch);

              $dom = new DOMDocument();
              libxml_use_internal_errors(true);
              $dom->loadHTML($html);
              libxml_clear_errors();

              $xpath = new DOMXPath($dom);

              $productImageContainer = $xpath->query("//div[contains(@class, 'products-grid')]//li[contains(@class, 'product-item')][1]");

              if ($productImageContainer->length > 0) {
                   $productItemPhoto = $xpath->query(".//a[contains(@class, 'product-item-photo')]", $productImageContainer->item(0));

                   if ($productItemPhoto->length > 0) {
                       $dataSku = $productItemPhoto->item(0)->getAttribute("data-sku");

                       $searchArticle = $brandFormatted.'-'.$article;
                       if ($dataSku == $searchArticle) {
                           $productImage = $xpath->query(".//img", $productImageContainer->item(0));

                           if ($productImage->length > 0) {
                               $img_src = $productImage->item(0)->getAttribute("src");
                           }
                       }
                   }
                }


              if (!empty($img_src)) {
                  $imageData = file_get_contents($img_src);
                  if ($imageData !== false) {
                      file_put_contents($imagePath, $imageData);
                      echo "Изображение для артикула {$article} успешно загружено.\n";
                  } else {
                      echo "Ошибка при загрузке изображения для артикула {$article}.\n";
                  }
              } else {
                  echo "Изображение не найдено на странице для артикула {$article}.\n";
              }
              sleep( rand(1,5) );
          } else {
              echo "Изображение для артикула {$article} уже загружено.\n";
          }

          unset($img_src);
          unset($imagePath);
      }
   }

    public function getImagesAllTime($articles = array())
    {
        foreach ($articles as $article) {
            $imagePath = $this->savePath . $article . '.png';

            if (!file_exists($imagePath)) {
                $url = 'https://www.alltime.ru/search/?NAME='.$article.'&digiSearch=true/';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $html = curl_exec($ch);
                if ($html === false) {
                    echo 'Ошибка cURL: ' . curl_error($ch) . '\n';
                    curl_close($ch);
                    continue; // Пропуск итерации
                }
                curl_close($ch);

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($html);
                libxml_clear_errors();

                $xpath = new DOMXPath($dom);

                // Ищем элемент с классом .catalog
                $catalog = $xpath->query("//div[contains(@class, 'catalog')]");

                if ($catalog->length > 0) {
                    // Ищем элементы catalog-item внутри .catalog
                    $catalogItems = $xpath->query(".//div[contains(@class, 'catalog-item')]", $catalog->item(0));

                    if ($catalogItems->length > 0) {
                        // Берем первый попавшийся catalog-item
                        $firstCatalogItem = $catalogItems->item(0);

                        // Ищем catalog-item-photo-holder внутри catalog-item
                        $photoHolder = $xpath->query(".//div[contains(@class, 'catalog-item-photo-holder')]", $firstCatalogItem);

                        if ($photoHolder->length > 0) {
                            // Ищем тег img внутри catalog-item-photo-holder
                            $img = $xpath->query(".//img", $photoHolder->item(0));

                            if ($img->length > 0) {
                                // Получаем src изображения
                                $img_src = $img->item(0)->getAttribute('src');

                                if (!empty($img_src)) {
                                    //print_r($img_src);
                                    $imageData = file_get_contents($img_src);

                                    if ($imageData !== false) {
                                        file_put_contents($imagePath, $imageData);
                                        echo "Найдено изображение для артикула {$article}.\n";
                                    } else {
                                        echo "Ошибка при загрузке изображения для артикула {$article}.\n";
                                        continue; // Пропуск итерации
                                    }
                                }
                            } else {
                                echo "Не найден тег img для артикула {$article}.\n";
                                continue; // Пропуск итерации
                            }
                        } else {
                            echo "Не найден catalog-item-photo-holder для артикула {$article}.\n";
                            continue; // Пропуск итерации
                        }
                    } else {
                        echo "Не найдены catalog-item для артикула {$article}.\n";
                        continue; // Пропуск итерации
                    }
                } else {
                    echo "Не найден элемент .catalog для артикула {$article}.\n";
                    continue; // Пропуск итерации
                }
                sleep( rand(1,5) );
            } else {
                echo "Изображение для артикула {$article} уже загружено.\n";
            }
        }
        unset($img_src);
        unset($imagePath);
    }

    public function getImagesKronos( $brand = '', $articles = array())
    {
        foreach ($articles as $article) {
            $imagePath = $this->savePath . $article . '.png';

            if (!file_exists($imagePath)) {
                $url = "https://kronostime.ru/{$brand}_{$article}/";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $html = curl_exec($ch);
                if ($html === false) {
                    echo 'Ошибка cURL: ' . curl_error($ch) . '\n';
                    curl_close($ch);
                    continue; // Пропуск итерации
                }
                curl_close($ch);

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($html);
                libxml_clear_errors();

                $xpath = new DOMXPath($dom);

                // Проверяем наличие элемента с class="q-error"
                $q_error = $xpath->query('//*[contains(@class, "q-error")]');
                if ($q_error->length > 0) {
                    echo "Ошибка: Найден элемент с class=\"q-error\" для артикула {$article}.\n";
                    continue; // Пропуск итерации
                }

                // Ищем "product-gallery" -> "item" -> "img"
                $product_gallery = $xpath->query('//*[contains(@class, "product-gallery")]//*[contains(@class, "item")]/img');
                if ($product_gallery->length > 0) {
                    $img_src = $product_gallery[0]->getAttribute('src');
                    $img_src = 'https://kronostime.ru' . $img_src;
                    echo 'Найден src изображения: ' . $img_src . '\n';
                } else {
                    echo "Ошибка: Элемент с изображением не найден для артикула {$article}.\n";
                    continue; // Пропуск итерации
                }

                if (!empty($img_src)) {
                    $position = strpos($img_src, '?io');
                    if ($position !== false) {
                        $img_src = substr($img_src, 0, $position);
                    }
                    $imageData = file_get_contents($img_src);

                    if ($imageData !== false) {
                        file_put_contents($imagePath, $imageData);
                    } else {
                        echo "Ошибка при загрузке изображения для артикула {$article}.\n";
                        continue; // Пропуск итерации
                    }
                }
                sleep( rand(1,5) );
            } else {
                echo "Изображение для артикула {$article} уже загружено.\n";
            }
        }
        unset($img_src);
        unset($imagePath);
    }


}

( new SpreadsheetHandler($argv[1]) )->run();
