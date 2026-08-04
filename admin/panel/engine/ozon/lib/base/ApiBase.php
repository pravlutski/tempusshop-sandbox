<?php
class ApiBase
{
  public function __construct( private array $settings )
  {
    // Валидация настроек АПИ
  }

  protected function getHeaders():array
  {
    return [
      'Api-Key:' . $this->settings['key'],
      'Client-Id:' . $this->settings['client_id'],
      'Content-Type:application/json'
    ];
  }

  protected function request( array $headers, string $url, string|bool $body ):Response
  {
    $startTime = microtime(true);

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    if ( !empty($body) && is_string($body) ){
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    }

    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

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
}
 ?>
