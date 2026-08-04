<?php
class PricesRepository extends RepositoryBase
{
  // Конструктор унаследован

  public function getStartPrices( array &$items ):void
  {
    if ( empty($items) ){
      throw new EmptyItemsListException('Cannot get start price: items array is empty');
    }

    $priceProperty = ConfigProvider::getPricePropertyName();

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => array_keys( $items ),
    ];
    $arSelect = [ "IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "{$priceProperty}" ];

    $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

    // $fboPrices = $this->getFboPrices();
    $fboPrices = [];

    while ( $row = $res->GetNext() ){
      $model = $row["PROPERTY_CML2_ARTICLE_VALUE"];

      if ( isset($items[ $model ]['installed']['startPrice']) ){
        $items[ $model ]['startPrice'] = $items[ $model ]['installed']['startPrice'];
        continue;
      }

      $price = $row["{$priceProperty}_VALUE"] * ( 1 - ConfigProvider::getSellerDiscount() );

      $items[ $model ]['startPrice'] = floatval( $fboPrices[$model] ?? round($price) );
    }
  }

  public function getCosts( array &$items ):void
  {
    if ( empty($items) ){
      throw new EmptyItemsListException('Cannot get orders: items array is empty');
    }
    $models = $this->prepareModelsFilter( models: array_keys( $items ) );
    $filter = ConfigProvider::getPriceSetFilterName();

    $strSql = "SELECT * FROM ci_price_set WHERE PRICE_TYPE = '{$filter}' AND ARTICLE IN ({$models})";
    $rows = $this->main->query( $strSql );
    $costs = [];

    while ( $row = $rows->fetch() ){
      $costs[ $row['ARTICLE'] ] = $row['PRICE_SUPPLIER'];
    }

    foreach ( $costs as $model => $cost ){
      $items[$model]['cost'] = $cost;
    }

  }

  public function getCostsLeg( array &$items ):void
  {
    if ( empty($items) ){
      throw new EmptyItemsListException('Cannot get orders: items array is empty');
    }

    $fboCosts = $this->getFboCosts();
    $reserved = $this->getReservedItems();
    $models = $this->prepareModelsFilter( models: array_keys( $items ) );

    $filter = ConfigProvider::getPriceFilterName();

    $strSql = "SELECT model, price, count FROM ci_price WHERE {$filter} = 'Y' AND model IN ({$models})";
    $rows = $this->main->Query( $strSql );

    $data = $items;

    while ( $row = $rows->Fetch() ){
      $data[ $row['model'] ]['priceData'][] = [
        'price' => $row['price'],
        'count' => $row['count'],
      ];
    }

    foreach ( $data as $model => $arModel ){
      $items[$model]['cost'] = $this->getMinCost(
        model: $model,
        data: $arModel['priceData'] ?? [],
        fbo: $fboCosts,
        reserved: $reserved
      );
    }
  }


  public function getFboCosts():array
  {
    if ( !ConfigProvider::getCheckFboFlag() ) return [];

    $result = [];
    $rows = $this->panel->select( ['*'], ConfigProvider::getFboCostTable() )->make();

    $modelKey = ConfigProvider::getFboSelectField('cost', 'model');
    $costKey = ConfigProvider::getFboSelectField('cost', 'cost');

    foreach ( $rows as $row ){
      $result[ $row[$modelKey] ] = $row[ $costKey ];
    }

    return $result;
  }

  public function getFboPrices():array
  {
    if ( !ConfigProvider::getCheckFboFlag() ) return [];
    $result = [];

    $rows = $this->panel->select( ['*'], ConfigProvider::getFboPriceTable() )->make();

    $modelKey = ConfigProvider::getFboSelectField('price', 'model');
    $priceKey = ConfigProvider::getFboSelectField('price', 'price');

    foreach ( $rows as $row ){
      $result[ $row[$modelKey] ] = $row[ $priceKey ];
    }

    return $result;
  }

  private function getReservedItems():array
  {
    $strSql = "SELECT * FROM ci_reserved";
    $rows = $this->main->Query( $strSql );

    $data = [];
    while ( $row = $rows->Fetch() ){
      $data[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $data;
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

  private function prepareModelsFilter( array $models ):string
  {
    $models = array_filter($models);
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $string = implode( ',', $modelsFormatted );

    return $string ?? '';
  }
}
 ?>
