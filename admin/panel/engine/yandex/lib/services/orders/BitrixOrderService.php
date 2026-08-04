<?php
class BitrixOrderService
{
  private array $orders = [];

  public function __construct(
    private ?DataProvider $data,
    private ?ConfigProviderInterface $config,
  ){}

  public function loadSavedOrders( array $ids ):void
  {
    $rows = $this->data->items()->getBitrixOrders( $ids );
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['order_id'] ] = $row;
    }

    $this->orders = $result;
  }

  public function checkIfOrderExists( int $orderId ):bool
  {
    return isset( $this->orders[$orderId] );
  }

  public function addOrder( array $data, array $map ):int
  {
    CommunicationService::log('Bitrix order module is disabled');
    return 0;

    $order = \Bitrix\Sale\Order::create(
      $this->config->getSiteId(),
      $this->config->getUserId(),
      $this->config->getCurrency(),
    );

    $this->setOrderBasket( $order, $data['products'] );
    $this->setBaseProperties( $order, $data, $map );
    $this->setCustomProperties( $order, $data );

    $this->setShipment( $order );
    $this->setPayment( $order );

    $result = $order->save();

    if ( $result->isSuccess() ){
      $this->setTradingPlatform( $order );
    }

    return $order->getId();
  }

  private function setOrderBasket( \Bitrix\Sale\Order $order, array $products ):void
  {
    $basket = \Bitrix\Sale\Basket::create( $this->config->getSiteId() );
    $order->setBasket( $basket );

    foreach ( $products as $product ){
      $item = [
        "PRODUCT_ID" => (int) $product['offer_id'],
        "NAME" => (string) $product['name'],
        "BASE_PRICE" => (float) $product['price'],
        "PRICE" => (float) $product['price'],
        "CURRENCY" => (string) $order->getCurrency(),
        "QUANTITY" => (int) $product['count'],
        "LID" => (string) $this->config->getSiteId(),
        "CUSTOM_PRICE" => 'Y'
      ];

      $basket->createItem( 'catalog', $item['PRODUCT_ID'] )->setFields( $item );
    }
  }

  private function setBaseProperties( \Bitrix\Sale\Order $order, array $data, array $map ):void
  {
    $order->setPersonTypeId( $this->config->getPersonType() );
    $status = $map[ $data['status'] ] ?? false;

    if ( !$actualStatus ){
      $errorText = $this->config->getErrorText('status_match');
      $message = sprintf( $errorText, $data['order_id'], $data['status'] );
      throw new OrderStatusMapException( $message );
    }

    $order->setFields([
      "STATUS_ID" => $status,
      "COMMENTS" => $this->config->getComment(),
    ]);
  }

  private function setCustomProperties( \Bitrix\Sale\Order $order, array $data ):void
  {
    $userId = $this->config->getUserId();
    $customer = $this->data->items()->getCustomerDetails( $userId );

    $propertyCollection = $order->getPropertyCollection();
    $propertyFields = [
      "ORDER_NUMBER_YA" => $data['order_id'],
      "PHONE" => $customer['phone'],
      "FIO" => $customer['fio'],
      "EMAIL" => $customer['email'],
      "ADDRESS" => $customer['address'],
    ];

    foreach ( $propertyFields as $code => $value ) {
      $prop = $propertyCollection->getItemByOrderPropertyCode( $code );
      if ( $prop ) $prop->setValue( $value );
    }
  }

  private function setShipment( \Bitrix\Sale\Order $order ):void
  {
    $shipmentCollection = $order->getShipmentCollection();
    $shipment = $shipmentCollection->createItem();
    $shipId = $this->config->getShipId();

    $service = Delivery\Services\Manager::getById( $shipId );

    $shipment->setFields([
      "DELIVERY_ID" => $service['ID'],
      "DELIVERY_NAME" => $service['NAME'],
    ]);

    $shipmentItemCollection = $shipment->getShipmentItemCollection();
    $basket = $order->getBasket();

    foreach ( $basket as $item ) {
      $shipmentItem = $shipmentItemCollection->createItem( $item );
      $shipmentItem->setQuantity( $item->getQuantity() );
    }
  }

  private function setPayment( \Bitrix\Sale\Order $order ):void
  {
    $paymentCollection = $order->getPaymentCollection();
    $payment = $paymentCollection->createItem();
    $payId = $this->config->getPayId();

    $paySystemService = PaySystem\Manager::getObjectById( $payId );

    $orderSum = 0;

    foreach ( $order->getBasket()->getBasketItems() as $item ){
      $orderSum += $item->getPrice();
    }

    $payment->setFields([
      "PAY_SYSTEM_ID" => $paySystemService->getField('PAY_SYSTEM_ID'),
      "PAY_SYSTEM_NAME" => $paySystemService->getField('NAME'),
      "SUM" => $orderSum,
    ]);
  }

  private function setTradingPlatform( \Bitrix\Sale\Order $order ):void
  {
    $res = \Bitrix\Sale\TradingPlatform\OrderTable::add([
      "ORDER_ID" => $order->getId(),
      "EXTERNAL_ORDER_ID" => $order->getField('ACCOUNT_NUMBER'),
      "TRADING_PLATFORM_ID" => $this->config->getTradingPlatformId(),
    ]);
  }

  public function updateOrder( array $data, array $map ):bool
  {
    CommunicationService::log('Bitrix order module is disabled');
    return false;

    $bid = $this->orders[ $data['order_id'] ];
    $order = \Bitrix\Sale\Order::load( $bid );

    $status = $order->getField("STATUS_ID");
    $finalStatus = $this->config->getFinalOrderStatus();
    $actualStatus = $map[ $data['status'] ] ?? false;

    if ( $status == $finalStatus ){
      CommunicationService::log("Order {$data['order_id']} already has final status and cannot be updated");
      return false;
    }

    if ( !$actualStatus ){
      $errorText = $this->config->getErrorText('status_match');
      $message = sprintf( $errorText, $data['order_id'], $data['status'] );
      throw new OrderStatusMapException( $message );
    }

    if ( $status == $actualStatus ){
      CommunicationService::log("Order {$data['order_id']} already is up to date");
      return false;
    }

    $order->setField( "STATUS_ID", $actualStatus );
    $result = $order->save();

    return $result->isSuccess();
  }
}
 ?>
