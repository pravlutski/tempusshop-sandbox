<?php
class StocksConfigProvider extends ConfigProviderBase
{
  private int $defaultStock = 0;
  private float $defaultMarkup = 1;

  private int $requestStockLimit = 200; // Лимит при запросе товаров на маркете
  private int $maxChunkSize = 2000; // Лимит размера пачки отправляемых остатков

  private string $priceProperty = 'CATALOG_PRICE_1';
  private string $pricePropertyVal = 'CATALOG_PRICE_1'; // Bitrix specific (if price property is custom it will be accessed via 'PROPERTY_NAME_VALUE')

  private string $logFolder = __DIR__."/../../logs/stocks/";
  private string $logFile = "%s.txt";

  public function getDefaultStockValue():int
  {
    return $this->defaultStock;
  }

  public function getDefaultMarkupValue():float
  {
    return $this->defaultMarkup;
  }

  public function getMaxChunkSize():int
  {
    return $this->maxChunkSize;
  }

  public function getRequestStockLimit():int
  {
    return $this->requestStockLimit;
  }

  public function getPriceProperty():string
  {
    return $this->priceProperty;
  }

  public function getPricePropertyVal():string
  {
    return $this->pricePropertyVal;
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
