<?php
class SalesDataProvider // Все, что касается манипулирования данными базы
{
  private string $cabinet;
  private DBPanel $dbPanel;
  private object $dbMain;

  private array $fboPrices;
  private array $dynamicPrices;

  public function __construct( string $cabinet, DBPanel $dbPanel, object $dbMain )
  {
    $this->cabinet = $cabinet;
    $this->dbPanel = $dbPanel;
    $this->dbMain = $dbMain;
    $this->dynamicPrices = $this->getDynamicPrices();
    $this->fboPrices = $this->getFboPrices();
  }

  // Полчение товаров и все сопутствующее
  public function getItems():array
  {
    $arFilter = [
      "IBLOCK_ID" => 16,
      "PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
    ];
    $arSelect = [
      "IBLOCK_ID", // Идентификатор инфоблока
      "ID", // Идентификатор
      "PROPERTY_CML2_ARTICLE", // Артикул
      "PROPERTY_WBARTICLE", // Артикул продавца
      "PROPERTY_BRAND", // Идентификатор бренда
      $this->getConfigParameter('ozon_id', false),
      $this->getConfigParameter('price', false),
    ];

    $result = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

    $items = [];

    while ( $row = $result->GetNext() ){

      $ozon_id = $row[ $this->getConfigParameter('ozon_id', true) ];
      $price = $row[ $this->getConfigParameter('price', true) ];
      $model = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      if ( empty( $price ) ) continue;
      if ( empty( $ozon_id ) ) continue;
      if ( empty( $model ) ) continue;

      $items[ $ozon_id ] = [
        "id" => $row['ID'],
        "price" => $this->getPriorityPrice( $model, $price ),
        "model" => $model,
        "ozon_id" => $ozon_id,
        "brand_id" => $row['PROPERTY_BRAND_VALUE'],
        "vendor_code" => $row["PROPERTY_WBARTICLE_VALUE"],
        "fbo_status" => isset( $this->fbo[$model] ),
        "dp_status" => isset( $this->dynamicPrices[$model] ),
      ];

    }
    $costs = $this->getItemsCost();

    return $this->mergeItems( $items, $costs );
  }

  private function getPriorityPrice( string $model, float $price ):float
  {
    return $this->dynamicPrices[ $model ] ?? $this->fboPrices[ $model ] ?? $price;
  }

  private function mergeItems( array $items, array $costs ):array
  {
    foreach ( $items as $ozon_id => &$data ){
      $cost = $costs[ $data['model'] ] ?? false;

      if ( !$cost ) {
        // some log function
        unset( $items[$ozon_id] );
        continue;
      }
      $data['cost'] = $cost;
    }

    return $items;
  }

  private function getItemsCost():array
  {
    $strSql = "SELECT ARTICLE, PRICE_SUPPLIER FROM ci_price_set WHERE PRICE_TYPE = 'OS'";
    $rows = $this->dbMain->query( $strSql );
    $result = [];

    while( $row = $rows->Fetch() ){
      $result[ $row['ARTICLE'] ] = (float) $row['PRICE_SUPPLIER'];
    }

    return $result;
  }

  private function getItemsCostLeg( ):array
  {
    $reserved = $this->getReserves();
    $fbo = $this->getFboCost();

    $filter = $this->getConfigParameter('filter', false);
    $strSql = "SELECT * FROM ci_price WHERE {$filter} = 'Y'";
    $result = $this->dbMain->Query( $strSql );

    $priceData = [];
    $items = [];

    while ( $row = $result->Fetch() ){
      $priceData[ $row['model'] ][] = [
        'count' => $row['count'],
        'price' => $row['price'],
      ];
    }

    foreach ( $priceData as $model => $priceVariants ){
      $items[$model] = $this->getMinCost(
        model: $model,
        data: $priceVariants,
        fbo: $fbo,
        reserved: $reserved
      );
    }

    foreach ( $fbo as $model => $cost ){
      if ( isset($items[$model]) ) continue;
      $items[$model] = $cost;
    }

    return $items;
  }

  private function getReserves():array
  {
    $strSql = "SELECT * FROM ci_reserved";
    $result = $this->dbMain->Query( $strSql );

    $items = [];
    while ( $row = $result->Fetch() ){
      $items[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $items;
  }

  private function getFboPrices():array
  {
    $rows = $this->dbPanel->select(['*'], "ozon_fbo_price_{$this->cabinet}")->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = $row['price'];
    }

    return $result;
  }

  private function getFboCost():array
  {
    $rows = $this->dbPanel->select(['*'], "ozon_fbo_sebes_{$this->cabinet}")->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['sebes'];
    }

    return $result;
  }

  private function getDynamicPrices():array
  {
    // return [];
    $rows = $this->dbPanel->select(['*'], 'ozon_dp_prices')->where('cabinet', $this->cabinet)->make();
    $result = [];
    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['price'];
    }

    return $result;
  }

  private function getConfigParameter( string $name, bool $isValue )
  {
    $param = SalesConfigProvider::getDataConfig($this->cabinet)[$name] ?? '';
    return $isValue ? "{$param}_VALUE" : $param;
  }

  private function getMinCost( string $model, array $data, array $fbo, array $reserved ):float
  {
    if ( isset($fbo[$model]) ){
      return floatval( $fbo[$model] );
    }
    if ( empty($data) ) return 0;

    usort($data, function($a, $b) {
      return $a['price'] <=> $b['price'];
    });

    $result = 0;
    $itemReserved = $reserved[$model] ?? 0;

    foreach ( $data as $priceData ){
      if ( $priceData['count'] - $itemReserved <= 0 ){
        $itemReserved = abs($priceData['count'] - $itemReserved);
        continue;
      }

      $result = floatval( $priceData['price'] );
      break;
    }

    return $result;
  }

  // Полчение акций и все сопутствующее
  public function getSalesList():array
  {
    $date = date("d.m.Y");

    $rows = $this->dbPanel->select(['*'], "ozon_sales_{$this->cabinet}")->where('active', 1)->asc('sort')->make();
    $items = [];

    foreach ( $rows as $row ){
      $items[ $row['sale_id'] ] = $row;
    }

    return $items;
  }

  private function parseExcludedBrands( string $brands ):array
  {
    if ( empty( trim($brands) ) ) return [];

    $array = explode(',', $brands);
    $array = array_filter( $array );

    return array_map("trim", $array);
  }

  // Получение настроек для АПИ
  public function getMainSettings():array
  {
    $rows = $this->dbPanel->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['name'] ] = $row['value'];
    }

    return $result;
  }

  // Получение настроек модуля
  public function getSalesSettings():array
  {
    $rows = $this->dbPanel->select(['*'], "ozon_sales_pi_{$this->cabinet}")->make();
    $result = $rows[0] ?? [];

    $result['brands_unset'] = $this->parseExcludedBrands( $result['brands_unset'] ?? '' );

    return $result;
  }

}
 ?>
