<?php
class AdvertConfigProvider
{
  private static array $config = [
    'file' => [
      'competitor' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/adverts/files/comp.xlsx',
      'own' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/adverts/files/own.xlsx',
      'log' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/adverts/logs/log.json',
    ],
    'auth' => [
      'client_id' => '92352313-1771405802481@advertising.performance.ozon.ru',
      'client_secret' => 'YAV_CeGiaHYrWBd04Guab0RUlPGI3CwRXthMRap74JJJhqVL1APmjg7zavmi1sDYU2gL6nxxjy7OxmE1nA',
      'grant_type' => 'client_credentials'
    ],
    'methods' => [
      'auth' => 'https://api-performance.ozon.ru/api/client/token',
      'list' => 'https://api-performance.ozon.ru:443/api/client/campaign?advObjectType=SKU',
      'add' =>  'https://api-performance.ozon.ru:443/api/client/campaign/%s/products',
      'goods' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/v2/products?pageSize=500',
      'delete' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s/products/delete',
      'parameters' => 'https://api-performance.ozon.ru:443/api/client/campaign/%s',
      'report' => 'https://api-performance.ozon.ru:443/api/client/statistics/all_sku_promo/orders/generate',
    ],
    'tables' => [
      'sku_dict' => 'ozon_sku_dict_IP',
      'profit' => 'ms_profit_ru_12',
      'settings' => 'ozon_adverts_settings',
      'sales_log' => 'ozon_sales_detail_log_IP',
    ],
    'prepared' => [
      'adverts' => [23250562, 23250575, 23250589],
      'average_coinvest' => 0.56, // это не соинвест, а множитель с учетом соинвеста в 44%
      'global_limit' => 300,
      'advert_limit' => 30,
      'minimum_price_limit' => 5000,
      'bid' => 4,
      'profit_field' => 'quantity',
    ],
    'parameters' => [
      'min_weekly_budget' => 2000,
      'multiplier' => 1000000
    ],
    'reason_bad' => [
      'c1' => 'Добавлен в неустановленную РК',
      'c2' => 'Нет в наличии',
      'c3' => 'Товар в карантине',
      'c4' => 'Не прошел по мин. лимиту цены',
      'c5' => 'Не прошел по глобальному лимиту',
      'c6' => 'Цена конкурента ниже',
    ],
  ];

  public static function init( array $params ):void
  {
    foreach ( $params as $key => $value ){
      self::$config['prepared'][$key] = $value;
    }
  }

  public static function getPreparedSettings():array
  {
    return self::$config['prepared'];
  }

  public static function getAuthData():array
  {
    return self::$config['auth'];
  }

  public static function getCompetitorFilePath():string
  {
    return self::$config['file']['competitor'];
  }

  public static function getOwnFilePath():string
  {
    return self::$config['file']['own'];
  }

  public static function getSkuDictionaryTable():string
  {
    return self::$config['tables']['sku_dict'];
  }

  public static function getProfitTable():string
  {
    return self::$config['tables']['profit'];
  }

  public static function getSettingsTable():string
  {
    return self::$config['tables']['settings'];
  }

  public static function getSalesLogTable():string
  {
    return self::$config['tables']['sales_log'];
  }

  public static function getAdvertListMethod():string
  {
    return self::$config['methods']['list'];
  }

  public static function getAddAdvertGoodsMethod():string
  {
    return self::$config['methods']['add'];
  }

  public static function getReportMethod():string
  {
    return self::$config['methods']['report'];
  }

  public static function getDeleteAdvertGoodsMethod():string
  {
    return self::$config['methods']['delete'];
  }

  public static function getAdvertGoodsMethod():string
  {
    return self::$config['methods']['goods'];
  }

  public static function getEditAdvertParametersMethod():string
  {
    return self::$config['methods']['parameters'];
  }

  public static function getAuthMethod():string
  {
    return self::$config['methods']['auth'];
  }

  public static function getActionLogPath():string
  {
    return self::$config['file']['log'];
  }

  public static function getPreparedAdverts():array
  {
    return self::$config['prepared']['adverts'];
  }

  public static function getMinItemBudget():int
  {
    return self::$config['parameters']['min_weekly_budget'];
  }

  public static function getBudgetMultiplier():int
  {
    return self::$config['parameters']['multiplier'];
  }

  public static function getAverageCoInvest():float
  {
    return self::$config['prepared']['average_coinvest'];
  }

  public static function getMaxTopItemsCount():int
  {
    return self::$config['prepared']['global_limit'];
  }

  public static function getAdvertItemsLimit():int
  {
    return self::$config['prepared']['advert_limit'];
  }

  public static function getProfitOrderField():string
  {
    return self::$config['prepared']['profit_field'];
  }

  public static function getBidValue():int
  {
    return self::$config['prepared']['bid'];
  }

  public static function getMinimumPriceLimit():int
  {
    return self::$config['prepared']['minimum_price_limit'];
  }

  public static function getReason( string $key ):string
  {
    return self::$config['reason_bad'][$key];
  }
}
 ?>
