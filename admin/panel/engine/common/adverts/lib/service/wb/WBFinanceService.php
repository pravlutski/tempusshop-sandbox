<?php
class WBFinanceService
{
  private bool $isBonusActive = false;
  private int $bonusPercent = 0;

  public function __construct(
    private ApiManagerInterface $api
  ){
    $this->getBalance();
  }

  public function refill( string|int $advertId ):bool
  {
    $response = $this->api->getCampaignBudget( ['id' => $advertId] );
    if ( $response['code'] != 200 ){
      CommunicationService::log("ERROR [{$response['code']}]: Cannot get [{$advertId}] budget");
      CommunicationService::log( $response['response'] );
      return false;
    }
    $budget = $response['result']['total'];
    $cashback = [];

    if ( $budget > Config::instance()->getBudgetSettings('minBudget') ){
      CommunicationService::log("Advert's [{$advertId}] budget is still enough");
      return true;
    }

    if ( $this->isBonusActive ){
      CommunicationService::log( "Advert [{$advertId}]: Cashback refill is available" );
      $cashback = [
        'cashback_sum' => $this->calculateCashbackRefill(),
        'cashback_percent' => $this->bonusPercent
      ];
    }

    $data = [
      'sum' => Config::instance()->getBudgetSettings('refill'),
      'type' => Config::instance()->getBudgetSettings('type'),
      'return' => true,
    ];

    CommunicationService::log( "Refill body: " );
    CommunicationService::log( json_encode(array_merge( $data, $cashback )) );

    $response = $this->api->refillCampaignBudget(
      query: [ 'id' => $advertId ],
      data: array_merge( $data, $cashback )
    );

    if ( $response['code'] != 200 ){
      CommunicationService::log("ERROR [{$response['code']}]: Cannot refill [{$advertId}] budget");
      CommunicationService::log( $response['response'] );

      return false;
    }
    CommunicationService::log("Advert [{$advertId}]: Budget refilled");
    return true;
  }

  private function getBalance():void
  {
    $response = $this->api->getAccountBalance();
    if ( $response['code'] != 200 ){
      CommunicationService::log("ERROR [{$response['code']}]");
      CommunicationService::log( $response['response'] );

      throw new Exception("Error [{$response['code']}]. Cannot get account balance");
    }
    $balance = $response['result'];

    if ( isset($balance['cashbacks']) ){
      $expirationDate = strtotime( $balance['cashbacks'][0]['expiration_date'] . " + 3 hours" );
      $stillAvalialble = $expirationDate > time();
      $this->bonusPercent = $expirationDate ? $balance['cashbacks'][0]['percent'] : 0;
      $this->isBonusActive = $stillAvalialble;
    }
  }

  private function calculateCashbackRefill():float
  {
    $refillSum = Config::instance()->getBudgetSettings('refill');

    return (int)($refillSum / 100 * $this->bonusPercent);
  }
}

 ?>
