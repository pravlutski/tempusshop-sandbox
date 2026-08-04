<?php
class WBApiManager extends ApiManagerBase implements ApiManagerInterface
{
  private array $auth = [];

  public function setAuthData( array $auth ):void
  {
    $this->auth = $auth;
  }

  public function getCampaignsList():array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('list'),
      headers: $this->getRequestHeaders(),
      method: "GET"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
      throw new Exception( "Cannot get critical data (All campaigns list)" );
    }

    return $result;
  }

  public function getCampaignProducts( array $query ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('products'),
      headers: $this->getRequestHeaders(),
      query: $query,
      method: "GET"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  public function getAvailableItems( array $data ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('available'),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "POST"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  public function editCampaignProducts( array $data ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('edit'),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "PATCH"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  public function editCampaignBids( array $data ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('bids'),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "PATCH"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  public function getAccountBalance():array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('balance'),
      headers: $this->getRequestHeaders(),
      method: "GET"
    );

    return $result;
  }

  public function getCampaignBudget( array $query ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('budget'),
      headers: $this->getRequestHeaders(),
      query: $query,
      method: "GET"
    );

    return $result;
  }

  public function refillCampaignBudget( array $query, array $data ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('deposit'),
      headers: $this->getRequestHeaders(),
      query: $query,
      body: $data,
      method: "POST"
    );

    return $result;
  }

  public function createCampaign( array $data ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod('create'),
      headers: $this->getRequestHeaders(),
      body: $data,
      method: "POST"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  public function changeCampaignActivity( array $query, string $apiMethod ):array
  {
    $result = $this->request(
      url: Config::instance()->getApiMethod( $apiMethod ),
      headers: $this->getRequestHeaders(),
      query: $query,
      method: "GET"
    );

    if ( $result['code'] != 200 ){
      CommunicationService::log("ERROR [{$result['code']}]");
      CommunicationService::log( $result['response'] );
    }

    return $result;
  }

  private function getRequestHeaders():array
  {
    return [
      "Content-Type: application/json",
      "Authorization: {$this->auth['key']}",
    ];
  }
}
 ?>
