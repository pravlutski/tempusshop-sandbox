<?php
namespace Yandex\Market\Trading\Setup;

class ModelPool
{
	protected static $pool = [];

	public static function getByTradingInfo(array $tradingInfo)
	{
		$cacheKey = static::tradingInfoCacheKey($tradingInfo);

		if (!isset(static::$pool[$cacheKey]))
		{
			static::$pool[$cacheKey] = Model::loadByTradingInfo($tradingInfo);
		}

		return static::$pool[$cacheKey];
	}

	protected static function tradingInfoCacheKey(array $tradingInfo)
	{
		if (!empty($tradingInfo['SETUP_ID']))
		{
			return 'setup:' . (int)$tradingInfo['SETUP_ID'];
		}

		return 'platform:' . (int)$tradingInfo['TRADING_PLATFORM_ID'] . ':' . $tradingInfo['SITE_ID'];
	}
}