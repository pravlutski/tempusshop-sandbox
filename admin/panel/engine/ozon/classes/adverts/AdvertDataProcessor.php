<?php
class AdvertDataProcessor
{
  public function sortItemsByProfitLeg( array $own, array $comp, array $profitData ):array
  {
    $tmp = [];
    $result = [];
    $priceLimit = AdvertConfigProvider::getMinimumPriceLimit();

    foreach ( $own as $model => $price ){
      $tmp[] = [
        'model' => $model,
        'profit' => isset($comp[$model]) ? ($profitData[$model] ?? 1) : 0,
        'price' => $price
      ];
    }

    usort($tmp, function($a, $b) {
      return $b['profit'] <=> $a['profit'];
    });

    foreach ( $tmp as $key => $row ){
      $result[ $row['model'] ] = $own[$row['model']];
    }

    return $result;
  }

  public function sortItemsByProfit( array $own, array $comp, array $profitData ):array
  {
    $tmp = [];
    $result = [];
    // $priceLimit = AdvertConfigProvider::getMinimumPriceLimit();

    // foreach ( $own as $model => $price ){
    //   $tmp[] = [
    //     'model' => $model,
    //     'profit' => isset($comp[$model]) ? ($profitData[$model] ?? 1) : 0,
    //     'price' => $price
    //   ];
    // }
    //
    // usort($tmp, function($a, $b) {
    //   return $b['profit'] <=> $a['profit'];
    // });
    //
    // foreach ( $tmp as $key => $row ){
    //   $result[ $row['model'] ] = $own[$row['model']];
    // }

    $result = [];
    $tail = [];
    foreach ( $own as $model => $price ){
      if ( !isset($profitData[$model]) ){
        $tail[$model] = $price;
      }
    }
    foreach ( $profitData as $model => $value ){
      if ( !isset($own[$model]) ) continue;
      $result[$model] = $own[$model];
    }
    $result += $tail;
    return $result;
  }
}
 ?>
