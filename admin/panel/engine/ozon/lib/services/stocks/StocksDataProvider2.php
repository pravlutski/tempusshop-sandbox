<?php
class StocksDataProvider2
{
  private array $stockSuppliers = [47, 103, 129, 141, 144];
  private string $expressName = "Express 7D";

  public function __construct(
    private \Bitrix\Main\DB\MysqliConnection $main,
    private DBPanel $panel
  ){}

  public function getActiveItems():array
  {
    $priceData = $this->getCostData();
    $suppliers = $this->getSuppliersStockSettings();
    $dict = $this->getOfferIds( array_keys($priceData) );
    $result = [];

    foreach ( $priceData as $model => $offers ){
      $offer = reset( $offers );
      $suppId = $offer['supplier_id'];

      if ( empty($suppliers[ $suppId ]) ) continue;

      $warehouses = $this->getStockWarehouses( 0, $offers, $suppliers );

      $warehouses = array_unique( array_merge($suppliers[$suppId], $warehouses) );

      $result[ $offer['bitrix_id'] ] = [
        'cost' => $offer['price'],
        'supplier' => $offer['supplier_id'],
        'model' => $offer['model'],
        'offerId' => $dict[ $offer['bitrix_id'] ] ?? false,
        'warehouses' => $warehouses,
      ];
    }

    return $result;
  }

  public function getFboStock():array
  {
    $rows = $this->panel->select(['*'], 'ozon_fbo_stock_IP')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = true;
    }

    return $result;
  }

  public function getWarehouseDict( string $cabinet ):array
  {
    switch( $cabinet ){
      case 'IP':
        return TYPE_SKLAD_CONST;
        break;
      case 'WT':
        return TYPE_SKLAD_CONST_WT;
        break;
      case 'IP':
        return TYPE_SKLAD_CONST_TI;
        break;
      default:
        throw new Exception("Undefined cabinet");
    }
  }

  public function getHeaders( string $cabinet ):array
  {
    $rows = $this->panel->select( ['*'], 'ozon_main_settings_'.$cabinet )->make();
    $settings = array_column( $rows, 'value', 'name' );

    return [
      "Api-Key:{$settings['key']}",
  		"Client-Id:{$settings['client_id']}",
  		'Content-Type:application/json'
    ];
  }

  private function getOfferIds( array $items ):array
  {
    if ( empty($items) ){
      // CommunicationService
      throw new Exception("Critical occured during getting items' offerids. Filter array is empty");
    }

    $rows = CIBlockElement::GetList(
      [],
      ['IBLOCK_ID' => 16, 'PROPERTY_CML2_ARTICLE' => $items],
      false, false,
      ["ID", "IBLOCK_ID", "PROPERTY_WBARTICLE"]
    );

    $result = [];

    while ( $row = $rows->GetNext() ){
      $result[ $row['ID'] ] = $row['PROPERTY_WBARTICLE_VALUE'];
    }

    return $result;
  }

  private function getSuppliersStockSettings():array
  {
    $strSql = "SELECT id, settings_type_sklad as settings FROM ci_suppliers";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      if ( empty($row['settings']) ) continue;
      $result[ $row['id'] ] = json_decode($row['settings'], true);
    }

    return $result;
  }

  private function getCiPriceSetData():array
  {
    $strSql = "SELECT ARTICLE FROM ci_price_set WHERE PRICE_TYPE = 'OS'";
    $rows = $this->main->query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[] = $row['ARTICLE'];
    }

    return $result;
  }

  private function getCostData():array
  {
    $service = PanelManager::getPriceManager();
    $servicePrice = $service->updatePriceService("OS", 'debug');

    $models = $this->getCiPriceSetData();
    if ( empty($models) ) throw new Exception("No active models for selected price type");

    $filter = [
      'article' => $models
    ];

    $servicePrice->market->setPriceFilter( $filter );
    $servicePrice->market->setConfig('tbl_sebes_fbo', false);

    $result = $servicePrice->getMinPurchasePrice();

    return $result;
  }

  private function getStockWarehouses( int $key, array $data, array $suppliers ):array
  {
    $stockSuppliers = array_flip( $this->stockSuppliers );
    $result = [];

    while( isset($data[$key]) ) { // starts from best proposition
      $supplierId = $data[$key]['supplier_id'];

      if ( isset($stockSuppliers[$supplierId]) ){
        $result += $this->placeExpressEnd( $suppliers[$supplierId] );
      }

      $key++;
    }

    return $result;
  }

  private function placeExpressEnd( array $warehouses ):array
  {
    if ( reset($warehouses) == $this->expressName ) {
      return array_reverse( $warehouses );
    }

    return $warehouses;
  }

  private function getBestProposeKey( array $data, int $reserved ):int|bool
  {
    foreach ( $data as $key => $row ){
      if ( $row['count'] - $reserved <= 0 ){
        $reserved = abs( $row['count'] - $reserved );
        continue;
      }
      return $key;
    }

    return false;
  }
}

 ?>
