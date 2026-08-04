<?php
class ItemsRepository extends RepositoryBase implements RepositoryInterface
{
  public function getItems( array $filter = [], array $select = [] ):array
  {
    $arFilter = array_merge(
      [ "IBLOCK_ID" => 16 ],
      $filter
    );
    $arSelect = array_merge(
      [ "ID", "IBLOCK_ID" ],
      $select
    );

    $rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect );
    $result = [];

    while ( $row = $rows->getNext() ){
      $result[] = $row;
    }

    return $result;
  }

  public function getBitrixOrders( array $ids ):array
  {
    if ( empty($ids) ) throw new InvalidArgumentException("Orders id filter cannot be empty");
    $ids = array_map( fn($el) => intval($el), $ids );
    $filter = implode(',', $ids);
    $filter = "(".$filter.")";

    $strSql = "SELECT
    ord.ID as order_bid,
    ord.STATUS_ID as status,
    props.VALUE as order_id
    FROM b_sale_order_props_value as props
    JOIN b_sale_order as ord ON props.ORDER_ID = ord.ID
    WHERE props.CODE = 'ORDER_NUMBER_YA' AND props.VALUE IN $filter";

    // var_dump( $strSql );
    // die;

    $rows = $this->main->query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[] = $row;
    }

    return $result;
  }

  public function getPanelOrders( array $ids ):array
  {
    if ( empty($ids) ) throw new InvalidArgumentException("Orders id filter cannot be empty");
    $ids = array_map( fn($el) => intval($el), $ids );

    $table = Config::instance()->getTableName('panel_orders');

    return $this->panel->select(['*'], $table)->where('order_id', $ids)->make();
  }

  public function getStatusList():array
  {
    $rows = CSaleStatus::GetList( [], [], false, false, [] );
    $result = [];

    while ( $row = $rows->GetNext() ){
      $result[ $row['ID'] ] = $row['NAME'];
    }

    return $result;
  }
}
 ?>
