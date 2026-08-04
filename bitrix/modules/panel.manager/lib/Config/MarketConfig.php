<?php
namespace Panel\Manager\Config;
use Panel\Manager\Config\MarketWarehousePriorityTable;

class MarketConfig
{
	private static $cache = [];
	
	private static array $configs = [
		'RU' => [
			'active' => true,
			'name' => 'tempus.ru',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => 5,
			'siteR' => 's1',
			'siteU' => 's1',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_ru',
			'discount_price_key' => 'price_discount_ru',
			'option_update' => 'UPDATE_PRICE_RU',
			'tradingId' => 4,
			'column_price' => 'price_discount_ru',
			'column_active' => 'active_ru',
			//'option_status_parser' => 'PARSER_s1',
		],
		'BY' => [
			'active' => true,
			'name' => 'tempus.by',
			'round' => 0,
			'currency' => 'BYN',
			'rate' => 1.0,
			'price_type_id' => 2,
			'siteR' => 's2',
			'siteU' => 's2',
			'url' => 'https://tempus.by',
			'price_key' => 'price_by',
			'discount_price_key' => 'price_discount_by',
			'option_update' => 'UPDATE_PRICE_BY',
			'tradingId' => 4,
			'column_price' => 'price_discount_by',
			'column_active' => 'active_by',
			'option_status_parser' => 'PARSER_s2',
		],
		'PL' => [
			'active' => false,
			'name' => '',
			'round' => 0,
			'currency' => 'PLN',
			'rate' => 1.0,
			'price_type_id' => 3,
			'siteR' => 's3',
			'siteU' => 's3',
			'url' => 'https://tempusshop.pl',
			'price_key' => 'price_pl',
			'discount_price_key' => 'price_discount_pl',
			'option_update' => 'UPDATE_PRICE_PL',
			'tradingId' => 4,
			'column_price' => 'price_discount_pl',
			'column_active' => 'active_pl',
		],
		'KZ' => [
			'active' => false,
			'name' => '',
			'round' => 0,
			'currency' => 'KZT',
			'rate' => 1.0,
			'price_type_id' => 6,
			'siteR' => 's4',
			'siteU' => 'kz',
			'url' => 'https://tempuswatch.kz',
			'price_key' => 'price_kz',
			'discount_price_key' => null,
			'option_update' => 'UPDATE_PRICE_KZ',
			'tradingId' => 4,
			'column_price' => false,
			'column_active' => false,
		],
		'YA' => [
			'active' => true,
			'name' => 'Яндекс маркет',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => 1,
			'siteR' => 'ya',
			'siteU' => 'v1',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_ya',
			'discount_price_key' => 'price_discount_ya',
			'option_update' => 'UPDATE_PRICE_YA',
			'tradingId' => 4,
			'column_price' => '',
			'column_active' => 'active_ya',
		],
		'OS' => [
			'active' => true,
			'name' => 'OZ',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'v2',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_os',
			'discount_price_key' => 'price_os',
			'option_update' => 'UPDATE_PRICE_OS',
			'tradingId' => 8,
			'column_price' => '',
			'column_active' => 'active_os',
			'tbl_sebes_fbo' => 'ozon_fbo_sebes_IP',
			'tbl_price_fbo' => 'ozon_fbo_price_IP',
		],
		/*'OZIP' => [
			'active' => true,
			'round' => -1,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'v2',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_os',
			'discount_price_key' => 'price_os',
			'option_update' => 'UPDATE_PRICE_OS',
			'tradingId' => 8,
			'column_price' => '',
			'column_active' => 'active_os',
		],*/
		'WB' => [
			'active' => true,
			'name' => 'WB',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'wb',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_wb',
			'discount_price_key' => 'price_wb',
			'option_update' => 'UPDATE_PRICE_WB',
			'tradingId' => 6,
			'column_price' => '',
			'column_active' => 'active_wb',
			'tbl_sebes_fbo' => 'wb_fbo_cost_WR',
			'tbl_price_fbo' => 'wb_fbo_price_WR',
		],
		'WBBY' => [
			'active' => false,
			'name' => 'WB Беларусь',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'wb',
			'url' => 'https://tempusshop.ru',
			//'price_key' => 'price_wbby',
			//'discount_price_key' => 'price_wbby',
			'option_update' => 'UPDATE_PRICE_WBBY',
			'tradingId' => false,
			'column_price' => '',
			'column_active' => 'active_wbby',
		],
		'WBTL' => [
			'active' => true,
			'name' => 'WBIP',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'wbtl',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_wbtl',
			'discount_price_key' => 'price_wbtl',
			'option_update' => 'UPDATE_PRICE_WBTL',
			'tradingId' => 4,
			'column_price' => '',
			'column_active' => 'active_wbtl',
			'tbl_sebes_fbo' => 'wb_fbo_cost_TL',
		],
		'AV' => [
			'active' => true,
			'name' => 'Авито',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'av',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_av',
			'discount_price_key' => 'price_av',
			'option_update' => 'UPDATE_PRICE_AV',
			'tradingId' => 9,
			'column_price' => '',
			'column_active' => 'active_av',
		],
		'SB' => [
			'active' => false,
			'name' => 'SB',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'sb',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_sb',
			'discount_price_key' => 'price_sb',
			'option_update' => 'UPDATE_PRICE_SB',
			'tradingId' => 7,
			'column_price' => '',
			'column_active' => 'active_sb',
		],
		'OZKZ' => [
			'active' => false,
			'name' => '',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's4',
			'siteU' => 'kz',
			'url' => 'https://tempuswatch.kz',
			'price_key' => 'price_ozkz',
			'discount_price_key' => 'price_ozkz',
			'option_update' => 'UPDATE_PRICE_OZKZ',
			'tradingId' => 4,
			'column_price' => '',
			'column_active' => '',
		],
		'OZTI' => [
			'active' => false,
			'name' => '',
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => null,
			'siteR' => 's1',
			'siteU' => 'OZTI',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_ozti',
			'discount_price_key' => 'price_ozti',
			'option_update' => 'UPDATE_PRICE_OZTI',
			'tradingId' => 4,
			'column_price' => '',
			'column_active' => 'active_ozti',
		],
		'OPT' => [
			'active' => true,
			'name' => '',
			'optional' => true,
			'round' => 0,
			'currency' => 'RUB',
			'rate' => 1.0,
			'price_type_id' => 5,
			'siteR' => 's1',
			'siteU' => 's1',
			'url' => 'https://tempusshop.ru',
			'price_key' => 'price_ru',
			'discount_price_key' => 'price_discount_ru',
			'option_update' => 'UPDATE_PRICE_RU',
			'tradingId' => 4,
			'column_price' => '',
			'column_active' => 'active_opt',
		],
	];

