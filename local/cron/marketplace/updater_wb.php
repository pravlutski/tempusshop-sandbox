#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class Updater{
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
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('START ', true).PHP_EOL,);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/active.txt', print_r('START', true) . "\r\n",);

		$this->getItems();

		$this->updatePhotoAdd();
	}

	public function getItems(){
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/upload/resize_wb_new/result.txt', print_r('START') . "\r\n");
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"ACTIVE"	=> "Y",
			">PROPERTY_WBPRICE" => 0,
			">CATALOG_QUANTITY" => 0,
			"!PROPERTY_PROP_MAXYSS_WB" => false,
		);

		$arFilter["!PROPERTY_AEN2"] = false;
		$arFilter["!PROPERTY_WBARTICLE2"] = false;

		$arFilter["ID"] = CMaxyssWb::getItemsWB();
		$arSelect = Array("ID", "PROPERTY_IMAGES_WB", "PROPERTY_MORE_PHOTO","PROPERTY_123");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
		);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

		while ($el = $result->GetNext()){
			$this->items[] = [
				"ID" => $el["ID"],
				"IMAGES_WB" => $el['PROPERTY_IMAGES_WB_VALUE'],
				"MORE_PHOTO" => $el['PROPERTY_MORE_PHOTO_VALUE'],
				"ART" => $el['PROPERTY_123_VALUE'],
			];

		}
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/arr.txt', print_r($this->items, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}
	public function updatePhotoAdd(){
		$i = 0;

			foreach($this->items as $key => &$arItem){
				unset($arItem['MORE_PHOTO'][0]);

					foreach ($arItem['MORE_PHOTO'] as $key => $image) {
						if (!empty($image)) {
								$sourcePath = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($image);
								$extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
								$destinationPath = '/var/www/bitrix/data/www/tempusshop.ru/upload/resize_wb_new/'.$arItem['ART'].'@'.$key.'.' . $extension;

								if (copy($sourcePath, $destinationPath)) {
										file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/upload/resize_wb_new/result.txt', print_r('Файл '.$arItem['ART'].'@'.$key.'  успешно скопирован',true) . "\r\n", FILE_APPEND);
								} else {
										file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/upload/resize_wb_new/result.txt', print_r('Произошла ошибка при копировании файла. '.$arItem['ART'].'@'.$key,true) . "\r\n", FILE_APPEND);
								}


				}
			}
		}
	}

}

(new Updater())->run();
?>
