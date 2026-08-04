<?php
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
		"keyFilePath" => "/home/bitrix/tempus_gbq/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
	];
  private $bqDataset;
  private $bqTable;

  function __construct()
  {
    $this->logPath = '';
    $this->objMS = new MoyskladAPI('msk');

    $this->bigQuery = new BigQueryClient($this->bqConfig);
		$this->bqDataset = $this->bigQuery->dataset('TEST');
    $this->bqTable = $this->bqDataset->table('GoodsReAL');
  }

  public function run()
  {
    // $this->getCommReport();
    // var_dump($this->report);
    $this->readBQuery();
  }

  public function getCommReport()
  {
    $this->commReport = $this->objMS->GetCommissionReport('positions.assortment,returnToCommissionerPositions.assortment,agent');
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
      }
      foreach ($value['returnToCommissionerPositions']['rows'] as $position) {
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
      }
    }
    // var_dump($this->sales);
  }

  // public function getSalesReturn()
  // {
  //   $saleReturns = $this->objMS->getSalesReturnCustom('positions.assortment');
  //   foreach ($saleReturns as $value) {
  //     $agentHref = explode('/', $value['agent']['meta']['href']);
  //     $agentId = end($agentHref);
  //     $this->agents[$agentHref] = ['date' => $value['created']];
  //     foreach ($value['positions']['rows'] as $position) {
  //       $this->report[$agentId][] = [
  //         'agentId' => $agentId,
  //         'dataType' => 'return',
  //         'model' => $position['assortment']['name'],
  //         'date' => explode(' ', $value['created'])[0],
  //         'quantity' => (int)$position['quantity'],
  //         'price' => null
  //       ];
  //     }
  //   }
  //   // var_dump($saleReturns);
  // }

  public function getSellCost($date, $product)
  {
    $arFilter = [
      'momentFrom' => date('Y-m-d', strtotime($date . ' -30 days')),
      'momentTo' => $date . '&filter=product=' . $product,
    ];
    $sellCost = $this->objMS->getListProfitCustom(0, false, $arFilter, 0);
    return $sellCost['rows'][0]['sellCost'];
  }

  public function readBQuery()
  {
    $queryJobConfig = $this->bigQuery->query('SELECT * FROM `lucky-kayak-385510.TEST.GoodsReAL`');
    $queryResults = $this->bigQuery->runQuery($queryJobConfig);
    var_dump($queryResults->rows());

    // foreach ($queryResults as $row) {
    // }
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
