<?php
class DistributeService
{
  public static function distribute( array $profiles, array $items ):array
  {
    $result = [];

    foreach ( $items as $id => $item ){
      if ( !isset($profiles[$item['brand']]) ) continue;

      $profile = $profiles[ $item['brand'] ];

      if ( $item['stockDays'] < $profile['stockDays'] ){
        CommunicationService::log( "{$item['model']} filtered by stock days {$item['stockDays']} < {$profile['stockDays']}", true );
        continue;
      }
      if ( $item['cost'] < $profile['minCost'] || $item['cost'] > $profile['maxCost'] ){
        CommunicationService::log( "{$item['model']} filtered by cost {$profile['minCost']} < {$item['cost']} < {$profile['maxCost']}", true );
        continue;
      }

      $result[ $item['brand'] ][] = $item;
    }

    foreach ( array_keys($profiles) as $brand ){
      if ( isset($result[$brand]) ) continue;
      $result[$brand] = [];
    }

    return $result;
  }
}
 ?>
