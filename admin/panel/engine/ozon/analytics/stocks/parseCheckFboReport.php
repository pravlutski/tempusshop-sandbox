<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class CheckFboReport
{
  private DBPanel $panel;

  public function __construct()
  {
    $this->panel = new DBPanel;
    $this->main = \Bitrix\Main\Application::getConnection();
  }

  public function fun():void
  {
    $data = $this->parseFile( $path );
    $this->save( $data );
  }

  private function parseFile():array
  {
    $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/checkFbo.json";
    $json = file_get_contents( $path );
    $data = json_decode( $json, true );

    $ms = $this->getCostFromMS();

    $result = array_map( function($item) use ($ms){
      return [
        'sku' => $item['sku'],
        'model' => end( explode('_', $item['item_code']) ),
        'stock' => $item['free_to_sell_amount'],
        'reserved' => $item['reserved_amount'],
        'promised' => $item['promised_amount'],
        'cost' => $ms[ end( explode('_', $item['item_code']) ) ] ?? 0,
        'date' => date('Y-m-d'),
      ];
    }, $data );

    return $this->prepare( $result );
  }

  private function prepare( array $items ):array
  {
    $result = [];
    $keys = array_keys( reset($items) );
    $excl = [
      'sku' => true,
      'model' => true,
      'cost' => true,
      'sku' => true,
      'date' => true,
    ];

    foreach( $items as $row )
    {
      if ( isset($result[$row['sku']]) ){
        foreach ( $keys as $key ){
          if ( $excl[$key] ) continue;
          $result[ $row['sku'] ][ $key ] += $row[$key];
        }
        continue;
      }
      $result[ $row['sku'] ] = $row;
    }

    return array_values( $result );
  }

  private function getCostFromMS():array
  {
    $rows = $this->main->query("SELECT * FROM current_cost_ms");
    $result = [];

    while( $row = $rows->fetch() ){
      $result[ $row['model'] ] = $row['cost'];
    }

    return $result;
  }

  private function save( array $items ):void
  {
    $this->panel->insert( 'ozon_fbo_stat_IP', $items );
  }
}

( new CheckFboReport )->fun();
 ?>
