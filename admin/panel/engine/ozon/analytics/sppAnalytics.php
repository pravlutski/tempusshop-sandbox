<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require('ZennolabParser.php');
set_time_limit(0);

class SppAnalytics
{
  private $dbLeg;
  private $dbPanel;

  private $items = [];
  private $topGoods = [];
  private $fboData = [];

  private $parsedData = [];

  private $api = [];
  private $cabinet = '';

  private $headers = [];

  public function __construct( $cabinet )
  {
    if ( !in_array($cabinet, ['IP','TI']) ) die('Cabinet unknown');
    $this->cabinet = $cabinet;
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $result = $this->dbPanel->query("SELECT * FROM ozon_main_settings_{$cabinet}");
    $rows = $this->dbPanel->fetchAll( $result );
    foreach ( $rows as $row ) {
    	$arSettings[$row['name']] = $row['value'];
    }
    $this->headers = [
      'Api-Key:' . $arSettings['key'],
			'Client-Id:' . $arSettings['client_id'],
			'Content-Type:application/json'
    ];
  }

  public function run()
  {
    $this->getItems();
    var_dump('GOT ITEMS');
    $this->getFboData();
    var_dump('GOT FBO');
    $this->getZennolabData();
    $this->getPriceInfo();
    var_dump('GOT PRICES');
    $this->writeInDatabase();
    // var_dump($this->topGoods);
  }

  public function getItems():void
  {
    $result = $this->dbPanel->query("SELECT * FROM ozon_top_models");
    $rows = $this->dbPanel->fetchAll($result);
    foreach ($rows as $row) {
    	$tops[] = $row['model'];
    }
    unset($result);
    unset($rows);

    foreach ($tops as $ka => $va) {
    	$execCurl[] = $va;
    }

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_WBARTICLE", "PROPERTY_OZON_ID");
    $arFilter = Array(
    	"IBLOCK_ID" => CProSet::IB_CATALOG,
    	"PROPERTY_CML2_ARTICLE" => $execCurl,
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

    while ( $el = $result->GetNext() ){
    	$this->topGoods[$el['PROPERTY_WBARTICLE_VALUE']] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
      $this->items[ $el['PROPERTY_OZON_ID_VALUE'] ] = $el['PROPERTY_WBARTICLE_VALUE'];
    }
  }

  private function getFboData():void
  {
    $json_path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/export/analytics.json";
    if ( !file_exists( $json_path ) ) return;

    $json = file_get_contents( $json_path );
    $this->fboData = json_decode($json, true);

  }

  private function getZennolabData():void
  {
    $obj = new ZennolabParser(false);
    $this->parsedData = $obj->exportDataAsArray();
  }

  private function getPriceInfo():void
  {
    $data = [
  		"filter" => [
        "offer_id" => array_keys($this->topGoods)
      ],
  		"limit" => 1000
  	];
    $url = "https://api-seller.ozon.ru/v5/product/info/prices";
    $res = $this->request( $url, $this->headers, json_encode($data) );
    // var_dump($res);

    foreach ( $res['items'] as $item ) {
      $our = $item['price']['marketing_seller_price'];
      $sell = $item['price']['marketing_price'];
      $model = end( explode('_', $item['offer_id']) );

    	$this->topGoods[ $item['offer_id'] ] = [
        'model' => $model,
        'our_price' => $this->parsedData[ $model ]['our_price'] ?? 0,
        'green_price' => $this->parsedData[ $model ]['green'] ?? 0,
        'black_price' => $this->parsedData[ $model ]['black'] ?? 0,
        'stock_fbo' => $this->fboData[ $model ] ?? 0,
        'hour' => date('G'),
        'date' => date('Y-m-d')
      ];
    }

    unset( $our );
    unset( $sell );
  }

  public function writeInDatabase():void
  {
    $data = array_values( $this->topGoods );
    // var_dump($data);
    $this->fuckYouBitrixORM( 'ozon_spp_analytics_by_hour', $data );
    // foreach ( $data as $chunk ){
    //   // var_dump( end($chunk) );
    // }
  }

  public function request( $url, $headers = [], $body = '' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    $res = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }
    curl_close( $ch );

    if ( $error_msg ) {
      $this->writeLog('CUrl returned an error: ' . $error_msg);
      return false;
    }

    return json_decode( $res, true );
  }

  private function writeLog( string $message ):void
  {
    // file_put_contents( $this->logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

  private function fuckYouBitrixORM($tableName , $arrayData)
  {
    global $DB;
    $cardSample = $arrayData[0];
    $fields = [];
    foreach ($cardSample as $key => $value) {
      $fields[] = $key;
    }
    if (empty($fields) || count($fields) < 2) return false;
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
    // var_dump($strSql);
    $this->dbPanel->query( $strSql );
  }

}

(new SppAnalytics('IP'))->run();

 ?>
