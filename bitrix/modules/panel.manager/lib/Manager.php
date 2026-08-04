<?php
namespace Panel\Manager;

use Panel\Manager\Service\PriceUpdateService;
use Panel\Manager\Service\OrderReservedService;
use Panel\Manager\Service\CatalogPriceService;
use Panel\Manager\Market\MarketFactory;
use Panel\Manager\Config\MarketConfig;

class Manager
{
	private static array $priceUpdateService = [];

	public static function getPriceUpdateService(string $marketCode, string $mode = 'prod'): PriceUpdateService
	{
		return new PriceUpdateService($marketCode, $mode);
		/*if (!isset(self::$priceUpdateService[$marketCode][$mode])) {
			self::$priceUpdateService[$marketCode][$mode] = new PriceUpdateService($marketCode, $mode);
		}
		return self::$priceUpdateService[$marketCode][$mode];*/
	}
    
    public static function getMarket(string $marketCode): Market\AbstractMarket
    {
        return MarketFactory::create($marketCode);
    }
    
    //public static function updatePrices(string $marketCode): array
    //{
    //    return self::getPriceUpdateService()->updatePrices($marketCode);
    //}
	
    public static function updatePriceService(string $marketCode, string $mode = 'prod')
    {
        return self::getPriceUpdateService($marketCode, $mode);
    }

    public static function truncatePriceService()
    {
        self::$priceUpdateService = [];
    }
	
    public static function getTypePrices($fullList = false)
    {
        return MarketConfig::getTypePrices($fullList);
    }
	
    public static function getAllTradingSettings()
    {
        return MarketConfig::getAllTradingSettings();
    }
    public static function orderReserved()
    {
        return new OrderReservedService();
    }
	
    public static function getCatalogPriceService()
    {
        return new CatalogPriceService();
    }
	
}