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

		$arStat = [
			'status' => 'IN_PROCESS',
			'status_text' => 'Собираем массив товаров с фото',
			'percent' => '5',
			'time_start' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );

		$this->getItems();
        $this->createInfoGrahp();

		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => '100',
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('START'.date('Y-m-d H:i:s'), true).PHP_EOL, FILE_APPEND);

    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE","PROPERTY_DIAMETER","PROPERTY_INFOOZON_IMAGE");

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
		  "PROPERTY_CML2_ARTICLE" => array("LTP-1303D-7A","MTP-V005D-1B","MTP-VD01L-1E","EFR-571D-1A","LA-20WH-2A","MTP-V005L-1B4","MTP-V001L-7B","MTP-V004D-1C","W-218H-4B2","MTP-VD01B-1B","W-218HC-4A2","LTP-V007D-4E","MTP-V006L-1B2","LTP-V007L-9E","MTP-V004L-1C","A-168WA-8A","MTP-1384D-1A","W-218HC-8A","MTP-V001D-7B","EFR-539D-1A2","A-159W-N1","F-91WS-7E","MTP-V001GL-9B","LTP-V005D-7A","LTP-V001GL-7B","LTP-V007D-1B","LTP-V002G-9B3","LTP-V007L-7E2","LTP-V001L-1B","LTP-V007D-7E","LTP-V007L-9B","EFV-550P-1A","AE-1200WHB-3B","F-91WS-4E","A-700WE-1A","MTP-1375D-1A","LTP-V007D-7B","LA-670WEGA-9E","MTP-VD300D-1E","LTP-1235SG-7A","MTP-VD01D-7C","MTP-V006D-2B","AQ-230A-1D","GA-2100SKE-7A","LTP-V005D-7B","GA-2100-1A","MRW-200H-1B","GA-2110SU-3A","LTP-V005D-4B","MTP-V006D-7C","EFV-640D-1A","MW-240-3B","LTP-V001D-1B","MTP-1374L-1A","AE-1500WH-8B","MTP-VD03D-1A","MTP-VD03B-1A","HDC-700-1A","LTP-VT01GL-1B","F-91WM-1B","MTP-VD03D-2A","MTP-VD01D-1E","MTP-1302D-7A1","MTP-1183A-7A","LTP-V007L-7E1","MTP-V300D-1A","MTP-V005D-1B4","LTP-V300D-7A","GM-2100-1A","EFV-C100D-1A","MTP-V002L-7B2","MTP-1381D-1A","MQ-24-1B","MW-240-1E2","MTP-V002L-1B3","MTP-V004D-1B","MTP-V002D-1B","MTP-V005D-7B4","LTP-V001GL-1B","LTP-V007D-1E","MTP-V006D-1B2","LRW-200H-7B","LA-20WH-4A1","LTP-1302D-7A2","LTP-VT01D-1B","LTP-V002D-7A","EFR-539D-1A","F-91WM-7A","MTP-V006D-1B","MW-240-1E","MTP-V005D-7B","MTP-V004D-2B","LTP-V005D-4B2","W-218H-1A","W-800H-1B","MTP-1183A-1A","MTP-V004D-7C","MW-240-1B","GA-700-7A","MQ-24-1E","MTP-V005D-2B5","MTP-V005D-7B5","MTS-100D-1A","W-218H-1B","LTP-V009D-4E","W-800H-1A","MTP-1375D-7A","F-91W-1Q","W-218H-3A","EFV-600D-2A","A-178WA-1A","LA-680WEA-7E","LTP-V007G-9E","MTP-VD01D-1B","EFR-526L-1A","MTP-VD01D-1E2","LTP-V009G-7E","MTP-V005L-2B4","MTP-VD03D-3A2","MTP-VD01L-1B","F-91WM-9A","LTP-1169D-7A","MTP-V002L-5B3","LTP-V007L-7B2","MTP-VD01D-1C","MTP-VD01D-3B","MTP-V002D-7B","MTP-V005D-2B4","LTP-V005D-2B3","LTP-V009D-7E","A-159WA-N1","AQ-230A-7D","MTP-VD01D-2E","MTP-V002D-2B3","F-105W-1A","A-168WA-1Q","MTP-VD01L-1C","EFR-526D-1A","MTP-V002D-1B3","MTP-V001L-1B","AMW-870D-1A","MTP-V005L-1B","MTP-V005L-7B5","F-91WG-9Q","MTP-V001GL-1B","LTP-V009D-1E","W-218HD-1A","LTP-V005D-7B2","AQ-230A-7A","AE-1500WHX-1A","F-91W-3S","MTS-100D-2A","B640WD-1A","LTP-V001GL-9B","LTP-V001D-7B","LA-680WA-1E","MTP-V005L-1B5","AE-1500WHX-3A","GBA-900-1A","LTP-V002D-4B","GA-2100-1A1","MTP-1303D-1A","MTP-VD03D-3A1","AE-1200WH-1B","MTP-V005D-1B5","MTP-1183A-2A","MTP-VD01D-2B","LTP-V007L-7B1","LA-670WA-4E","LTP-V009D-2E","AE-1500WH-8B2","LTP-VT01D-7B","MTP-VT01B-1B","LQ-139AMV-1E","A-158WA-1","CA-53W-1","EFR-552D-1A","A-168WEGG-1B","LTP-V007G-9B","MQ-24-7B","LTP-V001L-7B","DW-5600BB-1E","MTP-V001D-1B","LTP-V007SG-9E","GA-2100-1A2","MQ-24-7B2","MTP-1374D-1A","MTP-V005L-2B5","MTP-V004D-1B2","LTP-1129A-7A","LTP-V007SG-9B","F-91WM-3A","LTP-1183A-7A","AE-1200WHD-1A","LTP-V007D-2E","LA-670WEA-7E","MTP-1302D-1A1","A-168WA-3A","EFV-C100D-1B","MTP-V002L-1B"),
			// "ID" => 73668
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

	public function createInfoGrahp(){
		$manager = new Intervention\Image\ImageManager(['driver' => 'gd']);
		$totalItems = count($this->items);
		$chunkSize = ceil($totalItems / 9);
		$currentChunk = 0;
		$processedItems = 0;
		foreach($this->items as $key => &$arItem){

			$processedItems++;

			// Проверяем, нужно ли обновить статус после завершения части
			if ($processedItems % $chunkSize === 0 || $processedItems === $totalItems) {
				$currentChunk++;
				$progress = min(round(($processedItems / $totalItems) * 100, 2), 100);

				$this->updateStatus( $this->module, ['status_text' => 'Генерация инфографики', 'percent' => $progress] );
			}

			if (!empty($arItem['INFO_BASE'])) {
				$baseImage = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
			}


			$desiredWidth = 933;
			$desiredHeight = 1244;
			$background = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/background.png';
			$image = $manager->make($background);
			$image->insert(
			    $baseImage,
			    'top-left',
			    30,
			    200,
			);
			//brand
			if (!empty($arItem['BRAND_ID'])) {
				$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/brand/'.$arItem['BRAND_ID'].'.png';
				$image->insert(
				    $brandWater,
				    'top-left',
				    0,
				    0,
				);
			}

			// механизм
			if (!empty($arItem['MECHANISM'])) {
				switch ($arItem['MECHANISM']) {
					case 'Кварцевые':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/quartz.png';
						break;
					case 'Механические':
						$mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/mecha/mehanicheskie.png';
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
				print_r($mechWater);
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
						$warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/waranty/1.png';
						break;
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

				$image->insert(
				    $warWater,
				    'top-left',
				    0,
				    0,
				);
			}
			//стекло
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
					$diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/diameter/'.$arItem['DIAMETER'].'.png';
					if (file_exists($diamWater)) {
						$image->insert(
								$diamWater,
								'top-left',
								0,
								0,
						);
					}
			}



			// $encoded = $image->toJpg();
			// $encoded->save('newinf_tmp/'.$arItem['ID'].'.jpg');
			// $image->setImageFormat('jpg');
			// $image->writeImage('newinf_tmp/'.$arItem['ID'].'.jpg');
			$image->encode('jpg');
			$image->save('/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg');
			$new_url = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf_tmp/'.$arItem['ID'].'.jpg';
			if (file_exists($new_url)) {
				//print_r($arItem["INFOOZON_IMAGE"]);
				$file = new \CFile;
				$fileId = $file->SaveFile(CFile::MakeFileArray($new_url),'info_graph_image');
				//print_r($fileId);
				if (!empty($fileId)) {
					CFile::Delete($arItem["INFOOZON_IMAGE"]);
					CIBlockElement::SetPropertyValueCode($arItem['ID'], "INFOOZON_IMAGE", array('VALUE' => $fileId));
					//unlink($new_url);
				}
			}
		}
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
