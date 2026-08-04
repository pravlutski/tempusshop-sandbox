<?php
namespace Panel\Manager\Config;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Data\Cache;

class MarketConfigTable extends DataManager
{
    private static $cacheKey = 'market_config_all';
    private static $cacheTtl = 3600;
	
    public static function getTableName()
    {
        return 'ci_market_config';
    }

    public static function getMap()
    {
        return [
            new Fields\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Fields\IntegerField('SORT', [
                'default_value' => 500
            ]),
            new Fields\StringField('PRYCE_TYPE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 10),
                    ];
                }
            ]),
            new Fields\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            new Fields\StringField('SITE_ID', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 4),
                    ];
                }
            ]),
            new Fields\StringField('NAME', [
                'size' => 100,
                'default_value' => ''
            ]),
            new Fields\IntegerField('ROUND_PRICE', [
                'default_value' => 0
            ]),
            new Fields\StringField('CURRENCY', [
                'size' => 3,
                'default_value' => 'RUB'
            ]),
            new Fields\FloatField('RATE', [
                'default_value' => 1.0
            ]),
            new Fields\IntegerField('PRICE_TYPE_ID', [
                'nullable' => true
            ]),
            new Fields\StringField('PROPERTY_PRICE', [
                'size' => 100,
                'default_value' => '',
            ]),
            new Fields\StringField('URL', [
                'size' => 255,
                'default_value' => ''
            ]),
            new Fields\IntegerField('TRADING_PLATFORM_ID', [
                'nullable' => true
            ]),
            new Fields\StringField('COLUMN_PRICE', [
                'size' => 50,
                'default_value' => ''
            ]),
            new Fields\StringField('COLUMN_DISCOUNT_PRICE', [
                'size' => 50,
                'default_value' => ''
            ]),
            new Fields\StringField('COLUMN_ACTIVE', [
                'size' => 50,
                'default_value' => ''
            ]),
            new Fields\StringField('TBL_SEBES_FBO', [
                'size' => 100,
                'nullable' => true
            ]),
            new Fields\StringField('TBL_PRICE_FBO', [
                'size' => 100,
                'nullable' => true
            ]),
            new Fields\StringField('OPTION_UPDATE', [
                'size' => 50,
                'default_value' => ''
            ]),
            new Fields\StringField('OPTION_STATUS_PARSER', [
                'size' => 50,
                'default_value' => ''
            ]),
        ];
    }

    /**
     * все конфиги
     */
    public static function getAll()
    {
        $cache = Cache::createInstance();
        
        if ($cache->initCache(self::$cacheTtl, self::$cacheKey . '_all', 'market_config')) {
            $configs = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $configs = [];
            $result = self::getList([
                'order' => ['SORT' => 'ASC', 'PRYCE_TYPE' => 'ASC']
            ]);
            
            while ($row = $result->fetch()) {
                $configs[$row['PRYCE_TYPE']] = self::formatConfig($row);
            }
            
            $cache->endDataCache($configs);
        }
        
        return $configs;
    }
	
    public static function getByCode($code)
    {
        $all = self::getAll();
        return $all[$code] ?? null;
    }
	
    private static function formatConfig($row)
    {
        return [
            'id' => (int)$row['ID'],
            'code' => $row['PRYCE_TYPE'],
            'active' => $row['ACTIVE'] === 'Y',
            'name' => $row['NAME'],
            'round' => (int)$row['ROUND_PRICE'],
            'currency' => $row['CURRENCY'],
            'rate' => (float)$row['RATE'],
            'price_type_id' => $row['PRICE_TYPE_ID'] ? (int)$row['PRICE_TYPE_ID'] : null,
            'site_id' => $row['SITE_ID'],
            'url' => $row['URL'],
            'tradingId' => $row['TRADING_PLATFORM_ID'] ? (int)$row['TRADING_PLATFORM_ID'] : null,
            'column_price' => $row['COLUMN_PRICE'],
            'column_discount_price' => $row['COLUMN_DISCOUNT_PRICE'],
            'column_active' => $row['COLUMN_ACTIVE'],
            'tbl_sebes_fbo' => $row['TBL_SEBES_FBO'],
            'tbl_price_fbo' => $row['TBL_PRICE_FBO'],
            'option_update' => $row['OPTION_UPDATE'],
            'option_status_parser' => $row['OPTION_STATUS_PARSER'],
            'sort' => (int)$row['SORT'],
			
            'price_key' => $row['COLUMN_PRICE'],// временно
            'discount_price_key' => $row['COLUMN_DISCOUNT_PRICE'],// временно
        ];
    }
	
    public static function clearCache()
    {
        $cache = Cache::createInstance();
        $cache->clean(self::$cacheKey . '_all', 'market_config');
    }
}