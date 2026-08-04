<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

class CollectionWB
{
  private $items;
  private $requestBody;

  private $db;
  private $dbPanel;

  private $header;
  private $cabinet;

  public function __construct( $cabinet = "WR" )
  {
    $this->cabinet = $cabinet;
    global $DB;
    $this->db = $DB;
    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$cabinet}'";
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $results->SelectedRowsCount() < 1 ){
      $this->writeLog('No API settings for cabinet.');
      die;
    }
    CModule::includeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $api = $results->Fetch();

    $this->headers = [
      "Content-Type: application/json",
      "Authorization: " . $api['api']
    ];
  }

  public function run():void
  {
    $this->getItems();
    $this->buildArray();
    $this->sendRequest();
  }

  public function getItems():void
  {
    $strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}'";
    $res = $this->db->Query($strSql, false, $err_mess.__LINE__);
    $this->items = [];
    while( $row = $res->Fetch() ){
      $this->items[ $row['article'] ] = $row['nmid'];
    }

    if ( empty($this->items) ){
      die('Table wdhs_wb_props is empty');
    }

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => array_keys( $this->items )
    ];

    $arSelect = ["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLLECTION"];
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    $collection = [];

    while( $row = $result->GetNext() ){
      if ( empty($row['PROPERTY_COLLECTION_VALUE']) ) continue;
      if ( empty( $this->items[$row['PROPERTY_CML2_ARTICLE_VALUE']] ) ) continue;
      $collection[ $row['PROPERTY_COLLECTION_VALUE'] ][] = $this->items[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ];
    }
    $this->items = $collection;
  }

  public function buildArray():void
  {
    if ( empty($this->items) ) {
      var_dump( 'empty itmes');
      die;
    }
    $this->requestBody = [];
    foreach ( $this->items as $collection => $nmids ){
      if ( count($nmids) <= 30 ){
        $this->requestBody[] = $nmids;
      }else{
        $split = array_chunk( $nmids, 30 );
        foreach ( $split as $k => $chunk ){
          $this->requestBody[] = $chunk;
        }
      }
    }
  }

  public function sendRequest():void
  {
    if ( empty($this->requestBody) ){
      var_dump( 'empty this->requestBody');
      die;
    }
    $url = "https://content-api.wildberries.ru/content/v2/cards/moveNm";
    foreach ($this->requestBody as $key => $value) {
      try{
        $data = array_map( function( $item ){
          return intval( $item );
        },$value );
      } catch( Throwable $e ){
        var_dump( $key );
        var_dump( $value );
        var_dump( $e->getMessage() );
        die;
      }
      $dataStr = json_encode( ['nmIDs' => $data] );
      $this->request(
        $url,
        $this->headers,
        $dataStr
      );
      sleep(1);
    }
  }

  private function request( $url, $headers = [], $body = '', $customReq = 'GET' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    // curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $customReq );
    $res = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close( $ch );

    if ( $error_msg ) {
      $this->writeLog('CUrl returned an error: ' . $error_msg);
      return false;
    }
    $result = json_decode( $res, true );
    $result['http_code'] = $http_code;
    return $result;
  }
}

( new CollectionWB )->run();
 ?>
