<?php
class ConfigProvider
{
  private static array $allowedConstructValues = [
    'platforms' => ['OZON', 'WB'],
    'cabinets' => [
      'OZON' => ['IP', 'TI', 'WT'],
      'WB' => ['IP', 'WR', 'WT'],
    ],
  ];

  private static array $config = [
    'OZON' => [
      'common' => [
        'settings_table' => 'ozon_dp_settings',
        'default_settings_table' => 'ozon_dp_defaults',
        'coefficients_table' => 'ozon_dp_coefficients',
        'final_price_table' => 'ozon_dp_prices',
        'orders_table' => 'wdhs_ozon_orders',
        'order_products_table' => 'wdhs_ozon_order_products',
        'history_table' => 'ozon_dp_history',
        'order_provider' => [
          'path' => "/../classes/order/OzonOrderProvider.php",
          'class' => "OzonOrderProvider",
        ],
        'seller_discount' => 0,
        'price_difference_threshold' => 0.03,
      ],
      'IP' => [
        'check_fbo_flag' => true,
        'module_settings' => 'ozon_main_settings_IP',
        'fbo_cost_table' => 'ozon_fbo_sebes_IP',
        'fbo_price_table' => 'ozon_fbo_price_IP',
        'price_property' => 'PROPERTY_OZSB_PRICE',
        'price_filter' => 'active_os',
        'fbo_select_fields' => [
          'cost' => [
            'model' => 'model',
            'cost' => 'sebes',
          ],
          'price' => [
            'model' => 'article',
            'price' => 'price',
          ]
        ],
      ],
    ],
    'WB' => [
      'common' => [
        'settings_table' => 'wb_dp_settings',
        'default_settings_table' => 'wb_dp_defaults',
        'coefficients_table' => 'wb_dp_coefficients',
        'final_price_table' => 'wb_dp_prices',
        'orders_table' => 'wdhs_wb_orders',
        'order_products_table' => 'wdhs_wb_order_products',
        'history_table' => 'wb_dp_history',
        'order_provider' => [
          'path' => "/../classes/order/WBOrderProvider.php",
          'class' => "WBOrderProvider",
        ],
        'seller_discount' => 0.42,
        'price_difference_threshold' => 0.03,
      ],
      'WR' => [
        'check_fbo_flag' => false,
        'module_settings' => 'wb_main_settings_WR',
        'fbo_cost_table' => 'wb_fbo_cost_WR',
        'fbo_price_table' => 'wb_fbo_price_WR',
        'price_property' => 'PROPERTY_WBPRICE',
        'price_filter' => 'active_wb',
        'fbo_select_fields' => [
          'cost' => [
            'model' => 'article',
            'cost' => 'cost',
          ],
          'price' => [
            'model' => 'article',
            'price' => 'price',
          ]
        ],
      ],
    ],
  ];

  private static $logPathTemplate = "/var/www/bitrix_logs/dynamicPriceSettings/%s/%s.log";

  private static string $cabinet;
  private static string $marketplace;

  public static function init( string $marketplace, string $cabinet )
  {
    self::$cabinet = $cabinet;
    self::$marketplace = $marketplace;
  }

  public static function getCabinet():string
  {
    return self::$cabinet;
  }

  public static function getMarketplace():string
  {
    return self::$marketplace;
  }

  public static function getAllowedPlatforms():array
  {
    return self::$allowedConstructValues['platforms'];
  }

  public static function getAllowedCabinets( string $key ):array
  {
    return self::$allowedConstructValues['cabinets'][$key] ?? [];
  }

  public static function getSettingsTable():string
  {
    return self::$config[self::$marketplace]['common']['settings_table'] ?? '';
  }

  public static function getDefaultSettingsTable():string
  {
    return self::$config[self::$marketplace]['common']['default_settings_table'] ?? '';
  }

  public static function getCoefficientsTable():string
  {
    return self::$config[self::$marketplace]['common']['coefficients_table'] ?? '';
  }

  public static function getFinalPriceTable():string
  {
    return self::$config[self::$marketplace]['common']['final_price_table'] ?? '';
  }

  public static function getOrdersTable():string
  {
    return self::$config[self::$marketplace]['common']['orders_table'] ?? '';
  }

  public static function getOrderProductsTable():string
  {
    return self::$config[self::$marketplace]['common']['order_products_table'] ?? '';
  }

  public static function getHistoryDataTable():string
  {
    return self::$config[self::$marketplace]['common']['history_table'] ?? '';
  }

  public static function getFboCostTable():string
  {
    return self::$config[self::$marketplace][self::$cabinet]['fbo_cost_table'] ?? '';
  }

  public static function getFboPriceTable():string
  {
    return self::$config[self::$marketplace][self::$cabinet]['fbo_price_table'] ?? '';
  }

  public static function getPricePropertyName():string
  {
    return self::$config[self::$marketplace][self::$cabinet]['price_property'] ?? '';
  }

  public static function getPriceFilterName():string
  {
    return self::$config[self::$marketplace][self::$cabinet]['price_filter'] ?? '';
  }

  public static function getPlatformSettingsTable():string
  {
    return self::$config[self::$marketplace][self::$cabinet]['module_settings'] ?? '';
  }

  public static function getFboSelectField( string $category, string $field ):string
  {
    return self::$config[self::$marketplace][self::$cabinet]['fbo_select_fields'][$category][$field] ?? '';
  }

  public static function getLogPathTemplate():string
  {
    return self::$logPathTemplate ?? '';
  }

  public static function getOrderProviderPath():string
  {
    return self::$config[self::$marketplace]['common']['order_provider']['path'] ?? '';
  }

  public static function getOrderProviderName():string
  {
    return self::$config[self::$marketplace]['common']['order_provider']['class'] ?? '';
  }

  public static function getCheckFboFlag():bool
  {
    return self::$config[self::$marketplace][self::$cabinet]['check_fbo_flag'] ?? false;
  }

  public static function getSellerDiscount():float
  {
    return self::$config[self::$marketplace]['common']['seller_discount'];
  }

  public static function getPriceDifferenceThreshold():float
  {
    return self::$config[self::$marketplace]['common']['price_difference_threshold'];
  }
}

 ?>
