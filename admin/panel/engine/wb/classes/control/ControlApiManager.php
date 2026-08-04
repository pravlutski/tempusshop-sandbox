<?php
class ControlApiManager
{
  private static array $config = [
    'files' => [
      'cookie' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json',
      'result' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reportStock/%s/control.txt',
    ],
    'supp' => ['WR' => 724646, 'TL' => 320313],
    'link' => 'https://u-catalog.wb.ru/sellers/v4/catalog',
    'maxAttempts' => 9,
    'delay' => 1200,
    'filter' => [
      'WR' => 'active_wb',
      'WT' => 'active_wb',
      'TL' => 'active_wbtl',
    ],
  ];

  public function __construct(
      private string $cabinet,
  ){}

  public function getItemsWB( int $maxPage ):array
  {
    $query = [
      'dest' => -3339991,
      'page' => 1,
      'limit' => 300,
      'sort' => 'popular',
      'hide_dflags' => 131072,
      'hide_dtype' => 13,
      'hide_vflags' => 4294967296,
      'supplier' => self::$config['supp'][$this->cabinet],
    ];
    $options = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => false,
      CURLOPT_COOKIE => $this->getAuthCookie()
    ];

    $res = [];

    for ( $i = 1; $i <= $maxPage; $i++ ){
      $query['page'] = $i;
      print_r("###### Page {$query['page']} ######\n");
      $res += $this->request(
        url: self::$config['link'],
        query: $query,
        options: $options
      );
    }

    return $res;
  }

  private function request( string $url, array $query, array $options ):array
  {
    $queryString = http_build_query($query);
    $url = "{$url}?{$queryString}";

    $ch = curl_init( $url );
    curl_setopt_array( $ch, $options );

    $request = function() use ($ch){
      $response = curl_exec( $ch );
      return [
        'response' => $response,
        'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'handle' => $ch,
      ];
    };

    $data = $this->withRetries(
      fn: $request,
      maxAttempts: self::$config['maxAttempts'],
      delay: self::$config['delay']
     );

    if ( !$data['status'] ) return [];

    return $this->processResponse( $data['response'] );
  }

  private function withRetries( callable $fn, int $maxAttempts, int $delay ):array
  {
    $attempt = 1;
    while ( $attempt <= $maxAttempts ){
      try{
        $r = $fn();
        print_r("Attempt {$attempt}: Code {$r['code']}\n");
        if ( $r['code'] == 429 ) throw new Exception( "Too Many Requests" );
        usleep( $delay * 800 );
        break;
      } catch ( Exception $e ){
        $attempt++;
        print_r( "Delay: " . $delay * 1000 * $attempt . "\n" );
        usleep( $delay * 1000 * $attempt );
        continue;
      }
    }
    print_r("__________________\n");

    if ( $r['code'] == 429 ) throw new IncompleteDataException("Ошибка: нарушена целостность ответа WB");

    return [
      'response' => json_decode( $r['response'], true ),
      'status' => ($r['code'] == 200),
    ];
  }

  private function processResponse( array $data ):array
  {
    if ( empty($data) ) return [];
    if ( empty($data['products']) ) return [];
    $result = [];

    foreach ( $data['products'] as $product ){
      $result[ $product['id'] ] = $product['id'];
    }
    return $result;
  }

  private function getAuthCookie():string
  {
    if ( !file_exists( self::$config['files']['cookie'] ) ) die('Нет куки файла');

    $json = file_get_contents( self::$config['files']['cookie'] );
    $cookieArray = json_decode( $json, true );

    return $cookieArray['cookie'];
  }

}
 ?>
