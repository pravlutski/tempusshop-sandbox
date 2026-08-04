<?php
class SalesApiManager // Все, что касается общения по апи с озоном
{
  private array $settings;

  public function __construct( array $settings )
  {
    if ( empty($settings) ) throw new \InvalidArgumentException("\$settings cannot be an empty string");
    $this->settings = $settings;
  }

  private function getHeaders():array
  {
    return [
      'Api-Key:' . $this->settings['key'],
      'Client-Id:' . $this->settings['client_id'],
      'Content-Type:application/json'
    ];
  }

  private function getApiUrl( string $method ):string
  {
    return $this->settings['api_url'] . $method;
  }

  private function request( string $url, array $headers, string|bool $data = false ):array
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    if ( $data ) curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close( $ch );

    return json_decode($res, true);
  }

  private function buildManageRequestBody( int $saleId, array $data, bool $only_id ):string
  {
    $products = [];
    $result = [];

    foreach ( $data as $value ){
      if ( $only_id ){
        $products[] = $value['product_id'];
        continue;
      }
      $products[] = [
        'action_price' => $value['action_price'],
        'product_id' => $value['product_id']
      ];
    }

    $result['action_id'] = $saleId;
    if ( $only_id ){
      $result['product_ids'] = $products;
    }else{
      $result['products'] = $products;
    }

    return json_encode($result);
  }

  private function manageSale( int $saleId, string $method, array $data, bool $deactivateFlag ):void
  {
    if ( empty($data) ){
      SalesCommunicationService::logTech("{$saleId} [{$method}] - Нет данных для отправки");
      return;
    }

    $chunks = array_chunk( $data, 1000 );

    foreach ( $chunks as $chunk ){
      $res = $this->request(
        url: $this->getApiUrl( $method ),
        headers: $this->getHeaders(),
        data: $this->buildManageRequestBody( $saleId, $chunk, $deactivateFlag )
      );

      file_put_contents(
        "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/response_{$saleId}.json",
        print_r( json_encode($res), true ),
        FILE_APPEND,
      );

      SalesCommunicationService::logTech("SENT SOME REQUEST");
      sleep( rand(2,5) );
    }

  }


  public function getSalesProducts( array $salesList, string $method ):array
  {
    $items = [];
    $limit = 1000;

    foreach ( $salesList as $saleId => $saleData ){
      $runFlag = true;
      $i = 0;
      while ( $runFlag ){
        $data = json_encode([
          "action_id" => $saleId,
          "limit" => $limit,
          "last_id" => $lastId ?? "", // При первом прогоне передадим пустую строку
        ]);

        $res = $this->request(
          url: $this->getApiUrl( $method ),
          headers: $this->getHeaders(),
          data: $data
        );

        if ( !isset($res['result']) ){
          SalesCommunicationService::logTech("Ошибка получения товаров. Этап {$i}");
          $runFlag = false;
          break;
        }
        // if ( $saleId == 3200313 ){
        //   var_dump( $method );
        //   if ( $method == '/v1/actions/candidates' ){
        //     var_dump($data);
        //     var_dump( $res['result'] );
        //   }
        // }

        if ( count($res['result']['products']) < $limit ) $runFlag = false;

        foreach ( $res['result']['products'] as $product ){
          $items[$saleId][$product['id']] = [
            'product_id' => $product['id'],
            'max_action_price' => SalesCalculator::calculateElasticPrice( item: $product, sale: $saleData ),
            'price_min_elastic' => $product['price_min_elastic'],
            'price_max_elastic' => $product['price_max_elastic'],
          ];
        }
        $lastId = $res['result']["last_id"];
        sleep( rand(3, 7) );
        $i++;
      }
      unset($lastId);
    }

    return $items;
  }

  public function sendSalesData( array $data ):void
  {
    foreach ( $data as $saleId => $types ){
      foreach ( $types as $type => $products ){
        $this->manageSale(
          saleId: $saleId,
          method: SalesConfigProvider::getManageMethods( $type ),
          data: $products ?? [],
          deactivateFlag: SalesConfigProvider::getDeactivateFlag( $type )
        );
      }
    }
  }

}


 ?>
