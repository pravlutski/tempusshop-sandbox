<?php
class ImportOrderService
{
  public function __construct(
    private ?DataProvider $data,
    private ?ConfigProviderInterface $config,
    private ?PanelOrderService $pos,
    private ?BitrixOrderService $bos,
  ){}

  public function enrichProductsWithCost( array $orders ):array
  {
    $costs = $this->data->prices()->getCostDataDefault();
    $result = [];

    foreach ( $orders as $id => $order ){
      $order['products'] = array_map(function($item) use ($costs){
        $item['cost'] = (float)($costs[ $item['offer_id'] ] ?? 0);
        return $item;
      }, $order['products']);

      $result[ $id ] = $order;
    }

    return $result;
  }

  public function createOrder( array $order, bool $bitrix, bool $panel ):bool
  {
    $savedBOS = true;
    $savedPOS = false;


    // if ( !$bitrix ){
    //   $statusMap = $this->getOrderStatusMap();
    //
    //   $result = RescueService::rescue(
    //     action: fn() => $this->bos->addOrder( $order, $statusMap ),
    //     context: sprintf( $this->config->getErrorText("bos_create"), $order['order_id'] )
    //   );
    //
    //   $bitrixOrderId = $result->getData();
    //   $savedBOS = $result->isSuccess();
    // }

    if ( !$panel ){
      $order['order_bid'] = $bitrixOrderId ?? 0;

      $result = RescueService::rescue(
        action: fn() => $this->pos->addOrder( $order ),
        context: sprintf( $this->config->getErrorText("pos_create"), $order['order_id'] )
      );

      $savedPOS = $result->isSuccess();
    }

    return $savedBOS && $savedPOS;
  }

  public function updateOrder( array $order, bool $bitrix, bool $panel ):bool
  {
    $updatedPOS = false;
    $updatedBOS = true;

    if ( $panel ){
      $result = RescueService::rescue(
        action: fn() => $this->pos->updateOrder( $order ),
        context: sprintf( $this->config->getErrorText("pos_update"), $order['order_id'] )
      );
      $updatedPOS = $result->isSuccess();
    }

    // if ( $bitrix ){
    //   $statusMap = $this->getOrderStatusMap();
    //
    //   $result = RescueService::rescue(
    //     action: fn() => $this->bos->updateOrder( $order, $statusMap ),
    //     context: sprintf( $this->config->getErrorText("bos_update"), $order['order_id'] )
    //   );
    //
    //   $updatedBOS = $result->isSuccess();
    // }

    return $updatedPOS && $updatedBOS;
  }

  private function getOrderStatusMap():array
  {
    $rows = $this->data->settings()->getOrderStatusMap();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['status_ya'] ] = $row['status_bx'];
    }

    return $result;
  }
}
 ?>
