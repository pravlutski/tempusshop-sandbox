<?php
class ApiManagerBase
{
  protected function request(
    string $url = '',
    ?array $query = null,
    ?array $body = null,
    array $headers = [],
    string $method = "POST",
    ?array $optionsAdd = null
  ):array
  {
    if ( $query ) $url = $url . "?". http_build_query($query);

    $ch = curl_init($url);

    $options = [
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_CONNECTTIMEOUT => 30,
      CURLOPT_POSTFIELDS => json_encode( $body === [] ? (object) $body : $body ),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method
    ];

    if ( !empty($optionsAdd) ){
      $options = array_replace( $options, $optionsAdd );
    }
    curl_setopt_array($ch, $options);

    $r = $this->withRetires( $ch );
    curl_close( $r['ch'] );
    return [
      'response' => $r['res'],
      'code' => $r['code'],
      'error' => $r['error'],
      'result' => json_decode( $r['res'], true ),
    ];
  }

  private function withRetires( CurlHandle $ch ):array
  {
    $attempts = Config::instance()->getMaxRequestAttempts();
    $retryCodes = Config::instance()->getRetryCodes();
    $retryDelay = Config::instance()->getRetryDelay();

    for ( $i = 1; $i < $attempts; $i++ ){
      $result = curl_exec( $ch );
      $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

      if ( $retryCodes[$code] ){
        CommunicationService::log("Request failed with {$code}. Attempt {$i}/{$attempts}");
        sleep( $retryDelay * $i );
        continue;
      }

      break;
    }

    return [
      'res' => $result,
      'code' => $code,
      'error' => curl_error( $ch ),
      'ch' => $ch
    ];
  }
}
 ?>
