<?php
class ConfigProvider
{
  private static array $metaDict = [
    'organization' => 'organization',
    'store' => 'store',
    'sourceStore' => 'store',
    'targetStore' => 'store',
    'agent' => 'counterparty',
    'state' => 'state',
    'group' => 'group',
  ];

  private static array $organization = [
    'msk' => [
      'Новатайм' => '60850714-ad71-11ef-0a80-163f007c4295',
      'Хронос' => '96a1b40f-652b-11ef-0a80-108b0010b66a',
    ],
    's1' => [
      'ИП Сподырева' => '8a4f1ca9-30d3-11f0-0a80-1198001374c8',
      'Вотчес-ритейл' => '27af8b5c-58d1-11ec-0a80-08e7000a6716',
    ],
    's2' => [
      'Вотч-трейд' => '6812f6e0-aa06-11ee-0a80-138b002c126e',
    ],
  ];

  private static array $counterparty = [
    'msk' => [
      'wr' => [],
    ],
    's1' => [
      'Вотч-трейд' => '0355a50a-f25a-11ee-0a80-029b00090b92',
    ],
    's2' => [
      'Вотчес-ритейл' => 'd9f84aa8-9a7e-11ef-0a80-0fc20065d55e',
    ],
  ];

  private static array $store = [
    'msk' => [],
    's1' => [
      'Основной' => '79ed7d71-0aa6-11ea-0a80-004200039aa4',
      'Новокузнецкая' => 'af3fe058-f5f6-11f0-0a80-045f000e671a',
    ],
    's2' => [
      'Минск' => '6f6d2169-180c-11ea-0a80-00b30004eaef',
      'Немига' => '276d0399-1e4d-11f1-0a80-07f50001606e',
    ],
  ];

  private static array $currency = [
    's1' => 'RUB',
    's1' => 'RUB',
    's2' => 'BYN',
  ];

  private static $parameters = [
    'onlyValue' => ['description', 'applicable'],
  ];


  public static function getMeta( string $key ):string
  {
    return self::$metaDict[$key];
  }

  public static function getMetaDicitonary():array
  {
    return self::$metaDict;
  }

  public static function getStore( string $cabinet, string $code ):string
  {
    return self::$store[ $cabinet ][ $code ];
  }

  public static function getOrganization( string $cabinet, string $code ):string
  {
    return self::$organization[ $cabinet ][ $code ];
  }

  public static function getCounterparty( string $cabinet, string $code ):string
  {
    return self::$counterparty[ $cabinet ][ $code ];
  }

  public static function getOnlyValueFields():array
  {
    return self::$parameters['onlyValue'];
  }

  public static function getCurrency( string $cabinet ):string
  {
    return self::$currency[ $cabinet ];
  }
}
 ?>
