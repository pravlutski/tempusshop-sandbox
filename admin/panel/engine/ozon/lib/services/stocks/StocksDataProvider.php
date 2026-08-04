<?php
class StocksDataProvider
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
    $reserved = $this->getReservedItems();
    $suppliers = $this->getSuppliersStockSettings();
    $dict = $this->getOfferIds( array_keys($priceData) );

    $result = [];

    foreach ( $priceData as $bitrix_id => $data ){
      $key = $this->getBestProposeKey(
        data: $data,
        reserved: $reserved[ $bitrix_id ] ?? 0,
      );

      if ( $key === false ) continue;

      $suppId = $data[$key]['supplier_id'];
      if ( empty($suppliers[ $suppId ]) ) continue;

      $warehouses = $this->getStockWarehouses( $key, $data, $suppliers );
      $warehouses = array_unique( array_merge($suppliers[$suppId], $warehouses) );

      $result[ $bitrix_id ] = [
        'cost' => $data[$key]['price'],
        'supplier' => $data[$key]['supplier_id'],
        'model' => $data[$key]['model'],
        'offerId' => $dict[ $bitrix_id ] ?? false,
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

  private function getOfferIds( array $ids ):array
  {
    if ( empty($ids) ){
      // CommunicationService
      throw new Exception("Critical occured during getting items' offerids. Filter array is empty");
    }

    $rows = CIBlockElement::GetList(
      [],
      ['IBLOCK_ID' => 16, 'ID' => $ids],
      false,
      false,
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

  private function getReservedItems():array
  {
    $result = [];
    $strSql = "SELECT PRODUCT_ID, RESERVED FROM ci_reserved";
    $rows = $this->main->query( $strSql );

    while ( $row = $rows->fetch() ){
      $result[ $row['PRODUCT_ID'] ] = $row['RESERVED'];
    }

    return $result;
  }

  private function getCostData():array
  {
    $result = [];

    $strSql = "SELECT
      bitrix_id,
      supplier_id,
      count,
      model,
      IF ( supplier_id IN (47, 103, 129, 141, 144), 0, 1 ) as sort
      FROM ci_price
      WHERE active_os = 'Y'
      ORDER BY sort ASC, price ASC";

    // $strSql = "SELECT bitrix_id, count, supplier_id, model FROM ci_price WHERE active_os ='Y' ORDER BY price ASC";
    $rows = $this->main->query( $strSql );

    while ( $row = $rows->fetch() ){
      $result[ $row['bitrix_id'] ][] = $row;
    }

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
