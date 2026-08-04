<?php
class ConfigProvider
{
  private static array $config = [
    'common' => [
      'methods' => [
        'importProducts' => 'https://api-seller.ozon.ru/v3/product/import',
        'importStocks' => 'https://api-seller.ozon.ru/v2/products/stocks',
        'importPrices' => 'https://api-seller.ozon.ru/v1/product/import/prices',
        'getProductList' => 'https://api-seller.ozon.ru/v3/product/list',
        'getWarehouseList' => 'https://api-seller.ozon.ru/v1/warehouse/list',
        'getStockOnWarehouses' => 'https://api-seller.ozon.ru/v2/analytics/stock_on_warehouses',
        'getAnalyticsStock' => 'https://api-seller.ozon.ru/v1/analytics/stocks',
      ],
      'items' => [
        'defaultProperties' => [
          'ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_WBARTICLE', 'PROPERTY_BRAND',
        ],
        'defaultFilter' => [
          'IBLOCK_ID' => 16,
          'PROPERTY_OZON_ACTIVE_VALUE' => 'Да',
        ],
        'infoblock' => [
          'catalog' => 16,
          'brands' => 11
        ]
      ],
      'tables' => [
        'quarantine' => 'ci_quarantine',
        'reserves' => 'ci_reserved',
      ],
    ],
    'IP' => [
      'tables' => [
        'mainSettingsTable' => 'ozon_main_settings_IP',
        'fboCostTable' => 'ozon_fbo_sebes_IP',
        'fboPriceTable' => 'ozon_fbo_price_IP',
        'fboStockTable' => 'ozon_fbo_stock_IP',
        'skuDictionary' => 'ozon_sku_dict_IP',
      ],
      'properties' => [
        'price' => 'PROPERTY_OZSB_PRICE',
        'ozon_id' => 'PROPERTY_OZON_ID',
      ],
      'filterPriceTable' => 'active_os',
    ],
    'TI' => [],
    'WT' => []
  ];

  private static array $allowedCabinets = [
    'IP' => true,
    'TI' => false,
    'WT' => false,
  ];

  private static string $cabinet;

  public static function init( string $cabinet ):void
  {
    if ( !(self::$allowedCabinets[$cabinet] ?? false) ){
      throw new Exception("$cabinet is not supported or disabled");
    }

    self::$cabinet = $cabinet;
  }

  public static function getCabinetTableName( string $key ):string
  {
    return self::$config[self::$cabinet]['tables'][$key] ?? '';
  }

  public static function getCommonTableName( string $key ):string
  {
    return self::$config['common']['tables'][$key] ?? '';
  }

  public static function getApiMethod( string $code ):string
  {
    return self::$config['common']['methods'][$code] ?? '';
  }

  public static function getDefaultSelectProperties():array
  {
    return self::$config['common']['items']['defaultProperties'] ?? [];
  }

  public static function getDefaultPropertyFilter():array
  {
    return self::$config['common']['items']['defaultFilter'] ?? [];
  }

  public static function getCabinetSpecifiedProperties():array
  {
    return array_values(self::$config[self::$cabinet]['properties'] ?? []);
  }

  public static function getInfoblock( string $key ):int
  {
    return self::$config['common']['infoblock'][$key];
  }

}
 ?>
