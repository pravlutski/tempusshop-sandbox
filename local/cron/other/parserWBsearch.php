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

class SearchParserWB
{
  private $dataWB;
  private $sortedData;
  private $arSettings;
  private $logPath;
  private $bqConfig = [
    //"projectId" => "lucky-kayak-385510",
    "keyFilePath" => "/home/bitrix/tempus_gbq/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
  ];
  private $bqDataset;
  private $bqTable;

  function __construct()
  {
    $this->arSettings = [
      'keywords' => ['часы мужские casio', 'часы casio', 'casio', 'мужские кварцевые часы', 'casio мужские наручные часы'],
      'maxPage' => 5,
      'baseUrl' => 'https://search.wb.ru/exactmatch/ru/common/v5/search'
    ];
    $this->logPath = 'logs/parserWB.txt';
    $this->bigQuery = new BigQueryClient($this->bqConfig);
    $this->bqDataset = $this->bigQuery->dataset('TEST');
    $this->bqTable = $this->bqDataset->table('positionsWB');
  }
  public function run()
  {
    $this->getDataWB();
    $this->sortDataWB();
		$this->writeBQuery();
    var_dump($this->sortedData);
  }

  public function getDataWB()
  {
    foreach ($this->arSettings['keywords'] as $kword) {
      for ($i = 1; $i <= $this->arSettings['maxPage']; $i++) {
        $arQuery = [
          'ab_testing' => 'false',
          'appType' => '1',
          'curr' => 'rub',
          'dest' => '-1257786',
          'query' => $kword,
          'resultset' => 'catalog',
          'sort' => 'popular',
          'spp' => '30',
          'suppressSpellcheck' => 'false',
          'page' => $i
        ];
        $query = http_build_query($arQuery);
        $url = $this->arSettings['baseUrl'] . '?' . $query;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
        $resCurl = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resCurl,true);
        $this->dataWB[$kword][$i] = $result['data']['products'];
        $this->writeLog('Получено ' . count($result['data']['products']) . ' строк');
        sleep(2);
      }
    }
  }

	// lucky-kayak-385510.TEST.positionsWB

  public function sortDataWB()
  {
    foreach($this->dataWB as $keyword => $arPage){
      foreach ($arPage as $page => $products) {
        foreach($products as $position => $card){
          if ($card['supplier'] == 'TEMPUS - Наручные часы' && $card['subjectId'] == 60){
            $this->sortedData[] = [
              'date' => date('Y-m-d'),
              'keyword' => $keyword,
              'page' => $page,
              'position' => $position + 1,
              'nmid' => $card['id'],
              'name' => $card['name'],
              'isPromotion' => empty($card['log']) ? 0 : 1
            ];
          }
        }
      }
    }
    if ( !empty($this->sortedData) ){
      $this->writeLog('Получены позиции Темпуса');
    }else{
      $this->writeLog('Позиций на заданных страницах нет или при получении данных возникла ошибка');
    }
  }

	private function readBQuery()
	{
		$queryJobConfig = $this->bigQuery->query('SELECT * FROM `lucky-kayak-385510.TEST.GoodsReAL`');
		$queryResults = $this->bigQuery->runQuery($queryJobConfig);
		$counter = [];
		foreach ($queryResults as $row) {
			$counter[] = $row;
		}
		var_dump( count($counter) );
		var_dump($counter);
	}

  private function prepareBQItems()
  {
    if(empty($this->sortedData)){
      $this->writeLog('Нет данных для подготовки массива');
      return false;
    }
    $arData = [];
    foreach ($this->sortedData as $card) {
      $arData[] = [
        'data' => [
          'Date_Time' => $card['date'],
          'Request' => $card['keyword'],
          'Page_num' => intval($card['page']),
          'Position_num' => intval($card['position']),
          'NmID' => intval($card['nmid']),
          'Name' => $card['name'],
          'Type' => intval($card['isPromotion']),
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
    }
  }
	
	public function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }
}

$objSearch = new SearchParserWB();
$objSearch->run();

 ?>
