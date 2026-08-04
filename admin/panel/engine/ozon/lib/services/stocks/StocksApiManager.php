<?php
class StocksApiManager
{
  private string $baseUrl = 'https://api-seller.ozon.ru';
  private int $timeoutDelay = 5;
  private int $defaultDelay = 1;
  private int $maxAttempts = 5;

  public function __construct(
    private array $headers
  ) {}

  public function getProductInfoStocks( int $warehouseId ):array
  {
    $flag = true;
    $attempts = 1;

    $data = [
      'cursor' => '',
      'limit' => 1000,
      'warehouse_id' => (int) $warehouseId
    ];

    $result = [];

    while ( $flag ){
      $response = $this->request('/v1/product/info/warehouse/stocks', $data);
      if ( $response['code'] == 429 ){
        $this->avoidRateLimit( $attempts );
        continue;
      }
      if ( $response['code'] != 200 ) $this->displayUnsuccsessfulMessage( $response );
      if ( empty( $response['data']['cursor'] ) ) $flag = false;

      $data['cursor'] = $response['data']['cursor'];

      $result = array_merge( $result ,$response['data']['stocks'] );

      sleep( $this->defaultDelay );
    }

    return $result;
  }

  public function updateStocks( array $data ):array
  {
    return $this->request( '/v2/products/stocks', $data );
  }

  private function avoidRateLimit( int &$attempt ):void
  {
    if ( $attempt > $this->maxAttempts ){
      CommunicationService::updateStatus(
        text: "Ошибка! Достигнуто максимальное кол-во повторных попыток запроса",
        percent: 100,
        status: "ABORTED",
        end: date('Y.m.d G:i:s')
      );
      throw new Exception("Cannot avoid rate limit after {$this->maxAttempts} attempts");
    }

    CommunicationService::log("Trying to avoid rate limit. Attempt {$attempt}/{$this->maxAttempts}");
    CommunicationService::updateStatus(
      text: "Попытка обойти Rate Limit. Попытка {$attempt}/{$this->maxAttempts}",
      percent: 53
    );
    sleep( $this->timeoutDelay * $attempt );

    $attempt++;
  }

  private function displayUnsuccsessfulMessage( array $response ):void
  {
    CommunicationService::updateStatus(
      text: "Ошибка при получении остатков: {$response['code']}",
      percent: 100,
      status: "ABORTED",
      end: date('Y.m.d G:i:s')
    );
    CommunicationService::log("Unexpected error occured during getting stocks: {$response['code']}");

    throw new Exception("Unexpected error occured during getting stocks from OZON");
  }

  private function request( string $url, array $data ):array
  {
    $ch = curl_init();
    $options = [
      CURLOPT_URL => $this->baseUrl . $url,
      CURLOPT_HTTPHEADER => $this->headers,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => json_encode( $data )
    ];
    curl_setopt_array( $ch, $options );

    $res = curl_exec( $ch );
    $error = curl_error( $ch );
    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    return [
      'code' => $code,
      'error' => $error,
      'data' => json_decode( $res, true )
    ];
  }
}
 ?>
