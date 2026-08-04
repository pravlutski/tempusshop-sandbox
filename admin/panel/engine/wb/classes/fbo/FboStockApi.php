<?php
class FboStockApi
{
  private string $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json";
  private string $url = 'https://www.wildberries.by/__internal/u-card/cards/v4/detail';

  private array $query = [
    'appType' => '1',
    'curr' => 'rub',
    'dest' => '-3339991',
    'spp' => '30',
    'ab_testing' => 'false',
    'nm' => '',
  ];

  private array $codes = [
    'success' => 200,
    'rateLimit' => 429,
  ];

  private array $headers = [
    'Sec-GPC:1',
    // 'deviceid:site_aoaoaoaoaoa',
    // 'Accept-Language:en-US,en;q=0.9',
    // 'Accept-Encoding:gzip, deflate, br, zstd',
  ];

  private int $maxAttempts = 5;
  private int $delay = 1000;
  private int $supplierStore = 371889;

  private array $options = [];
  private ?Closure $request = null;

  public function __construct( private array $fbs )
  {
    $this->options = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTPHEADER => $this->headers,
      // CURLOPT_ENCODING => 'gzip, deflate',
      CURLOPT_COOKIE => $this->getAuthCookie(),
    ];

    $this->request = function( $handle ){
      $res = curl_exec( $handle );

      return [
        'response' => $res ?? null,
        'code' => curl_getinfo( $handle, CURLINFO_HTTP_CODE ),
      ];
    };
  }

  public function getCardInfo( array $items ):array
  {
    $result = $this->request( $items );

    return $this->processResponse( $result );
  }

  private function request( array $items ):array
  {
    $chunks = array_chunk( $items, 30 );
    $result = [];

    foreach ( $chunks as $chunk ){
      $query = $this->buildQuery( $chunk );

      $ch = curl_init( "{$this->url}?{$query}" );
      curl_setopt_array( $ch, $this->options );

      $data = $this->withRetries(
        request: $this->request,
        handler: $ch,
        maxAttempts: $this->maxAttempts,
        delay: $this->delay
      );

      curl_close($ch);

      $result[] = $data;
    }
    return $result;
  }

  private function withRetries( callable $request, CurlHandle $handler, int $maxAttempts, int $delay ):array
  {
    $attempt = 1;

    while ( $attempt <= $maxAttempts ){
      try{
        $r = $request( $handler );

        if ( $r['code'] == 429 ) throw new RateLimitException();
        if ( $r['code'] == 498 ) throw new UnauthorizedRequestException();
        usleep( $delay * 800 );

        break;
      } catch ( RateLimitException $e ){
        $attempt++;

        usleep( $delay * 1000 * $attempt );

        continue;
      } catch ( UnauthorizedRequestException $e ){
        $attempt++;
        usleep( $delay * 1000 * $attempt );

        continue;
      }
    }

    print_r("__________________\n");

    if ( $r['code'] == 429 ) throw new IncompleteDataException("Ошибка 429: превышен лимит запросов");
    if ( $r['code'] == 498 ) throw new UnauthorizedRequestException("Ошибка 498: не удалось получить ответ или кука умерла");

    return [
      'response' => json_decode( $r['response'], true ),
      'status' => ($r['code'] == 200),
      'code' => $r['code'],
    ];
  }

  private function processResponse( array $response ):array
  {
    if ( empty($response) ) throw new Exception( "Got no info from WB" );
    $result = [];

    foreach ( $response as $batch ){
      $data = $this->processBatch( $batch['response']['products'] );
      $result = array_merge( $result, $data );
    }

    return $result;
  }

  private function processBatch( array $batch ):array
  {
    $result = [];

    foreach ( $batch as $item ){
      $stocks = $item['sizes'][0]['stocks'];
      $count = count($stocks);

      if ( $count == 0 ){ // Если массив пустой, то товаров нет ни на ФБО, ни на ФБС
        $result[] = ['id' => $item['id'], 'isVisible' => false];
        continue;
      }

      foreach ( $stocks as $warehouseInfo ){ // Если среди складов есть НЕ склад продавца, то товар реально в наличии на ФБО
        if ( $warehouseInfo['wh'] != $this->supplierStore && $warehouseInfo['qty'] > 0 ){
          $result[] = ['id' => $item['id'], 'isVisible' => true];
          continue 2;
        }
      }
      $result[] = ['id' => $item['id'], 'isVisible' => false];
    }

    return $result;
  }

  private function buildQuery( array $batch ):string
  {
    $q = $this->query;

    $q['nm'] = implode( ';', $batch );
    $result = http_build_query( $q );

    return $result;
  }

  private function getAuthCookie():string
  {
    if ( !file_exists( $this->path ) ) throw new Exception('No cookie file');

    $json = file_get_contents( $this->path );
    if ( !$json ) throw new Exception("Cannot read cookie file");

    $cookie = json_decode( $json, true );
    if ( !$json ) throw new Exception("Cookie file is corrupted");

    return $cookie['cookie'];
  }
}

 ?>
