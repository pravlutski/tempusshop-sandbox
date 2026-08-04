<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/php-docs-samples/bigquery/api/vendor/autoload.php';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application,
	Bitrix\Main\Loader,
	Google\Cloud\BigQuery\BigQueryClient,
	Google\Cloud\Core\ExponentialBackoff,
	Google\Cloud\Core\Exception\NotFoundException;

class ParserDataExport
{
  private $bqConfig = [
    //"projectId" => "lucky-kayak-385510",
    "keyFilePath" => "/home/bitrix/tempus_gbq/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
  ];
  private $bqDataset;
  private $bqTable;
  private $logPath;
  private $items;

  function __construct()
  {
    $this->logPath = 'logs/ParserDataExport.log';
    $this->bigQuery = new BigQueryClient($this->bqConfig);
    $this->bqDataset = $this->bigQuery->dataset('TEST');
    $this->bqTable = $this->bqDataset->table('positionsWB');
  }

  public function run()
  {
    $this->getItems();
    $this->writeBQuery();
    // var_dump($this->items);
  }

  public function getItems()
  {
    global $DB;
    $strSql = "SELECT parseDate, keyword, AVG(globalPosition) AS avgPosition, nmid, name, cardType
     FROM parser_data_wb
     GROUP BY keyword, nmid";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $parserData = [];
    while ( $row = $resultDB->Fetch() ){
      $parserData[] = [
        'date' => $row['parseDate'],
        'keyword' => $row['keyword'],
        'avgPosition' => intval($row['avgPosition']),
        'nmid' => intval($row['nmid']),
        'name' => strval($row['name']),
        'cardType' => intval($row['cardType'])
      ];
    }
    if ( !empty($parserData) ){
      $this->writeLog('Получены данные парсера из таблицы');
      $this->items = $parserData;
    }else{
      $this->writeLog('Данные из таблицы не получены');
    }
  }

  private function prepareBQItems()
  {
    if(empty($this->items)){
      $this->writeLog('Нет данных для подготовки массива');
      return false;
    }
    $arData = [];
    foreach ($this->items as $card) {
      $arData[] = [
        'data' => [
          'Date_Time' => $card['date'],
          'Request' => $card['keyword'],
          'NmID' => intval($card['nmid']),
          'Name' => $card['name'],
					'avgPosition' => intval($card['avgPosition']),
          'Type' => intval($card['cardType'])
        ]
      ];
    }
    return $arData;
  }

  private function writeBQuery()
  {
    $arData = $this->prepareBQItems();
    if ( !is_array($arData) || $arData == false ){
      $this->writeLog('ОШИБКА! $arData пустой');
      return false;
    }
    $insertResponse = $this->bqTable->insertRows($arData);
    if ( !$insertResponse->isSuccessful() ){
      $this->writeLog('Ошибка записи');
      foreach ($insertResponse->failedRows() as $row) {
        $this->writeLog( print_r($row, 1) );
      }
    }else{
      $this->writeLog('Таблица обновлена');
      $this->clearBaseTable();
    }
  }

	private function clearBaseTable()
	{
		global $DB;
		$strSql = "TRUNCATE TABLE parser_data_wb";
		$DB->Query($strSql, false, $err_mess.__LINE__);
		$this->writeLog('Временная таблица очищена');
	}

  private function readBQuery()
  {
  	$queryJobConfig = $this->bigQuery->query('SELECT * FROM `lucky-kayak-385510.TEST.positionsWB`');
  	$queryResults = $this->bigQuery->runQuery($queryJobConfig);
  	$counter = [];
  	foreach ($queryResults as $row) {
  		$counter[] = $row;
  	}
  	var_dump( count($counter) );
  	var_dump($counter);
  }

  public function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }
}

$objExport = new ParserDataExport();
$objExport->run();

 ?>
