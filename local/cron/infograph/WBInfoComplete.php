#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");
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

		$logFile = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt';
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

			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt", print_r('#######' , true).PHP_EOL, FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt", print_r('Обновляем модель: '.$arItem['ARTICLE'] , true).PHP_EOL, FILE_APPEND);
			if (!empty($arItem['INFO_BASE'])) {
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt", print_r('У модели есть изображение INFO_BASE запускатеся метод генерации новой инфографики' , true).PHP_EOL, FILE_APPEND);
				$this->createInfoGrahpNew($arItem);
			} else {
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt", print_r('У модели отсутствует изображение INFO_BASE запускатеся метод генерации старой инфографики' , true).PHP_EOL, FILE_APPEND);
				$this->createInfoGraphOld($arItem);
			}

		}
		$arStat = [
			'status' => 'COMPLETED',
			'status_text' => 'Выполнено',
			'percent' => '100',
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt", print_r(date('Y-m-d H:i:s'), true).PHP_EOL);

    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE","PROPERTY_DIAMETER","PROPERTY_INFOOZON_IMAGE");

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			//"ID" => [5474],
			//"PROPERTY_BRAND" => [7971],
		);

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){

      $this->items[] = [
        "ID" => $el["ID"],
        "BRAND_ID" => $el["PROPERTY_BRAND_VALUE"],
        "COLLECTION" => $el["PROPERTY_COLLECTION_VALUE"],
        "ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLLECTION_UPDATE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLORTERM" => $el["PROPERTY_COLORTERM_VALUE"],
        'TYPE' => array_shift($el['PROPERTY_126_VALUE']),
        'DETAIL_PICTURE' => $el['DETAIL_PICTURE'],
        "TIMESTAMP_X" => $el['TIMESTAMP_X'],
        "CATALOG_QUANTITY" => $el['CATALOG_QUANTITY'],
        "COLORTERM_UPDATE" => "",
        "IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
        "NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
        "RICH_DESC" => $el['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
        "MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
				"DIAMETER" => $el['PROPERTY_DIAMETER_VALUE'],
        "GLASS" => $el['PROPERTY_GLASS_VALUE'],
        "CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
        "WR" => $el['PROPERTY_WR_VALUE'],
        "FACE" => $el['PROPERTY_FACE_ENUM_ID'],
        "WARRANTY" => $el['PROPERTY_WARRANTY_VALUE'],
        "INFO_WB_IMAGE" => $el['PROPERTY_INFO_WB_IMAGE_VALUE'],
				"INFOOZON_IMAGE" => $el['PROPERTY_INFOOZON_IMAGE_VALUE'],
				"INFO_BASE" => $el['PROPERTY_INFOGRAPH_BASE_VALUE'],
        "LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
		//print_r($this->items);
		// die();
	}

	public function createInfoGrahpNew($arItem){
		try {
			$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);





			if (!empty($arItem['INFO_BASE'])) {
				$baseImagePath = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
			}
			print_r('1');
			$dateM = filectime($baseImagePath);

			if ($this->logTimestamp > $dateM) {
				file_put_contents(
						"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
						'Изображение INFO_BASE не обновлялось с последней проверки. Обновление не требуется.'. PHP_EOL,
						FILE_APPEND
				);
				return false;
			}

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
			}

			$p = round($imageBase->width() / $desiredWidth,2);

			$resizeMain = false;
			if ($p < 0.75) {
				$resizeMain = true;
				$ocX = 23;
				$ocY = 150;
			} else {
				$resizeMain = false;
				$ocX = 30;
				$ocY = 200;
			}
			print_r($p);
			if ($resizeMain) {
			    $imageBase->resize(484, 712, function ($constraint) {
			        $constraint->aspectRatio(); // Сохраняем пропорции
			    });

			    // Обрезаем с прозрачным фоном (RGBA: 255, 255, 255, 0)
			    $imageBase->crop(484, 712, 0, 0, [
			        'background' => [255, 255, 255, 0] // Альфа-канал (0 = прозрачность)
			    ]);
			}
			$outputPath = 'test.png';
			$imageBase->save($outputPath);

				print_r('2');
			$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/background.png';
			$image = $manager->make($background);
			if (!$resizeHold && !$resizeMain) {
				$image->insert(
				    $imageBase,
				    'top-left',
				    30,
				    200,
				);
				//brand
				if (!empty($arItem['BRAND_ID'])) {
					$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/brand/'.$arItem['BRAND_ID'].'.png';
					$image->insert(
					    $brandWater,
					    'top-left',
					    0,
					    0,
					);
				}
			}
			if (is_array($arItem['MECHANISM'])) {
				foreach ($arItem['MECHANISM'] as $key => $value) {
					$mechaZ = $value;
					break;
				}
			}
			print_r($mechaZ);
			// механизм
			if (!empty($mechaZ)) {
				switch ($mechaZ) {
					case 'Кварцевые':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/quartz.png';
						break;
					case 'Механические':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/mehanicheskie.png';
						break;
					case 'Автоматические с ручным подзаводом':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/automatic_podzavod.png';
						break;
					case 'Автоматические':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/automatic.png';
						break;
					case 'Автокварц (кинетик)':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/autoqartz.png';
						break;
					case 'Процессор':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/mecha/processor.png';
						break;
					default:
						break;
				}

				$image->insert(
				    $mechWater,
				    'top-left',
				    0,
				    0,
				);
			}
			//гарантия
			if (!empty($arItem['WARRANTY'])) {
				switch ($arItem['WARRANTY']) {
					case '1 год':
						$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/waranty/1.png';
						break;
					case '1 года':
						$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/waranty/1.png';
						break;
					case '2 года':
						$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/waranty/2.png';
						break;
					case '3 года':
						$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/waranty/3.png';
						break;
					default:
						break;
				}

				$image->insert(
				    $warWater,
				    'top-left',
				    0,
				    0,
				);
			}
			//стекло
			print_r('###');

			if (!empty($arItem['GLASS'])) {
				switch ($arItem['GLASS']) {
					case 'Минеральное':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/glass/mineralnoe.png';
						break;
					case 'Органическое':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/glass/organic.png';
						break;
					case 'Сапфировое':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/glass/sapphire.png';
						break;
					case 'Пластиковое':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/glass/plastic.png';
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

			//диаметр
			if (!empty($arItem['DIAMETER'])) {
					$diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/diameter/'.round($arItem['DIAMETER']).'.png';
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
					if($resizeMain) {



						$image->resize(700, 933, function ($constraint) {
								$constraint->aspectRatio();
								$constraint->upsize();
						});

						$image->resizeCanvas(700, 933, 'center', false, [255, 255, 255, 0]);

						$image->insert(
								$imageBase,
								'top-left',
								$ocX,
								$ocY,
						);


						if (!empty($arItem['BRAND_ID'])) {
								$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/brand/'.$arItem['BRAND_ID'].'.png';

								$watermark = Image::make($brandWater);
								$watermarkWidth = 700;
								$watermarkHeight = 933;
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
				} else {
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
							$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB/brand/'.$arItem['BRAND_ID'].'.png';

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
			}


			// $image->setImageFormat('jpg');
			// $image->writeImage('newinfWB_tmp/'.$arItem['ID'].'.jpg');
			$image->encode('jpg');
			$image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB_tmp/'.$arItem['ID'].'.jpg');
			$new_url = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinfWB_tmp/'.$arItem['ID'].'.jpg';
			if (file_exists($new_url)) {
				//print_r($arItem["INFOOZON_IMAGE"]);
				$file = new \CFile;
				$fileId = $file->SaveFile(CFile::MakeFileArray($new_url),'info_graph_image');
				print_r($fileId);
				if (!empty($fileId)) {
					CFile::Delete($arItem["INFO_WB_IMAGE"]);
					CIBlockElement::SetPropertyValueCode($arItem['ID'], "INFO_WB_IMAGE", array('VALUE' => $fileId));
					file_put_contents(
								            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
								            'Инфографика успешно обновлена!' . PHP_EOL,
								            FILE_APPEND
								        );
					//unlink($new_url);
				}
			}
		} catch (Exception $e) {
				 file_put_contents(
						 "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
						 'Ошибка при обработке элемента: ' . $e->getMessage() . PHP_EOL,
						 FILE_APPEND
				 );
				 return false;
		 }

		 return true;


    }

	public function createInfoGraphOld($arItem) {
		try {
		$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

        if (!empty($arItem['DETAIL_PICTURE'])) {
					$fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
					$dateM = filectime($fileDetail);

					// if ($this->logTimestamp > $dateM) {
					// 	file_put_contents(
					// 			"/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
					// 			'Детальное изображение не обновлялось с последней проверки. Обновление не требуется.'. PHP_EOL,
					// 			FILE_APPEND
					// 	);
					// 	return false;
					// }

            //if ((time() - 1 * 24 * 60 * 60) < $dateM) {
                $id = $arItem['ID'];
                $desiredWidth = 685;
                $desiredHeight = 930;

                $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
                $r0_main = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-'.$id.'.jpg';

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
                if ($image->width() < 685) {
                    $needed_width = 685 - $image->width();
                    $needed_width_p = $needed_width / 2;

                    $newImage = $manager->canvas(685, $image->height(), '#ffffff');
                    $newImage->insert($image, 'top', intval($needed_width_p), 0);

                    $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-'.$id.'.jpg';
                    $newImage->save($r0_main_r);
                } else {
                    $r0_main_r = $r0_main;
                }

                $image = $manager->make($r0_main_r);
                if ($image->height() < 930) {
                    $needed_height = 930 - $image->height();
                    $needed_height_p = $needed_height / 2;

                    $newImage = $manager->canvas($image->width(), 930, '#ffffff');
                    $newImage->insert($image, 'top', 0, intval($needed_height_p));

                    $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-hehight-'.$id.'.jpg';
                    $newImage->save($r0_main_r);
                }


                $image = $manager->make($r0_main_r);
                $newImage = $manager->canvas($image->width() + 18, $image->height() + 224, '#ffffff');
                $newImage->insert($image, 'top-left', 18, 224);
                $r0_top_left = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_top_left-'.$id.'.jpg';
                $newImage->save($r0_top_left);


                $image = $manager->make($r0_top_left);
                $newImage = $manager->canvas($image->width() + 229, $image->height() + 70, '#ffffff');
                $newImage->insert($image, 'top-left', 0, 0);
                $r0_bot_right = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_bot_right-'.$id.'.jpg';
                $newImage->save($r0_bot_right);

                $resized = $r0_bot_right;
                $image = $manager->make($resized);
                $srcWidth = $image->width();
                $srcHeight = $image->height();

                if (!empty($arItem['BRAND_ID'])) {
                    $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/brand/'.$arItem['BRAND_ID'].'.png';
                    if (file_exists($brandWater)) {
                        $image->insert($brandWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_brand-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }
								$mechM = '';
								if (is_array($arItem['MECHANISM'])) {
									foreach ($arItem['MECHANISM'] as $key => $value) {
										$mechM = $value;
									}
								}
								$arItem['MECHANISM'] = $mechM;
                if (!empty($arItem['MECHANISM']) && file_exists($resized)) {
                    $mechWater = $this->getMechanismWatermarkPath($arItem['MECHANISM']);
                    if ($mechWater) {
                        $image = $manager->make($resized);
                        $image->insert($mechWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_mech-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['WARRANTY']) && file_exists($resized)) {
                    $warWater = $this->getWarrantyWatermarkPath($arItem['WARRANTY']);
                    if ($warWater) {
                        $image = $manager->make($resized);
                        $image->insert($warWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_war-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['GLASS']) && file_exists($resized)) {
                    $glassWater = $this->getGlassWatermarkPath($arItem['GLASS']);
                    if ($glassWater) {
                        $image = $manager->make($resized);
                        $image->insert($glassWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_glass-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['WR']) && file_exists($resized)) {
                    $wrWater = $this->getWRWatermarkPath($arItem['WR']);
                    if ($wrWater) {
                        $image = $manager->make($resized);
                        $image->insert($wrWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_wr-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (file_exists($resized)) {
                    $logoWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/logo_tempus.png';
                    $image = $manager->make($resized);
                    $image->insert($logoWater, 'center');
                    $finalImage = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/RESULT-'.$id.'.jpg';
                    $image->save($finalImage);

                    $file = new \CFile;
                    $fileId = $file->SaveFile(CFile::MakeFileArray($finalImage), 'info_graph_image');
                    if (!empty($fileId)) {
                        CFile::Delete($arItem["INFO_WB_IMAGE"]);
                        CIBlockElement::SetPropertyValueCode($id, "INFO_WB_IMAGE", array('VALUE' => $fileId));
                        	file_put_contents(
					            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
					            'Инфографика успешно обновлена!' . PHP_EOL,
					            FILE_APPEND
					        );
                    }
                }
            //}
        }
        unset($arItem);
		} catch (Exception $e) {
	        file_put_contents(
	            "/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt",
	            'Ошибка при обработке элемента: ' . $e->getMessage() . PHP_EOL,
	            FILE_APPEND
	        );
	        return false;
	    }

	    return true;
	}


	private function getMechanismWatermarkPath($mechanism) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/mecha/';
    $map = [
        'Кварцевые' => 'qartz.png',
        'Механические' => 'mehanicheskie.png',
        'Автоматические с ручным подзаводом' => 'automatic_podzavod.png',
        'Автоматические' => 'automatic.png',
        'Автокварц (кинетик)' => 'autoqartz.png',
        'Процессор' => 'processor.png'
    ];

    if (isset($map[$mechanism])) {
        $path = $basePath . $map[$mechanism];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством MECHANISM: ' . $mechanism, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getWarrantyWatermarkPath($warranty) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/garantiya/';
    $map = [
        '1 год' => 'garantiya_1_god.png',
        '1 года' => 'garantiya_1_god.png',
        '2 года' => 'garantiya_2_goda.png',
        '3 года' => 'garantiya_3_goda.png'
    ];

    if (isset($map[$warranty])) {
        $path = $basePath . $map[$warranty];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством WARRANTY: ' . $warranty, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getGlassWatermarkPath($glass) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/steklo/';
    $map = [
        'Минеральное' => 'mineralnoe.png',
        'Органическое' => 'organicheskoe.png',
        'Сапфировое' => 'sapfirovoe.png',
				'Пластиковое' => 'plastic.png'
    ];

    if (isset($map[$glass])) {
        $path = $basePath . $map[$glass];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством GLASS: ' . $glass, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getWRWatermarkPath($wr) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/WR/';
    $map = [
        'WR30m' => 'WR30.png',
        'WR200m' => 'WR200.png',
        'WR1000m' => 'WR1000.png',
        'WR100m' => 'WR100.png',
        'WR600m' => 'WR600.png',
        'WR300m' => 'WR300.png',
        'WR50m' => 'WR50.png',
        'WR500m' => 'WR500.png',
        'WR180m' => 'WR180.png',
        'WR150m' => 'WR150.png'
    ];

    if (isset($map[$wr])) {
        $path = $basePath . $map[$wr];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством WR: ' . $wr, true).PHP_EOL, FILE_APPEND);
    return null;
}

  	public function updateStatus( string $code, array $arStat ):void
	{
		if ( empty($arStat) ) return;
		$strSql = "UPDATE wb_agents SET ";
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
