<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class ClearSales{
	public function __construct($cabinet = "TI"){
		global $DB;
		$this->loadModules();
		
		if(!$cabinet || !in_array($cabinet, ["TI", "IP"])) die("fail cabinet");
		$this->cabinet = $cabinet;
		$this->CurDB = new DBPanel;
		$this->db = $DB;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);


		$result = $this->CurDB->query("SELECT * FROM ozon_sales_pi_{$this->cabinet} WHERE pi_sets = 'main'");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->minprof = $row['min_profit'];
			$this->unset = $row['unset'];
			$this->com = $row['com'];
			$this->tops = json_decode($row['tops']);
		}

		unset($result);
		unset($rows);

		$this->api_url = $arSetting['api_url'];
		$this->client_id = $arSetting['client_id'];
		$this->token = $arSetting['key'];

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule('panel.manager');
	}

	public function run(){
		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();

		$this->GetActiveSales();

		$this->clear();

		$this->clearDay();
	}

	public function GetActiveSales() {
		$result = $this->CurDB->query("SELECT * FROM ozon_sales_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
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
		 	$this->CurDB->query("DELETE FROM ozon_sales_{$this->cabinet} WHERE sale_id = {$id}", false, $err_mess.__LINE__);
		 }
	 }
	}

	public function clearDay() {
		$dateThreshold = date('Y-m-d H:i:s', strtotime('-5 days'));

		$sql = "DELETE FROM ozon_sales_log_{$this->cabinet} WHERE datetime < '{$dateThreshold}'";
		$this->CurDB->query($sql);
	}

}

//(new OzonImportSales())->run();
(new ClearSales("TI"))->run();
(new ClearSales("IP"))->run();
