<?php
class DeviationDataProvider
{
  public function __construct( private DBPanel $panel ){}

  public function get( array $items, array $priceData ):array
  {
    return array_merge(
      $this->getUpdateListData(),
      $this->getPriceDeviations( $items ),
      $this->getCostDeviations( $items, $priceData )
    );
  }

  private function getCostDeviations( $items, $priceData ):array
  {
    $result = [];

    foreach ( $items as $model => $item ){
      if ( !isset($priceData[$model]) ) continue; // Cause it is probably a fresh item and there is nothing to correct yet
      $data = $priceData[$model];
      if ( round($item['cost']) == round($data['cost']) ) continue;
      if ( $item['cost'] == 0 ) continue;

      var_dump($item['cost']);
      var_dump($data['cost']);

      $result[ $model ] = true;
    }

    var_dump( 'cost: ' . count($result) );
    return $result;
  }

  private function getUpdateListData():array
  {
    $rows = $this->panel->select( ['model'], ConfigProvider::getUpdateListTable() )->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = true;
    }

    var_dump( 'update list: ' . count($result) );
    return $result;
  }

  private function getPriceDeviations( array $items ):array
  {
    $result = [];

    foreach ( $items as $row ){
      if ( $row['cost'] == 0 ) continue;
      $expectedPrice = $row['startPrice'] * (1 + $row['installed']['step'] / 100 );
      if ( round($expectedPrice) == round($row['installed']['price']) ) continue;

      $result[ $row['model'] ] = true;
    }

    var_dump( 'prices: ' . count($result) );
    return $result;
  }
}

 ?>
