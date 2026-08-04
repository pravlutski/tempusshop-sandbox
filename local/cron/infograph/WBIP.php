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

			$this->getItems();


		foreach($this->items as $key => &$arItem){


			if (!empty($arItem['INFO_BASE'])) {
						$this->createInfoGrahpNew($arItem);
			}
		}
	}

	public function getItems(){

		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE","PROPERTY_DIAMETER","PROPERTY_INFO_WBIP_IMAGE");

		$arFilter = Array(
      "IBLOCK_ID" => 16,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			//"PROPERTY_BRAND" => [43508],
			//"ID" => 4193,
			//"!XML_ID" => [181721,145476,181717,145486,181694,145536,145537,181560,181516,181509,181508,181481,181397,181471,181399,181402,207893,206353]
    );

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
        "MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
				"DIAMETER" => $el['PROPERTY_DIAMETER_VALUE'],
        "GLASS" => $el['PROPERTY_GLASS_VALUE'],
        "CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
        "WR" => $el['PROPERTY_WR_VALUE'],
        "FACE" => $el['PROPERTY_FACE_ENUM_ID'],
        "WARRANTY" => $el['PROPERTY_WARRANTY_VALUE'],
        "INFO_WBIP_IMAGE" => $el['PROPERTY_INFO_WBIP_IMAGE_VALUE'],
				"INFO_BASE" => $el['PROPERTY_INFOGRAPH_BASE_VALUE'],
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
	}


	public function createInfoGrahpNew($arItem) {
			try {
					$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

					if (!empty($arItem['INFO_BASE'])) {
							$baseImagePath = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
					}

					$dateM = filectime($baseImagePath);



			    $desiredWidth = 659;
			    $desiredHeight = 958;


					$resizeHold = false;
					$p = 0;
					$k = 0.69;
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
							// print_r($d);
							// print_r('#####');
							// print_r($k);
							if ($d>$k) {
								$imageBase->resizeCanvas(null, round($imageBase->width() / $k), 'center', false, [255, 255, 255, 0]);
							} else if ($d<$k) {
								$imageBase->resizeCanvas(round($imageBase->height() * $k), null, 'center', false, [255, 255, 255, 0]);
							}
							$p = round($imageBase->width() / $desiredWidth,2);

	        }
					// print_r($imageBase->width());
					// print_r('#####');
					// print_r($imageBase->height());
					$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/background.png';


					$image = $manager->make($background);

					if (!empty($arItem['DIAMETER'])) {
							$diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/diametr/'.intval($arItem['DIAMETER']).'.png';
							// print_r($diamWater);
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
						if ($p < 0.75) {
							$p = 1;
							$imageBase->resize(493, 715, function ($constraint) {
									$constraint->aspectRatio();
							});
							$image->resize(700, 914, function ($constraint) {
									$constraint->aspectRatio();
									$constraint->upsize();
							});
							$image->insert(
									$imageBase,
									'top-left',
									round(22),
									round(130),
							);
							// $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/orig.png';
							//
							// $watermark = Image::make($brandWater);
							// $watermarkWidth = round(700);
							// $watermarkHeight = round(914);
							// $watermark->resize($watermarkWidth, $watermarkHeight, function ($constraint) {
							// 		$constraint->aspectRatio();
							// 		$constraint->upsize();
							// });
							//
							// $image->insert(
							// 		$watermark,
							// 		'top-left',
							// 		0,
							// 		0,
							// );
						} else {
							$image->resize(round(933 * $p), round(1244 * $p), function ($constraint) {
									$constraint->aspectRatio();
									$constraint->upsize();
							});

							// $image->resizeCanvas(round(933 * $p), round(1244 * $p), 'center', false, [255, 255, 255, 0]);

							$image->insert(
									$imageBase,
									'top-left',
									round(22 * $p),
									round(130 * $p),
							);
							// $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/orig.png';
							//
							// $watermark = Image::make($brandWater);
							// $watermarkWidth = round(933* $p);
							// $watermarkHeight = round(1244* $p);
							// $watermark->resize($watermarkWidth, $watermarkHeight, function ($constraint) {
							// 		$constraint->aspectRatio();
							// 		$constraint->upsize();
							// });
							//
							// $image->insert(
							// 		$watermark,
							// 		'top-left',
							// 		0,
							// 		0,
							// );
						}

					} else {
						$image->insert(
								$imageBase,
								'top-left',
								round(22),
								round(130),
						);
						// $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/orig.png';
						//
						// $watermark = Image::make($brandWater);
						// $watermarkWidth = round(933);
						// $watermarkHeight = round(1244);
						// $watermark->resize($watermarkWidth, $watermarkHeight, function ($constraint) {
						// 		$constraint->aspectRatio();
						// 		$constraint->upsize();
						// });
						//
						// $image->insert(
						// 		$watermark,
						// 		'top-left',
						// 		0,
						// 		0,
						// );
					}



					//
					// $image->encode('jpg');
					// $image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/'.$arItem['ID'].'.jpg');
					// die();
					$image->encode('jpg');
					$image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/inf/'.$arItem['ID'].'.jpg');

					$new_url = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wbip/inf/'.$arItem['ID'].'.jpg';
					if (file_exists($new_url)) {
							$file = new \CFile;
							$fileId = $file->SaveFile(CFile::MakeFileArray($new_url),'info_graph_wb_ip');
							if (!empty($fileId)) {
									CFile::Delete($arItem["INFO_WBIP_IMAGE"]);
									CIBlockElement::SetPropertyValueCode($arItem['ID'], "INFO_WBIP_IMAGE", array('VALUE' => $fileId));
							}
					}
			} catch (Exception $e) {
					print_r($e->getMessage());
					return false;
			}

			return true;
	}


}

(new InfoGraph())->run();
?>
