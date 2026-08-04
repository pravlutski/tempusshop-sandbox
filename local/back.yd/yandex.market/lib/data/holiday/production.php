<?php

namespace Yandex\Market\Data\Holiday;

use Yandex\Market\Reference\Concerns;

class Production extends National
{
	use Concerns\HasMessage;

	public function title()
	{
		return self::getMessage('TITLE');
	}

	public function holidays()
	{
		return array_unique(array_merge(parent::holidays(), [
			'23.02',
			'24.02',
			'25.02',
			'08.03',
			'09.03',
			'10.03',
			'28.04',
			'29.04',
			'30.04',
			'01.05',
			'09.05',
			'10.05',
			'11.05',
			'12.05',
			'12.06',
			'03.11',
			'04.11',
		]));
	}

	public function workdays()
	{
		return [
			'22.02',
			'07.03',
			'27.04',
			'08.05',
			'11.06',
			'02.11',
		];
	}
}