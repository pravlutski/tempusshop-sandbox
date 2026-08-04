<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require('ZennolabParser.php');
set_time_limit(0);

class TopAnalytics
{
  private $dbLeg;
  private $dbPanel;

  private $items = [];
  private $topGoods = [];
  private $fboGoods = [];
  private $saleGoods = [];

  private $salesX5;
  private $salesX4;

  private $api = [];
  private $cabinet = '';

  private $headers = [];

  public function __construct( $cabinet = 'IP', $clearFlag = 'N' )
  {
    if ( !in_array($cabinet, ['IP','TI']) ) die('Cabinet unknown');
    $this->cabinet = $cabinet;
    $this->clearFlag = $clearFlag;
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
    $this->getActualSalesInfo();
    $this->getSalesCandidates();
    $this->getPriceInfo();
    $this->getZennolabData();
    $this->clearTodayData();
    $this->writeInDatabase();
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

    $result = $this->dbPanel->query("SELECT * FROM ozon_fbo_stock_{$this->cabinet}");
    $rows = $this->dbPanel->fetchAll($result);
    foreach ($rows as $row) {
    	$this->fboGoods[$row['article']] = $row['article'];
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

    $index = 0;
    while ( $el = $result->GetNext() ){
    	$this->topGoods[$el['PROPERTY_WBARTICLE_VALUE']] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
      $this->items[ $el['PROPERTY_OZON_ID_VALUE'] ] = $el['PROPERTY_WBARTICLE_VALUE'];
    }

  }

  public function getActualSalesInfo():void
  {
    // $strSql = "SELECT sn.name as saleName, bis.model as model, bis.action_max_price as salePrice FROM ozon_sales_info_{$this->cabinet} as bis JOIN ozon_sales_{$this->cabinet} as sn ON bis.sale_id = sn.sale_id";

    $strSql = "SELECT sale_id, name, sort FROM ozon_sales_{$this->cabinet} WHERE name LIKE '%Бустинг х4%' ORDER BY sort ASC";
    $strSql = "SELECT sale_id, name, sort FROM ozon_sales_{$this->cabinet}";
    $result = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll($result);

    $whereStatement = "";
    foreach ( $rows as $row ){
      $this->salesX5[$row['sale_id']] = [
        'id' => $row['sale_id'],
        'name' => $row['name'],
        'sort' => $row['sort'],
      ];
    }
    unset( $rows );
    unset( $result );
    if ( empty($this->salesX5) ){
      $this->writeLog('Нет акций x5');
      var_dump('no salse');
      return;
    }
    $sid = implode(',', array_keys($this->salesX5) );
    $strSql = "SELECT sale_id, model, action_max_price FROM ozon_sales_info_{$this->cabinet} WHERE sale_id IN ({$sid})";
    $result = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll($result);

    foreach ($rows as $row) {
    	$this->saleGoods[ $row['model'] ] = [
        'name' => $this->salesX5[$row['sale_id']]['name'],
        'price' => $row['action_max_price']
      ];
    }
  }

  public function getSalesCandidates():void
  {
    $strSql = "SELECT sale_id, name, sort FROM ozon_sales_{$this->cabinet} WHERE name LIKE '%Бустинг%' ORDER BY sort ASC";
    $res = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll( $res );
    $boost_id = $rows[0]['sale_id'];

    $check = true;
    $i = 0;
    $allCands[$sale['id']] = [];
    $last_id = "";
    while ( $check ){
      $data = [
        'action_id' => $boost_id,
        'limit' => 1000,
        'last_id' => $last_id
      ];
      $url = "https://api-seller.ozon.ru/v1/actions/candidates";
      $res = $this->request( $url, $this->headers, json_encode($data) );
      if (isset($res['result'])) {
        if (count($res['result']['products']) < 1000) {
          $check = false;
        } else {
          $check = true;
        }
        $allCands[$sale['id']] = array_merge( $allCands[$sale['id']], $res['result']['products'] );
      } else {
        $check = false;
        print_r($res );
        echo "ОШИБКА В ОТВЕТЕ API OZON. getSalesCandidates";
        $this->writeLog('ОШИБКА В ОТВЕТЕ API OZON. getSalesCandidates');
        return;
      }
      $last_id = $res['result']['last_id'];
      $i += 1000;
    }
    foreach ( $allCands as $sale ){
      foreach ( $sale as $product ){
        $this->salesX4[ $product['id'] ] = $product['max_action_price'];
      }
    }

  }

  public function getPriceInfo():void
  {
    $data = [
  		"filter" => [
        "offer_id" => array_keys($this->topGoods)
      ],
  		"limit" => 1000
  	];
    $url = "https://api-seller.ozon.ru/v5/product/info/prices";
    $res = $this->request( $url, $this->headers, json_encode($data) );

    foreach ( $res['items'] as $item ) {
      $our = $item['price']['marketing_seller_price'];
      $sell = $item['price']['marketing_price'];
      $spp = ( $our - $sell ) / $our * 100;
      $model = end( explode('_', $item['offer_id']) );

      if ( isset( $this->salesX4[ $item['product_id'] ] ) ){
        $sale_price = $this->salesX4[ $item['product_id'] ];
      }
      elseif ( isset($this->saleGoods[$model]) ){
        $sale_price = $this->saleGoods[$model]['price'];
      }
      else{
        $sale_price = NULL;
      }

    	$this->topGoods[ $item['offer_id'] ] = [
        'model' => $model,
        'our' => $our,
        'sell' =>	intval($sell),
        'spp' => intval($spp),
        'is_fbo' => isset( $this->fboGoods[$model] ) ? 'Y' : 'N',
        'sale_name' => isset( $this->saleGoods[$model] ) ? $this->saleGoods[$model]['name'] : 'Не участвует',
        'sale_price' => $sale_price ?? 0,
        'date' => date('Y-m-d'),
        'orders_count' => 0
        // 'date' => '2025-02-20'
      ];
    }

    unset( $our );
    unset( $sell );
    unset( $spp );
  }

  private function clearTodayData():void
  {
    $date = date('Y-m-d');
    $strSql = "DELETE FROM ozon_top_analytics WHERE date ='{$date}'";
    $this->dbPanel->query( $strSql );
  }

  private function getZennolabData():void
  {
    $obj = new ZennolabParser( false );
    $obj->run();
  }

  public function writeInDatabase():void
  {
    $data = array_values( $this->topGoods );
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/log.txt", print_r($data, true));
    $this->fuckYouBitrixORM( 'ozon_top_analytics', $data );
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

(new TopAnalytics('IP'))->run();

 ?>
