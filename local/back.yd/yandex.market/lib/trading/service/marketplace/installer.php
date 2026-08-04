<?php

namespace Yandex\Market\Trading\Service\Marketplace;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @property Provider $provider
*/
class Installer extends TradingService\Common\Installer
{
	use Market\Reference\Concerns\HasLang;

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public function install(TradingEntity\Reference\Environment $environment, $siteId, array $context = [])
	{
		parent::install($environment, $siteId, $context);
		$this->installListener($environment);
		$this->installAdminExtension($environment);
		$this->installShipmentMenu();
	}

	public function tweak(TradingEntity\Reference\Environment $environment, $siteId, array $context = [])
	{
		static::clearCache();
		$this->applyPushAgents($this->getPushAgents(false, $context), $context);
		$this->linkBusiness($context);
		$this->linkStoreGroup($context);
		$this->installSettingsSync($context);
		$this->installSyncAgent($context);
		$this->installReturnAgent($context);
	}

	public function uninstall(TradingEntity\Reference\Environment $environment, $siteId, array $context = [])
	{
		$exportStatuses = $this->getPushAgents(true);
		$exportStatuses = array_fill_keys(array_keys($exportStatuses), false);

		parent::uninstall($environment, $siteId, $context);
		$this->unlinkBusiness($context);
		$this->unlinkStoreGroup($context);
		$this->uninstallListener($environment, $context);
		$this->uninstallAdminExtension($environment, $context);
		$this->uninstallShipmentMenu($context);
		$this->uninstallSyncAgent($context);
		$this->uninstallReturnAgent($context);
		$this->uninstallSettingsSync($context);
		$this->applyPushAgents($exportStatuses, $context);
	}

	protected function installListener(TradingEntity\Reference\Environment $environment)
	{
		$environment->getListener()->bind();
	}

	protected function uninstallListener(TradingEntity\Reference\Environment $environment, array $context)
	{
		if (!$context['SERVICE_USED'])
		{
			$environment->getListener()->unbind();
		}
	}

	protected function installAdminExtension(TradingEntity\Reference\Environment $environment)
	{
		$environment->getAdminExtension()->install();
	}

	protected function uninstallAdminExtension(TradingEntity\Reference\Environment $environment, array $context)
	{
		if (!$context['SERVICE_USED'])
		{
			$environment->getAdminExtension()->uninstall();
		}
	}

	protected function installShipmentMenu()
	{
		if (!$this->isShipmentMenuSupported()) { return; }

		Market\Config::setOption('menu_logistic', 'Y');
	}

	protected function uninstallShipmentMenu(array $context)
	{
		if (!empty($context['BEHAVIOR_USED']) || !$this->isShipmentMenuSupported()) { return; }

		Market\Config::removeOption('menu_logistic');
	}

	protected function isShipmentMenuSupported()
	{
		return $this->provider->getRouter()->hasDataAction('admin/shipments');
	}

	protected function installSyncAgent(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$setupId = $context['SETUP_ID'];
		$parameters = [
			'method' => 'start',
			'arguments' => [ $setupId ],
			'update' => Market\Reference\Agent\Controller::UPDATE_RULE_STRICT,
		];

		if ($this->provider->getOptions()->getYandexMode() === Options::YANDEX_MODE_PUSH)
		{
			$nextExec = $this->getSyncAgentNextExec();

			$parameters['next_exec'] = ConvertTimeStamp($nextExec->getTimestamp(), 'FULL');
		}
		else
		{
			$interval = (int)Market\Config::getOption('trading_pull_period', 600);

			$nextExec = new Main\Type\DateTime();
			$nextExec->add(sprintf('PT%sS', $interval));

			$parameters['interval'] = $interval;
			$parameters['next_exec'] = ConvertTimeStamp($nextExec->getTimestamp(), 'FULL');;
		}

		Market\Trading\State\OrderStatusSync::register($parameters);
	}

	protected function getSyncAgentNextExec()
	{
		$result = new Main\Type\DateTime();
		$result->setTime(mt_rand(0, 10), mt_rand(0, 59));

		if ($result->getTimestamp() <= time())
		{
			$result->add('P1D');
		}

		return $result;
	}

