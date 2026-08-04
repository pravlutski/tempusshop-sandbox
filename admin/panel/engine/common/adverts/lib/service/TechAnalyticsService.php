<?php
class TechAnalyticsService
{
  public static function countList( array $list, string $type ):int
  {
    switch( $type ){
      case 'create':
        return self::countDoubleList( $list );
        break;
      case 'disable':
        return self::countFlatList( $list );
        break;
      case 'delete':
        return self::countDoubleList( $list );
        break;
      case 'update':
        return self::countTripleList( $list, 1 );
        break;
      case 'add':
        return self::countTripleList( $list, 2 );
        break;
    }
  }

  public static function countFlatList( array $list ):int
  {
    return count($list);
  }

  public static function countDoubleList( array $list ):int
  {
    $result = 0;

    foreach ( $list as $advertId => $items ){
      $result += count($items);
    }

    return $result;
  }

  public static function countTripleList( array $list, int $status ):int
  {
    $result = 0;

    foreach ( $list as $brand => $adverts ){
      foreach ( $adverts as $advert ){
        $result += count( array_filter($advert, fn($item) => $item == $status) );
      }
    }

    return $result;
  }
}
 ?>
