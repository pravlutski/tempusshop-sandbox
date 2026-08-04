<?php
interface OrderProviderInterface
{
  public function getOrders( array &$items, bool $isPeriod ):void;
}

 ?>
