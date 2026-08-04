<?php

namespace Site\PhpInterface\YandexMarket\Entity\Product;

use Yandex\Market;
use Bitrix\Main;
use Bitrix\Iblock;

if (!Main\Loader::includeModule('yandex.market'))
{
	throw new Main\SystemException('require module yandex.market');
}

Main\Localization\Loc::loadMessages(__FILE__);

class Source extends Market\Export\Entity\Reference\Source
{
	protected $iblockPropertyMap = [];

	/**
	 * Список полей источника
	 *
	 * @param array $context
	 *
	 * @return array
	 */
	public function getFields(array $context = [])
	{
		return $this->buildFieldsDescription([
			'DELIVERY_OPTIONS' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_DELIVERY_OPTIONS,
				'SELECTABLE' => true,
				'FILTERABLE' => false
			],
			'ATTRIBUTES_VALUE' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'SELECTABLE' => true,
				'FILTERABLE' => false
			],
			'ATTRIBUTES_NAME' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'SELECTABLE' => true,
				'FILTERABLE' => false
			],
			'ATTRIBUTES_UNIT' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'SELECTABLE' => true,
				'FILTERABLE' => false
			]
		]);
	}

	/**
	 * Метод для взаимодействия с другими источниками данных и загрузки данных, которые идентичны для всех элементов
	 *
	 * @param $select array
	 * @param $queryContext array
	 * @param $sourceSelect array
	 */
	public function initializeQueryContext($select, &$queryContext, &$sourceSelect)
	{
		$requirePropertyList = [
			'ELEMENT' => [],
			'OFFERS' => []
		];

		foreach ($select as $field)
		{
			switch ($field)
			{
				case 'ATTRIBUTES_VALUE':
				case 'ATTRIBUTES_NAME':
				case 'ATTRIBUTES_UNIT':
					$requirePropertyList['OFFERS']['CML2_ATTRIBUTES'] = [ 'VALUE', 'DESCRIPTION' ];
				break;
			}
		}

		$this->initializeIblockPropertyMap($requirePropertyList, $queryContext, $sourceSelect);
	}

	public function getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues)
	{
		$result = [];
		$attributes = null;

		foreach ($selectFields as $field)
		{
			switch ($field)
			{
				case 'ATTRIBUTES_VALUE':
				case 'ATTRIBUTES_NAME':
				case 'ATTRIBUTES_UNIT':
					if ($attributes === null)
					{
						$attributes = $this->loadElementListAttributes($elementList, $queryContext, $sourceValues);
					}

					if (isset($attributes[$field]))
					{
						foreach ($elementList as $elementId => $element)
						{
							$result[$elementId][$field] = $attributes[$field][$elementId];
						}
					}
				break;

				case 'DELIVERY_OPTIONS':
					$this->loadElementListDeliveryOptions($result, $field, $elementList);
				break;
			}
		}

		return $result;
	}

	/**
	 * Загружаем значения аттрибутов для элементов
	 *
	 * @param $elementList
	 * @param $queryContext
	 * @param $sourceValues
	 *
	 * @return array
	 */
	protected function loadElementListAttributes($elementList, $queryContext, $sourceValues)
	{
		$attributesPropertyId = $this->getIblockPropertyId('OFFERS', 'CML2_ATTRIBUTES');
		$result = [
			'ATTRIBUTES_VALUE' => [],
			'ATTRIBUTES_NAME' => [],
			'ATTRIBUTES_UNIT' => [],
		];

		if ($attributesPropertyId !== null)
		{
			$sourceType = Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY;
			$valueKey = $attributesPropertyId . '.VALUE';
			$descriptionKey = $attributesPropertyId . '.DESCRIPTION';
			$langPrefix = 'YANDEX_MARKET_' . $this->getLangPrefix();

			foreach ($elementList as $elementId => $element)
			{
				if (!empty($sourceValues[$elementId][$sourceType][$valueKey]) && !empty($sourceValues[$elementId][$sourceType][$descriptionKey]))
				{
					$elementAttributeValuesList = (array)$sourceValues[$elementId][$sourceType][$valueKey];
					$elementAttributeNameList = (array)$sourceValues[$elementId][$sourceType][$descriptionKey];
					$elementAttributeUnitList = [];

					foreach ($elementAttributeNameList as $elementAttributeName)
					{
						$elementAttributeUnit = null;

						switch ($elementAttributeName)
						{
							case Main\Localization\Loc::getMessage($langPrefix . 'ATTRIBUTE_WEIGHT'):
								$elementAttributeUnit = Main\Localization\Loc::getMessage($langPrefix . 'ATTRIBUTE_WEIGHT_UNIT');
							break;
						}

						$elementAttributeUnitList[] = $elementAttributeUnit;
					}

					$result['ATTRIBUTES_VALUE'][$elementId] = $elementAttributeValuesList;
					$result['ATTRIBUTES_NAME'][$elementId] = $elementAttributeNameList;
					$result['ATTRIBUTES_UNIT'][$elementId] = $elementAttributeUnitList;
				}
			}
		}

		return $result;
	}

	/**
	 * Заполняем DELIVERY_OPTIONS для списка элементов
	 *
	 * @param $result
	 * @param $field
	 * @param $elementList
	 */
	protected function loadElementListDeliveryOptions(&$result, $field, $elementList)
	{
		foreach ($elementList as $elementId => $element)
		{
			$result[$elementId][$field] = [
				[
					[ 'COST' => ceil($elementId / 50) * 100, 'DAYS' => '1-2' ],
					[ 'COST' => 0, 'DAYS' => '3-4', 'ORDER_BEFORE' => 21 ]
				]
			];
		}
	}

	/**
	 * Строковый идентификатор для формирования ключа языковой фразы
	 *
	 * @return string
	 */
	protected function getLangPrefix()
	{
		return 'DEMO_PRODUCT_';
	}

	/**
	 * Ид свойства инфоблока
	 *
	 * @param $targetName
	 * @param $propertyCode
	 *
	 * @return int|null
	 */
	protected function getIblockPropertyId($targetName, $propertyCode)
	{
		return isset($this->iblockPropertyMap[$targetName][$propertyCode]) ? $this->iblockPropertyMap[$targetName][$propertyCode] : null;
	}

	/**
	 * Сохранить карту свойств инфоблока
	 *
	 * @param $targetName
	 * @param $propertyMap
	 */
	protected function setIblockPropertyMap($targetName, $propertyMap)
	{
		$this->iblockPropertyMap[$targetName] = $propertyMap;
	}

	/**
	 * Добавляем запрос свойств в источники данных, записываем карты свойств
	 *
	 * @param $requirePropertyList
	 * @param $queryContext
	 * @param $sourceSelect
	 */
	protected function initializeIblockPropertyMap($requirePropertyList, $queryContext, &$sourceSelect)
	{
		foreach ($requirePropertyList as $targetName => $propertyConfigList)
		{
			$iblockId = $queryContext['IBLOCK_ID'];
			$sourceType = Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_PROPERTY;
			$propertyCodeList = array_keys($propertyConfigList);

			if ($targetName === 'OFFERS')
			{
				$iblockId = isset($queryContext['OFFER_IBLOCK_ID']) ? $queryContext['OFFER_IBLOCK_ID'] : null;
				$sourceType = Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY;
			}

			$propertyMap = $this->fetchIblockPropertyMap($iblockId, $propertyCodeList);

			foreach ($propertyMap as $propertyCode => $propertyId)
			{
				$propertyConfig = $propertyConfigList[$propertyCode];

				if (!isset($sourceSelect[$sourceType]))
				{
					$sourceSelect[$sourceType] = [];
				}

				if (is_array($propertyConfig))
				{
					foreach ($propertyConfig as $innerField)
					{
						$sourceSelect[$sourceType][] = $propertyId . '.' . $innerField;
					}
				}
				else
				{
					$sourceSelect[$sourceType][] = $propertyId;
				}
			}

			$this->setIblockPropertyMap($targetName, $propertyMap);
		}
	}

	/**
	 * Карта свойств инфоблока [код свойства] => [ид свойства]
	 *
	 * @param $iblockId int
	 * @param $codeList array
	 *
	 * @return array
	 */
	protected function fetchIblockPropertyMap($iblockId, $codeList)
	{
		$iblockId = (int)$iblockId;
		$result = [];

		if ($iblockId > 0 && !empty($codeList) && Main\Loader::includeModule('iblock'))
		{
			$queryProperties = Iblock\PropertyTable::getList([
				'filter' => [
					'=IBLOCK_ID' => $iblockId,
					'=ACTIVE' => 'Y',
					'=CODE' => $codeList
				],
				'select' => [
					'ID',
					'CODE'
				]
			]);

			while ($property = $queryProperties->fetch())
			{
				$result[$property['CODE']] = $property['ID'];
			}
		}

		return $result;
	}
}