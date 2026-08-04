<?php
class OzonOrderProvider implements OrderProviderInterface
{
  private ?\Bitrix\Main\DB\MysqliConnection $main;
  private ?DBPanel $panel;

  public function __construct( \Bitrix\Main\DB\MysqliConnection $main, DBPanel $panel )
  {
    $this->main = $main;
    $this->panel = $panel;
  }

  public function getOrders( array &$items, bool $isPeriod ):void
  {
    if ( $isPeriod ){
      $intervals = [];
      foreach ( $items as $model => $data ){
        $intervals[] = strtotime($data['intervals']['lastRunDate']);
      }

      $this->getOrdersCountByPeriod(
        items: $items,
        minDate: date( 'Y-m-d H:i:s', min($intervals) )
      );
      return;
    }
    $this->getOrdersCountByDay(
      items: $items,
      minDate: date( 'Y-m-d 00:00:00' )
    );
  }

  private function getOrdersCountByDay( array &$items, string $minDate ):void
  {
    if ( empty($items) ){
      throw new InvalidArgumentException('Items array cannot be empty');
    }
    $filter = $this->prepareModelsFilter(
      models: array_keys( $items )
    );
    $ordersTable = ConfigProvider::getOrdersTable();
    $orderProductsTable = ConfigProvider::getOrderProductsTable();

    $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `{$orderProductsTable}` AS op
      JOIN `{$ordersTable}` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$minDate}' AND op.vendor_code IN ({$filter})";

    $res = $this->main->Query( $strSql );

    foreach ( $items as $model => $data ){
      $items[$model]['ordersCount'] = 0;
    }

    while ( $row = $res->Fetch() ){
      $items[ $row['model'] ]['ordersCount'] += 1;
    }
  }

  private function getOrdersCountByPeriod( array &$items, string $minDate ):void
  {
    if ( empty($items) ) throw new InvalidArgumentException("\$items array cannot be empty");
    if ( empty($minDate) ) throw new InvalidArgumentException("\$minDate argument cannot be empty");

    $filter = $this->prepareModelsFilter(
      models: array_keys( $items )
    );
    $ordersTable = ConfigProvider::getOrdersTable();
    $ordersProductsTable = ConfigProvider::getOrderProductsTable();

    $strSql = "SELECT op.vendor_code as model, UNIX_TIMESTAMP(o.in_process_at) as date
      FROM {$ordersProductsTable} AS op
      JOIN {$ordersTable} AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$minDate}' AND op.vendor_code IN ({$filter})";


    $result = $this->main->Query( $strSql );

    $ordersData = [];

    while ( $row = $result->Fetch() ){
      $inProcessAt = $row['date'];
      $lastRunDate = strtotime( $items[$row['model']]['intervals']['lastRunDate'] );
      if ( $lastRunDate > $inProcessAt ) continue;
      $ordersData[ $row['model'] ] += 1;
    }


    foreach ( $items as $model => $item ){
      $items[$model]['ordersCount'] = $ordersData[$model] ?? 0;
    }
  }

  private function prepareModelsFilter( array $models ):string
  {
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $string = implode( ',', $modelsFormatted );

    return $string ?? '';
  }

}
 ?>
