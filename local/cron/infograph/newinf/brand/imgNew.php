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
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE","PROPERTY_INFOGRAPH_BASE");

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
		// print_r($this->items);
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
				    $baseImage,
				    'top-left',
				    0,
				    0,
				    100
				);
			}
			
			$encoded = $image->toJpg();
			$encoded->save('example.jpg');
		}
  }
}

(new InfoGraph())->run();
?>
