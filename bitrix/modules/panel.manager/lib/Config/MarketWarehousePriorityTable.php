<?php
namespace Panel\Manager\Config;

use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

class MarketWarehousePriorityTable extends DataManager
{
    public static function getTableName()
    {
        return 'ci_market_warehouse_priority';
    }

    public static function getMap()
    {
        return [
            new Fields\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Fields\StringField('PRYCE_TYPE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 10),
                    ];
                }
            ]),
            new Fields\IntegerField('WAREHOUSE_ID', [
                'required' => true
            ]),
            new Fields\FloatField('PRIORITY', [
                'required' => true,
                'default_value' => 0,
				'scale' => 2
            ]),
            new Fields\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
        ];
    }
    
    public static function getPrioritiesByMarketCode($marketCode)
    {
        $result = self::getList([
            'filter' => [
                '=PRYCE_TYPE' => $marketCode,
                '=ACTIVE' => 'Y'
            ],
            'order' => ['PRIORITY' => 'ASC', 'WAREHOUSE_ID' => 'ASC']
        ]);
        
        $priorities = [];
        while ($row = $result->fetch()) {
            $priorities[$row['WAREHOUSE_ID']] = $row;
        }
        
        return $priorities;
    }

    public static function savePriorities($marketCode, array $priorities)
    {
        global $DB;
        
        $DB->StartTransaction();
        
        try {
            self::deleteByMarketCode($marketCode);
            
            // новые
            foreach ($priorities as $priority) {
                if (!empty($priority['WAREHOUSE_ID']) && isset($priority['PRIORITY'])) {
                    self::add([
                        'PRYCE_TYPE' => $marketCode,
                        'WAREHOUSE_ID' => (int)$priority['WAREHOUSE_ID'],
                        'PRIORITY' => (float)$priority['PRIORITY'],
                        'ACTIVE' => $priority['ACTIVE'] ?? 'Y'
                    ]);
                }
            }
            
            $DB->Commit();
            return true;
            
        } catch (\Exception $e) {
            $DB->Rollback();
            return false;
        }
    }

    public static function deleteByMarketCode($marketCode)
    {
        $result = self::getList([
            'filter' => ['=PRYCE_TYPE' => $marketCode],
            'select' => ['ID']
        ]);
        
        while ($row = $result->fetch()) {
            self::delete($row['ID']);
        }
    }
}