	protected function uninstallSyncAgent(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$setupId = $context['SETUP_ID'];

		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'start',
			'arguments' => [ (int)$setupId ], // fix
		]);
		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'start',
			'arguments' => [ $setupId ],
		]);
		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'sync',
			'arguments' => [ $setupId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function installReturnAgent(array $context)
	{
		if (!$this->provider->getOptions()->useTrackReturn())
		{
			$this->uninstallReturnAgent($context);
			return;
		}

		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		if (!$this->hasTrackOrderReturn($context['SETUP_ID']))
		{
			return;
		}

		Market\Trading\State\OrderReturnPickup::register([
			'method' => 'start',
			'arguments' => [ (int)$context['SETUP_ID'] ],
		]);
	}

	protected function hasTrackOrderReturn($setupId)
	{
		return (bool)Market\Trading\State\Internals\OrderReturnTable::getRow([
			'filter' => [
				'=SETUP_ID' => $setupId,
				'=STATUS' => Market\Trading\State\Internals\OrderReturnTable::STATUS_PROCESS,
			],
		]);
	}

	protected function uninstallReturnAgent(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$setupId = (int)$context['SETUP_ID'];

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'start',
			'arguments' => [ $setupId ],
		]);

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'sync',
			'arguments' => [ $setupId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function installSettingsSync(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		if ($this->provider->getOptions()->getValue('BUSINESS_ID') === null) { return; }

		$nextExec = $this->getSyncAgentNextExec();

		Market\Trading\State\SettingsSync::register([
			'method' => 'process',
			'arguments' => [ (int)$context['SETUP_ID'] ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
			'next_exec' => ConvertTimeStamp($nextExec->getTimestamp(), 'FULL'),
		]);
	}

	protected function uninstallSettingsSync(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		Market\Trading\State\SettingsSync::unregister([
			'method' => 'process',
			'arguments' => [ (int)$context['SETUP_ID'] ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function getPushAgents($onlyList = false, array $context = [])
	{
		$options = $this->provider->getOptions();

		return [
			'push/stocks' => !$onlyList && $options->usePushStocks() && !$this->groupPushStocksUsed($options, $context),
			'push/prices' => !$onlyList && $options->usePushPrices(),
		];
	}

	protected function groupPushStocksUsed(Options $options, array $context = [])
	{
		$result = false;

		foreach ($options->getStoreGroup() as $setupId)
		{
			if ((int)$setupId === (int)$context['SETUP_ID']) { continue; }

			$isRegistered = Market\Trading\State\PushAgent::isRegistered([
				'method' => 'change',
				'arguments' => [ (string)$setupId, 'push/stocks' ],
			]);

			if ($isRegistered)
			{
				$result = true;
				break;
			}
		}

		return $result;
	}

	protected function hasPushRefreshAgent($path)
	{
		return true;
	}

	protected function applyPushAgents(array $statuses, array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$setupId = (string)$context['SETUP_ID'];

		foreach ($statuses as $path => $status)
		{
			if ($status)
			{
				if ($this->hasPushRefreshAgent($path))
				{
					$refreshDelay = Market\Trading\State\PushAgent::getRefreshPeriod();
					$refreshNext = $this->getPushAgentNextExec($refreshDelay);

					Market\Trading\State\PushAgent::register([
						'method' => 'refresh',
						'arguments' => [ $setupId, $path ],
						'next_exec' => $refreshNext,
						'interval' => $refreshDelay,
					]);
				}

				$changeDelay = Market\Trading\State\PushAgent::getChangePeriod();
				$changeNext = $this->getPushAgentNextExec($changeDelay);

				Market\Trading\State\PushAgent::register([
					'method' => 'change',
					'arguments' => [ $setupId, $path ],
					'next_exec' => $changeNext,
					'interval' => $changeDelay,
				]);
			}
			else
			{
				Market\Trading\State\PushAgent::unregister([
					'method' => 'refresh',
					'arguments' => [ $setupId, $path ],
				]);

				Market\Trading\State\PushAgent::unregister([
					'method' => 'change',
					'arguments' => [ $setupId, $path ],
				]);

				Market\Trading\State\PushAgent::unregister([
					'method' => 'process',
					'arguments' => [ $setupId, $path ],
					'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
				]);
			}
		}
	}

	protected function getPushAgentNextExec($delay = 60)
	{
		$result = new Main\Type\DateTime();
		$result->add(sprintf('PT%sS', $delay));

		return $result;
	}

	/** @deprecated */
	protected function getExportAgentNextExec()
	{
		$defaults = Market\Trading\State\PushAgent::getDefaultParams();
		$interval = isset($defaults['interval']) ? (int)$defaults['interval'] : 60;

		return $this->getPushAgentNextExec($interval);
	}

	protected function linkBusiness(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$command = $this->provider->getContainer()->get(TradingService\Marketplace\Command\LinkBusiness::class, [
			'setupId' => $context['SETUP_ID'],
			'businessId' => $this->provider->getOptions()->getValue('BUSINESS_ID'),
		]);

		$command->install();
	}

	protected function unlinkBusiness(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$command = $this->provider->getContainer()->get(TradingService\Marketplace\Command\LinkBusiness::class, [
			'setupId' => $context['SETUP_ID'],
			'businessId' => $this->provider->getOptions()->getValue('BUSINESS_ID'),
		]);

		$command->uninstall();
	}

	protected function linkStoreGroup(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$command = $this->provider->getContainer()->get(TradingService\Marketplace\Command\GroupStoresTweak::class, [
			'setupId' => $context['SETUP_ID'],
			'previous' => $this->provider->getOptions()->getValue('STORE_DATA'),
		]);

		$command->install();
	}

	protected function unlinkStoreGroup(array $context)
	{
		Market\Reference\Assert::notNull($context['SETUP_ID'], 'context["SETUP_ID"]');

		$command = $this->provider->getContainer()->get(TradingService\Marketplace\Command\GroupStoresTweak::class, [
			'setupId' => $context['SETUP_ID'],
		]);

		$command->uninstall();
	}
}