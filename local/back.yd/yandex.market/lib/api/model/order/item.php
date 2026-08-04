<?php

namespace Yandex\Market\Api\Model\Order;

use Bitrix\Main;
use Yandex\Market;

class Item extends Market\Api\Model\Cart\Item
{
	public function getPrice()
	{
		return (float)$this->getRequiredField('price');
	}

	/** @return Item\SubsidyCollection */
	public function getSubsidies()
	{
		return $this->getChildCollection('subsidies');
	}

	public function getSubsidy()
	{
		return (float)$this->getSubsidies()->getSum();
	}

	public function getFullPrice()
	{
		return $this->getPrice() + $this->getSubsidy();
	}

	public function getVat()
	{
		return (string)$this->getField('vat');
	}

	protected function getChildCollectionReference()
	{
		return parent::getChildCollectionReference() + [
			'subsidies' => Item\SubsidyCollection::class,
		];
	}
}