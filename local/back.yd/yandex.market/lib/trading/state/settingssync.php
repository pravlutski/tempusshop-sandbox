<?php
namespace Yandex\Market\Trading\State;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class SettingsSync extends Internals\AgentSkeleton
{
	const PERIOD_STEP_DEFAULT = 86400;

	public static function getDefaultParams()
	{
		return [
			'interval' => static::getPeriod('step', static::PERIOD_STEP_DEFAULT),
		];
	}

	public static function process($setupId, $errorCount = 0)
	{
		return static::wrapAction(
			[static::class, 'processBody'],
			[ $setupId ],
			$errorCount
		);
	}

	protected static function processBody($setupId)
	{
		try
		{
			$trading = Market\Trading\Setup\Model::loadById($setupId);

			if (!$trading->isActive()) { return false; }

			$service = $trading->wakeupService();

			if (!($service instanceof TradingService\Marketplace\Provider)) { return false; }

			static::syncBusiness($trading);
			static::syncStoreData($trading);

			return true;
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			return false;
		}
	}

	protected static function syncBusiness(Market\Trading\Setup\Model $trading)
	{
		/** @var Market\Trading\Service\Marketplace\Command\LinkBusiness $command */
		$service = $trading->wakeupService();
		$command = $service->getContainer()->get(Market\Trading\Service\Marketplace\Command\LinkBusiness::class, [
			'setupId' => $trading->getId(),
			'businessId' => $service->getOptions()->getValue('BUSINESS_ID'),
		]);
		$command->install();
	}

	protected static function syncStoreData(Market\Trading\Setup\Model $trading)
	{
		/** @var Market\Trading\Service\Marketplace\Command\LinkBusiness $command */
		$service = $trading->wakeupService();
		$command = $service->getContainer()->get(Market\Trading\Service\Marketplace\Command\GroupStoresTweak::class, [
			'setupId' => $trading->getId(),
			'previous' => $service->getOptions()->getValue('STORE_DATA'),
		]);
		$command->install();
	}

	protected static function getOptionPrefix()
	{
		return 'trading_settings_sync';
	}
}