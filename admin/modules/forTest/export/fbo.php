<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class Pens
{
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
  }

  public function getStock():void
  {
    $rows = $this->dbLeg->query("SELECT nmid, article FROM wdhs_wb_props WHERE cabinet = 'WR'");
    $dict = [];
    while ( $row = $rows->fetch() ){
      $dict[ $row['nmid'] ] = $row['article'];
    }

    $url = "https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses";

    $res = $this->curl($url, [ "limit" => 250000, "offset" => 0 ]);

    $result = json_decode( $res, true );

    $stocks = [];
    foreach ( $result['data']['items'] as $item ){
      $stocks[] = [
        'stock_date' => date('Y-m-d G:i:s'),
        'nmid' => $item['nmId'],
        'model' => $dict[ $item['nmId'] ] ?? $item['nmId'],
        'to_client' => $item['inWayToClient'],
        'from_client' => $item['inWayFromClient'],
        'stock' => $item['quantity'],
        // 'cost' => $this->fromMS[ $dict[ $item['nmId'] ] ?? 'A' ] ?? 0,
        'warehouseName' => $item['warehouseName'],
      ];
    }

    $headers = array_keys( reset($stocks) );
    $rowZ[] = $headers;
    $rowZ = array_merge($rowZ, $stocks);

    $rowZ = array_map( fn($item) => implode(';', $item), $rowZ );
    var_dump($rowZ);
    $text = implode(PHP_EOL, $rowZ);

    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/raaw.csv",
      $text
    );
  }

  public function parse():void
  {
    $json = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/fboResponse.json");
    $ar = json_decode( $json, true );
    $rr = [];
    foreach ( $ar as $p ) {
      $model = end( explode('_', $p['vendorCode']) );
      foreach ( $p['warehouses'] as $wh ){
        $rr[] = [
          'model' => $model,
          'whName' => $wh['warehouseName'],
          'quan' => $wh['quantity'],
        ];
      }
    }

    $headers = array_keys( reset($rr) );
    $rowZ[] = $headers;
    $rowZ = array_merge($rowZ, $rr);

    $rowZ = array_map( fn($item) => implode(';', $item), $rowZ );
    var_dump($rowZ);
    $text = implode(PHP_EOL, $rowZ);

    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/raaw.csv",
      $text
    );

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
}

(new Pens)->parse();

 ?>
