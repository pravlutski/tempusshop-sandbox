<?php
class WBOrderProvider extends OrderProviderBase implements OrderProviderInterface
{
  public function getOrdersCount( array &$items, bool $isPeriod, string $dateField = '', string $joinField = '' ):void
  {
    parent::getOrdersCount(
      items: $items,
      isPeriod: $isPeriod,
      dateField: 'created_at',
      joinField: 'order_id'
    );
  }

  public function getOrdersData( array &$items, string $minDate, string $maxDate, string $dateField = '', string $joinField = '' ):void
  {
    parent::getOrders(
      items: $items,
      minDate: $minDate,
      maxDate: $maxDate,
      dateField: 'created_at',
      joinField: 'order_id'
    );
  }
}

 ?>
