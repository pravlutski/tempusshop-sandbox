<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class Order extends Market\Api\Reference\Model
{
	public function getId()
	{
		return (int)$this->getRequiredField('ID');
	}

	public function getSetupId()
	{
		return (int)$this->getRequiredField('SETUP_ID');
	}

	public function getInternalId()
	{
		return (int)$this->getRequiredField('INTERNAL_ID');
	}

	public function getAccountNumber()
	{
		return (string)$this->getRequiredField('ACCOUNT_NUMBER');
	}

	public function getShipmentId()
	{
		return (int)$this->getRequiredField('SHIPMENT_ID');
	}

	public function getInitialBoxCount()
	{
		return (int)$this->getRequiredField('BOX_INITIAL_COUNT');
	}

	public function useAutoFinish()
	{
		return (string)$this->getField('AUTO_FINISH') === 'Y';
	}

	/** @return BoxCollection */
	public function getBoxCollection()
	{
		return $this->getRequiredCollection('BOX');
	}

	/** @return BasketConfirm */
	public function getBasketConfirm()
	{
		return $this->getChildModel('BASKET_CONFIRM');
	}

	protected function getChildModelReference()
	{
		return [
			'BASKET_CONFIRM' => BasketConfirm::class,
		];
	}

	protected function getChildCollectionReference()
	{
		return [
			'BOX' => BoxCollection::class,
		];
	}
}