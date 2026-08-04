<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(3600);
//error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/php-docs-samples/bigquery/api/vendor/autoload.php';

global $DB, $USER;

use Bitrix\Main\Application,
	Bitrix\Main\Loader,
	Google\Cloud\BigQuery\BigQueryClient,
	Google\Cloud\Core\ExponentialBackoff,
	Google\Cloud\Core\Exception\NotFoundException;

class BqParseDirectory{
	private $arStockIDs;
	private $ms;
	private $reportList = [];
	private $report;
	private $connection;
	private $dateList;

	public $LAST_ERROR;

	private $bqConfig = [
		//"projectId" => "lucky-kayak-385510",
		"keyFilePath" => "/home/bitrix/tempus_gbq/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
	];
	private $BQ_PROJECT_ID = "lucky-kayak-385510";

	private $BQ_DATASET = "directory";

	function __construct(){
		global $DB;

		$this->connection = Application::getConnection();
		$this->db = $DB;

		$this->triggers = new TsTriggers();
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);

		$this->bigQuery = new BigQueryClient($this->bqConfig);
		$this->bqDataset = $this->bigQuery->dataset($this->BQ_DATASET);

		$this->loadModules();
	}

    function __destruct() {
		if($this->LAST_ERROR){
			file_put_contents($this->report["FILE_PROGRESS"], "error#" . $this->LAST_ERROR);
		}
    }

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
	}

	public function run(){
		// получаем список всех отчетов
		$this->getReport();

		foreach($this->reportList as $arItem){
			$this->parse($arItem["ID"]);
		}
	}

	private function getActiveReport(){
		$strSql = "SELECT ID FROM ci_ms_directory WHERE active = 'Y'";
		$results = $this->connection->query($strSql);

		while ($row = $results->Fetch()){
			$this->reportList[] = $row;
		}
	}

	public function parse($ID = 0){
		//file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('start'));

		if(!$ID) return false;
		file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('start:'.$ID,true));
		// получаем настройки
		$this->getSettings($ID);
		file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($this->LAST_ERROR,true).PHP_EOL,FILE_APPEND);
		if($this->LAST_ERROR) exit;

		// получаем данные из систем
		$arData = $this->getData();

		if(is_array($arData)){
			// сохраняем csv файл
			$this->saveCSV($arData);

			// отправляем в BiqQuery
			$this->sendBigQuery($arData);

			$this->setOptions($ID);
		}

	}

	private function getData(){


		$ob = new MoyskladAPI($this->report["SETTINGS"]["LOGIN"]);
		if(!method_exists('MoyskladAPI', $this->report["SETTINGS"]["METHOD"])){
			$this->LAST_ERROR = "Нет метода {$this->report["SETTINGS"]["METHOD"]} в классе MoyskladAPI";
			return false;
		}

		$error = false;

		if($this->report["SETTINGS"]["METHOD"] == "getProducts") {
			$arData = $ob->getProducts();
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('ENTER').PHP_EOL, FILE_APPEND);
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($arData, true).PHP_EOL, FILE_APPEND);
			if(is_array($arData)){
				$percent = 100;
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			} else {
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
			}
		} else if ($this->report["SETTINGS"]["METHOD"] == "getCustomers") {
			$arData = $ob->getCustomers();
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('ENTER').PHP_EOL, FILE_APPEND);
			if(is_array($arData)){
				if ($this->report["SETTINGS"]["LOGIN"] == 'msk') {
					$arData[] = [
							'customer_id' => 'ООО "ХРОНОС-ГРУПП"',
							'customer_name' => 'ООО "ХРОНОС-ГРУПП"',
							'group' => '',
							'adress' => '',
							'code' => '',
							'external_code' => '',
							'type' => '',
							'TIN' => '',
						];
				}
				$percent = 100;
				file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($arData, true).PHP_EOL, FILE_APPEND);
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			} else {
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
			}
		}  else if ($this->report["SETTINGS"]["METHOD"] == "getWarehouses") {
			$res = $ob->getWarehouses();
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('ENTER').PHP_EOL, FILE_APPEND);
			if(is_array($res)){
				foreach ($res as $key => $value) {
					$arData[] = [
							'warehouse_id' => $value['id'],
							'warehouse_name' => $value['name'],
						];
				}
				$percent = 100;
				file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($arData, true).PHP_EOL, FILE_APPEND);
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			} else {
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
			}
		}

		if($error === true){
			return false;
		}
		return $arData;
	}

	private function saveCSV($arData = []){
		unlink($this->report["FILE_RESULT"]);
		$str_csv = implode(",", $this->report["SETTINGS"]["COLUMN"]) . "\r\n";
		file_put_contents($this->report["FILE_RESULT"], $str_csv, FILE_APPEND);

		foreach($arData as $k => $arItem){
			$ar = [];
			foreach($this->report["SETTINGS"]["COLUMN"] as $col){
				$ar[] = '"' . $arItem[$col] . '"';
			}
			$str_csv = implode(",", $ar) . "\r\n";
			file_put_contents($this->report["FILE_RESULT"], $str_csv, FILE_APPEND);
		}
	}

	private function getSettings($ID){
		$strSql = "SELECT * FROM ci_ms_directory WHERE id = '{$ID}'";
		$results = $this->connection->query($strSql);

		if ($row = $results->Fetch()){
			$this->report = $row;
			$this->report["SETTINGS"] = unserialize($this->report["settings"]);
		}else{
			$this->LAST_ERROR = "Нет записи по данному отчету";
			$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/progress_directory_{$ID}.lock";
			return false;
		}


		$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/progress_directory_{$ID}.lock";

		if(!$this->report["SETTINGS"]["LOGIN"] || !$this->report["SETTINGS"]["NAME_FILE"] || !$this->report["SETTINGS"]["METHOD"] || !$this->report["SETTINGS"]["BQ_TABLE"]){
			if(!$this->report["SETTINGS"]["LOGIN"]){
				$this->LAST_ERROR = "Логин MS неопределен";
			}
			if(!$this->report["SETTINGS"]["NAME_FILE"]){
				$this->LAST_ERROR = "Не заполнено имя файла csv";
			}
			if(!$this->report["SETTINGS"]["METHOD"]){
				$this->LAST_ERROR = "Метод мойсклад неопределен";
			}
			if(!$this->report["SETTINGS"]["BQ_TABLE"]){
				$this->LAST_ERROR = "Таблица BQ не заполнена";
			}
			return false;
		}

		// получаем схему таблицы BQ
		$info = $this->getTableInfo($this->report["SETTINGS"]["BQ_TABLE"]);

		if(!$info){
			$this->LAST_ERROR = "Таблица '{$this->report["SETTINGS"]["BQ_TABLE"]}' в BQ не найдена";
			return false;
		}else{
			//prent($info);
			if($info["streamingBuffer"]){
				$this->LAST_ERROR = "Таблица '{$this->report["SETTINGS"]["BQ_TABLE"]}' в BQ заблокирована. Попробуйте позже";
				return false;
			}
			$this->schema = $info["schema"]["fields"];
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('SCHEMA', true).PHP_EOL, FILE_APPEND);
			file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($this->schema, true).PHP_EOL, FILE_APPEND);
		}

		$this->report["FILE_RESULT"] = $_SERVER["DOCUMENT_ROOT"] . "/upload/ms/directory/{$this->report["SETTINGS"]["NAME_FILE"]}.csv";

		unlink($this->report["FILE_PROGRESS"]);

		if(!$this->report["SETTINGS"]["COLUMN"]){
			$this->report["SETTINGS"]["COLUMN"] = BQ_DIRECTORY[$this->report["TYPE"]]["METHOD"][$this->report["SETTINGS"]["METHOD"]]["COLUMN"];
		}
	}

	// отправляем массив в BQ
	private function sendBigQuery($arData = []){

		$this->bq_table = $this->BQ_PROJECT_ID . "." . $this->BQ_DATASET . "." . $this->report["SETTINGS"]["BQ_TABLE"];

		if($this->deleteItems() === false){
			$this->LAST_ERROR = "Данные из таблицы '{$this->report["SETTINGS"]["BQ_TABLE"]}' не удалось удалить";
			return false;
		}

		// пишим новые данные
		$arData = $this->prepareItems($arData);

		$this->insertItems($arData);
	}

	private function deleteItems(){

		file_put_contents($this->report["FILE_PROGRESS"], "in_process#70#Удаляем данные из таблицы BQ");

		$queryJobConfig = $this->bigQuery->query(
			 "DELETE FROM `{$this->bq_table}` WHERE 1=1"
		);

		$queryResults = $this->bigQuery->runQuery($queryJobConfig);

		if($queryResults->isComplete()){
			return true;
		}
		file_put_contents($this->report["FILE_PROGRESS"], "error#Ошибка удаления");
		return false;
	}

	private function prepareItems($arData){
		$items = [];
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r('ENTER PREPARE', true), FILE_APPEND);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r($arData, true), FILE_APPEND);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r('PRINT SCHEMA', true), FILE_APPEND);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r($this->schema, true), FILE_APPEND);
		foreach($arData as $arItem){
			$ar = [];
			foreach($this->schema as $sc){
				if(isset($arItem[$sc["name"]])){
					$ar[$sc["name"]] = $this->prepareValue($arItem[$sc["name"]], $sc["type"]);//$arItem[$sc["name"]];

					//$ar[] = ['name' => 'name', 'type' => 'string'];
				}else{
					if($sc["name"] == "date_upload"){
						$ar[$sc["name"]] = date("Y-m-d H:i:s");
					}else{
						$ar[$sc["name"]] = "";
					}
				}

			}
			$items[] = ["data" => $ar];
		}
		return $items;
	}

	private function prepareValue($val, $type = "STRING"){
		switch($type){
			case "DATE":
				break;
			case "INTEGER":
				$val = intval($val);
				break;
			case "FLOAT":
				$val = (float) $val;
				if(!$val) $val = 0;
				break;
			default:
				$val = (string) $val;
				break;
		}
		return $val;
	}

	// пишем данные в таблицу BQ
	private function insertItems($arData = []){
		if(!$arData) return false;

		file_put_contents($this->report["FILE_PROGRESS"], "in_process#80#Отправляем данные в BQ");


		$arChunks = array_chunk($arData, 5000);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r('ENTER', true). PHP_EOL, FILE_APPEND);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r('CHANK', true). PHP_EOL, FILE_APPEND);
		// file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r($arChunks, true). PHP_EOL, FILE_APPEND);
		//file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r('CHANK', true).PHP_EOL, FILE_APPEND);
		//file_put_contents("/home/bitrix/logs/BqParse/DTEST.txt", print_r($arChunks, true).PHP_EOL, FILE_APPEND);
		foreach ($arChunks as $i => $chunk) {

			$percent = round(($i / count($arChunks) * 100) / 5, 2) + 80;
			file_put_contents($this->report["FILE_PROGRESS"], "in_process#{$percent}#Отправляем данные в BQ");

			$insertResponse = $this->bqTable->insertRows($chunk);

			if (!$insertResponse->isSuccessful()) {
				$err = "";
				foreach ($insertResponse->failedRows() as $row) {
					file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/error_{$ID}.txt", date("Y-m-d H:i:s") . "\r\n" . print_r($row, true), 8);
					file_put_contents("/home/bitrix/logs/BqParse/error.txt", date("Y-m-d H:i:s") . "\r\n" . print_r($row, true), 8);
					foreach ($row['errors'] as $error) {
						$err .= $error["message"] . "<br>";
					}
				}
				// file_put_contents($this->report["FILE_PROGRESS"], "error#" . $err);
				exit;
			}else{

			}
		}

		file_put_contents($this->report["FILE_PROGRESS"], "end#100#Загрузка закончена");

	}

	public function setOptions($ID){
    //Insert
      $in = array(
        'update_bq' => "'".date("Y-m-d H:i:s")."'",
      );
      $this->db->Update("ci_ms_directory_options", $in, "WHERE agent_id ='".$ID."'", $err_mess.__LINE__);
  }

	private function getTableInfo($name = ""){
		if ($this->report["SETTINGS"]["METHOD"] == 'getListDemandPur') {
			$this->bqDataset = $this->bigQuery->dataset($this->BQ_DATASET_TEST);
			$this->bqTable = $this->bqDataset->table($name);
		} else {
			$this->bqTable = $this->bqDataset->table($name);
		}

		if($this->bqTable->exists()){
			$info = $this->bqTable->info();
			return $info;
		}
		return false;
	}
}

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if($_REQUEST["ACTION"] == "parse" && intval($_REQUEST["ID"]) > 0){
	$ID = intval($_REQUEST["ID"]);
	$obj = new BqParseDirectory();
	$obj->parse($ID);
}elseif($_REQUEST["ACTION"] == "parseAll"){
	$items = [];
	$strSql = "SELECT * FROM ci_ms_directory WHERE active = 'Y'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	while ($row = $results->Fetch()){
		$items[] = $row;
	}

	file_put_contents("/home/bitrix/logs/BqParse/RSTEST.txt", print_r($items,true).PHP_EOL,FILE_APPEND);

	foreach ($items as $arItem) {
		$obj = new BqParseDirectory();
		$obj->parse($arItem["id"]);
	}

	//$obj = new BqParseDirectory();
	//$obj->sendCustomReport();
}
//if($USER->isAdmin()){
//	$obj = new BqParse();
//	$obj->sendCustomReport();
//}
?>
