<?php
class PricesRepository extends RepositoryBase implements RepositoryInterface
{
  public function getReservedItems():array
  {
    $strSql = "SELECT PRODUCT_ID, RESERVED FROM ci_reserved";
    $rows = $this->main->Query($strSql);
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[ $row['PRODUCT_ID'] ] = $row['RESERVED'];
    }

    return $result;
  }

  public function getCostData():array
  {
    $strSql = "SELECT bitrix_id, price, supplier_id, count, model FROM ci_price WHERE active_ya = 'Y' ORDER BY price ASC";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[ $row['bitrix_id'] ][] = $row;
    }

    return $result;
  }

  public function getCostDataWithPriority( array $filter = [] ):array
  {
    if ( empty($filter) ){
      $filter = $this->getCostDataDefault( "ARTICLE" );
    }

    if ( empty($filter) ) throw new Exception("Got no active items");

    $service = PanelManager::getPriceManager();
    $servicePrice = $service->updatePriceService( "YA", 'debug' );

    $servicePrice->market->setPriceFilter([ 'article' => array_keys($filter) ]);

    return $servicePrice->getMinPurchasePrice();
  }

  public function getCostDataDefault( string $key = "PRODUCT_ID" ):array
  {
    $strSql = "SELECT PRODUCT_ID, PRICE_SUPPLIER, ARTICLE FROM ci_price_set WHERE PRICE_TYPE = 'YA'";
    $rows = $this->main->query( $strSql );
    $result = [];

    while( $row = $rows->fetch() ){
      $result[ $row[$key] ] = $row['PRICE_SUPPLIER'];
    }

    return $result;
  }
}
 ?>
