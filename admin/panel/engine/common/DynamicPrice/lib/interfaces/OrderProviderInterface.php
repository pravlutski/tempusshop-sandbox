<?php
interface OrderProviderInterface
{
  public function getOrdersCount( array &$items, bool $isPeriod ):void;
  
  public function getOrdersData( array &$items, string $minDate, string $maxDate ):void;
}

 ?>
