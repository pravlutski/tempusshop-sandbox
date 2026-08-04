<?php

namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Bitrix\Main;
use Yandex\Market\Reference\Storage as ReferenceStorage;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings as TradingSettings;

class GroupStores
{
	/** @var TradingService\Marketplace\Provider */
	protected $provider;
	/** @var int[] */
	protected $linked;
	/** @var array */
	protected $settings;
	/** @var array */
	protected $preload = [
		'PRODUCT_FEED',
		'PRODUCT_STORE',
		'USE_ORDER_RESERVE',
		'STOCKS_BEHAVIOR',
	];

	public function __construct(TradingService\Marketplace\Provider $provider, array $linked = [])
	{
		$this->provider = $provider;
		$this->linked = array_diff($linked, [
			$this->provider->getOptions()->getSetupId(),
		]);
	}

	public function useOrderReserve()
	{
		$withReserve = $this->linkedWithReserve();

		return !empty($withReserve);
	}

	/** @noinspection PhpDeprecationInspection */
	public function linkedWithReserve()
	{
		$compatibleCheckboxes = $this->setting('USE_ORDER_RESERVE');
		$stocksBehaviors = $this->setting('STOCKS_BEHAVIOR');

		$enabled = $this->valuePositive($compatibleCheckboxes);
		$enabled += $this->valueFilter($stocksBehaviors, [
			TradingService\Marketplace\Options::STOCKS_WITH_RESERVE,
			TradingService\Marketplace\Options::STOCKS_ONLY_AVAILABLE,
		]);

		return array_keys($enabled);
	}

	public function feeds()
	{
		$result = [];

		foreach ($this->setting('PRODUCT_FEED') as $setupId => $value)
		{
			if (!is_array($value)) { $value = []; }

			Main\Type\Collection::normalizeArrayValuesByInt($value);

			$result[$setupId] = $value;
		}

		return $result;
	}

	public function stores()
	{
		$stores = $this->setting('PRODUCT_STORE');

		return $this->valueMerge($stores);
	}

	/** @deprecated */
	public function primarySetup()
	{
		$grouped = $this->linked;

		return reset($grouped) ?: null;
	}

	public function linked()
	{
		return $this->linked;
	}

	protected function valueMerge(array $values)
	{
		$partials = [];

		foreach ($values as $value)
		{
			if (!is_array($value)) { continue; }

			$partials[] = $value;
		}

		return !empty($partials) ? array_merge(...$partials) : [];
	}

	protected function valuePositive(array $values)
	{
		return $this->valueFilter($values, ReferenceStorage\Table::BOOLEAN_Y);
	}

	protected function valueFilter(array $values, $filter)
	{
		$result = [];

		foreach ($values as $setupId => $value)
		{
			if (is_array($filter))
			{
				$matched = in_array($value, $filter, true);
			}
			else
			{
				$matched = ((string)$value === $filter);
			}

			if ($matched)
			{
				$result[$setupId] = $value;
				break;
			}
		}

		return $result;
	}

	protected function setting($name)
	{
		$settings = $this->settings();

		if (!isset($settings[$name]))
		{
			throw new Main\ArgumentException(sprintf('register setting %s preload', $name));
		}

		return $settings[$name];
	}

	protected function settings()
	{
		if ($this->settings === null)
		{
			$this->settings = $this->loadSettings();
		}

		return $this->settings;
	}

	protected function loadSettings()
	{
		$setupIds = $this->linked();
		$result = array_fill_keys($this->preload, []);

		if (empty($setupIds)) { return $result; }

		$query = TradingSettings\Table::getList([
			'filter' => [
				'=SETUP_ID' => $setupIds,
				'=NAME' => $this->preload,
			],
			'select' => [ 'SETUP_ID', 'NAME', 'VALUE' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['NAME']][$row['SETUP_ID']] = $row['VALUE'];
		}

		return $result;
	}
}