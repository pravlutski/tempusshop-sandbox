<?php
class OzonApiManager extends ApiManagerBase implements ApiManagerInterface
{
  private array $api = [];

  public function authorize():void
  {
    if ( $this->validateAuthCache() ) return;

    $result = $this->request(
      url: Config::instance()->getApiMethod('auth'),
      headers: $this->getAuthHeaders(),
      body: Config::instance()->getAuthData(),
      method: 'POST'
    );

    if ( $result['code'] == 200 ){
      CommunicationService::log("Authorization passed successfully");
      $this->api = $result['result'];
      $this->cacheAuthResult();
      return;
    }

    CommunicationService::log("Authorization was not passed: {$result['code']}");
    CommunicationService::log($result['response']);

    throw new Exception("Token request error");
  }

  private function cacheAuthResult():void
  {
    $res = file_put_contents(
      Config::instance()->getAuthCachePath(),
      json_encode($this->api)
    );

    if ( !$res ){
      CommunicationService::log("Cannot cache auth data");
      return;
    }

    CommunicationService::log("Cached successfully");
  }

  private function validateAuthCache():bool
  {
    $path = Config::instance()->getAuthCachePath();
    if ( !file_exists($path) ) return false;

    $lifespan = abs( time() - filectime($path) );

    if ( $lifespan <= Config::instance()->getAuthLifespan() ){
      $json = file_get_contents( $path );
      $this->api = json_decode( $json, true );

      CommunicationService::log("Auth data is up to date");

      return true;
    }

    CommunicationService::log("Auth data is expired");
    return false;
  }

  public function getCampaignProducts( string $id ):array
  {
    $result = $this->request(
      url: sprintf( Config::instance()->getApiMethod('products'), $id ),
      headers: $this->getRequestHeaders(),
      method: 'GET'
    );

    if ( $result['code'] == 200 ){
      CommunicationService::log("Got items for campaign {$id} successfully");
    }else{
      CommunicationService::log("Cannot get items for campaign {$id}: {$result['code']}");
    }

    return $result;
  }

  public function createCampaign( array $data ):?string
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('create'),
      headers: $this->getRequestHeaders(),
      body: $data,
    );

    if ( $result['code'] == 200 && isset($result['result']['campaignId']) ){
      CommunicationService::log("SUCCESS advId: {$result['result']['campaignId']}");
      return $result['result']['campaignId'];
    }

    CommunicationService::log("ERROR {$result['code']}");
    CommunicationService::log( $result['response'] );
  }

  public function addProducts( string $advertId, array $data, string $method = "POST" ):array
  {
    $result = $this->request(
      url: sprintf( Config::instance()->getApiMethod('add'), $advertId ),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: $method
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR {$result['code']} [{$method}]");
      CommunicationService::log( $result['response'] );

      return $result;
    }

    CommunicationService::log("SUCCESS [{$method}]");

    return $result;
  }

  public function updateParameters( string $advertId, array $data ):array
  {
    $result = $this->request(
      url: sprintf( Config::instance()->getApiMethod('parameters'), $advertId ),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "PATCH"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log( "ERROR {$result['code']}" );
      CommunicationService::log( $result['response'] );

      return $result;
    }

    CommunicationService::log("SUCCESS");

    return $result;
  }

  public function deleteProducts( string $advertId, array $data ):array
  {
    $result = $this->request(
      url: sprintf( Config::instance()->getApiMethod('delete'), $advertId ),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "POST"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR {$result['code']}");
      CommunicationService::log( $result['response'] );
    }

    CommunicationService::log("SUCCESS");

    return $result;
  }

  public function changeCampaignActivity( string $advertId, string $apiMethod ):array
  {
    $result = $this->request(
      url: sprintf( Config::instance()->getApiMethod($apiMethod), $advertId ),
      headers: $this->getRequestHeaders(),
      method: "POST",
      body: []
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR {$result['code']} [{$apiMethod}]");
      CommunicationService::log( $result['response'] );
    }

    CommunicationService::log("SUCCESS [{$apiMethod}]");

    return $result;
  }

  public function getCampaignsList( array $query ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('list'),
      headers: $this->getRequestHeaders(),
      method: "GET",
      query: $query
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
      throw new Exception( "Cannot get critical data (All campaigns list)" );
    }

    return $result;
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
}
 ?>
