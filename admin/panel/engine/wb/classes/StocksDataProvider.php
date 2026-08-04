<?php
class StocksDataProvider
{
  private array $stockSuppliers = [47, 103, 129, 141, 144];
  private array $cabinets = ['WR' => 'wb', 'TL' => 'wbtl', 'WT' => 'wb'];

  public function __construct(
    private \Bitrix\Main\DB\MysqliConnection $main,
    private DBPanel $panel
  ){}

  public function getActiveItems( string $cabinet ):array
  {
    if ( !isset($this->cabinets[$cabinet]) ) throw new Exception("Unknown cabinet");

    $priceData = $this->getCostData( filter: $this->cabinets[$cabinet] );
    $reserved = $this->getReservedItems();

    $result = [];

    foreach ( $priceData as $bitrix_id => $data ){
      $key = $this->getBestProposeKey(
        data: $data,
        reserved: $reserved[ $bitrix_id ] ?? 0,
      );

      if ( $key === false ) continue;

      $result[ $bitrix_id ] = [
        'cost' => $data[$key]['price'],
        'supplier' => $data[$key]['supplier_id'],
        'model' => $data[$key]['model'],
        'chrtId' => $dict[ $bitrix_id ] ?? false,
      ];
    }

    return $result;
  }

  public function getStockSuppliers():array
  {
    return $this->stockSuppliers;
  }

  public function getFboStock(  string $cabinet ):array
  {
    if ( $cabinet != 'WR' ) return [];

    $rows = $this->panel->select(['*'], 'wb_fbo_stock_WR')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = true;
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

  private function getCostData( string $filter ):array
  {
    $result = [];

    $strSql = "SELECT
      bitrix_id, supplier_id, count, model, price,
      IF ( supplier_id IN (47, 103, 129, 141, 144), 0, 1 ) as sort
      FROM ci_price
      WHERE active_{$filter} = 'Y'
      ORDER BY sort ASC, price ASC";

    $rows = $this->main->query( $strSql );

    while ( $row = $rows->fetch() ){
      $result[ $row['bitrix_id'] ][] = $row;
    }

    return $result;
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
