<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_ozon_control_checkCheckFBo_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require( $_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/ozon/lib/core.php" );

class checkCheckFBO
{
  private ?ApiManager $api = null;

  public function __construct()
  {
    CModule::IncludeModule('panel.manager');
    $dbPanel = new DBPanel;
    $rows = $dbPanel->select(['*'], 'ozon_main_settings_IP')->make();

    $settings = [];
    foreach ( $rows as $row ){
      $settings[ $row['name'] ] = $row['value'];
    }

    $this->api = new ApiManager( $settings );
    $this->dbPanel = $dbPanel;
  }

  public function run():void
  {
    $sow = $this->getStockOnWarehouses();
    $mas = $this->getAnalyticsStock();

    $this->compareInventory( $sow, $mas );
  }

  private function getStockOnWarehouses():array
  {
    $data = [
      'offset' => 0,
      'limit' => 1000,
    ];
    $flag = true;
    $stocks = [];

    while ( $flag ){
      $result = $this->api->getStockOnWarehouses( $data );
      $response = $result->getData()->decode();

      if ( $response['result']['rows'] < 1000 ) $flag = false;

      foreach ( $response['result']['rows'] as $row ){
        if ( isset($stocks[ $row['item_code'] ]) ){
          $stocks[ $row['item_code'] ] += $row['free_to_sell_amount'];
          continue;
        }
        $stocks[ $row['item_code'] ] += $row['free_to_sell_amount'];
      }

      $data['offset'] += $data['limit'];
    }

    return $stocks;
  }

  private function getAnalyticsStock():array
  {
    $skus = $this->getSKUDictionary();
    $chunks = array_chunk( $skus, 100 );

    $stocks = [];

    foreach ( $chunks as $chunk ) {
      $data = [
        'skus' => $chunk
      ];
      $result = $this->api->getAnalyticsStock( $data );
      $response = $result->getData()->decode();

      foreach ( $response['items'] as $row ){
        if( empty($row['available_stock_count']) ) continue;
        if ( isset( $stocks[ $row['offer_id'] ] ) ){
          $stocks[ $row['offer_id'] ] += $row['available_stock_count'];
          continue;
        }
        $stocks[ $row['offer_id'] ] = $row['available_stock_count'];
      }

      sleep(2);
    }

    return $stocks;
  }

  private function getSKUDictionary():array
  {
    $rows = $this->dbPanel->select(['*'], 'ozon_sku_dict_IP')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[] = (string)$row['sku'];
    }

    return $result;
  }

  private function compareInventory($inventory1, $inventory2) {
      $skus = $this->getSKUDictionary();
      $result = [
          'identical' => false,
          'only_in_first' => [],
          'only_in_second' => [],
          'different_quantities' => []
      ];

      // Проверка полной идентичности
      if ($inventory1 === $inventory2) {
          $result['identical'] = true;
          return $result;
      }

      // Находим артикулы, которые есть только в первом массиве
      $result['only_in_first'] = array_diff_key($inventory1, $inventory2);

      // Находим артикулы, которые есть только во втором массиве
      $result['only_in_second'] = array_diff_key($inventory2, $inventory1);

      // Находим артикулы с разными количествами
      $commonKeys = array_intersect_key($inventory1, $inventory2);

      foreach ($commonKeys as $key => $value) {
          if ($inventory1[$key] !== $inventory2[$key]) {
              $result['different_quantities'][$key] = [
                  'first' => $inventory1[$key],
                  'second' => $inventory2[$key]
              ];
          }
      }
      $hasSKU = [];
      foreach ( $result['only_in_first'] as $item ){
        $model = end( explode('_', $item) );
        if ( isset($skus[$model]) ){
          $hasSKU[$model] = 1;
          continue;
        }
        $hasSKU[$model] = 0;
      }
      $result['hasSKU'] = $hasSKU;
      $this->log( $result );
      $this->log( '-----------------------------------------------------------' );
  }

  private function log( mixed $message ):void
  {
    $display = date('Y-m-d G:i:s') . ' --- ' . print_r($message, true) . PHP_EOL;
    file_put_contents(
      '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/control/control.log',
      $display,
      FILE_APPEND
    );
  }
}

( new checkCheckFBO )->run();
 ?>
