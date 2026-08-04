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
class BqParse{
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
	private $BQ_DATASET = "erp";
	

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
		$strSql = "SELECT ID FROM bq_exchange WHERE ACTIVE = 'Y'";
		$results = $this->connection->query($strSql);

		while ($row = $results->Fetch()){
			$this->reportList[] = $row;
		}
	}
	
	public function parse($ID = 0){
		if(!$ID) return false;
		
		// получаем настройки 
		$this->getSettings($ID);
		
		if($this->LAST_ERROR) exit;
		
		// получаем данные из систем
		$arData = $this->getData();
		
		if(is_array($arData)){
			// сохраняем csv файл
			$this->saveCSV($arData);
			
			// отправляем в BiqQuery
			$this->sendBigQuery($arData);
		}

	}
	
	private function getData(){
		
		if(count($this->dateList) <= 0) {
			$this->LAST_ERROR = "Даты не определены";
			return false;
		}
		
		if($this->report["TYPE"] == 1){
			$ob = new MoyskladAPI($this->report["SETTINGS"]["LOGIN"]);
			if(!method_exists('MoyskladAPI', $this->report["SETTINGS"]["METHOD"])){
				$this->LAST_ERROR = "Нет метода {$this->report["SETTINGS"]["METHOD"]} в классе MoyskladAPI";
				return false;
			}
		}else{
			$this->LAST_ERROR = "Группа отчетов не определена";
			return;
		}
		
		$error = false;

		$ctnAll = count($this->dateList);
		$i = 0;
		
		$method = $this->report["SETTINGS"]["METHOD"];
		$arData = [];
		foreach($this->dateList as $key => $date){
			if($key > 1) continue;
			$arFilter = array(
				"momentFrom" => "{$date} 00:00:00",
				"momentTo" => "{$date} 23:59:59",
			);
			
			$res = $ob->{$method}($arFilter);
			if(is_array($res)){
				$dateKey = str_replace("-", "", $date);
				if($method == "getListProfitChannel"){
					foreach($res as $k => $v){
						$arData[] = [
							"date" => $date . '"',
							"channelName" => $v["salesChannel"]["name"],
							"channelType" => $v["salesChannel"]["type"],
							"salesCount" => $v["salesCount"],
							"salesAvgCheck" => $v["salesAvgCheck"],
							"sellSum" => $v["sellSum"],
							"sellCostSum" => $v["sellCostSum"],
							"returnAvgCheck" => $v["returnAvgCheck"],
							"returnSum" => $v["returnSum"],
							"returnCostSum" => $v["returnCostSum"],
							"profit" => $v["profit"],
							"margin" => $v["margin"],
						];
					}
				}else if($method == "getListProfitDay"){
					foreach($res as $k => $v){
						$arData[] = [
							"date" => $date,
							"productName" => $v["assortment"]["name"],
							"productXmlID" => $v["assortment"]["code"],
							"productArticle" => $v["assortment"]["article"],
							"sellQuantity" => $v["sellQuantity"],
							"sellPrice" => $v["sellPrice"],
							"sellCost" => $v["sellCost"],
							"sellSum" => $v["sellSum"],
							"sellCostSum" => $v["sellCostSum"],
							"returnQuantity" => $v["returnQuantity"],
							"returnPrice" => $v["returnPrice"],
							"returnCost" => $v["returnCost"],
							"returnSum" => $v["returnSum"],
							"returnCostSum" => $v["returnCostSum"],
							"profit" => $v["profit"],
							"margin" => $v["margin"],
						];
					}
				}
				//file_put_contents("/home/bitrix/logs/ms/profit_channel/{$date}.txt", serialize($res));
			}else{
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
				break;
			}
			$i++;
			$percent = round((($i / $ctnAll) * 100) / 2, 2);
			file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
		}

		if($error === true){
			return false;
		}
		
		return $arData;
	}
	
	private function saveCSV($arData = []){
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
		$this->dateList = [];
		$strSql = "SELECT * FROM bq_exchange WHERE ID = '{$ID}'";
		$results = $this->connection->query($strSql);
		
		if ($row = $results->Fetch()){
			$this->report = $row;
			$this->report["SETTINGS"] = unserialize($this->report["SETTINGS"]);
		}else{
			$this->LAST_ERROR = "Нет записи по данному отчету";
			$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/progress_{$ID}.lock";
			return false;
		}

		
		$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/progress_{$ID}.lock";
		
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
		}
		
		$this->report["FILE_RESULT"] = $_SERVER["DOCUMENT_ROOT"] . "/upload/ms/{$this->report["SETTINGS"]["NAME_FILE"]}.csv";
		
		unlink($this->report["FILE_RESULT"]);
		unlink($this->report["FILE_PROGRESS"]);
		
		if(!$this->report["SETTINGS"]["COLUMN"]){
			$this->report["SETTINGS"]["COLUMN"] = BQ_EXCHANGE[$this->report["TYPE"]]["METHOD"][$this->report["SETTINGS"]["METHOD"]]["COLUMN"];
		}
		
		switch($this->report["SETTINGS"]["PERIOD"]){
			case "1month":
				$from = (new DateTime('-1 month'))->format('d.m.Y');
				break;
			case "2month":
				$from = (new DateTime('-2 month'))->format('d.m.Y');
				break;
			case "1year":
				$from = (new DateTime('-1 year'))->format('d.m.Y');
				break;
			case "from_date":
				if($this->report["SETTINGS"]["FIRST_DATE"]){
					$from = $this->report["SETTINGS"]["FIRST_DATE"];
				}else{
					$from = (new DateTime('-1 month'))->format('d.m.Y');
				}
				break;
			default:
				$from = (new DateTime('-1 month'))->format('d.m.Y');
				break;
		}
		
		$period = new DatePeriod(
			new DateTime($from),
			new DateInterval('P1D'),
			new DateTime(date("d.m.Y") . ' 23:59')
		);
		 
		
		foreach ($period as $key => $value) {
			$this->dateList[] = $value->format('Y-m-d');     
		}
		$this->dateList = ["2023-05-25"];
	}

	// отправляем массив в BQ
	private function sendBigQuery($arData = []){
		
		$this->bq_table = $this->BQ_PROJECT_ID . "." . $this->BQ_DATASET . "." . $this->report["SETTINGS"]["BQ_TABLE"];

		// удаляем все значения в диапазоне дат
		if($this->deleteItems() === false){
			$this->LAST_ERROR = "Данные из таблица '{$this->report["SETTINGS"]["BQ_TABLE"]}' не удалось удалить";
			return false;
		}
		
		// пишим новые данные
		$arData = $this->prepareItems($arData);

		$this->insertItems($arData);

		//$queryJobConfig = $this->bigQuery->query(
		//	 "SELECT * FROM `{$bq_table}` LIMIT 100"
		//);
			// "SELECT * FROM `{$bq_table}` WHERE date IN ('".implode("','", $this->dateList)."')"
		$queryJobConfig = $this->bigQuery->query(
			 "SELECT * FROM `{$this->bq_table}` WHERE date IN ('".implode("','", $this->dateList)."')"
		);
		//prent($queryJobConfig);
		//$queryResults = $this->bigQuery->runQuery($queryJobConfig);
		//delete from `project-id.data_set.table_name` where 1=1;
		$queryResults = $this->bigQuery->runQuery($queryJobConfig);

		//$job = $this->bigQuery->startQuery($queryJobConfig);
		//$queryResults = $job->queryResults();

		foreach ($queryResults as $row) {
			prent($row);
		}
		
	}
	
	private function deleteItems(){
		
		file_put_contents($this->report["FILE_PROGRESS"], "in_process#70#Удаляем данные из таблицы BQ");
		
		$queryJobConfig = $this->bigQuery->query(
			 "DELETE FROM `{$this->bq_table}` WHERE date IN ('".implode("','", $this->dateList)."')"
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
	
	private function insertItems($arData = []){
		if(!$arData) return false;
		
		file_put_contents($this->report["FILE_PROGRESS"], "in_process#80#Отправляем данные в BQ");
		
		$insertResponse = $this->bqTable->insertRows($arData);
		
		if (!$insertResponse->isSuccessful()) {
			$err = "";
			foreach ($insertResponse->failedRows() as $row) {
				file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/error_{$ID}.txt", date("Y-m-d H:i:s") . "\r\n" . print_r($row, true));
				
				foreach ($row['errors'] as $error) {
					$err .= $error . "<br>";
				}
			}
			file_put_contents($this->report["FILE_PROGRESS"], "error#" . $err);
		}else{
			file_put_contents($this->report["FILE_PROGRESS"], "end#100#Загрузка закончена");
		}

	}
	
	private function getTableInfo($name = ""){
		$this->bqTable = $this->bqDataset->table($name);
		
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
	$obj = new BqParse();
	$obj->parse($ID);
}
	$obj = new BqParse();
	$obj->parse(6);



?>