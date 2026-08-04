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
		"keyFilePath" => "/var/www/bitrix/data/www/tempusshop.ru/admin/settings/bq_exchange/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
	];
	private $BQ_PROJECT_ID = "lucky-kayak-385510";

	private $BQ_DATASET = "erp";
  private $BQ_DATASET_TEST = "shipment";

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
		var_dump( $this->LAST_ERROR );
		if(is_array($arData)){
			// сохраняем csv файл
			$this->saveCSV($arData);

			$fileTime = date('Y-m-d G:i:s', filectime($this->report['FILE_RESULT']));
			$linkElem = "<a href='tempusshop.ru//upload/ms/{$this->report["SETTINGS"]["NAME_FILE"]}.csv'>Сохранить {$fileTime}</a>";
			file_put_contents($this->report["FILE_PROGRESS"], "end#" . 100 . "#{$response}");
			// отправляем в BiqQuery
			// if ($this->report["SETTINGS"]["METHOD"] == 'getListDemandPur') {
			// 	$this->sendBigQueryDemands($arData['demands']);
			// 	$this->sendDemandsPos($arData['pos']);
			// } else {
			// 	$this->sendBigQuery($arData);
			// }
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
		file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r($this->report["SETTINGS"], true));

		if($this->report["SETTINGS"]["METHOD"] == "getListDemandPur") {
			$arData = $ob->getListDemandPur();

			if(is_array($arData)){
				$percent = 100;
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			} else {
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
			}

		} else if($this->report["SETTINGS"]["METHOD"] == "getSupply"){
			$res = $ob->getSupply();
			foreach($res as $k => $v){
				$warehouse_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/store/',$v['store']['meta']['href']);
				$customer_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',$v['agent']['meta']['href']);
				if (empty($customer_id[1])) {
					$customer = 'ООО "ХРОНОС-ГРУПП"';
				} else {
					$customer = $customer_id[1];
				}
				//$dateP = explode('+',$date);
				if (!empty($v['rate']['value'])) {
					$quant = ($v['sum'] / 100) * $v['rate']['value'];
				} else {
					$quant = $v['sum'] / 100;
				}
				$date = explode(' ',$v['moment']);
				$arData[] = [
					"supply_id" => $v['id'],
					"date" => $date[0],
					"customer_id" => $customer,
					"quantity" => $v['positions']['meta']['size'],
					"sum" => $quant,
					"warehouse_id" => $warehouse_id[1],
				];
			}
			if(is_array($arData)){
				$percent = 100;
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			} else {
				$error = true;
				$this->LAST_ERROR = "Ошибка при запросе {$date}";
			}
		} else {
			$ctnAll = count($this->dateList);
			$i = 0;

			$method = $this->report["SETTINGS"]["METHOD"];
			$arData = [];


			foreach($this->dateList as $key => $date){
				//if($key > 1) continue;
				if (!empty($this->report["SETTINGS"]["ADD_FILTER"])) {
					$salesChannel = 'https://api.moysklad.ru/api/remap/1.2/entity/saleschannel/'.$this->report["SETTINGS"]["ADD_FILTER"];
					$arFilter = array(
						"momentFrom" => "{$date} 00:00:00",
						"momentTo" => "{$date} 23:59:59",
						"salesChannel" => $salesChannel
					);
				} else {
					$arFilter = array(
						"momentFrom" => "{$date} 00:00:00",
						"momentTo" => "{$date} 23:59:59"
					);
				}


				if($method == "getStocksCount"){
					$res = $ob->{$method}($date);
				} else {
					$res = $ob->{$method}($arFilter);
				}
				if(is_array($res)){
					$dateKey = str_replace("-", "", $date);
					if($method == "getListProfitChannel"){
						foreach($res as $k => $v){
							$arData[] = [
								"date" => $date,
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
						file_put_contents("/home/bitrix/logs/BqParse/test_sup.txt", print_r($res,true));
						foreach($res as $k => $v){
							$msId = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$v["assortment"]['meta']['href']);
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
								"product_id" => $msId[1],
								//"product_id" => $v["assortment"]['meta']['href'],
							];
						}
					} else if($method == "getStocksCount"){
						foreach($res as $k => $v){
							$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$v['meta']['href']);
							$sku_id = explode('?expand=supplier',$sku_id[1]);
							//$dateP = explode('+',$date);
							$arData[] = [
								'date' => $date.' 23:59:59',
								'product_id' => $sku_id[0],
								'quantity' => $v['stock'],
								'COGS' => $v['price'] / 100,
								'warehouse_id' => $v['WH_ID'],
							];
						}
					}
					file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/logs/test_sup.txt", print_r($arData,true));

				}else{
					$error = true;
					$this->LAST_ERROR = "Ошибка при запросе {$date}";
					break;
				}
				$i++;
				$percent = round((($i / $ctnAll) * 100), 2);
				file_put_contents($this->report["FILE_PROGRESS"], "in_process#" . $percent . "#Забираем данные из МС");
			}
		}
		if($error === true){
			return false;
		}
		file_put_contents("/home/bitrix/logs/BqParse/test.txt", print_r($arData, true), FILE_APPEND);
		return $arData;
	}

	private function saveCSV($arData = []){
		unlink($this->report["FILE_RESULT"]);
		unlink($this->report["FILE_RESULT_POS"]);
		if ($this->report["SETTINGS"]["METHOD"] == 'getListDemandPur') {
			$str_csv = implode(",", $this->report["SETTINGS"]["COLUMN"]) . "\r\n";
			file_put_contents($this->report["FILE_RESULT"], $str_csv, FILE_APPEND);

			foreach($arData['demands'] as $k => $arItem){
				$ar = [];
				foreach($this->report["SETTINGS"]["COLUMN"] as $col){
					$ar[] = '"' . $arItem[$col] . '"';
				}
				$str_csv = implode(",", $ar) . "\r\n";
				file_put_contents($this->report["FILE_RESULT"], $str_csv, FILE_APPEND);
			}

			$str_csv = implode(",", $this->report["SETTINGS"]["COLUMN_POS"]) . "\r\n";
			file_put_contents($this->report["FILE_RESULT_POS"], $str_csv, FILE_APPEND);

			foreach($arData['pos'] as $k => $arItem){
				$ar = [];
				foreach($this->report["SETTINGS"]["COLUMN_POS"] as $col){
					$ar[] = '"' . $arItem[$col] . '"';
				}
				$str_csv = implode(",", $ar) . "\r\n";
				file_put_contents($this->report["FILE_RESULT_POS"], $str_csv, FILE_APPEND);
			}
		} else {
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
			$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/progress_{$ID}.lock";
			return false;
		}

		$this->report["FILE_PROGRESS"] = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/progress_{$ID}.lock";

		if(!$this->report["SETTINGS"]["LOGIN"] || !$this->report["SETTINGS"]["NAME_FILE"] || !$this->report["SETTINGS"]["METHOD"] ){
			if(!$this->report["SETTINGS"]["LOGIN"]){
				$this->LAST_ERROR = "Логин MS неопределен";
			}
			if(!$this->report["SETTINGS"]["NAME_FILE"]){
				$this->LAST_ERROR = "Не заполнено имя файла csv";
			}
			if(!$this->report["SETTINGS"]["METHOD"]){
				$this->LAST_ERROR = "Метод мойсклад неопределен";
			}
			return false;
		}

		$this->report["FILE_RESULT"] = $_SERVER["DOCUMENT_ROOT"] . "/upload/ms/{$this->report["SETTINGS"]["NAME_FILE"]}.csv";
		$this->report["FILE_RESULT_POS"] = $_SERVER["DOCUMENT_ROOT"] . "/upload/ms/{$this->report["SETTINGS"]["NAME_FILE_POS"]}.csv";

		unlink($this->report["FILE_PROGRESS"]);

		if(!$this->report["SETTINGS"]["COLUMN"]){
			$this->report["SETTINGS"]["COLUMN"] = BQ_EXCHANGE[$this->report["TYPE"]]["METHOD"][$this->report["SETTINGS"]["METHOD"]]["COLUMN"];
		}
		var_dump( $this->report["SETTINGS"]["PERIOD"] );
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
}elseif($_REQUEST["ACTION"] == "parseAll"){
	$items = [];
	$strSql = "SELECT * FROM bq_exchange WHERE ACTIVE = 'Y'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$items[] = $row;
	}
	foreach($items as $arItem){
		$obj = new BqParse();
		$obj->parse($arItem["ID"]);
	}

	$obj = new BqParse();
	// $obj->sendCustomReport();
}
//if($USER->isAdmin()){
//	$obj = new BqParse();
//	$obj->sendCustomReport();
//}
?>
