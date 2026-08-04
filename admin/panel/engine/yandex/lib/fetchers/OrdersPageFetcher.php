<?php
class OrdersPageFetcher extends PageFetcherBase implements PageFetcherInterface
{
  public function fetch( ?string $id = null, ?int $limit = null, ?callable $request = null, ?callable $response = null ):array
  {
    return $this->paginate(
      limit: $limit ?? $this->config->getOrdersListLimit(),
      requestFn: $request ?? $this->getRequestCallable(),
      responseFn: $response ?? $this->getResponseCallable(),
    );
  }

  private function getRequestCallable( ):Closure
  {
    return function($query) {
      $days = Config::instance()->getDaysFrom();
      $data = [
        'dates' => [
          'updateDateFrom' => date('Y-m-d\T20:00:00\Z', strtotime("- {$days} days")),
          'updateDateTo' => date('Y-m-d\TG:i:s\Z')
        ],
      ];
      return $this->api->getBusinessOrders( $data, $query );
    };
  }

  private function getResponseCallable():Closure
  {
    return function($data) {
      return $this->processResponse( $data );
    };
  }

  private function processResponse( array $data ):array
  {
    $orders = $data['orders'] ?? [];
    $result = [];

    foreach ( $orders as $row ){
      $order = [
        'order_id' => $row['orderId'],
        'order_bid' => 0,
        'campaign_id' => $row['campaignId'],
        'created_at' => $row['creationDate'],
        'status' => $row['status'],
        'timestamp' => time(),
      ];

      $products = [];

      foreach ( $row['items'] as $item ){
        $model = end( explode(' ', $item['offerName']) );
        $products[ $item['offerId'] ] = [
          'order_id' => $row['orderId'],
          'vendor_code' => $model,
          'name' => $item['offerName'],
          'offer_id' => $item['offerId'],
          'payment' => $item['prices']['payment']['value'],
          'subsidy' => $item['prices']['subsidy']['value'],
          'price' => $item['prices']['payment']['value'] + $item['prices']['subsidy']['value'],
          'cost' => 0,
          'count' => $item['count'],
        ];
      }

      $order['products'] = $products;
      $result[ $row['orderId'] ] = $order;
    }

    return $result;
  }
}
 ?>
