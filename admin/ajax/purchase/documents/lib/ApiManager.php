<?php
class ApiManager
{
  public function __construct( private ?MoyskladAPI $ms = null )
  {
    $this->baseUrl = 'https://api.moysklad.ru/api/remap/1.2';
    $this->token = $ms->getAccessParams()['access_token'];
  }

  public function request( string $url, array $headers, array $data, string $method ):array
  {
    $options = [
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => 'gzip,deflate',
      CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ( !empty($data) ) $options[CURLOPT_POSTFIELDS] = json_encode( $data );

    $ch = curl_init( $this->baseUrl . $url );
    curl_setopt_array( $ch, $options );
    $res = curl_exec( $ch );

    return json_decode( $res, true );
  }

  public function getHeaders():array
  {
    return [
      "Accept: application/json;charset=utf-8",
      "Content-Type: application/json",
      "Authorization: Bearer {$this->token}"
    ];
  }
}

 ?>
