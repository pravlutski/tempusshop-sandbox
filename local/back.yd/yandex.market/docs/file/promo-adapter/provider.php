<?php

namespace Site\PhpInterface\YandexMarket\Promo;

use Bitrix\Main;
use Bitrix\Iblock;
use Yandex\Market;

if (!Main\Loader::includeModule('yandex.market'))
{
	throw new Main\SystemException('require module yandex.market');
}

Main\Localization\Loc::loadMessages(__FILE__);

class MyProvider extends Market\Export\Promo\Discount\AbstractProvider
{
	/**
	 * Название для отображения пользователю
	 *
	 * @return string
	 */
	public static function getTitle()
	{
		return Main\Localization\Loc::getMessage('SITE_PHP_YM_PROMO_MY_PROVIDER_TITLE');
	}

	/**
	 * Описание работы класса для пользователя
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return Main\Localization\Loc::getMessage('SITE_PHP_YM_PROMO_MY_PROVIDER_DESCRIPTION');
	}

	/**
	 * Список акций для выбора пользователем
	 *
	 * @return array
	 */
	public static function getExternalEnum()
	{
		$result = [];
		$iblockId = static::getIblockId();

		if ($iblockId > 0 && Main\Loader::includeModule('iblock'))
		{
			$queryActions = Iblock\ElementTable::getList([
				'filter' => [
					'=IBLOCK_ID' => $iblockId,
					'=ACTIVE' => 'Y'
				],
				'select' => [
					'ID',
					'NAME'
				]
			]);

			while ($action = $queryActions->fetch())
			{
				$result[] = [
					'ID' => $action['ID'],
					'VALUE' => $action['NAME']
				];
			}
		}

		return $result;
	}

	/**
	 * Загрузка данных акции.
	 * Результат будет сохранен в переменную класса, для получения значений используйте метод getField, getFields
	 *
	 * @return array
	 */
	protected function loadFields()
	{
		$result = [];

		if ($this->id > 0 && Main\Loader::includeModule('iblock'))
		{
			$queryAction = \CIBlockElement::GetList(
				[],
				[
					'IBLOCK_ID' => static::getIblockId(),
					'=ID' => $this->id
				],
				false,
				false,
				[
					'IBLOCK_ID',
					'ID',
					'ACTIVE',
					'ACTIVE_FROM',
					'ACTIVE_TO',
					'NAME',
					'PREVIEW_TEXT',
					'DETAIL_PAGE_URL',
					'PROPERTY_*'
				]
			);

			if ($actionNode = $queryAction->GetNextElement(false, false))
			{
				$action = $actionNode->GetFields();
				$action['PROPERTIES'] = $actionNode->GetProperties();

				$result = $action;
			}
		}

		return $result;
	}

	/**
	 * Активна ли акция без учёта даты активности
	 *
	 * @return bool
	 */
	public function isActive()
	{
		return $this->getField('ACTIVE') === 'Y';
	}

	/**
	 * Определение типа акции для выгрузки. Варианты значений: 'promo code', 'flash discount', 'n plus m', 'gift with purchase'
	 * Результат будет сохранен в переменную класса, для получения значения используйте метод getPromoType
	 *
	 * @return string
	 */
	public function detectPromoType()
	{
		switch ($this->getProperty('DISCOUNT_TYPE', 'VALUE_XML_ID'))
		{
			case 'flash':
				$result = Market\Export\Promo\Table::PROMO_TYPE_FLASH_DISCOUNT;
			break;

			case 'coupon':
				$result = Market\Export\Promo\Table::PROMO_TYPE_PROMO_CODE;
			break;

			case 'nplusm':
				$result = Market\Export\Promo\Table::PROMO_TYPE_GIFT_N_PLUS_M;
			break;

			case 'gift':
				$result = Market\Export\Promo\Table::PROMO_TYPE_GIFT_WITH_PURCHASE;
			break;
		}

		return $result;
	}

	/**
	 * Условия действия скидки
	 *
	 * @return array Ассоциативный массив [
	 * 	START_DATE (Bitrix\Main\Type\DateTime|null) - Дата начала акции
	 * 	FINISH_DATE (Bitrix\Main\Type\DateTime|null) - Дата завершения акции
	 * 	DISCOUNT_UNIT (string|null) - правило применения скидки. Варианты: percent - процент, currency - валюта
	 * 	DISCOUNT_CURRENCY (string|null) - валюта, в которой применяется скидка
	 * 	DISCOUNT_VALUE (float|null) - размер скидки
	 * 	PROMO_CODE (string|null) - промокод
	 * 	GIFT_REQUIRED_QUANTITY (int|null) - количество товара, необходимое для получения товара
	 * 	GIFT_FREE_QUANTITY (int|null) - количество товара, которое вы предоставляете в подарок
	 * 	URL (string|null) - относительный или абсолютный url-адрес, на которой представлена акция
	 * 	DESCRIPTION (string|null) - описание акции, которое будет выгружено на Яндекс.Маркет
	 * ]
	 */
	public function getPromoFields()
	{
		$result = [
			'START_DATE' => null,
			'FINISH_DATE' => null,
			'DISCOUNT_UNIT' => null,
			'DISCOUNT_CURRENCY' => null,
			'DISCOUNT_VALUE' => null,
			'PROMO_CODE' => null,
			'GIFT_REQUIRED_QUANTITY' => null,
			'GIFT_FREE_QUANTITY' => null,
			'URL' => $this->getField('DETAIL_PAGE_URL'),
			'DESCRIPTION' => $this->getField('PREVIEW_TEXT')
		];

		// date

		$dateFields = [
			'START_DATE' => 'ACTIVE_FROM',
			'FINISH_DATE' => 'ACTIVE_TO'
		];

		foreach ($dateFields as $promoField => $internalField)
		{
			$value = (string)$this->getField($internalField);
			$timestamp = $value !== '' ? MakeTimeStamp($value) : false;

			if ($timestamp !== false)
			{
				$result[$promoField] = Main\Type\DateTime::createFromTimestamp($timestamp);
			}
		}

		// discount

		switch ($this->getPromoType())
		{
			case Market\Export\Promo\Table::PROMO_TYPE_GIFT_WITH_PURCHASE:
			case Market\Export\Promo\Table::PROMO_TYPE_GIFT_N_PLUS_M:
				$result['GIFT_REQUIRED_QUANTITY'] = (int)$this->getProperty('GIFT_REQUIRED_QUANTITY') ?: 1;
				$result['GIFT_FREE_QUANTITY'] = (int)$this->getProperty('GIFT_FREE_QUANTITY') ?: 1;
			break;

			case Market\Export\Promo\Table::PROMO_TYPE_PROMO_CODE:
				$result['PROMO_CODE'] = $this->getProperty('COUPON');
				$discountRule = $this->getPromoDiscountRule();

				if ($discountRule !== null)
				{
					$result = array_merge($result, $discountRule);
				}
			break;

			default:
				$discountRule = $this->getPromoDiscountRule();

				if ($discountRule !== null)
				{
					$result = array_merge($result, $discountRule);
				}
			break;
		}

		return $result;
	}

