<?php
class ConfigProviderBase implements ConfigProviderInterface
{
  protected array $cabinets = [
    'WR' => "Основной кабинет",
  ];

  protected array $modules = [
    'stocks' => true,
    'prices' => true,
    'promos' => true,
  ];

  protected array $tables = [
    'main_settings' => 'yandex_main_settings',
    'price_settings' => 'yandex_price_settings',
    'stocks_settings' => 'yandex_stocks_settings',
    'campaigns_list' => 'yandex_campaigns_list',
    'campaigns_match_list' => 'yandex_campaigns_match_list',
    'promos_list' => 'yandex_promos_list',
    'promos_settings' => 'yandex_promos_settings',
    'promos_detail_log' => 'yandex_promos_detail_log',
    'agents' => 'yandex_agents',
    'panel_orders' => 'yandex_orders',
    'panel_order_products' => 'yandex_order_products',
    'order_status_map' => 'yandex_order_status_map',
  ];

  protected array $apiMethods = [
    'getOfferCardsContentStatus' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/offer-cards',
    'getOfferMappings' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/offer-mappings',
    'getCampaigns' => 'https://api.partner.market.yandex.ru/v2/campaigns',
    'getStocks' => 'https://api.partner.market.yandex.ru/v2/campaigns/%s/offers/stocks',
    'updateStocks' => 'https://api.partner.market.yandex.ru/v2/campaigns/%s/offers/stocks',
    'getPromos' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/promos',
    'getPromoOffers' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/promos/offers',
    'deletePromoOffers' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/promos/offers/delete',
    'updatePromoOffers' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/promos/offers/update',
    'updatePrices' => 'https://api.partner.market.yandex.ru/v2/campaigns/%s/offer-prices/updates',
    'updateBusinessPrices' => 'https://api.partner.market.yandex.ru/v2/businesses/%s/offer-prices/updates',
    'getBusinessOrders' => 'https://api.partner.market.yandex.ru/v1/businesses/%s/orders',
    'generateGoodsPricesReport' => 'https://api.partner.market.yandex.ru/v2/reports/goods-prices/generate',
    'generateUnitedOrdersReport' => 'https://api.partner.market.yandex.ru/v2/reports/united-orders/generate',
    'getReportInfo' => 'https://api.partner.market.yandex.ru/v2/reports/info/%s',
  ];

  protected array $availabilityCodes = [
    'RU' => 512,
    'BY' => 493
  ];

  protected array $prioritySuppliers = [144];
  protected int $defaultPrioritySupplier = 0; // Unexisted supplierId to prevent mysql error
  protected array $stockSuppliersList = [47, 103, 129, 141, 144];
  protected string $expressWarehouseName = "Express 7D";

  protected array $httpCodes = [
    'success' => 200,
    'rateLimit' => 429,
    'internalError' => 500,
    'notFound' => 404,
  ];

  protected array $repositories = [
    'items' => 'ItemsRepository',
    'settings' => 'SettingsRepository',
    'prices' => 'PricesRepository',
  ];

  protected array $pageFetchers = [
    'stocks' => 'StocksPageFetcher',
    'promos' => 'PromosPageFetcher',
    'prices' => 'PricesPageFetcher',
    'orders' => 'OrdersPageFetcher',
  ];

  private array $configs = [
    'stocks' => 'StocksConfigProvider',
    'prices' => 'PricesConfigProvider',
    'orders' => 'OrdersConfigProvider',
    'products' => 'ProductsConfigProvider',
    'promos' => 'PromosConfigProvider',
    'adverts' => 'AdvertsConfigProvider',
    'orders' => 'OrdersConfigProvider',
    'analytics' => 'AnalyticsConfigProvider',
    'ui' => 'UIConfigProvider',
  ];

  private array $parents = [
    'fetcher' => 'PageFetcherBase',
    'config' => 'ConfigProviderBase',
    'repository' => 'RepositoryBase',
  ];

  private array $interfaces = [
    'fetcher' => 'PageFetcherInterface',
    'config' => 'ConfigProviderInterface',
    'repository' => 'RepositoryInterface',
  ];


  public function getAllCabinets():array
  {
    return $this->cabinets;
  }

  public function getTableName( string $key ):string
  {
    if ( !isset($this->tables[$key]) ) throw new UnknownConfiguraionKeyException("No value for key {$key} in tables list");
    return $this->tables[$key];
  }

  public function getApiMethod( string $key ):string
  {
    if ( !isset($this->apiMethods[$key]) ) throw new UnknownConfiguraionKeyException("No value for key {$key} in tables list");
    return $this->apiMethods[$key];
  }

  public function getRepositoryClass( string $key ):string|bool
  {
    return $this->repositories[$key] ?? false;
  }

  public function getConfigClass( string $key ):string|bool
  {
    return $this->configs[$key] ?? false;
  }

  public function getPageFetcherClass( string $key ):string|bool
  {
    return $this->pageFetchers[$key] ?? false;
  }

  public function getAvailabilityCode( string $key ):int
  {
    return $this->availabilityCodes[$key] ?? false;
  }

  public function getSuccessHttpCode():int
  {
    return $this->httpCodes['success'];
  }

  public function getRateLimitHttpCode():int
  {
    return $this->httpCodes['rateLimit'];
  }

  public function getStockSuppliersList():array
  {
    return $this->stockSuppliersList;
  }

  public function getExpressWarehouseName():string
  {
    return $this->expressWarehouseName;
  }

  public function getParentClassName( string $key ):string
  {
    return $this->parents[$key];
  }

  public function getRequiredInterfaceName( string $key ):string
  {
    return $this->interfaces[ $key ];
  }
}
?>
