<?php
class DataProvider
{
  public function __construct(
    private \Bitrix\Main\DB\MysqliConnection $main
  ){}

  public function getPurchaseList( int|array|bool $supp = false, array $site_id, string $active ):array
  {
    $whereIn = array_map(function($item){
      return "'".$item."'";
    }, $site_id);
    $whereIn = "(".implode(',', $whereIn).")";

    $type = gettype( $supp );

    switch ( $type ){
      case "integer":
        $strSql = "SELECT * FROM ci_purchase WHERE supp_id = '{$supp}' AND active = '{$active}' AND site_id IN {$whereIn}";
        break;
      case "array":
        $suppFilter = "(".implode(',', $supp).")";
        $strSql = "SELECT * FROM ci_purchase WHERE supp_id IN {$suppFilter} AND active = '{$active}' AND site_id IN {$whereIn}";
        break;
      case "boolean":
        $strSql = "SELECT * FROM ci_purchase WHERE active = '{$active}' AND site_id IN {$whereIn}";
        break;
      default:
        break;
    }

    $rows = $this->main->Query( $strSql );
    $result = [];
    while ( $row = $rows->Fetch() ){
      $result[] = $row;
    }
    return $result;
  }

  public function getSuppliersList( string $location = '' ):array
  {
    $strSql = "SELECT * FROM ci_suppliers";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while( $row = $rows->Fetch() ){
      $settings = json_decode( $row['settings_pricelist'], true );
      if ( $settings['location'] != $location ) continue;
      $result[] = (int)$row['id'];
    }

    return $result;
  }

  public function getSuppliersDict():array
  {
    $strSql = "SELECT id, settings FROM ci_suppliers";
    $rows = $this->main->Query( $strSql );
    $suppliers = [];

    while ( $row = $rows->Fetch() ) {
      $msid = json_decode($row['settings'], true)['mc_name'] ?? false;
      if ( empty($msid) ) continue;
      $suppliers[ $row['id'] ] = $msid;
    }

    $suppliers = array_map( (fn($val) => "'{$val}'"), $suppliers );
    $filter = "(".implode( ',', $suppliers ).")";

    $strSql = "SELECT NAME, MS_ID, SITE_ID FROM ci_ms_agent WHERE MS_ID IN {$filter}";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ) {
      $hash = md5( $row['NAME'] );
      $result[ $hash ] = [
        "id" => $row['MS_ID'],
        "cabinet" => $row['SITE_ID']
      ];
    }

    return $result;
  }

  public function getItemsIds( array $models ):array
  {
    if ( empty($models) ) throw new InvalidArgumentException("Argument cannot be an empty array");

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $models
    ];

    $arSelect = [ "ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE" ];
    $rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect );
    $result = [];

    while( $row = $rows->getNext() ){
      $result[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = $row["ID"];
    }

    return $result;
  }

  public function getProductsDict( array $ids ):array
  {
    $filter = "(".implode( ',', $ids ).")";
    $strSql = "SELECT SITE_ID, MS_ID, BX_ID FROM ci_ms_assortment WHERE BX_ID IN {$filter}";

    $rows = $this->main->Query( $strSql );

    $result = [];

    while( $row = $rows->Fetch() ){
      $result[ $row['SITE_ID'] ][ $row['BX_ID'] ] = $row['MS_ID'];
    }

    return $result;
  }

  public function getTradingPlatformList():array
  {
    $strSql = "SELECT * FROM b_sale_tp";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while( $row = $rows->Fetch() ){
      $result[ $row['NAME'] ] = $row['ID'];
    }

    return $result;
  }

  public function getTradingPlatformMatch( array $orders ):array
  {
    $filter = "(".implode( ",", $orders ).")";
    $strSql = "SELECT * FROM b_sale_tp_order WHERE ORDER_ID IN {$filter}";

    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $result[ $row['ORDER_ID'] ] = $row['TRADING_PLATFORM_ID'];
    }

    return $result;
  }

  public function getCurrencyRate( $cabinet ):float
  {
    $code = ConfigProvider::getCurrency( $cabinet );
    if ( $code == 'RUB' ) return 1;
    $strSql = "SELECT rate FROM ci_currency WHERE id = '{$code}'";
    $result = $this->main->Query( $strSql )->Fetch()['rate'];
    return $result;
  }

  public function getOrderStatus( int $orderId ):string
  {
    $order = \Bitrix\Sale\Order::load( $orderId );
    return $order->getField("STATUS_ID");
  }

  public function getOrderInfo( int $orderId ):array
  {
    $order = \Bitrix\Sale\Order::load( $orderId );
    $propertyCollection = $order->getPropertyCollection();

    echo '<pre>';
    var_dump( $propertyCollection->getItemByOrderPropertyId(1)->getValue() );
    var_dump( $propertyCollection->getArray()['properties'] );
    echo '</pre>';
    return [
      'comment' => $order->getField('USER_DESCRIPTION'),
      'fio' => $order->getField('FIO'),
    ];
  }
}
 ?>
