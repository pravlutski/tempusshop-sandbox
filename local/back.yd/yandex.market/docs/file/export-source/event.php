<?php

namespace Site\PhpInterface\YandexMarket\Entity\Product;

use Yandex\Market;
use Bitrix\Main;

if (!Main\Loader::includeModule('yandex.market'))
{
	throw new Main\SystemException('require module yandex.market');
}

class Event extends Market\Export\Entity\Reference\Event
{
	// nothing by default
}