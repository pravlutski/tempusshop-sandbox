<?php
class PricesConfigProvider extends ConfigProviderBase
{
  private string $priceProperty = 'CATALOG_PRICE_1';
  private string $pricePropertyVal = 'CATALOG_PRICE_1'; // Bitrix specific (if price property is custom it will be accessed via 'PROPERTY_NAME_VALUE')
  private float $defaultMarkup = 1;
  private string $currency = 'RUR';

  private int $updateBusinessPricesLimit = 500;
  private int $updateBusinessPricesDelay = 4;
  private int $limitPerMinute = 10000;
  private float $delayThreshold = 0.8;
  private int $minDelay = 1;

  private string $logFolder = __DIR__."/../../logs/prices/";
  private string $logFile = "%s.txt";

  public function getPriceProperty():string
  {
    return $this->priceProperty;
  }

  public function getPricePropertyVal():string
  {
    return $this->pricePropertyVal;
  }

  public function getDefaultMarkup():float
  {
    return $this->defaultMarkup;
  }

  public function getCurrency():string
  {
    return $this->currency;
  }

  public function getUpdateBusinessPricesLimit():int
  {
    return $this->updateBusinessPricesLimit;
  }

  public function getUpdateBusinessPricesDelay():int
  {
    return $this->updateBusinessPricesDelay;
  }

  public function getLimitPerMinute():int
  {
    return $this->limitPerMinute;
  }

  public function getDelayThreshold():float
  {
    return $this->delayThreshold;
  }

  public function getMinDelay():int
  {
    return $this->minDelay;
  }

  public function getLogFolder():string
  {
    return $this->logFolder;
  }

  public function getLogPath():string
  {
    return $this->logFolder . $this->logFile;
  }
}
 ?>
