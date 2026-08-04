<?php
class PromosConfigProvider extends ConfigProviderBase
{
  private string $logFolder = __DIR__."/../../logs/promos/";
  private string $logFile = "%s.txt";
  private float $fakeDiscount = 0.8;

  private int $requestOffersLimit = 500;
  private int $getLogChunkSize = 1000;
  private int $updatePromoOffersLimit = 500;
  private int $deletePromoOffersLimit = 500;

  private array $reasons = [
    'eng' => [
      'priority' => "Offer was added in promo with higher priority. FAILED",
      'map_eq' => "Item's price lower or equal MAP. PASSED",
      'profit_good' => "Item's MAP satisfies profit check (margin: %s%%, profit: %s rub, discount: %s%%). PASSED",
      'profit_bad' => "Item\'s price or discount failed profit check (margin: %s%%, profit: %s rub, discount: %s%%). FAILED",
      'fix_map_eq' => "Item's price with fix discount lower or equal to MAP. PASSED",
      'fix_bad' => "Item's price with fix discount greater than MAP. FAILED",
      'bad_mode' => "Offer does not satisfy selected mode requirements",
      'no_req' => "Promo has no price requirements",
    ],
    'ru' => [
      'priority' => "Товар добавлен в другую акцию по приоритету.",
      'map_eq' => "Цена товара ниже или равна MAP.",
      'profit_good' => "Товар с установленным MAP прошел по маржинальности (margin: %s%%, profit: %s rub, discount: %s%%)",
      'profit_bad' => "Товар с установленным MAP не прошел проверку по маржинальности (margin: %s%%, profit: %s rub, discount: %s%%).",
      'fix_map_eq' => "Цена товара с фикс. скидкой ниже или равна MAP",
      'fix_bad' => "Цена товара с фикс. скидкой выше MAP.",
      'bad_mode' => "Товар не подходит по требованиям для выбранного режима работы",
      'no_req' => "Акция не имеет требований к цене товара"
    ],
  ];
  private string $locale = 'ru';

  public function getRequestOffersLimit():int
  {
    return $this->requestOffersLimit;
  }

  public function getLogFolder():string
  {
    return $this->logFolder;
  }

  public function getLogPath():string
  {
    return $this->logFolder . $this->logFile;
  }

  public function getFakeDiscount():float
  {
    return $this->fakeDiscount;
  }

  public function getReason( string $key ):string
  {
    return $this->reasons[ $this->locale ][$key];
  }

  public function getLogChunkSize():int
  {
    return $this->getLogChunkSize;
  }

  public function getUpdatePromoOffersLimit():int
  {
    return $this->updatePromoOffersLimit;
  }

  public function getDeletePromoOffersLimit():int
  {
    return $this->deletePromoOffersLimit;
  }
}
 ?>
