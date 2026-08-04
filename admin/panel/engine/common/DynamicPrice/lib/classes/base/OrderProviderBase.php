<?php
class OrderProviderBase
{
  public function __construct(
    protected ?\Bitrix\Main\DB\MysqliConnection $main,
    protected ?DBPanel $panel
  )
  {}

  public function getOrdersCount( array &$items, bool $isPeriod, string $dateField, string $joinField ):void // Mutation of $items
  {
    if ( $isPeriod ){
      $intervals = [];
      foreach ( $items as $model => $data ){
        $intervals[] = strtotime($data['intervals']['lastRunDate']);
      }

      $this->getOrdersCountByPeriod(
        items: $items,
        minDate: date( 'Y-m-d H:i:s', min($intervals) ),
        dateField: $dateField,
        joinField: $joinField,
      );
      return;
    }
    $this->getOrdersCountByDay(
      items: $items,
      minDate: date( 'Y-m-d 00:00:00' ),
      dateField: $dateField,
      joinField: $joinField,
    );
  }

  protected function getOrdersCountByDay( array &$items, string $minDate, string $dateField, string $joinField ):void
  {
    if ( empty($items) ){
      throw new InvalidArgumentException('Items array cannot be empty');
    }
    $whereIn = DPUtils::buildWhereInCondition(
      field: 'op.vendor_code',
      data: array_keys( $items )
    );
    $ordersTable = ConfigProvider::getOrdersTable();
    $orderProductsTable = ConfigProvider::getOrderProductsTable();

    $strSql = "SELECT op.vendor_code as model, o.{$dateField} as date
      FROM `{$orderProductsTable}` AS op
      JOIN `{$ordersTable}` AS o
      ON o.{$joinField} = op.{$joinField}
      WHERE o.{$dateField} > '{$minDate}' AND {$whereIn}";

    $res = $this->main->Query( $strSql );

    foreach ( $items as $model => $data ){
      $items[$model]['ordersCount'] = 0;
    }

    while ( $row = $res->Fetch() ){
      $items[ $row['model'] ]['ordersCount'] += 1;
    }
  }

  protected function getOrdersCountByPeriod( array &$items, string $minDate, string $dateField, string $joinField ):void
  {
    if ( empty($items) ) throw new InvalidArgumentException("\$items array cannot be empty");
    if ( empty($minDate) ) throw new InvalidArgumentException("\$minDate argument cannot be empty");

    $whereIn = DPUtils::buildWhereInCondition(
      field: 'op.vendor_code',
      data: array_keys( $items )
    );
    $ordersTable = ConfigProvider::getOrdersTable();
    $ordersProductsTable = ConfigProvider::getOrderProductsTable();

    $strSql = "SELECT op.vendor_code as model, UNIX_TIMESTAMP(o.{$dateField}) as date
      FROM {$ordersProductsTable} AS op
      JOIN {$ordersTable} AS o
      ON o.{$joinField} = op.{$joinField}
      WHERE o.{$dateField} > '{$minDate}' AND {$whereIn}";


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

  public function getOrdersData( array &$items, string $minDate, string $maxDate, string $dateField, string $joinField ):void // Mutation of $items
  {
    if ( empty($items) ) throw new InvalidArgumentException("\$items array cannot be empty");
    if ( empty($minDate) ) throw new InvalidArgumentException("\$minDate argument cannot be empty");
    if ( empty($maxDate) ) throw new InvalidArgumentException("\$maxDate argument cannot be empty");

    $whereIn = DPUtils::buildWhereInCondition(
      field: 'op.vendor_code',
      data: array_keys( $items )
    );

    $ordersTable = ConfigProvider::getOrdersTable();
    $ordersProductsTable = ConfigProvider::getOrderProductsTable();

    $strSql = "SELECT op.vendor_code as model, op.price as price, op.quantity as quantity, op.cost as cost, o.status as status, o.{$dateField} as date
      FROM {$ordersProductsTable} AS op
      JOIN {$ordersTable} AS o
      ON o.{$joinField} = op.{$joinField}
      WHERE (o.{$dateField} >= '{$minDate}' AND o.{$dateField} <= '{$maxDate}') AND {$whereIn}";

    $result = $this->main->Query( $strSql );

    foreach ( $items as $model => $item ){
      $items[$model]['orders'] = [];
    }

    while ( $row = $result->Fetch() ){
      $items[ $row['model'] ]['orders'][] = [
        'price' => (float) $row['price'],
        'cost' => (float) $row['cost'],
        'quantity' => (int) $row['quantity'],
        'status' => (string) $row['status'],
        'date' => (string) $row['date']
      ];
    }
  }
}
?>
