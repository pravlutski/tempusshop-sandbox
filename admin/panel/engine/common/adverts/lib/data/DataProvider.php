<?php
class DataProvider
{
  public function __construct(
     private DBPanel $panel,
     private \Bitrix\Main\DB\MysqliConnection $main,
     private MoyskladAPI $ms,
     private string $platform
  ){}

  public function getSettings():array
  {
    $rows = $this->panel->select(['*'], "am_mp_settings")->where('platform', $this->platform)->make();

    return reset( $rows );
  }

  public function getProfiles():array
  {
    $rows = $this->panel->select(['*'], "am_brand_profiles")->where('platform', $this->platform)->make();

    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['brand_id'] ] = [
        'minCost' => $row['minCost'] ?? Config::instance()->getDefaultValue('minCost'),
        'maxCost' => $row['maxCost'] ?? Config::instance()->getDefaultValue('maxCost'),
        'stockDays' => $row['stockDays'] ?? Config::instance()->getDefaultValue('stockDays'),
        'bid' => $row['bid'] ?? Config::instance()->getDefaultValue('bid'),
      ];
    }

    return $result;
  }

  public function getCampaigns():array
  {
    $rows = $this->panel->select(['advertId',' brand'], 'am_campaign_products')->where('platform', Config::instance()->getPlatform())->group('advertId, brand')->make();

    $this->getCampaignsNameNumbers();

    return empty($rows) ? [] : array_column($rows, 'brand', 'advertId');
  }

  public function getBrandDictionary():array
  {
    $rows = CIBlockElement::getList([], ["IBLOCK_ID" => 11], false, false, ['ID', 'NAME']);
    $result = [];

    while( $row = $rows->GetNext() ){
      $result[ $row['ID'] ] = $row['NAME'];
    }

    return $result;
  }

  public function getItems():array
  {
    $settings = $this->getSettings();
    $stockData = $this->getStockData2( $settings );

    $reserved = $this->getReserved( $settings );

    return $this->getItemsInfo( $stockData, $settings );
  }

  public function getStockData( array $settings ):array
  {
    if ( empty($settings['store']) ) throw new Exception("Store settings are not installed");

    $storeList = explode( '|', $settings['store'] );
    $stores = array_map( fn($item) => "store=https://api.moysklad.ru/api/remap/1.2/entity/store/" . $item, $storeList );

    $filter = implode( ';', $stores );
    $this->ms->getStock( filter: "filter=" . $filter );

    return $this->ms->MSPosition;
  }

  public function getStockData2( array $settings ):array
  {
    if ( empty($settings['store']) ) throw new Exception("Store settings are not installed");

    $storeList = explode( '|', $settings['store'] );
    $stores = array_map( fn($item) => "store=https://api.moysklad.ru/api/remap/1.2/entity/store/" . $item, $storeList );

    $filter = implode( ';', $stores );
    $service = new MSStockService( $this->ms );

    return $service->getStockData( $filter );
  }

  public function getItemsInfo( array $stockData, array $reserved ):array
  {
    if ( empty($stockData) ) throw new Exception("No items to get info about");
    $rows = CIBlockElement::getList(
      [],
      ['IBLOCK_ID' => 16, 'XML_ID' => array_keys($stockData)],
      false, false,
      ["IBLOCK_ID", "ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"]
    );

    $nmids = $this->getNmidDict();
    $skus = $this->getSkuDict();
    $result = [];
    $idKey = Config::instance()->getIdKey();

    while ( $row = $rows->getNext() ){
      $validStockCount = $stockData[ $row['XML_ID'] ]['stock'] - ( $reserved[ $row['ID'] ] ?? 0 );
      if ( $validStockCount <= 0 ) {
        continue;
      }

      $item = [
        'sku' => $skus[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ],
        'nmid' => $nmids[ $row['ID'] ],
        'brand' => $row['PROPERTY_BRAND_VALUE'],
        'model' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
        'cost' => $stockData[ $row['XML_ID'] ]['price'],
        'stockDays' => $stockData[ $row['XML_ID'] ]['stockDays'],
      ];

      if ( !isset($item[$idKey]) ){
        CommunicationService::log( "Item [{$row['ID']}] has no {$idKey}. Item skipped." );
        continue;
      }

      $result[ $item[$idKey] ] = $item;
    }

    return $result;
  }

  public function setAdvertInfo( string|int $advertId, int $brand, array $items, ?int $key = null ):void
  {
    $platform = Config::instance()->getPlatform();

    switch( $platform ){
      case 'ozon':
        $dict = $this->getSkuDict();
        break;
      case 'wb':
        $dict = $this->getNmidDict();
        break;
    }

    $number = $this->getAdvertNameNumber( $brand, $key ?? $advertId );

    $insert = array_map( function($item) use ($advertId, $brand, $dict, $platform, $number){
      return [
        'platform' => $platform,
        'advertId' => $advertId,
        'platform_product_id' => $item,
        'brand' => $brand,
        'number' => $number,
      ];
    }, array_values($items) );

    $where = [
      'column' => 'advertId',
      'operator' => '=',
      'value' => $advertId
    ];

    $this->panel->delete("am_campaign_products", [$where]);
    $this->panel->insert("am_campaign_products", $insert);
  }

  public function deleteAdvertInfo( int $advertId, ?array $products = null ):void
  {
    if ( $products === [] ) return;

    if ( !empty($products) ){
      $filter = "(".implode( ',', $products ).")";
      $query = "DELETE FROM am_campaign_products WHERE platform_product_id IN {$filter}";
    }else{
      $query = "DELETE FROM am_campaign_products WHERE advertId = {$advertId}";
    }

    $this->panel->query( $query );
  }

  private function getCampaignsNameNumbers():void
  {
    $rows = $this->panel->select(['advertId', 'brand', 'number'], 'am_campaign_products')->where('platform', Config::instance()->getPlatform())->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['brand'] ][ $row['advertId'] ] = $row['number'];
    }

    $this->advertNamesNumbers = $result;
  }

  public function getAdvertNameNumber( int $brand, string|int $advertId ):int
  {
    if ( !isset($this->advertNamesNumbers[$brand]) ) {
      $this->advertNamesNumbers[$brand][$advertId] = 0;
      return 0;
    }
    if ( !isset($this->advertNamesNumbers[$brand][$advertId]) ){
      $this->advertNamesNumbers[$brand][$advertId] = max( $this->advertNamesNumbers[$brand] ) + 1;

      return $this->advertNamesNumbers[$brand][$advertId];
    }

    return $this->advertNamesNumbers[$brand][$advertId];
  }

  public function getAuthData():array
  {
    $strSql = "SELECT api FROM wdhs_wb_main_settings WHERE cabinet = 'WR'";
    $key = $this->main->query($strSql)->fetch()['api'];

    return ['key' => $key];
  }

  private function getReserved( array $settings ):array
  {
    $storeList = explode( '|', $settings['store'] );
    $suppliers = array_map( fn($item) => Config::instance()->getStockInfo(id: $item), $storeList );
    $filter = implode( ',', $suppliers );

    $strSql = "SELECT PRODUCT_ID as prid, sum(RESERVED) as reserved FROM ci_order_reserved WHERE SUPPLIER_ID IN ({$filter}) GROUP BY PRODUCT_ID";
    $rows = $this->main->query( $strSql );

    $result = [];

    while ( $row = $rows->fetch() ){
      $result[ $row['prid'] ] = $row['reserved'];
    }

    return $result;
  }

  private function getNmidDict():array
  {
    $rows = $this->main->query( "SELECT * FROM wdhs_wb_props WHERE cabinet = 'WR'" );
    $result = [];

    while( $row = $rows->fetch() ){
      $result[ $row['bitrix_id'] ] = $row['nmid'];
    }

    return $result;
  }

  private function getSkuDict():array
  {
    $rows = $this->panel->select(['*'], 'ozon_sku_dict_IP')->make();

    return array_column( $rows, 'sku', 'model' );
  }
}
 ?>
