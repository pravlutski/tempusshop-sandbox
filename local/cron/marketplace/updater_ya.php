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

		$this->getItems();
		$this->active_ya();
	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK","PROPERTY_OZON_ID","PROPERTY_WBARTICLE");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			// "PROPERTY_CML2_ARTICLE" => array("ID-11S-2E","IQ-126-5E","IQ-133-5E","C036.407.16.050.00","C036.407.16.040.00","IQ-152-1E","IQ-152-5E","SSB430P1","SSB429P1","SSB427P1","SSB425P1","SUR555P1","SUR558P1","C039.251.33.367.00","C039.251.33.017.00","SUR557P1","C038.462.16.037.00","C030.250.11.106.00","C036.407.18.040.00","C033.051.22.118.01","C032.807.22.051.01","C032.807.22.041.10"),
			// "ID" => 1002
			// "ID" => 178901
		);
//Array("nPageSize"=>50)
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
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
				"OZON_ARTICLE" => $el['PROPERTY_WBARTICLE_VALUE'],
				"OZON_ID" => $el['PROPERTY_OZON_ID_VALUE'],
			];

		}
		print_r(count($this->items));
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/arr.txt', print_r($this->items, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}


	public function active_ya(){
		foreach($this->items as $key => &$arItem){
					$strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_ya = 'Y'";
					$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
					$i = 0;
					while ($row = $results->Fetch()){
						$i++;
					}
					if ($i == 0) {
						CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["ACTIVE_YA" => 2082]);
					} else {
						CIblockElement::SetPropertyValuesEx($arItem['ID'], CProSet::IB_CATALOG, ["ACTIVE_YA" => 2081]);
					}
		}
		unset($arItem);
	}

}

(new Updater())->run();
?>
