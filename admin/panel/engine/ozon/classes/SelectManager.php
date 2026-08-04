<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]. '/admin/panel/engine/ozon/lib/core.php');

class SelectManager
{
  public function __construct(
    private \Bitrix\Main\DB\MysqliConnection $main,
    private DBPanel $panel
  ){}

  public function run()
  {
    CommunicationService::initConnection(
      panel: $this->panel,
      module: 'selectVisibility_IP'
    );

    CommunicationService::updateStatus(
      text: 'Получаем активные товары',
      percent: 0,
      status: 'PROCESS',
      start: date('Y.m.d G:i:s')
    );

    $items = $this->getItems();
    CommunicationService::updateStatus( percent: 20, text: 'Получаем настройки' );
    $settings = $this->getSettings();
    CommunicationService::updateStatus( percent: 40, text: 'Распределяем товары по группам' );
    $data = $this->distributeItems( $items, $settings );
    //
    // var_dump( $data );
    // file_put_contents(
    //   "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/select.txt",
    //   print_r($data, true)
    // );
    // die;
    CommunicationService::updateStatus( percent: 40, text: 'Отправляем запросы' );
    $this->setPlacement(
      data: $data,
      headers: $this->getHeaders( $settings )
    );

    CommunicationService::updateStatus(
      text: 'Завершено',
      percent: 100,
      status: 'COMPLETE',
      end: date('Y.m.d G:i:s')
    );
  }

  private function getItems():array
  {
    $activeItemsIds = $this->getActiveItems();
    $activeItemsData = $this->getItemsData( $activeItemsIds );

    return $activeItemsData;
  }

  private function getActiveItems():array
  {
    $strSql = "SELECT PRODUCT_ID FROM ci_price_set WHERE PRICE_TYPE = 'OS'";
    $rows = $this->main->query( $strSql );
    $result = [];

    while( $row = $rows->fetch() ){
      $result[] = $row['PRODUCT_ID'];
    }

    return $result;
  }

  private function getItemsData( array $ids ):array
  {
    if ( empty($ids) ) throw new Exception("Cannot get items data with an empty filter");

    $rows = CIBlockElement::getList(
      [],
      [ "IBLOCK_ID" => 16, "ID" => $ids ],
      false, false,
      ["ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_OZSB_PRICE"]
    );

    $dictionary = $this->getSkuDictionary();
    $dp = $this->getDPPrices();

    $result = [];

    while( $row = $rows->GetNext() ){
      $model = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $result[] = [
        'id' => $row['ID'],
        'model' => $model,
        'sku' => $dictionary[ $model ] ?? null,
        'price' => $dp[$model] ?? $row['PROPERTY_OZSB_PRICE_VALUE'],
      ];
    }

    return $result;
  }

  private function getSkuDictionary():array
  {
    return array_column(
      $this->panel->select(['*'], 'ozon_sku_dict_IP')->make(),
      'sku',
      'model'
    );
  }

  private function getDPPrices():array
  {
    $rows = $this->panel->select(['model', 'price'], 'ozon_dp_prices')->make();
    if ( empty($rows) ) return [];
    return array_column( $rows, 'price', 'model' );
  }

  private function distributeItems( array $items, array $settings ):array
  {
    $result = [];
    $threshold = $settings['select_threshold'];

    foreach ( $items as $item ){
      if ( empty($item['sku']) ) continue;

      $result[] = [
        'sku' => (int) $item['sku'],
        'placement' => ($item['price'] > $threshold) ? 'OZON_SELECT' : 'OZON',
      ];
    }

    return $result;
  }

  private function getSettings():array
  {
    $rows = $this->panel->select(['*'], 'ozon_main_settings_IP')->make();

    return array_column( $rows, 'value', 'name' );
  }

  private function getHeaders( array $settings ):array
  {
    return [
      "Api-Key: {$settings['key']}",
      "Client-Id:{$settings['client_id']}",
      'Content-Type:application/json'
    ];
  }


  private function setPlacement( array $data, array $headers ):void
  {
    $batches = array_chunk( $data, 100 );

    foreach( $batches as $batch ){
      $res = $this->request(
        url: "https://api-seller.ozon.ru/v1/product/visibility/set",
        data: json_encode( ["item_placement" => $batch] ),
        headers: $headers,
      );
      if ( $res['code'] == 200 ){
        file_put_contents(
          "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/selectResponse.txt",
          print_r($res['response'], true) . PHP_EOL,
          FILE_APPEND
        );
      }else{
        file_put_contents(
          "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/selectResponseError.txt",
          print_r($res['response'], true) . PHP_EOL,
          FILE_APPEND
        );
      }
      sleep( 3 );
    }
  }

  private function request( string $url, string $data, array $headers ):array
  {
    $ch = curl_init( $url );

    curl_setopt_array( $ch, [
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_RETURNTRANSFER => true,
    ]);

    $res = curl_exec( $ch );
    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

    return [
      'response' => json_decode( $res, true ),
      'raw' => $res,
      'code' => $code,
    ];
  }
}

$obj = new SelectManager(
  panel: new DBPanel,
  main: \Bitrix\Main\Application::getConnection()
);
$obj->run();
 ?>
