#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/cron/infograph/vendor/autoload.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
use Intervention\Image\ImageManagerStatic as Image;

class InfoGraph{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
  }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->getItems();
    $this->createInfoGrahp();
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('START'.date('Y-m-d H:i:s'), true).PHP_EOL, FILE_APPEND);

    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE","PROPERTY_DIAMETER");

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
		  //"PROPERTY_CML2_ARTICLE" => array(),
			"ID" => 4704
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
				"INFO_BASE" => $el['PROPERTY_INFOGRAPH_BASE_VALUE'],
        "LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
		print_r($this->items);
		// die();
	}

	public function createInfoGrahp(){
		$manager = new Intervention\Image\ImageManager(
				new Intervention\Image\Drivers\Gd\Driver()
		);
		foreach($this->items as $key => &$arItem){
			if (!empty($arItem['INFO_BASE'])) {
				$baseImage = '/var/www/bitrix/data/www/tempusshop.ru'. CFile::GetPath($arItem['INFO_BASE']);
			}
			print_r($baseImage);
			$desiredWidth = 933;
			$desiredHeight = 1244;
			$background = 'newinf/background.png';
			$image = $manager->read($background);
			$image->place(
			    $baseImage,
			    'top-left',
			    30,
			    200,
			    100
			);
			//brand
			if (!empty($arItem['BRAND_ID'])) {
				$brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/brand/'.$arItem['BRAND_ID'].'.png';
				$image->place(
				    $brandWater,
				    'top-left',
				    0,
				    0,
				    100
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
				$image->place(
				    $mechWater,
				    'top-left',
				    0,
				    0,
				    100
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

				$image->place(
				    $warWater,
				    'top-left',
				    0,
				    0,
				    100
				);
			}
			//стекло
			if (!empty($arItem['GLASS'])) {
				switch ($arItem['GLASS']) {
					case 'Минеральное':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/mineralnoe.png';
						break;
					case 'Органическое':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/organicheskoe.png';
						break;
					case 'Сапфировое':
						$glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/glass/sapfirovoe.png';
						break;
					default:
						break;
				}

				$image->place(
				    $glassWater,
				    'top-left',
				    0,
				    0,
				    100
				);
			}
			//диаметр
			if (!empty($arItem['DIAMETER'])) {
					$diamWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/newinf/diameter/'.$arItem['DIAMETER'].'.png';
					if (file_exists($diamWater)) {
						$image->place(
								$diamWater,
								'top-left',
								0,
								0,
								100
						);
					}
			}



			$encoded = $image->toJpg();
			$encoded->save('example.jpg');
		}
  }
}

(new InfoGraph())->run();
?>
