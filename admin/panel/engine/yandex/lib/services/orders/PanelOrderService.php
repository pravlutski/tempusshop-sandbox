<?php
class PanelOrderService
{
  private array $orders = [];

  public function __construct(
    private ?DataProvider $data = null,
    private ?ConfigProviderInterface $config = null,
    private ?Updater $updater = null,
  ){}

  public function loadSavedOrders( array $ids ):void
  {
    $rows = $this->data->items()->getPanelOrders( $ids );
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

  public function addOrder( array $data ):void
  {
    $ordersTable = $this->config->getTableName('panel_orders');
    $productsTable = $this->config->getTableName('panel_order_products');

    $products = array_values($data['products']);
    unset( $data['products'] );
    $this->updater->insertOne( $ordersTable, $data );
    $this->updater->insertSome( $productsTable, $products );
  }

  public function updateOrder( array $data ):bool
  {
    $ordersTable = $this->config->getTableName('panel_orders');
    $savedOrder = $this->orders[ $data['order_id'] ];

    if ( $savedOrder['status'] == $data['status'] ){
      CommunicationService::log("Order {$data['order_id']} already is up to date");
      return false;
    }

    $this->updater->update(
      table: $ordersTable,
      values: [ 'status' => $data['status'] ],
      where: [ 'order_id' => $data['order_id'] ]
    );
  }
}
 ?>