	/**
	 * Список фильтров в формате модуля для выбора Товаров по акции
	 *
	 * @param array $context контекст выгрузки
	 *
	 * @return array
	 */
	public function getProductFilterList($context)
	{
		$result = [];

		$result[] = [
			'FILTER' => [
				Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD => [
					[
						'FIELD' => 'ID',
						'COMPARE' => '=',
						'VALUE' => $this->getProperty('PRODUCT') ?: -1
					],
				]
			],
			'DATA' => [
				'RULE' => $this->getPromoDiscountRule()
			]
		];

		return $result;
	}

	/**
	 * Cписок фильтров в формате модуля для выбора Подарков по акции
	 *
	 * @param $context контекст выгрузки
	 *
	 * @return array
	 */
	public function getGiftFilterList($context)
	{
		$result = [];

		$result[] = [
			'FILTER' => [
				Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD => [
					[
						'FIELD' => 'ID',
						'COMPARE' => '=',
						'VALUE' => $this->getProperty('GIFT') ?: -1
					],
				]
			],
			'DATA' => []
		];

		return $result;
	}

	/**
	 * Применение скидки к стоимости товара
	 *
	 * @param int $productId Ид товара
	 * @param float $price Стоимость товара без скидки
	 * @param string|null $currency Валюта стоимости товара
	 * @param array|null $filterData DATA из фильтра по товарам метода getProductFilterList
	 *
	 * @return float
	 */
	public function applyDiscountRules($productId, $price, $currency = null, $filterData = null)
	{
		$result = $price;

		if (isset($filterData['RULE']))
		{
			$rule = $filterData['RULE'];
			$discountSum = null;

			switch ($rule['DISCOUNT_UNIT'])
			{
				case Market\Export\Promo\Table::DISCOUNT_UNIT_CURRENCY:
					$discountSum = $rule['DISCOUNT_VALUE'];
				break;

				case Market\Export\Promo\Table::DISCOUNT_UNIT_PERCENT:
					$discountSum = $result * ($rule['DISCOUNT_VALUE'] / 100);
				break;
			}

			if ($discountSum > 0)
			{
				$result -= $discountSum;
			}
		}

		return $result;
	}

	/**
	 * Выгружать товары, которые не попали в выгрузку
	 *
	 * @param $context
	 *
	 * @return bool
	 */
	public function isExportExternalGift($context)
	{
		return true;
	}

	/**
	 * Правила применения скидки для Яндекс.Маркет
	 *
	 * @return array
	 */
	protected function getPromoDiscountRule()
	{
		$discountUnit = $this->getProperty('DISCOUNT_UNIT', 'VALUE_XML_ID');

		switch ($discountUnit)
		{
			case 'percent':
				$result = [
					'DISCOUNT_UNIT' => Market\Export\Promo\Table::DISCOUNT_UNIT_PERCENT,
					'DISCOUNT_VALUE' => (float)$this->getProperty('DISCOUNT_VALUE')
				];
			break;

			case 'currency':
				$result = [
					'DISCOUNT_UNIT' => Market\Export\Promo\Table::DISCOUNT_UNIT_CURRENCY,
					'DISCOUNT_VALUE' => (float)$this->getProperty('DISCOUNT_VALUE'),
					'DISCOUNT_CURRENCY' => $this->getProperty('DISCOUNT_CURRENCY')
				];
			break;
		}

		return $result;
	}

	/**
	 * Метод получения значений свойств из загруженны данных акции
	 *
	 * @param string $propertyName
	 * @param string $propertyField
	 *
	 * @return mixed
	 */
	protected function getProperty($propertyName, $propertyField = 'VALUE')
	{
		$properties = $this->getField('PROPERTIES');

		return isset($properties[$propertyName][$propertyField]) ? $properties[$propertyName][$propertyField] : null;
	}

	/**
	 * Идентификатор инфоблока акций (применим только в данном примере)
	 *
	 * @return int|null
	 */
	protected static function getIblockId()
	{
		$optionValue = (int)Main\Config\Option::get('site', 'action_iblock_id');

		return ($optionValue > 0) ? $optionValue : null;
	}
}