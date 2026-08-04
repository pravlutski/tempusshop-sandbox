<?php
class ApiManagerBase
{
  public function __construct(
    protected array $auth
  ){}

  protected function request( string $url, array $headers, string|false $body = false, string $method = 'GET' ):Response
  {
    $startTime = microtime(true);

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    if ( !empty($body) && is_string($body) ){
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    }

    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    $res = curl_exec( $ch );
    $error = curl_error( $ch );
    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

    curl_close( $ch );

    $executionTime = microtime(true) - $startTime;

    return new Response(
      data: new ResponseData( $res ?? '' ),
      headers: $headers,
      method: $url,
      curlError: $error,
      httpCode: $code,
      executionTime: $executionTime
    );
  }

  protected function callApi( string $method, string $http, null|int|string $id = null, ?array $data = null, ?array $query = null ):Response
  {
    $url = $this->buildUrl(
      url: Config::instance()->getApiMethod($method),
      id: $id ?? $this->auth['businessId'],
      params: http_build_query( $query ?? [] )
    );

    return $this->request(
      url: $url,
      headers: $this->buildHeaders(),
      body: !empty($data) ? json_encode($data) : false,
      method: $http
    );
  }

  protected function buildUrl( string $url, null|string|int $id, string $params = '' ):string
  {
    $result = sprintf( $url, $id );

    if ( empty($params) ) return $result;

    return "{$result}?{$params}";
  }

  protected function buildHeaders():array
  {
    return [
      "X-Market-Integration: custom/0.1",
      "Content-Type: application/json",
      "Api-Key: {$this->auth['api']}",
    ];
  }
}
 ?>
