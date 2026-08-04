<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class ClearSales{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$strSql = "SELECT * FROM wdhs_ozon_main_settings_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}
		$strSql = "SELECT * FROM wdhs_sales_pi_new WHERE pi_sets = 'main'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->minprof = $row['min_profit'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

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

		$this->arLog = array();

		$this->GetActiveSales();

		$this->clear();
	}

	public function GetActiveSales() {
		$strSql = "SELECT * FROM wdhs_ozon_sales_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $date1 = strtotime($row['date_end']);
			$date2 = strtotime(date('Y-m-d'));

			if ($date1 < $date2) {
			    $this->delete[] = $row['sale_id'];
			}
		}
	}


	public function clear(){
	 if (!empty($this->delete)) {
		 foreach ($this->delete as $key => $id) {
		 	$this->db->Query("DELETE FROM wdhs_ozon_sales_new WHERE sale_id = {$id}", false, $err_mess.__LINE__);
		 }
	 }
	}


}

//(new OzonImportSales())->run();
(new ClearSales())->run();
