#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("cron_marketplace_update_collection_ozon_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class UpdaterOzon{
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
		$this->updateCollection();

	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_126", "DETAIL_PICTURE",);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
		);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()){
			if (!empty($el['PROPERTY_126_VALUE'])) {
				$polArray = array_values($el['PROPERTY_126_VALUE']);
				$arSectionN = getSectionsElement($el["ID"]);
				$this->items[] = [
					"ID" => $el["ID"],
					"POL" => $polArray[0],
					"BRAND" => $arSectionN[1]['NAME'],
					"COLL" => $arSectionN[2]['NAME'],
				];
			}
		}
		//print_r($this->items);
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/arr.txt', print_r($this->items, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

	public function updateCollection(){
		foreach($this->items as $key => &$arItem){
			$newColl = $arItem["BRAND"].''.$arItem["COLL"].''.$arItem["POL"];
			//print_r($newColl);
			CIBlockElement::SetPropertyValueCode($arItem["ID"], "COLLECTION_OZON", array('VALUE' => $newColl));
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt', print_r($tnvd, true) . "\r\n", FILE_APPEND | LOCK_EX);
		}
	}


}

(new UpdaterOzon())->run();
$workers->updateStatus("N");
?>
