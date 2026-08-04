<?php
class AdvertApiManager
{
  private array $api = [];

  public function getAdvertList():array
  {
    return $this->request(
      url: AdvertConfigProvider::getAdvertListMethod(),
      headers: $this->getRequestHeaders(),
      query: false,
      method: 'GET'
    );
  }

  public function getAdvertGoods( int $advertId ):array
  {

    return $this->request(
      url: sprintf( AdvertConfigProvider::getAdvertGoodsMethod(), $advertId ),
      headers: $this->getRequestHeaders(),
      method: 'GET'
    );
  }

  public function deleteAdvertGoods( int $advertId, array $data ):array
  {
    return $this->request(
      url: sprintf( AdvertConfigProvider::getDeleteAdvertGoodsMethod(), $advertId ),
      headers: $this->getRequestHeaders(),
      data: json_encode($data),
      method: 'POST'
    );
  }

  public function addAdvertGoods( int $advertId, array $data, string $method = 'POST' ):array
  {
    return $this->request(
      url: sprintf( AdvertConfigProvider::getAddAdvertGoodsMethod(), $advertId ),
      headers: $this->getRequestHeaders(),
      data: json_encode($data),
      method: $method
    );
  }

  public function getAdvertReport( array $data, string $method = 'POST' ):array
  {
    return $this->request(
      url: AdvertConfigProvider::getReportMethod(),
      headers: $this->getRequestHeaders(),
      data: json_encode($data),
      method: $method
    );
  }

  public function editAdvertParameters( int $advertId, array $data ):array
  {
    return $this->request(
      url: sprintf( AdvertConfigProvider::getEditAdvertParametersMethod(), $advertId ),
      headers: $this->getRequestHeaders(),
      data: json_encode($data),
      method: "PATCH"
    );
  }

  public function getAuthoriztionToken():void
  {
    $result = $this->request(
      url: AdvertConfigProvider::getAuthMethod(),
      headers: $this->getAuthHeaders(),
      data: json_encode( AdvertConfigProvider::getAuthData() ),
      method: 'POST'
    );

    if ( $result['code'] == 200 ){
      $this->api = json_decode($result['result'], true);
      var_dump($this->api);
      return;
    }

    var_dump($result['result']);
    throw new Exception("Token request error");
  }

  private function getAuthHeaders():array
  {
    return [
      "Content-Type:application/json",
      "Accept: application/json",
    ];
  }

  private function getRequestHeaders():array
  {
    return [
      "Authorization: {$this->api['token_type']} {$this->api['access_token']}",
      "Content-Type: application/json",
      "Accept: application/json",
    ];
  }

  private function request(
    string $url = '',
    array $headers = [],
    string|bool $data = false,
    array|bool $query = false,
    string $method = 'GET'
    ):array
  {
    if ( $query ) $completedURL = $url . '?' . http_build_query($query);

    $ch = curl_init( $completedURL ?? $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    if ( $data ) curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close( $ch );

    return [
      'result' => $res,
      'code' => $code,
    ];
  }
}
?>
