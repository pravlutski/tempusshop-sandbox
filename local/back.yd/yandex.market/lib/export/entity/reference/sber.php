<?php

namespace Site\PhpInterface\YandexMarket\Entity\Product;

use Yandex\Market;
use Bitrix\Main;
use Bitrix\Iblock;

class Event extends Market\Export\Entity\Reference\Event
{
	public static function OnBeforeExportElement(Market\Export\Run\Data\Setup $setup, Market\Export\Run\Steps\Base $step, $elementId)
	{
		// Массив с артикулами, которые не должны попадать в фид
		$excludedArticleNumbers = ['ARTICLE1', 'ARTICLE2', 'ARTICLE3'];

		// Получаем артикул товара по его ID
		$article = self::getArticleByElementId($elementId);

		// Если артикул находится в массиве исключенных, пропускаем экспорт товара
		if (in_array($article, $excludedArticleNumbers)) {
			return false; // return false чтобы исключить товар из выгрузки
		}
	}

	private static function getArticleByElementId($elementId)
	{
		// Здесь ваш код для получения артикула товара по его ID
		// Например, запрос к таблице с товарами для получения свойства "Артикул"
	}
}

// Регистрируем обработчик
Market\Export\Entity\Manager::registerEventHandler('Product', 'Site\PhpInterface\YandexMarket\Entity\Product\Event');