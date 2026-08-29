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

class WBFboStat
{
  private $headers; // Массив с заголовками
  private $cabinet; // Кабинет

  private $dbLeg; // Экземпляр класса общей БД
  private $dbNew; // Экземпляр класса новой БД

  private $items = []; // Массив с подробной информацией о товарах
  private $stockFbo = []; // Массив с остатками товаров
  private $fromMS = []; // Массив с себестоимостями товаров

  private $tableName = ''; // Имя таблицы со статистикой
  private $logPath = ''; // Путь к логу

  function __construct( $cabinet = "WR" )
  {
    CModule::IncludeModule("panel_manager");

    global $DB;
    $this->dbLeg = $DB;
    $this->dbNew = new DBPanel;

    $this->cabinet = $cabinet;

    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $auth = $this->dbLeg->Query($strSql, false, $err_mess.__LINE__)->Fetch()['api'];
    $this->headers = [
      "Content-Type: application/json",
      "Authorization: {$auth}"
    ];

    $this->tableName = "wb_fbo_stat_{$this->cabinet}";
    $this->logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/stat/{$this->cabinet}/log.txt";
  }

  public function run()
  {
    $this->writeLog('START');

    $this->getStock();
    $this->writeDB();

    $this->writeLog('END');
    $this->writeLog('');
  }

  private function getStockLeg():void
  {
    $url = 'https://statistics-api.wildberries.ru/api/v1/supplier/stocks?dateFrom=2020-01-01';
    $resCurl = $this->curl( $url );
    $result = json_decode( $resCurl, 1 );
    $this->getTurnoverFromTable();

    $this->arWh = [];


    if ( is_array($result) && empty($result['error']) && empty($result['message']) ){
      $this->writeLog( 'Получено катрочек: ' . count($result) );

      foreach($result as $card){
        if($card['supplierArticle']){
          $keyMod = end( explode('_',$card['supplierArticle']) );

          if ($card['quantity'] != 0) {
            $this->arWh[$keyMod][$card['warehouseName']] = $card['quantity'];
          }

          // if ( array_key_exists($keyMod, $this->arWh) ){
          //
          // } else {
          //
          //   $this->arWh[$keyMod][$card['warehouseName']] = $card['quantity']
          // }

          if ( array_key_exists($card['supplierArticle'], $this->stockFbo) ){
            $this->stockFbo[$card['supplierArticle']] = $this->stockFbo[$card['supplierArticle']] + $card['quantity'];
            $keyMod = end( explode('_',$card['supplierArticle']) );
            $this->items[$keyMod]['stock'] = $this->items[$keyMod]['stock'] + $card['quantity'];
            $this->items[$keyMod]['to_client'] =  $this->items[$keyMod]['toClient'] + $card['inWayToClient'];
            $this->items[$keyMod]['from_client'] =  $this->items[$keyMod]['fromClient'] + $card['inWayFromClient'];
          }else{
            $this->stockFbo[$card['supplierArticle']] = $card['quantity'];
            $keyMod = end( explode('_',$card['supplierArticle']) );
            $this->items[$keyMod] = [
                'stock_date' => date('Y-m-d G:i:s'),
                'nmid' => $card['nmId'],
                'model' => $keyMod,
                'stock' => $this->stockFbo[$card['supplierArticle']],
                'to_client' => $card['inWayToClient'],
                'from_client' => $card['inWayFromClient'],
                'cost' => $this->fromMS[$keyMod] ?? 0
            ];
          }
        }
      }
    }else{
      $this->writeLog( 'Ошибка при получении данных со складов ВБ' );
      $this->writeLog( $result );
    }

    if (!empty($this->items)) {
      foreach ($this->items as $key => $value) {
        $this->items[$key]['warehouseName'] = json_encode($this->arWh[$key],JSON_UNESCAPED_UNICODE);
      }
    }
  }

  private function getStock():void
  {
    $rows = $this->dbLeg->query("SELECT nmid, article FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}'");
    $dict = [];
    while ( $row = $rows->fetch() ){
      $dict[ $row['nmid'] ] = $row['article'];
    }
    $this->getTurnoverFromTable();

    $url = "https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses";

    $res = $this->curl($url, [ "limit" => 250000, "offset" => 0 ]);

    $result = json_decode( $res, true );

    $stocks = [];
    foreach ( $result['data']['items'] as $item ){
      if ( isset($stocks[$item['nmId']]) ){
        $stocks[$item['nmId']]['stock'] += $item['quantity'];
        $stocks[$item['nmId']]['to_client'] += $item['inWayToClient'];
        $stocks[$item['nmId']]['from_client'] += $item['inWayFromClient'];
        $stocks[$item['nmId']]['warehouseName'][] = $item['warehouseName'];
        continue;
      }
      $stocks[ $item['nmId'] ] = [
        'stock_date' => date('Y-m-d G:i:s'),
        'nmid' => $item['nmId'],
        'model' => $dict[ $item['nmId'] ] ?? $item['nmId'],
        'to_client' => $item['inWayToClient'],
        'from_client' => $item['inWayFromClient'],
        'stock' => $item['quantity'],
        'cost' => $this->fromMS[ $dict[ $item['nmId'] ] ?? 'A' ] ?? 0,
        'warehouseName' => [ $item['warehouseName'] ],
      ];
    }

    array_walk($stocks, function(&$item){
      $item['warehouseName'] = json_encode($item['warehouseName'], JSON_UNESCAPED_UNICODE);
    });

    $this->items = $stocks;
    file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/fbostat.log", print_r($this->items, true) );
    var_dump( count($this->items) );
  }

  private function getTurnoverFromTable():void
  {
    $strSql = "SELECT * FROM current_cost_ms";
    $result = $this->dbLeg->Query($strSql, false, $err_mess.__LINE__);
    $this->fromMS = [];
    while ( $row = $result->Fetch() ){
      $this->fromMS[ $row['model'] ] = $row['cost'];
    }
    unset($row);
  }

  private function writeDB():void
  {
    if ( empty($this->items) ){
      $this->writeLog('Ошибка: массив карточек пустой');
      return;
    }
    $this->writeLog('Старт записи данных в бд');
    $dataChunks = array_chunk( array_values($this->items), 500 );
    foreach ( $dataChunks as $chunk ) {
      $this->dbNew->insert( $this->tableName, $chunk );
    }
    $this->writeLog('Конец записи в БД');
  }

  private function fuckYouBitrixORM( string $tableName , array $arrayData ):void
  {
    $cardSample = $arrayData[0];
    $fields = [];
    foreach ($cardSample as $key => $value) {
      $fields[] = $key;
    }
    if (empty($fields) || count($fields) < 2) return;
    $strSql = "INSERT INTO {$tableName} " . '(';

    $i = 0;
    foreach ($fields as $fname) {
      $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
      $i++;
    }
    $strSql .= ') VALUES ';
    $c = 0;
    foreach ($arrayData as $card){
      $strSql .= '(';
      $k = 0;
      foreach ($card as $field) {
        $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
        $k++;
      }
      $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
      $c++;
    }
    var_dump($strSql);
    $this->dbNew->query( $strSql );
  }

  private function curl( $url, $data = false )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->headers );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    if ( $data != false ){
      curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
    }
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
    $result = curl_exec( $ch );

    var_dump( curl_getinfo($ch, CURLINFO_HTTP_CODE) );
    curl_close( $ch );

    return $result;
  }

  private function writeLog( $message ):void
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }
}

$cab = $argv[1] ?? '';

if ( !in_array($cab,['WR','TL']) ) $cab = 'WR';

( new WBFboStat( $cab ) )->run();
 ?>
