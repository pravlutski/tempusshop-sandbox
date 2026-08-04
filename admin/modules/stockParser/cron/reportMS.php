<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/php-docs-samples/bigquery/api/vendor/autoload.php';
CModule::IncludeModule("panel.manager");

use Bitrix\Main\Application,
	Bitrix\Main\Loader,
	Google\Cloud\BigQuery\BigQueryClient,
	Google\Cloud\Core\ExponentialBackoff,
	Google\Cloud\Core\Exception\NotFoundException;

class ReportMS
{
  private $logPath;
  private $objMS;
  private $stocks;
  private $returns;
  private $sales;
  private $commReport;
  private $report;
  private $agents;
  private $bqConfig = [
		//"projectId" => "lucky-kayak-385510",
		"keyFilePath" => "/var/www/bitrix/data/www/tempusshop.ru/admin/settings/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
	];
  private $bqDataset;
  private $bqTable;

  function __construct()
  {
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/stockParser/logs/reportMSlog.txt';
    $this->objMS = new MoyskladAPI('msk');

    $this->bigQuery = new BigQueryClient($this->bqConfig);
		$this->bqDataset = $this->bigQuery->dataset('TEST');
    $this->bqTable = $this->bqDataset->table('GoodsReAL');
  }

  public function run()
  {
		$this->getCommReport();
		// var_dump( count($this->commReport) );
		// die;
		$this->getSalesReturn();
    $this->writeBQuery();
    // $this->readBQuery();
		// var_dump($this->report);
  }

  public function getCommReport()
  {
    $this->commReport = $this->objMS->GetCommissionReport('positions.assortment,agent');
		$this->commReport = array_merge($this->commReport, $this->objMS->GetCommissionReport('positions.assortment,agent', 100));
    foreach ($this->commReport as $value) {
      $agentHref = explode('/', $value['agent']['meta']['href']);
      $agentId = end($agentHref);
      $this->agents[$agentId] = ['date' => $value['moment'], 'agentHref' => $agentHref];
      foreach ($value['positions']['rows'] as $position) {
        $date = explode(' ', $value['moment'])[0];
        $productHref = $position['assortment']['meta']['href'];
        $sellCost = $this->getSellCost($date, $productHref);
        // $sellCost = 0;
        $this->report[$agentId][] = [
          'agentId' => $agentId,
          'agent' => $value['agent']['name'],
          'dataType' => 'sale',
          'model' => $position['assortment']['name'],
          'date' => $date,
          'quantity' => (int)$position['quantity'],
          'sellCost' => $sellCost,
          'price' => $position['price']
        ];
				// sleep(1);
      }
      // foreach ($value['returnToCommissionerPositions']['rows'] as $position) {
      //   $date = explode(' ', $value['moment'])[0];
      //   $productHref = $position['assortment']['meta']['href'];
      //   $sellCost = $this->getSellCost($date, $productHref);
      //   // $sellCost = 0;
      //   $this->report[$agentId][] = [
      //     'agentId' => $agentId,
      //     'agent' => $value['agent']['name'],
      //     'dataType' => 'return',
      //     'model' => $position['assortment']['name'],
      //     'date' => $date,
      //     'quantity' => (int)$position['quantity'],
      //     'sellCost' => $sellCost,
      //     'price' => $position['price']
      //   ];
      // }
    }
    if ( empty($this->report) ){
      $this->writeLog('Продажи из МС не получены');
      return false;
    }else{
      $this->writeLog('Получены продажи из МС');
    }
  }

  public function getSalesReturn()
  {
    $saleReturns = $this->objMS->getSalesReturnCustom('positions.assortment,agent');
    foreach ($saleReturns as $value) {
				$date = explode(' ', $value['moment'])[0];
        $productHref = $position['assortment']['meta']['href'];
        $sellCost = $this->getSellCost($date, $productHref);
				$agentId = $value['agent']['id'];
        // $sellCost = 0;
				foreach ($value['positions']['rows'] as $position) {
	        $date = explode(' ', $value['moment'])[0];
	        $productHref = $position['assortment']['meta']['href'];
	        $sellCost = $this->getSellCost($date, $productHref);
	        // $sellCost = 0;
	        $this->report[$agentId][] = [
	          'agentId' => $agentId,
	          'agent' => $value['agent']['name'],
	          'dataType' => 'return',
	          'model' => $position['assortment']['name'],
	          'date' => $date,
	          'quantity' => (int)$position['quantity'],
	          'sellCost' => $sellCost,
	          'price' => $position['price']
	        ];
					sleep(1);
	      }
      }
			// var_dump($saleReturns);
    }

  public function getSellCost($date, $product)
  {
    $arFilter = [
      // 'momentFrom' => date('Y-m-d', strtotime($date . ' -30 days')),
			'momentFrom' => $date,
      'momentTo' => $date . '+23:59:00&filter=product=' . $product,
    ];
    $sellCost = $this->objMS->getListProfitCustom(0, false, $arFilter, 0);
    return $sellCost['rows'][0]['sellCost'];
  }

  private function readBQuery()
  {
    $queryJobConfig = $this->bigQuery->query('SELECT * FROM `lucky-kayak-385510.TEST.GoodsReAL`');
    $queryResults = $this->bigQuery->runQuery($queryJobConfig);
		$counter = [];
		foreach ($queryResults as $row) {
			$counter[] = $row;
			if( $row['type'] == 'return'){
			}
			var_dump($row);
		}
		var_dump( count($counter) );
  }

  private function prepareBQItems()
  {
    $arData = [];
    foreach ($this->report as $agentData) {
			foreach ($agentData as $key => $value) {
				$arData[] = [
					'data' => [
						'data' => $value['date'],
						'agent' => $value['agent'],
						'art' => $value['model'],
						'type' => $value['dataType'],
						'quantity' => intval($value['quantity']),
						'netCost' => intval($value['sellCost']),
						'saleCost' => intval($value['price'])
					]
				];
			}
    }
    if ( empty($arData) ){
      return false;
    }
		$this->writeLog('arData is ' . count($arData) . ' length');
    return $arData;
  }

  private function writeBQuery()
  {
    $arData = $this->prepareBQItems();
    if ( !is_array($arData) || $arData == false ){
      $this->writeLog('ОШИБКА! $arData пустой');
      return false;
    }
    $queryJobConfig = $this->bigQuery->query(
      "DELETE FROM `lucky-kayak-385510.TEST.GoodsReAL` WHERE (type = 'sale' OR type = 'return')"
    );
    $queryResults = $this->bigQuery->runQuery($queryJobConfig);

    if($queryResults->isComplete()){
  		$this->writeLog('Строки удалены');
  	}else{
      $this->writeLog('Ошибка удаления строк');
    }

    $insertResponse = $this->bqTable->insertRows($arData);

    if ( !$insertResponse->isSuccessful() ){
      $this->writeLog('Ошибка записи');
      foreach ($insertResponse->failedRows() as $row) {
        $this->writeLog( print_r($row, 1) );
      }
    }else{
      $this->writeLog('Таблица обновлена');
    }
  }

  public function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

}


$objRep = new ReportMS();
$report = $objRep->run();
// var_dump($report);

 ?>
