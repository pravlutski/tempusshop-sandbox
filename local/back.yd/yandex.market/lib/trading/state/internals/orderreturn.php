<?php
namespace Yandex\Market\Trading\State\Internals;

use Yandex\Market;
use Bitrix\Main;

class OrderReturnTable extends Market\Reference\Storage\Table
{
	const STATUS_PROCESS = 0;
	const STATUS_SUCCESS = 1;
	const STATUS_FAIL = 2;

	public static function getTableName()
	{
		return 'yamarket_trading_order_return';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('SETUP_ID', [
				'required' => true,
				'primary' => true,
			]),
			new Main\Entity\StringField('ORDER_ID', [
				'required' => true,
				'primary' => true,
				'validation' => function() {
					return [
						new Main\Entity\Validator\Length(null, 60),
					];
				},
			]),
			new Main\Entity\EnumField('STATUS', [
				'values' => [
					static::STATUS_PROCESS,
					static::STATUS_SUCCESS,
					static::STATUS_FAIL,
				],
			]),
			new Market\Reference\Storage\Field\CanonicalDateTime('TIMESTAMP_X', [
				'required' => true,
			]),
		];
	}
}