    private static array $tradingSourcesSettings = [
        4 => [
            'name' => 'YM',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'YA',
				's2' => false,
			],
        ],
        6 => [
            'name' => 'WB',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'WB',
				's2' => false,
			],
        ],
        8 => [
            'name' => 'OZON',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'OS',
				's2' => false,
			],
        ],
        9 => [
            'name' => 'avito',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'AV',
				's2' => false,
			],
        ],
        13 => [
            'name' => 'SITES',
            'warehouse_id' => false,
			'warehouse_sites' => [
				's1' => 47,
				's2' => 44,
				's1_nkz' => 128,
				's2_nemiga' => 149,
			],
            'type_prices' => [
				's1' => 'RU',
				's2' => 'BY',
			],
        ],
        15 => [
            'name' => 'ONLINER',
            'warehouse_id' => false,
			'warehouse_sites' => [
				's2' => 44,
				's2_nemiga' => 149,
			],
            'type_prices' => [
				's1' => false,
				's2' => 'BY',
			],
        ],
        16 => [
            'name' => 'OZON_FBO',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'OS',
				's2' => false,
			],
        ],
        17 => [
            'name' => 'WB_FBO',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'WB',
				's2' => false,
			],
        ],
        18 => [
            'name' => '21 VEK',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => 47,
			],
            'type_prices' => [
				's1' => 'BY',
				's2' => 'BY',
			],
        ],
        19 => [
            'name' => 'WBBY',
            'warehouse_id' => 44,
			'warehouse_sites' => [
				's1' => false,
				's2' => 44,
			],
            'type_prices' => [
				's1' => false,
				's2' => 'BY',
			],
        ],
        20 => [
            'name' => 'OZBY',
            'warehouse_id' => 44,
			'warehouse_sites' => [
				's1' => false,
				's2' => 44,
			],
            'type_prices' => [
				's1' => false,
				's2' => false,
			],
        ],
        21 => [
            'name' => 'WBIP',
            'warehouse_id' => 47,
			'warehouse_sites' => [
				's1' => 47,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'WBTL',
				's2' => false,
			],
        ],
        23 => [
            'name' => 'SITES_NKZ',
            'warehouse_id' => 128,
			'warehouse_sites' => [
				's1' => 128,
				's2' => false,
			],
            'type_prices' => [
				's1' => 'RU',
				's2' => false,
			],
        ],
        24 => [
            'name' => 'SITES_NEMIGA',
            'warehouse_id' => 149,
			'warehouse_sites' => [
				's1' => false,
				's2' => 149,
			],
            'type_prices' => [
				's1' => false,
				's2' => 'BY',
			],
        ],
    ];

    public static function getConfigOld(string $marketCode): array
    {
        $config = self::$configs[$marketCode] ?? [];

        if (empty($config)) {
            throw new \InvalidArgumentException("Неизвестный тип цены: {$marketCode}");
        }
        if (!$config['active']) {
            throw new \InvalidArgumentException("Тип цены не активен: {$marketCode}");
        }

        $config['rate'] = self::loadCurrencyRate($config['currency']);
        $config['default_rrc'] = self::loadDefaultRRC(strtolower($marketCode));

        if ($marketCode === 'WB' || $marketCode === 'WBTL') {
            $config['promo_per'] = (float)\CProSet::getOption("CATALOG_PROMO_{$marketCode}");
            $config['sale_per'] = (float)\CProSet::getOption("CATALOG_SALE_{$marketCode}");
        }

        return $config;
    }

    public static function getConfig(string $marketCode): array
    {
        if (isset(self::$cache[$marketCode])) {
            return self::$cache[$marketCode];
        }
        
        $config = MarketConfigTable::getByCode($marketCode);
        
        if (empty($config)) {
            throw new \InvalidArgumentException("Неизвестный тип рынка: {$marketCode}");
        }
        
        $config['rate'] = self::loadCurrencyRate($config['currency']);
        $config['default_rrc'] = self::loadDefaultRRC(strtolower($marketCode));
        
        $config['warehouse_priorities'] = self::getWarehousePriorities($marketCode);

        if ($marketCode === 'WB' || $marketCode === 'WBTL') {
            $config['promo_per'] = (float)\CProSet::getOption("CATALOG_PROMO_{$marketCode}");
            $config['sale_per'] = (float)\CProSet::getOption("CATALOG_SALE_{$marketCode}");
        }

        self::$cache[$marketCode] = $config;
        
        return $config;
    }
	
    private static function loadCurrencyRate(string $currency): float
    {
        try {
            $objCurrency = new \CPanelCurrency();
            $arCurrency = $objCurrency->getDetail($currency);

            if ($arCurrency && isset($arCurrency['rate'])) {
                return (float)$arCurrency['rate'];
            }

            return 1.0;

        } catch (\Exception $e) {
            return 1.0;
        }
    }

    private static function loadDefaultRRC(string $lowerMarketCode): array
    {
        try {
            $settingsRrc = json_decode(\CProSet::getOption("SETTINGS_RRC"), true);
            return $settingsRrc[$lowerMarketCode] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

	public static function getAllTradingSettings(): array
    {
        return self::$tradingSourcesSettings;
    }

	public static function getTypePrices($fullList = false): array
	{
		$configs = MarketConfigTable::getAll();
		$types = [];
		foreach ($configs as $typeId => $config) {
			if ($fullList === false && $config['code'] === 'OPT') {
				continue;
			}
			if ($config['active']) {
				$types[] = [
					'id' => $typeId,
					'name' => $config['name'] ? $config['name'] : $typeId,
					'column_active' => $config['column_active'],
					'option_update' => $config['option_update'],
				];
			}
		}
		return $types;
		prent($config);
		$types = [];
		foreach (self::$configs as $typeId => $config) {
			if ($fullList === false && $config['optional'] === true) {
				continue;
			}
			if ($config['active']) {
				$types[] = [
					'id' => $typeId,
					'name' => $config['name'] ? $config['name'] : $typeId,
					'column_active' => $config['column_active'],
					'option_update' => $config['option_update'],
				];
			}
		}
		return $types;
	}
	
    public static function getWarehousePriorities(string $marketCode): array
    {
        return MarketWarehousePriorityTable::getPrioritiesByMarketCode($marketCode);
    }
}
