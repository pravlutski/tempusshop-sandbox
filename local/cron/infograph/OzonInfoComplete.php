#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");
set_time_limit(0);

set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
use Intervention\Image\ImageManagerStatic as Image;

class InfoGraph{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel;
		$this->module = 'newInfoGraph';
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$logFile = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt';
		$logContent = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$logTime = trim($logContent[0]);
		$this->logTimestamp = strtotime($logTime);

		if (empty($this->logTimestamp)) {
			$this->logTimestamp = strtotime("-3 days");
		}


		$arStat = [
			'status' => 'IN_PROCESS',
			'status_text' => 'Собираем массив товаров с фото',
			'percent' => '5',
			'time_start' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );

		$this->getItems();

		$totalItems = count($this->items);
		$chunkSize = ceil($totalItems / 9);
		$currentChunk = 0;
		$processedItems = 0;

		foreach($this->items as $key => &$arItem){

			$processedItems++;

			if ($processedItems % $chunkSize === 0 || $processedItems === $totalItems) {
				$currentChunk++;
				$progress = min(round(($processedItems / $totalItems) * 100, 2), 100);

				$this->updateStatus( $this->module, ['status_text' => 'Генерация инфографики', 'percent' => $progress] );
			}

			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r('#######' , true).PHP_EOL, FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r('Обновляем модель: '.$arItem['ARTICLE'] , true).PHP_EOL, FILE_APPEND);
			if (!empty($arItem['INFO_BASE'])) {
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r('У модели есть изображение INFO_BASE запускатеся метод генерации новой инфографики' , true).PHP_EOL, FILE_APPEND);
				iF ($arItem['BRAND_ID'] == '7971') {
					$this->createInfoGrahpNewCasio($arItem);
				} else {
					$this->createInfoGrahpNew($arItem);
				}

			} else {
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r('У модели отсутствует изображение INFO_BASE запускатеся метод генерации старой инфографики' , true).PHP_EOL, FILE_APPEND);
				$this->createInfoGrahpOld($arItem);
			}

		}

		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => '100',
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );


	}

	private function getXmlIds():array|bool
	{
		$path = "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/listGenOzon.json";
		if ( !file_exists($path) ) return false;

		$json = file_get_contents($path);
		$result = json_decode( $json, true );

		return $result ?? false;
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r(date('Y-m-d H:i:s'), true).PHP_EOL);

		// var_dump( count($models) );
		// die;
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE","PROPERTY_DIAMETER","PROPERTY_INFOOZON_IMAGE");

		$arFilter = Array(
      "IBLOCK_ID" => 16,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			// "PROPERTY_CML2_ARTICLE" => $models,
			//"PROPERTY_BRAND" => [43508],
			// "ID" => 181520,
			// 'XML_ID' => 177597,
			//"!XML_ID" => [181721,145476,181717,145486,181694,145536,145537,181560,181516,181509,181508,181481,181397,181471,181399,181402,207893,206353]
    );
		// $addData = $this->getXmlIds();
		// if ( $addData ){
		// 	$arFilter['XML_ID'] = $addData;
		// }

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){

      $this->items[] = [
        "ID" => $el["ID"],
        "BRAND_ID" => $el["PROPERTY_BRAND_VALUE"],
        "COLLECTION" => $el["PROPERTY_COLLECTION_VALUE"],
        "ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLORTERM" => $el["PROPERTY_COLORTERM_VALUE"],
        'TYPE' => array_shift($el['PROPERTY_126_VALUE']),
        'DETAIL_PICTURE' => $el['DETAIL_PICTURE'],
        "TIMESTAMP_X" => $el['TIMESTAMP_X'],
        "CATALOG_QUANTITY" => $el['CATALOG_QUANTITY'],
        "COLORTERM_UPDATE" => "",
        "IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
        "NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
        "RICH_DESC" => $el['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
				// Если Мехнизм все еще множественное свойство, то берем первый элемент
        "MECHANISM" => is_array( $el['PROPERTY_MECHANISM_VALUE'] ) ? reset( $el['PROPERTY_MECHANISM_VALUE'] ) : $el['PROPERTY_MECHANISM_VALUE'],
				"DIAMETER" => $el['PROPERTY_DIAMETER_VALUE'],
        "GLASS" => $el['PROPERTY_GLASS_VALUE'],
        "CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
        "WR" => $el['PROPERTY_WR_VALUE'],
        "FACE" => $el['PROPERTY_FACE_ENUM_ID'],
        "WARRANTY" => $el['PROPERTY_WARRANTY_VALUE'],
        "INFOOZON_IMAGE" => $el['PROPERTY_INFOOZON_IMAGE_VALUE'],
				"INFO_BASE" => $el['PROPERTY_INFOGRAPH_BASE_VALUE'],
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
		// var_dump($this->items);
		// die;
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt", print_r('Всего эл-ов:'.count($this->items) , true).PHP_EOL, FILE_APPEND);
	}


	public function createInfoGrahpNewCasio($arItem) {
			try {
					$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

					if (!empty($arItem['INFO_BASE'])) {
							$baseImagePath = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
					}

					$dateM = filectime($baseImagePath);


					// if ($this->logTimestamp > $dateM) {
					// 	file_put_contents(
					// 			"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
					// 			'Изображение INFO_BASE не обновлялось с последней проверки. Обновление не требуется.'. PHP_EOL,
					// 			FILE_APPEND
					// 	);
					// 	return false;
					// }


			    $desiredWidth = 646;
			    $desiredHeight = 944;


					$resizeHold = false;
					$p = 0;
					$k = 0.68;
	        $imageBase = Image::make($baseImagePath);


	        if ($imageBase->height() >= $desiredHeight) {
	            $imageBase->resize(null, $desiredHeight, function ($constraint) {
	                $constraint->aspectRatio();
	            });
	        }

	        if ($imageBase->width() >= $desiredWidth) {
	            $imageBase->resize($desiredWidth, null, function ($constraint) {
	                $constraint->aspectRatio();
	            });

	            if ($imageBase->height() < $desiredHeight) {
	                $imageBase->resizeCanvas(null, $desiredHeight, 'center', false, [255, 255, 255, 0]);
	            }
	        } else {

							$resizeHold = true;
							$d = round($imageBase->width() / $imageBase->height(),2);

							if ($d>$k) {
								$imageBase->resizeCanvas(null, round($imageBase->width() / $k), 'center', false, [255, 255, 255, 0]);
							} else if ($d<$k) {
								$imageBase->resizeCanvas(round($imageBase->height() * $k), null, 'center', false, [255, 255, 255, 0]);
							}
							$p = round($imageBase->width() / $desiredWidth,2);

	        }

					// $outputPath = 'test.png';
			 		// $imageBase->save($outputPath);

					$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/background.png';
					$image = $manager->make($background);

					if (!$resizeHold) {
						$image->insert(
								$imageBase,
								'top-left',
								30,
								200,
						);


						if (!empty($arItem['BRAND_ID'])) {
								$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/'.$arItem['BRAND_ID'].'.png';
								$image->insert(
										$brandWater,
										'top-left',
										0,
										0,
								);
						}
					}

					switch ($arItem['MECHANISM']) {
							case 'Кварцевые':
									$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/quartz.png';
									break;
							case 'Автоматические с ручным подзаводом':
									$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/automatic_podzavod.png';
									break;
							default:
									$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/quartz.png';
									break;
					}

					$image->insert(
							$mechWater,
							'top-left',
							0,
							0,
					);

					$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/waranty.png';

					$image->insert(
							$warWater,
							'top-left',
							0,
							0,
					);
					if (!empty($arItem['GLASS'])) {
							switch ($arItem['GLASS']) {
									case 'Минеральное':
											$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/mineralnoe.png';
											break;
									case 'Органическое':
											$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/organic.png';
											break;
									case 'Сапфировое':
											$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/sapphire.png';
											break;
									case 'Пластиковое':
											$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/plastic.png';
											break;
									default:
											break;
							}

							$image->insert(
									$glassWater,
									'top-left',
									0,
									0,
							);
					}
					print_r("5");
					if (!empty($arItem['DIAMETER'])) {
							$diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/diameter/'.round($arItem['DIAMETER']).'.png';
							if (file_exists($diamWater)) {
									$image->insert(
											$diamWater,
											'top-left',
											0,
											0,
									);
							}
					}


					if($resizeHold) {
							$image->resize(round(933 * $p), round(1244 * $p), function ($constraint) {
							    $constraint->aspectRatio();
							    $constraint->upsize();
							});

							$image->resizeCanvas(round(933 * $p), round(1244 * $p), 'center', false, [255, 255, 255, 0]);

							$image->insert(
									$imageBase,
									'top-left',
									round(30 * $p),
									round(200 * $p),
							);


							if (!empty($arItem['BRAND_ID'])) {
									$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/casio/'.$arItem['BRAND_ID'].'.png';

									$watermark = Image::make($brandWater);
									$watermarkWidth = round(933 * $p);
									$watermarkHeight = round(1244 * $p);
									$watermark->resize($watermarkWidth, $watermarkHeight, function ($constraint) {
									    $constraint->aspectRatio();
									    $constraint->upsize();
									});

									$image->insert(
											$watermark,
											'top-left',
											0,
											0,
									);
							}

					}
					//
					// $outputPath = 'test2.png';
			 		// $image->save($outputPath);
					// die();

					$image->encode('jpg');
					$image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg');
					$new_url = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg';
					if (file_exists($new_url)) {
							$file = new \CFile;
							$fileId = $file->SaveFile(CFile::MakeFileArray($new_url),'info_graph_image');
							if (!empty($fileId)) {
									CFile::Delete($arItem["INFOOZON_IMAGE"]);
									CIBlockElement::SetPropertyValueCode($arItem['ID'], "INFOOZON_IMAGE", array('VALUE' => $fileId));
									file_put_contents(
											"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
											'Инфографика успешно обновлена!' . PHP_EOL,
											FILE_APPEND
									);
							}
					}
			} catch (Exception $e) {
					file_put_contents(
							"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
							'Ошибка при обработке элемента: ' . $e->getMessage() . PHP_EOL,
							FILE_APPEND
					);
					return false;
			}

			return true;
	}

	public function createInfoGrahpOld($arItem) {
		try {
			$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);


        if (!empty($arItem['DETAIL_PICTURE'])) {
            $fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
            $dateM = filectime($fileDetail);

						// if ($this->logTimestamp > $dateM) {
						// 	file_put_contents(
						// 			"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
						// 			'Детальное изображение не обновлялось с последней проверки. Обновление не требуется.'. PHP_EOL,
						// 			FILE_APPEND
						// 	);
						// 	return false;
						// }


            $id = $arItem['ID'];
            $desiredWidth = 685;
            $desiredHeight = 930;

            $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
            $r0_main = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';

            if (file_exists($r0_main)) {
                unlink($r0_main);
            }

            list($originalWidthD, $originalHeightD) = getimagesize($fileDetail);


            $image = $manager->make($file);

            if ($originalWidthD > 653 && $originalWidthD == $originalHeightD) {
                $image->fit($desiredWidth, $desiredHeight);
            } else {
                $image->resize($desiredWidth, $desiredHeight, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            $image->save($r0_main);


            $image = $manager->make($r0_main);
            $checkWidth = $image->width();

            if ($checkWidth < 685) {
                $srcWidth = $image->width();
                $srcHeight = $image->height();
                $needed_width = 685 - $srcWidth;
                $needed_width_p = $needed_width / 2;

                $newImage = $manager->canvas(685, $srcHeight, '#ffffff');
                $newImage->insert($image, 'left', intval($needed_width_p));

                $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';
                $newImage->save($r0_main_r);
            } else {
                $r0_main_r = $r0_main;
            }


            $image = $manager->make($r0_main_r);
            $checkHeight = $image->height();

            if ($checkHeight < 930) {
                $srcWidth = $image->width();
                $srcHeight = $image->height();
                $needed_height = 930 - $srcHeight;
                $needed_height_p = $needed_height / 2;

                $newImage = $manager->canvas($srcWidth, 930, '#ffffff');
                $newImage->insert($image, 'top', intval($needed_height_p));

                $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-hehight-'.$id.'.jpg';
                $newImage->save($r0_main_r);
            }


            $image = $manager->make($r0_main_r);
            $newImage = $manager->canvas($image->width() + 18, $image->height() + 224, '#ffffff');
            $newImage->insert($image, 'top-left', 18, 224);
            $r0_top_left = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_top_left-'.$id.'.jpg';
            $newImage->save($r0_top_left);


            $image = $manager->make($r0_top_left);
            $newImage = $manager->canvas($image->width() + 229, $image->height() + 70, '#ffffff');
            $newImage->insert($image, 'top-left');
            $r0_bot_right = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_bot_right-'.$id.'.jpg';
            $newImage->save($r0_bot_right);

            $resized = $r0_bot_right;
            $image = $manager->make($resized);
            $srcWidth = $image->width();
            $srcHeight = $image->height();


            if (!empty($arItem['BRAND_ID'])) {
                $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/brand_ozon/'.$arItem['BRAND_ID'].'.png';

                if (file_exists($brandWater)) {
                    $image->insert($brandWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                }
            }


            if (!empty($arItem['MECHANISM']) && isset($resized)) {
                $image = $manager->make($resized);
                $mechWater = null;

                switch ($arItem['MECHANISM']) {
                    case 'Кварцевые':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/qartz.png';
                        break;
                    case 'Механические':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/mehanicheskie.png';
                        break;
                    case 'Автоматические с ручным подзаводом':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/automatic_podzavod.png';
                        break;
                    case 'Автоматические':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/automatic.png';
                        break;
                    case 'Автокварц (кинетик)':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/autoqartz.png';
                        break;
                    case 'Процессор':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/processor.png';
                        break;
                }

                if ($mechWater && file_exists($mechWater)) {
                    $image->insert($mechWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                }
            }


            if (!empty($arItem['WARRANTY']) && isset($resized)) {
                $image = $manager->make($resized);
                $warWater = null;

                switch ($arItem['WARRANTY']) {
                    case '1 год':
                    case '1 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_1_god.png';
                        break;
                    case '2 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_2_goda.png';
                        break;
                    case '3 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_3_goda.png';
                        break;
                }

                if ($warWater && file_exists($warWater)) {
                    $image->insert($warWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                }
            }


            if (!empty($arItem['GLASS']) && isset($resized)) {
                $image = $manager->make($resized);
                $glassWater = null;

                switch ($arItem['GLASS']) {
                    case 'Минеральное':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/mineralnoe.png';
                        break;
                    case 'Органическое':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/organicheskoe.png';
                        break;
                    case 'Сапфировое':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/sapfirovoe.png';
                        break;
										case 'Пластиковое':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/plastic.png';
                        break;
                }

                if ($glassWater && file_exists($glassWater)) {
                    $image->insert($glassWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                }
            }


            if (!empty($arItem['WR']) && isset($resized)) {
                $image = $manager->make($resized);
                $wrWater = null;

                switch ($arItem['WR']) {
                    case 'WR30m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR30.png';
                        break;
                    case 'WR200m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR200.png';
                        break;
                    case 'WR1000m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR1000.png';
                        break;
                    case 'WR100m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR100.png';
                        break;
                    case 'WR600m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR600.png';
                        break;
                    case 'WR300m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR300.png';
                        break;
                    case 'WR50m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR50.png';
                        break;
                    case 'WR500m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR500.png';
                        break;
                    case 'WR180m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR180.png';
                        break;
                    case 'WR150m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR150.png';
                        break;
                }

                if ($wrWater && file_exists($wrWater)) {
                    $image->insert($wrWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                }
            }


            if (isset($resized)) {
                $image = $manager->make($resized);
                $logoWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/logo_tempus.png';

                if (file_exists($logoWater)) {
                    $image->insert($logoWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;


                    $file = new \CFile;
                    $fileId = $file->SaveFile(CFile::MakeFileArray($resized), 'info_graph_image');

                    if (!empty($fileId)) {
                        CFile::Delete($arItem["INFOOZON_IMAGE"]);
                        CIBlockElement::SetPropertyValueCode($id, "INFOOZON_IMAGE", array('VALUE' => $fileId));
												file_put_contents(
								            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
								            'Инфографика успешно обновлена!' . PHP_EOL,
								            FILE_APPEND
								        );


                        $filesToDelete = [
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg'
                        ];

                        foreach ($filesToDelete as $fileToDelete) {
                            if (file_exists($fileToDelete)) {
                                unlink($fileToDelete);
                            }
                        }
                    }
                }
            }
        }
			} catch (Exception $e) {
				 file_put_contents(
						 "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
						 'Ошибка при обработке элемента: ' . $e->getMessage() . PHP_EOL,
						 FILE_APPEND
				 );
				 return false;
		 }

		 return true;

	}

	public function createInfoGrahpNew($arItem) {
	    try {
	        $manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

	        if (!empty($arItem['INFO_BASE'])) {
	            $baseImagePath = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
	        }

					$dateM = filectime($baseImage);

					//
					// if ($this->logTimestamp > $dateM) {
					// 	file_put_contents(
					// 			"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
					// 			'Изображение INFO_BASE не обновлялось с последней проверки. Обновление не требуется.'. PHP_EOL,
					// 			FILE_APPEND
					// 	);
					// 	return false;
					// }


			    $desiredWidth = 646;
			    $desiredHeight = 944;

	        $imageBase = Image::make($baseImagePath);

	        if ($imageBase->height() > $desiredHeight) {
	            $imageBase->resize(null, $desiredHeight, function ($constraint) {
	                $constraint->aspectRatio();
	            });
	        } else {
	            $imageBase->resizeCanvas(null, $desiredHeight, 'center', false, [255, 255, 255, 0]);
	        }

	        if ($imageBase->width() > $desiredWidth) {
	            $imageBase->resize($desiredWidth, null, function ($constraint) {
	                $constraint->aspectRatio();
	            });

	            if ($imageBase->height() < $desiredHeight) {
	                $imageBase->resizeCanvas(null, $desiredHeight, 'center', false, [255, 255, 255, 0]);
	            }
	        } else {
	            $imageBase->resizeCanvas($desiredWidth, null, 'center', false, [255, 255, 255, 0]);
	        }

					// $outputPath = 'test.png';
			 		// $imageBase->save($outputPath);
					if ($arItem['BRAND_ID'] == 43508) {
						$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/background2.png';
					} else {
						$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/background.png';
					}

	        $image = $manager->make($background);
	        $image->insert(
	            $imageBase,
	            'top-left',
	            30,
	            200,
	        );

	        if (!empty($arItem['BRAND_ID'])) {
	            $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/brand/'.$arItem['BRAND_ID'].'.png';
	            $image->insert(
	                $brandWater,
	                'top-left',
	                0,
	                0,
	            );
	        }

	        if (!empty($arItem['MECHANISM'])) {
						if ($arItem['BRAND_ID'] == 43508) {
								switch ($arItem['MECHANISM']) {
										case 'Кварцевые':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha/quartz.png';
												break;
										case 'Механические':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha/mechanical.png';
												break;
										case 'Автоматические с ручным подзаводом':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha/automatic_podzavod.png';
												break;
										case 'Автоматические':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha/automatic.png';
												break;
										case 'Автокварц (кинетик)':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha//autoqartz.png';
												break;
										case 'Процессор':
												$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/mecha/processor.png';
												break;
										default:
												break;
								}
						} else {
		            switch ($arItem['MECHANISM']) {
		                case 'Кварцевые':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/quartz.png';
		                    break;
		                case 'Механические':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/mechanical.png';
		                    break;
		                case 'Автоматические с ручным подзаводом':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/automatic_podzavod.png';
		                    break;
		                case 'Автоматические':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/automatic.png';
		                    break;
		                case 'Автокварц (кинетик)':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/autoqartz.png';
		                    break;
		                case 'Процессор':
		                    $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/processor.png';
		                    break;
		                default:
		                    break;
		            }
							}

	            $image->insert(
	                $mechWater,
	                'top-left',
	                0,
	                0,
	            );
	        }

	        if (!empty($arItem['WARRANTY'])) {

							if ($arItem['BRAND_ID'] == 43508) {
								$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/tissot/waranty.png';
							} else {


		            switch ($arItem['WARRANTY']) {
		                case '1 год':
		                case '1 года':
		                    $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/waranty/1.png';
		                    break;
		                case '2 года':
		                    $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/waranty/2.png';
		                    break;
		                case '3 года':
		                    $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/waranty/3.png';
		                    break;
		                default:
		                    break;
		            }
							}

	            $image->insert(
	                $warWater,
	                'top-left',
	                0,
	                0,
	            );
	        }

	        if (!empty($arItem['GLASS'])) {
	            switch ($arItem['GLASS']) {
	                case 'Минеральное':
	                    $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/mineralnoe.png';
	                    break;
	                case 'Органическое':
	                    $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/organic.png';
	                    break;
	                case 'Сапфировое':
	                    $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/sapphire.png';
	                    break;
									case 'Пластиковое':
			                $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/plastic.png';
			                break;
	                default:
	                    break;
	            }

	            $image->insert(
	                $glassWater,
	                'top-left',
	                0,
	                0,
	            );
	        }

	        if (!empty($arItem['DIAMETER'])) {
	            $diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/diameter/'.round($arItem['DIAMETER']).'.png';
	            if (file_exists($diamWater)) {
	                $image->insert(
	                    $diamWater,
	                    'top-left',
	                    0,
	                    0,
	                );
	            }
	        }

	        $image->encode('jpg');
	        $image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg');
	        $new_url = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg';
	        if (file_exists($new_url)) {

	            $file = new \CFile;
	            $fileId = $file->SaveFile(CFile::MakeFileArray($new_url),'info_graph_image');
	            if (!empty($fileId)) {
	                CFile::Delete($arItem["INFOOZON_IMAGE"]);
	                CIBlockElement::SetPropertyValueCode($arItem['ID'], "INFOOZON_IMAGE", array('VALUE' => $fileId));
									file_put_contents(
					            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
					            'Инфографика успешно обновлена!' . PHP_EOL,
					            FILE_APPEND
					        );
	            }
	        }
	    } catch (Exception $e) {
				var_dump( $e->getMessage() );
				var_dump( $e->getTrace()[4]['line'] );
	        file_put_contents(
	            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/ozon/log.txt",
	            'Ошибка при обработке элемента: ' . $e->getMessage() . PHP_EOL,
	            FILE_APPEND
	        );
	        return false;
	    }

	    return true;
	}
	public function updateStatus( string $code, array $arStat ):void
  {
    if ( empty($arStat) ) return;
    $strSql = "UPDATE ozon_agents SET ";
    foreach ($arStat as $field => $value) {
      if ( array_key_last($arStat) == $field ){
        $str = "{$field} = '{$value}'";
      }else{
        $str = "{$field} = '{$value}', ";
      }
      $strSql .= $str;
    }
    $strSql .= " WHERE code = '{$code}'";
    try{
      $this->CurDB->query( $strSql );
    }catch( Throwable $ignored){
			print_r('Не удалось обновить статус' . $ignored . "\n");
    }
  }
}

(new InfoGraph())->run();
?>
