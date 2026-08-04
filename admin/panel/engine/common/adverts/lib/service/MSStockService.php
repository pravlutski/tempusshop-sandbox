<?php
class MSStockService extends ApiManagerBase
{
  private ?string $token = null;

  public function __construct( MoyskladAPI $ms )
  {
    $this->token = $ms->getAccessParams()['access_token'];
  }

  public function getStockData( string $filter ):array
  {
    $query = [
      'offset' => 0,
      'limit' => 1000,
      'filter' => $filter,
    ];
    $flag = true;
    $result = [];

    while ( $flag ){
      $response = $this->getStock( query: $query );

      if ( $response['code'] != 200 ){
        CommunicationService::log("Incompleted stock data. Critical error");
        throw new Exception("Incompleted stock data");
      }

      if ( count($response['result']['rows']) < $query['limit'] ) $flag = false;

      foreach ( $response['result']['rows'] as $row ){
        $result[ $row['externalCode'] ] = [
          'article' => $row['article'],
          'price' => $row['price'] / 100,
          'stock' => $row['stock'],
          'stockDays' => $row['stockDays'],
        ];
      }

      usleep( 500000 );
    }

    CommunicationService::log("Got items from MoySklad: " . count($result));

    return $result;
  }

  private function getStock( array $query ):array
  {
    $options = [
      CURLOPT_POSTFIELDS => null,
      CURLOPT_ENCODING => 'gzip,deflate'
    ];

    return $this->request(
      url: Config::instance()->getMSUrl('stock'),
      query: $query,
      body: null,
      headers: $this->getRequestHeaders(),
      method: "GET",
      optionsAdd: $options
    );
  }

  private function getRequestHeaders():array
  {
    return [
      "Accept: application/json;charset=utf-8",
      "Content-Type: application/json",
      "Authorization: Bearer {$this->token}"
    ];
  }
}
 ?>
