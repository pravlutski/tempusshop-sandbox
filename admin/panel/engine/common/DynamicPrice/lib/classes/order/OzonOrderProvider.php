<?php
class OzonOrderProvider extends OrderProviderBase implements OrderProviderInterface
{
  public function getOrdersCount( array &$items, bool $isPeriod, string $dateField = '', string $joinField = '' ):void
  {
    parent::getOrdersCount(
      items: $items,
      isPeriod: $isPeriod,
      dateField: 'in_process_at',
      joinField: 'posting_number'
    );
  }

  public function getOrdersData( array &$items, string $minDate, string $maxDate, string $dateField = '', string $joinField = '' ):void
  {
    parent::getOrdersData(
      items: $items,
      minDate: $minDate,
      maxDate: $maxDate,
      dateField: 'in_process_at',
      joinField: 'posting_number'
    );
  }
}
 ?>
