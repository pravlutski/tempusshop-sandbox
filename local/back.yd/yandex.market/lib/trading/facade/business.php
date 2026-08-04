<?php
namespace Yandex\Market\Trading\Facade;

use Bitrix\Main;
use Yandex\Market;

class Business
{
	public static function synchronize()
	{
		$changed = false;

		$tradingSetups = Market\Trading\Setup\Model::loadList([
			'filter' => [ '=ACTIVE' => true ],
		]);

		foreach ($tradingSetups as $tradingSetup)
		{
			$service = $tradingSetup->wakeupService();

			if (!($service instanceof Market\Trading\Service\Marketplace\Provider)) { continue; }
			if ($service->getOptions()->getValue('BUSINESS_ID') !== null) { continue; }

			try
			{
				/** @var Market\Trading\Service\Marketplace\Command\LinkBusiness $command */
				$command = $service->getContainer()->get(Market\Trading\Service\Marketplace\Command\LinkBusiness::class, [
					'setupId' => $tradingSetup->getId(),
					'businessId' => $service->getOptions()->getValue('BUSINESS_ID'),
				]);
				$command->install();
			}
			catch (Main\SystemException $exception)
			{
				$service->getLogger()->warning($exception);
			}

			$changed = true;
		}

		return $changed;
	}
}