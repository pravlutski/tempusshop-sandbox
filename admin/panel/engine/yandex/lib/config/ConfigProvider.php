<?php
class ConfigProvider
{
  protected static array $cabinets = [
    'WR' => "Основной кабинет",
  ];

  private static array $tables = [
    'main_settings' => 'yandex_main_settings',
    'price_settings' => 'yandex_price_settings',
    'stocks_settings' => 'yandex_stocks_settings',
    'campaigns_list' => 'yandex_campaigns_list',
    'campaigns_match_list' => 'yandex_campaigns_match_list',
  ];

  private static array $apiMethods = [
    'getOfferCardsContentStatus' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/offer-cards',
    'getOfferMappings' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/offer-mappings',
    'getCampaigns' => 'https://api.partner.market.yandex.ru/v2/campaigns',
    'getStocks' => 'https://api.partner.market.yandex.ru/v2/campaigns/%s/offers/stocks',
  ];

  protected static array $repositories = [
    'items' => 'ItemsRepository',
    'settings' => 'SettingsRepository',
    'prices' => 'PricesRepository',
  ];

  protected static array $configs = [
    'stocks' => 'StocksConfigProvider',
    'prices' => 'PricesConfigProvider',
    'orders' => 'OrdersConfigProvider',
    'products' => 'ProductsConfigProvider',
    'sales' => 'SalesConfigProvider',
    'adverts' => 'AdvertsConfigProvider',
    'ui' => 'UIConfigProvider',
  ];

  protected static array $availableValues = [
    'RU' => 512,
    'BY' => 493
  ];


  public static function getAllCabinets():array
  {
    return self::$cabinets;
  }

  public static function getTableName( string $key ):string
  {
    if ( !isset(self::$tables[$key]) ) throw new UnknownConfiguraionKeyException("No value for key {$key} in tables list");
    return self::$tables[$key];
  }

  public static function getApiMethod( string $key ):string
  {
    if ( !isset(self::$apiMethods[$key]) ) throw new UnknownConfiguraionKeyException("No value for key {$key} in tables list");
    return self::$apiMethods[$key];
  }

  public static function getRepositoryClass( string $key ):string|bool
  {
    return self::$repositories[$key] ?? false;
  }

  public static function getConfigClass( string $key ):string|bool
  {
    return self::$configs[$key] ?? false;
  }

  public static function getAvailableValue( string $key ):int
  {
    return self::$availableValues[$key] ?? false;
  }
}
?>
