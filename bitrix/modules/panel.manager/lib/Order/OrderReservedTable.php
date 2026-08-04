<?php
namespace Panel\Manager\Order;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;
use Panel\Manager\Config\MarketConfig;

class OrderReservedTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'ci_order_reserved';
    }

    public static function getConnection()
    {
        return Application::getConnection();
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('PRODUCT_ID', [
                'primary' => true,
                'required' => true,
                'title' => 'ID товара'
            ]),
            
            new Entity\StringField('SITE_ID', [
                'primary' => true,
                'size' => 3,
                'required' => true,
                'title' => 'ID сайта'
            ]),
            
            new Entity\StringField('PRICE_TYPE', [
                'primary' => true,
                'size' => 10,
                'required' => true,
                'title' => 'Тип цены'
            ]),

            new Entity\IntegerField('TRADING_PLATFORM_ID', [
                'primary' => true,
                'required' => true,
                'title' => 'ID торговой платформы'
            ]),
            
            new Entity\IntegerField('SUPPLIER_ID', [
                'required' => true,
                'title' => 'ID поставщика'
            ]),
            
            new Entity\StringField('ARTICLE', [
                'required' => true,
                'size' => 255,
                'title' => 'Артикул'
            ]),
            
            new Entity\IntegerField('ORDER_ID', [
                'default_value' => 0,
                'title' => 'Bitrix orderId'
            ]),

            new Entity\StringField('ORDER_BASKET_ID', [
                'size' => 255,
                'default_value' => '',
                'title' => 'Bitrix basketId и номер'
            ]),
            
            new Entity\IntegerField('TOP_ID', [
                'default_value' => 0,
                'title' => 'topId'
            ]),
			
            new Entity\IntegerField('AVAILABLE', [
                'required' => true,
                'default_value' => 0,
                'title' => 'Доступное количество'
            ]),
            
            new Entity\IntegerField('RESERVED', [
                'required' => true,
                'default_value' => 0,
                'title' => 'Зарезервированное количество'
            ]),
            
            new Entity\DatetimeField('TIMESTAMP', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Время обновления'
            ]),
        ];
    }

    public static function massReplace($items)
    {
        if (empty($items)) {
            return 0;
        }

        $connection = self::getConnection();
        $tableName = self::getTableName();
        
        $values = [];
        foreach ($items as $item) {
            $productId = (int)$item['PRODUCT_ID'];
            $siteId = $connection->getSqlHelper()->forSql($item['SITE_ID']);
            $priceType = $connection->getSqlHelper()->forSql($item['PRICE_TYPE']);
            $platformId = (int)$item['TRADING_PLATFORM_ID'];
            $supplierId = (int)$item['SUPPLIER_ID'];
            $article = $connection->getSqlHelper()->forSql($item['ARTICLE']);
            $available = (int)$item['AVAILABLE'];
            $reserved = (int)$item['RESERVED'];
            
            $values[] = "({$productId}, '{$article}', '{$siteId}', '{$priceType}', {$platformId}, {$supplierId}, {$available}, {$reserved})";
        }
        
        $sql = "REPLACE INTO {$tableName} 
                (PRODUCT_ID, ARTICLE, SITE_ID, PRICE_TYPE, TRADING_PLATFORM_ID, SUPPLIER_ID, AVAILABLE, RESERVED) 
                VALUES " . implode(',', $values);
        
        $result = $connection->query($sql);
        return $connection->getAffectedRowsCount();
    }
	
	public static function massInsert($items)
	{
		if (empty($items)) {
			return 0;
		}

		$connection = self::getConnection();
		$tableName = self::getTableName();
		
		$values = [];
		foreach ($items as $item) {
			$productId = (int)$item['PRODUCT_ID'];
			$article = $connection->getSqlHelper()->forSql($item['ARTICLE']);
			$siteId = $connection->getSqlHelper()->forSql($item['SITE_ID']);
			$priceType = $connection->getSqlHelper()->forSql($item['PRICE_TYPE']);
			$platformId = (int)$item['TRADING_PLATFORM_ID'];
			$orderId = (int)($item['ORDER_ID'] ?? 0);
			$orderBasketId = $connection->getSqlHelper()->forSql($item['ORDER_BASKET_ID']);
			$topId = (int)($item['TOP_ID'] ?? 0);
			$supplierId = (int)$item['SUPPLIER_ID'];
			$available = (int)($item['AVAILABLE'] ?? 1);
			$reserved = (int)($item['RESERVED'] ?? 1);
			
			$values[] = "({$productId}, '{$article}', '{$siteId}', '{$priceType}', {$platformId}, {$orderId}, {$orderBasketId}, {$topId}, {$supplierId}, {$available}, {$reserved}, NOW())";
		}

		$sql = "INSERT INTO {$tableName} 
				(PRODUCT_ID, ARTICLE, SITE_ID, PRICE_TYPE, TRADING_PLATFORM_ID, ORDER_ID, ORDER_BASKET_ID, TOP_ID, SUPPLIER_ID, AVAILABLE, RESERVED, TIMESTAMP) 
				VALUES " . implode(',', $values);

		$connection->query($sql);
		return $connection->getAffectedRowsCount();
	}

    public static function truncateReserved()
    {
        $connection = self::getConnection();
        $connection->query("TRUNCATE TABLE " . self::getTableName());
    }
	
    public static function getAllHashData()
    {
        $result = [];
        $res = self::getList([
            'select' => ['PRODUCT_ID', 'SITE_ID', 'PRICE_TYPE', 'TRADING_PLATFORM_ID', 'SUPPLIER_ID', 'AVAILABLE', 'RESERVED']
        ]);
        
        while ($row = $res->fetch()) {
            $key = $row['PRODUCT_ID'] . '|' . $row['SITE_ID'] . '|' . $row['PRICE_TYPE'] . '|' . $row['TRADING_PLATFORM_ID'] . '|' . $row['SUPPLIER_ID'];
            $result[$key] = md5($row['AVAILABLE'] . '|' . $row['RESERVED']);
        }
        
        return $result;
    }
}