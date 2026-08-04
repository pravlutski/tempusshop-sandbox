<?php
class StocksApiManager
{
  private string $baseUrl = 'https://api-seller.ozon.ru';
  private int $timeoutDelay = 5;
  private int $maxAttempts = 5;

  public function __construct(
    private array $headers
  ) {}

  public function getWarehousesStatus():array
  {
    $flag = true;
    $result = [];
    $data = [ 'cursor' => '', 'limit' => 1000, 'filter' => ['visibility' => 'ALL'] ];
    $attempts = 1;

    while ( $flag ){
      $response = $this->request('/v4/product/info/stocks', $data);

      if ( $response['code'] == 429 ){
        $this->avoidRateLimit();
        continue;
      }
      if ( $response['code'] != 200 ) $this->throwUnsuccsessfulMessage();

      if ( empty($response['data']['cursor']) ) $flag = false;

      $result += $response['data']['items'];
    }

    return $result
  }

  public function updateStocks():void
  {

  }

  private function avoidRateLimit( int &$attempt ):void
  {
    if ( $attempt <= $this->maxAttempts ){
      // CommunicationService
      throw new Exception("Cannot avoid rate limit after {$this->maxAttempts} attempts");
    }

    // CommunicationService
    sleep( $this->timeoutDelay * $attempt );

    $attempt++;
  }

  private function displayUnsuccsessfulMessage():void
  {
    // CommunicationService
    throw new Exception("Unexpected error occured during getting stocks from OZON");
  }

  private function request( string $url, array $data ):array
  {
    $ch = curl_init();
    $options = [
      CURLOPT_URL => $url,
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
      'data' => json_decode( $data, true )
    ];
  }
}
 ?>
