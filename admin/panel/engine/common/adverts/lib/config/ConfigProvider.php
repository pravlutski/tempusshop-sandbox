<?php
class ConfigProvider
{
  private static array $defaultValues = [
    'ozon' => [
      'minCost' => 1,
      'maxCost' => 999999,
      'stockDays' => 0,
      'bid' => 4,
    ],
    'wb' => [
      'minCost' => 1,
      'maxCost' => 999999,
      'stockDays' => 0,
      'bid' => 120,
    ],
  ];

  private static array $suppliers = [
    "79ed7d71-0aa6-11ea-0a80-004200039aa4" => ["id" => 47, "name" => "Склад Москва 1"],
    "51538bd5-6cf3-11ef-0a80-10ba001db77c" => ["id" => 129, "name" => "Склад Москва 2"],
    "8f9fc8a4-4b82-11f0-0a80-1af80012c175" => ["id" => 141, "name" => "Склад Импорт NF"],
    "b8e7c736-3bc2-11f0-0a80-09fd0010bf8f" => ["id" => 144, "name" => "Склад Импорт WR"],
  ];

  private static array $allowedPlatforms = [
    'ozon' => true,
    'wb' => true,
  ];

  private static array $serviceRequirements = [
    'ozon' => [
      'api' => 'OzonApiManager',
      'service' => 'OzonAdvertService'
    ],
    'wb' => [
      'api' => 'WBApiManager',
      'service' => 'WBAdvertService'
    ],
  ];

  private static array $auth = [
    'client_id' => '92352313-1771405802481@advertising.performance.ozon.ru',
    'client_secret' => 'YAV_CeGiaHYrWBd04Guab0RUlPGI3CwRXthMRap74JJJhqVL1APmjg7zavmi1sDYU2gL6nxxjy7OxmE1nA',
    'grant_type' => 'client_credentials'
  ];

  private static array $apiMethods = [
    'ozon' => [
      'auth' => 'https://api-performance.ozon.ru/api/client/token',
      'list' => 'https://api-performance.ozon.ru:443/api/client/campaign',
      'products' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/objects',
      'add' =>  'https://api-performance.ozon.ru:443/api/client/campaign/%s/products',
      'delete' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/products/delete',
      'parameters' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s',
      'disable' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/deactivate',
      'enable' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/activate',
      'create' => 'https://api-performance.ozon.ru:443/api/client/campaign/cpc/v2/product'
    ],
    'wb' => [
      'list' => 'https://advert-api.wildberries.ru/adv/v1/promotion/count',
      'products' => 'https://advert-api.wildberries.ru/api/advert/v2/adverts',
      'available' => 'https://advert-api.wildberries.ru/adv/v2/supplier/nms',
      'edit' => 'https://advert-api.wildberries.ru/adv/v0/auction/nms',
      'bids' => 'https://advert-api.wildberries.ru/api/advert/v1/bids',
      'balance' => 'https://advert-api.wildberries.ru/adv/v1/balance',
      'budget' => 'https://advert-api.wildberries.ru/adv/v1/budget',
      'deposit' => 'https://advert-api.wildberries.ru/adv/v1/budget/deposit',
      'create' => 'https://advert-api.wildberries.ru/adv/v2/seacat/save-ad',
      'disable' => 'https://advert-api.wildberries.ru/adv/v0/stop',
    ],
  ];

  private static array $budgetSettings = [
    'minBudget' => 300,
    'refill' => 1000,
  ];

  private static array $dataProviderKeys = [
    'ozon' => 'sku',
    'wb' => 'nmid',
  ];

  private static array $productsLimit = [
    'ozon' => 15,
    'wb' => 50,
  ];


  private static int $subjectId = 60;

  private static string $nameTemplate = "%s-%s Авто РК";
  private static string $platform;

  private static array $logPaths = [
    'default' => "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/logs/%s/%s.txt",
    "WBFinanceService" => "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/logs/%s/budget/b_%s.txt",
  ];

  public static function init( string $platform )
  {
    if ( !self::$allowedPlatforms[$platform] ) throw new Exception("Undefined platform");
    self::$platform = $platform;
  }

  public static function getPlatform():string
  {
    return self::$platform;
  }

  public static function getAuthData():array
  {
    return self::$auth;
  }

  public static function getDefaultValue( string $key ):?int
  {
    return self::$defaultValues[$key];
  }

  public static function getStockInfo( string $id, string $key = "id" ):string|int
  {
    return self::$suppliers[$id][$key];
  }

  public static function getSuppliers():array
  {
    return self::$suppliers;
  }

  public static function getProductsLimit():int
  {
    return self::$productsLimit[ self::$platform ];
  }

  public static function getLogPaths():array
  {
    return self::$logPaths;
  }

  public static function getServiceRequirements():?array
  {
    return self::$serviceRequirements[ self::$platform ];
  }

  public static function getApiMethod( string $key ):?string
  {
    return self::$apiMethods[ self::$platform ][ $key ];
  }

  public static function getIdKey():string
  {
    return self::$dataProviderKeys[ self::$platform ];
  }

  public static function getAdvertNameTemplate():string
  {
    return self::$nameTemplate;
  }

  public static function getSubjectId():int
  {
    return self::$subjectId;
  }

  public static function getBudgetSettings( string $key ):int
  {
    return self::$budgetSettings[ $key ];
  }
}

 ?>
