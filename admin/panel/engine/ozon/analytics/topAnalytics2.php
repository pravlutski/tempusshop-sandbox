<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

class TopAnalyticsCollector
{
  private DBPanel $panel;

  public function __construct()
  {
    $this->panel = new DBPanel;
  }

  public function run():void
  {
    $data = $this->collectAnalyticsData();
    $this->deleteTodayData();
    $this->importData( $data );
  }

  private function collectAnalyticsData():array
  {
    $models = $this->getTopModels();
    $fbo = $this->getFboStocks();
    $salesInfo = $this->getSalesData( $models );
    $pricesSTD = $this->getFinalPrices( $models );
    $pricesCLI = $this->getClientPrices( $models );

    $result = [];

    foreach ( $models as $model ){
      $result[] = [
        'model' => $model,
        'our' => $pricesSTD[$model] ?? 0,
        'sell' => $pricesCLI[$model] ?? 0,
        'spp' => 0,
        'is_fbo' => isset($fbo[$model]) ? 'Y' : 'N',
        'sale_name' => $salesInfo[$model] ?? 'Не участвует',
        'orders_count' => 0,
        'date' => date('Y-m-d'),
      ];
    }

    return $result;
  }

  private function deleteTodayData():void
  {
    $date = date('Y-m-d');
    $where = [ 'column' => 'date', 'operator' => '=', 'value' => $date ];
    $this->panel->delete( 'ozon_top_analytics', [$where] );
  }

  private function importData( array $data ):void
  {
    $this->panel->insert('ozon_top_analytics', $data );
  }

  private function getTopModels():array
  {
    $rows = $this->panel->select(['model'], 'ozon_top_models')->make();

    return array_map( fn($item) => $item['model'], $rows );
  }

  private function getFboStocks():array
  {
    $rows = $this->panel->select(['article'], 'ozon_fbo_stock_IP')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = true;
    }

    return $result;
  }

  private function getSalesData( array $models ):array
  {
    $date = date('Y-m-d');
    $rows = $this->panel->select(['model', 'saleName'], 'ozon_sales_detail_log_IP')
      ->where('model', $models)
      ->where('date', "%$date%", 'LIKE')
      ->where('status', 'Y')
      ->make();

    $result = [];

    foreach ( $rows as $row ){
      $ts = strtotime($row['date']);
      $result[ $ts ][ $row['model'] ] = $row['saleName'];
    }

    $lastTS = max( array_keys($result) );

    return $result[ $lastTS ];
  }

  private function getDynamicPrice( array $models ):array
  {
    $rows = $this->panel->select(['model', 'price'], 'ozon_dp_prices')->make();

    return array_column( $rows, 'price', 'model');
  }

  private function getFinalPrices( array $models ):array
  {
    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $models
    ];
    $arSelect = ["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_OZSB_PRICE"];

    $rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect );
    $dp = $this->getDynamicPrice( $models );

    $result = [];

    while( $row = $rows->GetNext() ){
      $model = (string) $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $price = (float) $row['PROPERTY_OZSB_PRICE_VALUE'];

      $result[ $model ] = $dp[$model] ?? $price;
    }

    return $result;
  }

  private function getSkusDictionary( array $models ):array
  {
    $rows = $this->panel->select(['model', 'sku'], 'ozon_sku_dict_IP')->where('model', $models)->make();

    return array_column( $rows, 'sku', 'model' );
  }

  private function getHeaders():array
  {
    $rows = $this->panel->select(['*'], 'ozon_main_settings_IP')->make();
    $settings = array_column( $rows, 'value', 'name' );

    return [
      "Api-Key: {$settings['key']}",
      "Client-Id:{$settings['client_id']}",
      'Content-Type:application/json'
    ];
  }

  private function getClientPrices( array $models ):array
  {
    $skus = $this->getSkusDictionary( $models );
    $data = array_map( fn($item) => $skus[$item] ?? null, $models );
    $data = array_map( 'strval', array_filter($data) );
    $data = array_values( $data );

    $response = $this->request(
      url: "https://api-seller.ozon.ru/v1/product/prices/details",
      headers: $this->getHeaders(),
      data: json_encode([ 'skus' => $data ]),
    );

    if ( $response['code'] != 200 ){
      var_dump($response['raw']);
      var_dump($response['code']);
      throw new Exception('Unexepected error during getting clients prices');
    }

    $rows = $response['result']['prices'];
    $result = [];

    foreach ( $rows as $row ){
      $model = end( explode('_', $row['offer_id']) );
      $result[ $model ] = $row['customer_price']['amount'];
    }

    return $result;
  }

  private function request( string $url, array $headers, ?string $data ):array
  {
    $ch = curl_init( $url );
    curl_setopt_array( $ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => $data
    ]);

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close( $ch );

    return [
      'result' => json_decode($res, true),
      'raw' => $res,
      'code' => $code
    ];
  }


}

(new TopAnalyticsCollector)->run();
 ?>
