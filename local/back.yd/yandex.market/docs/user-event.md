#Пользовательские события выгрузки

Процесс выгрузки:
1. Запрос данных;
2. Создание тегов;
3. Запись в файл.

## Дополнение данных выгрузки

Выполняется перед созданием тегов, используется для дополнения данных выгружаемого формата.

Параметры события:
- TAG_VALUE_LIST - список значений (массив $elementId => Yandex\Market\Result\XmlValue);
- ELEMENT_LIST - список данных предложений (массив $elementId => $elementRow) **;
- CONTEXT - контекст выгрузки;
- PARENT_LIST - данные родительских элементов тороговых предложений (массив $parentId => $parentRow) *** ****.

** $elementRow ```[ 'IBLOCK_ID' => int, 'ID' => int, 'CATALOG_TYPE' => int|null, 'PARENT_ID' => int|null ]``` *****;

*** Присутствует только в событии onExportOfferExtendData;

**** $parentRow ```[ 'IBLOCK_ID' => int, 'ID' => int, 'CATALOG_TYPE' => int|null ]``` *****;

***** Если включена опция "Новый синтаскис CIBlockElement::GetList", то ключ ```CATALOG_TYPE``` будет заменен на ```TYPE```.

Список событий:
- onExportRootExtendData - модификация данных корневого элемента xml-документа;
- onExportOfferExtendData - модификация данных для тега offer;
- onExportCategoryExtendData - модификация данных для тега category;
- onExportCurrencyExtendData - модификация данных для тега currency;

####Пример «Добавление дополнительных параметров запроса к тегу url»:

```$php

use Bitrix\Main;
use Yandex\Market;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportOfferExtendData', function(Main\Event $event) {

	/** @var $tagValueList Market\Result\XmlValue[] */
	/** @var $elementList array */
	/** @var $context array */
	/** @var $parentList array */
	$tagValueList = $event->getParameter('TAG_VALUE_LIST');
	$elementList = $event->getParameter('ELEMENT_LIST');
	$context = $event->getParameter('CONTEXT');
	$parentList = $event->getParameter('PARENT_LIST');

	foreach ($tagValueList as $elementId => $tagValue)
	{
		$element = $elementList[$elementId];
		$parent = null;
		$urlQueryParams = [
			'setup_id' => $context['SETUP_ID'],
			'offer_id' => $element['ID']
		];

		if (isset($element['PARENT_ID']))
		{
			$parent = $parentList[$element['PARENT_ID']];

			$urlQueryParams['parent_id'] = $parent['ID'];
		}

		$tagUrlValue = $tagValue->getTagValue('url');
		$tagUrlValue .= (strpos($tagUrlValue, '?') === false ? '?' : '&') . http_build_query($urlQueryParams);

		$tagValue->setTagValue('url', $tagUrlValue);
	}

});

```

####Пример «Установка атрибута available на основании mt_rand»:

```$php

use Bitrix\Main;
use Yandex\Market;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportOfferExtendData', function(Main\Event $event) {

	/** @var $tagValueList Market\Result\XmlValue[] */
	$tagValueList = $event->getParameter('TAG_VALUE_LIST');

	foreach ($tagValueList as $elementId => $tagValue)
	{
		$tagValue->setTagAttribute('offer', 'available', mt_rand(0, 100) > 50);
	}

});

```

## Модификация тега

Выполняется перед записью в файл. Может использоваться для:

- Отмены записи тега в файл;
- Добавления тегов и атрибутов, которые не описаны в формате выгрузки.

Параметры события:
- TAG_RESULT_LIST - список значений (массив $elementId => Yandex\Market\Result\XmlNode);
- ELEMENT_LIST - список данных предложений (массив $elementId => $elementRow) **;
- CONTEXT - контекст выгрузки;
- PARENT_LIST - данные родительских элементов тороговых предложений (массив $parentId => $parentRow) *** ****.

** $elementRow ```[ 'IBLOCK_ID' => int, 'ID' => int, 'CATALOG_TYPE' => int|null, 'PARENT_ID' => int|null ]``` *****;

*** - Присутствует только в событии onExportOfferExtendData;

**** $parentRow ```[ 'IBLOCK_ID' => int, 'ID' => int, 'CATALOG_TYPE' => int|null ]``` *****;

***** Если включена опция "Новый синтаскис CIBlockElement::GetList", то ключ ```CATALOG_TYPE``` будет заменен на ```TYPE```.

Список событий:
- onExportRootWriteData - модификация тега корневого элемента xml-документа;
- onExportOfferWriteData - модификация тега offer;
- onExportCategoryWriteData - модификация тега category;
- onExportCurrencyWriteData - модификация тега currency;

####Пример «Добавление кастомных тегов и атрибутов»:

```$php

use Bitrix\Main;
use Yandex\Market;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportOfferWriteData', function(Main\Event $event) {

	/** @var $tagResultList Market\Result\XmlNode[] */
	/** @var $elementList array */
	/** @var $context array */
	/** @var $parentList array */
	/** @var $tagNode \SimpleXMLElement */
	$tagResultList = $event->getParameter('TAG_RESULT_LIST');
	$elementList = $event->getParameter('ELEMENT_LIST');
	$context = $event->getParameter('CONTEXT');
	$parentList = $event->getParameter('PARENT_LIST');

	foreach ($tagResultList as $elementId => $tagResult)
	{
		if ($tagResult->isSuccess())
		{
			$tagNode = $tagResult->getXmlElement();
			$element = $elementList[$elementId];
			$parent = null;

			$tagNode->addChild('setup_id', $context['SETUP_ID']);
			$tagNode->addChild('offer_id', $element['ID']);

			if (isset($element['PARENT_ID']))
			{
				$parent = $parentList[$element['PARENT_ID']];

				$tagNode->addChild('parent_id', $parent['ID']);
			}

			$tagNode->addAttribute('custom', 'Y');

			$tagResult->invalidateXmlContents();
		}
	}

});

```

####Пример «Отмена выгрузки недоступных для продажи товаров»:

```$php

use Bitrix\Main;
use Yandex\Market;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportOfferWriteData', function(Main\Event $event) {

	/** @var $tagResultList Market\Result\XmlNode[] */
	/** @var $tagElement \SimpleXMLElement */
	$tagResultList = $event->getParameter('TAG_RESULT_LIST');

	foreach ($tagResultList as $elementId => $tagResult)
	{
		if ($tagResult->isSuccess())
		{
			$tagNode = $tagResult->getXmlElement();
			$attributeList = $tagNode->attributes();

			if (!isset($attributeList['available']) || (string)$attributeList['available'] !== 'true')
			{
				$tagResult->invalidate();
			}
		}
	}

});

```
