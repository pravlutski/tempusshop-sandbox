<?php
class SalesConfigProvider
{
  private static array $config = [
    'data_manager' => [
      'IP' => [
        'price' => "PROPERTY_OZSB_PRICE", // Свойство с ценой из битры
        'ozon_id' => "PROPERTY_OZON_ID", // Свойство с идентификатором продукта
        'filter' => 'active_os' // Столбец активности типа цены в таблице ci_price
      ],
      'TI' => [
        'price' => "PROPERTY_OZTI_PRICE", // Свойство с ценой из битры
        'ozon_id' => "PROPERTY_OZON_ID_TI", // Свойство с идентификатором продукта
        'filter' => 'active_ozti' // Столбец активности типа цены в таблице ci_price
      ],
      'WT' => [
        'price' => "PROPERTY_OZSB_PRICE", // Свойство с ценой из битры
        'ozon_id' => "PROPERTY_OZON_ID", // Свойство с идентификатором продукта
        'filter' => 'active_os' // Столбец активности типа цены в таблице ci_price
      ],
    ],
    'candidateFlag' => [
      'active' => false,
      'candidates' => true
    ],
    'deactivateFlag' => [
      'good' => false,
      'bad' => true,
    ],
    'manageMethods' => [
      'good' => '/v1/actions/products/activate',
      'bad' => '/v1/actions/products/deactivate',
    ],
    'allowedCabinets' => ['IP', 'TI', 'WT'],
    'moduleName' => [
      'IP' => 'importSales_IP',
      'TI' => 'importSales_TI',
      'WT' => 'importSales_WT',
    ],
    'logDict' => [
      'active' => 'Активные товары',
      'candidates' => 'Потенциальные',
    ],
  ];

  public static function getDataConfig( string $cabinet ):array
  {
    return self::$config['data_manager'][$cabinet] ?? '';
  }

  public static function getCandidateFlag( string $key ):bool
  {
    return self::$config['candidateFlag'][$key];
  }

  public static function getDeactivateFlag( string $key ):bool
  {
    return self::$config['deactivateFlag'][$key];
  }

  public static function getAllowedCabinets():array
  {
    return self::$config['allowedCabinets'] ?? [];
  }

  public static function getManageMethods( string $key ):string
  {
    return self::$config['manageMethods'][$key] ?? '';
  }

  public static function getModuleName( string $cabinet ):string
  {
    return self::$config['moduleName'][$cabinet] ?? '';
  }
  public static function getCategoryDict( string $key ):string
  {
    return self::$config['logDict'][$key] ?? '';
  }
}
 ?>
