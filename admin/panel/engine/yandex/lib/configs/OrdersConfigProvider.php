<?php
class OrdersConfigProvider extends ConfigProviderBase
{
  private int $ordersListLimit = 50;
  private int $userId = 81140;
  private string $siteId = 's1';
  private int $payId = 34;
  private int $shipId = 68;
  private int $personType = 1;
  private string $currency = 'RUB';
  private int $tradingPlatformId = 11;

  private int $daysFrom = 1;

  private array $errorTexts = [
    'pos_create' => 'Critical error ocurred during order (%s) save in panel DB',
    'pos_update' => 'Critical error ocurred during order (%s) update in panel DB',
    'bos_create' => 'Critical error ocurred during order (%s) save in bitrix DB',
    'bos_update' => 'Critical error ocurred during order (%s) update in bitrix DB',
    'status_match' => 'Error occured during order (%s) update: Status "%s" has no match value',
  ];

  private string $comment = 'Заказ поступил с Yandex';
  private string $finalOrderStatus = 'F';

  private string $logFolder = __DIR__."/../../logs/orders/";
  private string $logFile = "%s.txt";

  public function getOrdersListLimit():int
  {
    return $this->ordersListLimit;
  }

  public function getUserId():int
  {
    return $this->userId;
  }

  public function getSiteId():string
  {
    return $this->siteId;
  }

  public function getPayId():int
  {
    return $this->payId;
  }

  public function getShipId():int
  {
    return $this->shipId;
  }

  public function getPersonType():int
  {
    return $this->personType;
  }

  public function getCurrency():string
  {
    return $this->currency;
  }

  public function getErrorText( string $key ):string
  {
    return $this->errorTexts[ $key ];
  }

  public function getComment():string
  {
    return $this->comment;
  }

  public function getFinalOrderStatus():string
  {
    return $this->finalOrderStatus;
  }

  public function getTradingPlatformId():int
  {
    return $this->tradingPlatformId;
  }

  public function getLogPath():string
  {
    return $this->logFolder . $this->logFile;
  }

  public function getDaysFrom():int
  {
    return $this->daysFrom;
  }
}
 ?>
