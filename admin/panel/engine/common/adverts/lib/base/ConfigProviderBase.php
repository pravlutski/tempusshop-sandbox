<?php
class ConfigProviderBase
{
  private array $suppliers = [
    "79ed7d71-0aa6-11ea-0a80-004200039aa4" => ["id" => 47, "name" => "Склад Москва 1"],
    "51538bd5-6cf3-11ef-0a80-10ba001db77c" => ["id" => 129, "name" => "Склад Москва 2"],
    "8f9fc8a4-4b82-11f0-0a80-1af80012c175" => ["id" => 141, "name" => "Склад Импорт NF"],
    "b8e7c736-3bc2-11f0-0a80-09fd0010bf8f" => ["id" => 144, "name" => "Склад Импорт WR"],
  ];

  private array $serviceRequirements = [
    'ozon' => [
      'api' => 'OzonApiManager',
      'service' => 'OzonAdvertService',
    ],
    'wb' => [
      'api' => 'WBApiManager',
      'service' => 'WBAdvertService',
    ],
  ];

  private array $configs = [
    'ozon' => 'OzonConfigProvider',
    'wb' => 'WBConfigProvider',
  ];

  private array $msApiMethods = [
    'stock' => "https://api.moysklad.ru/api/remap/1.2/report/stock/all"
  ];

  private array $retryCodes = [
    429 => true,
    500 => true,
  ];

  private int $maxRequestAttempts = 5;
  private int $retryDelay = 3;

  protected string $nameTemplate = "%s-%s Авто РК";

  protected array $allowedPlatforms = [
    'ozon' => true,
    'wb' => true,
  ];

  protected array $logPaths = [
    'default' => "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/logs/%s/%s.txt",
    // "WBFinanceService" => "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/logs/%s/budget/b_%s.txt",
  ];

  public function getPlatform():string
  {
    return $this->platform;
  }

  public function getConfigClass( string $platform ):string
  {
    return $this->configs[ $platform ];
  }

  public function getStockInfo( string $id, string $key = "id" ):string|int
  {
    return $this->suppliers[$id][$key];
  }

  public function getSuppliers():array
  {
    return $this->suppliers;
  }

  public function getLogPaths():array
  {
    return $this->logPaths;
  }

  public function getServiceRequirements():?array
  {
    return $this->serviceRequirements[ $this->platform ];
  }

  public function getAllowedPlatforms():array
  {
    return $this->allowedPlatforms;
  }

  public function getAdvertNameTemplate():string
  {
    return $this->nameTemplate;
  }

  public function getDefaultValue( string $key ):?int
  {
    return $this->defaultValues[$key];
  }

  public function getLimit( string $key ):int
  {
    return $this->limits[ $key ];
  }

  public function getApiMethod( string $key ):?string
  {
    return $this->apiMethods[ $key ];
  }

  public function getIdKey():string
  {
    return $this->dataProviderKey;
  }

  public function getMSUrl( string $key ):string
  {
    return $this->msApiMethods[$key];
  }

  public function getMaxRequestAttempts():int
  {
    return $this->maxRequestAttempts;
  }

  public function getRetryDelay():int
  {
    return $this->retryDelay;
  }

  public function getRetryCodes():array
  {
    return $this->retryCodes;
  }
}
 ?>
