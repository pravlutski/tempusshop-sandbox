# Пользовательский Адаптер акции

Модуль yandex.market позволяет регистрировать пользовательские классы, которые предоставляют информацию об акциях вашего сайта.

## Регистрация Адаптера акции

Регистрация производится на событие `onExportPromoProviderBuildList` модуля `yandex.market`. Вам необходимо вернуть успешный результат с параметрами:
- `TYPE` - строка, внутренний идентификатор для Источника акции;
- `CLASS_NAME` - строка, название класс Адаптера акции.

```php
use Bitrix\Main;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportPromoProviderBuildList', function(Main\Event $event) {
    $providerType = 'my_promo';
    $providerClassName = 'Site\PhpInterface\YandexMarket\Promo\MyProvider';

    return new Main\EventResult(
        Main\EventResult::SUCCESS,
        [
            'TYPE' => $providerType,
            'CLASS_NAME' => $providerClassName
        ]
    );    
});
```

## Класс Адаптера акции

Адаптер акции - php-класс, наследованный от `Yandex\Market\Export\Promo\Discount\AbstractProvider`. Назначение класса:
- Список акций, который Администратор может выбрать при создании Акции в модуле `yandex.market`;
- Условия и товары акции, которая выбрана для выгрузки.

```php
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
    // contents
}
```

### Статические методы класса

- getTitle() - название типа Адаптера акций для отображения в административном интерфейсе. Результат работы метода - строка;
- getDescription() - описание работы Адаптера акций для отображения в административном интерфейсе. Результат - строка;
- getExternalEnum() - список акций для выбора Администратором при создании Акции в модуле `yandex.market`. Результат - массив, элементы которого представлены в виде ассоциативного массива `[ 'ID' => $action['ID'], 'VALUE' => $action['VALUE'] ]`.

```php
/**
class MyProvider extends Market\Export\Promo\Discount\AbstractProvider
{
    ....
    /*
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
    ....
}
```

### Переменные класса

- protected $id; - идентификатор акции, которую выбрал пользователь для выгрузки. Тип переменной - целое число;
- protected $fields; - массив, используется для сохранения данных акций в процессе выгрузки. Тип переменной - ассоциативный массив. Не рекомендуется работать напрямую, используйте методы `$this->getField($fieldName)` и `$this->getFields()`;
- protected $promoType; - тип акции для Яндекс.Маркет. Тип переменной - строка. Не рекомендуется работать напрямую, используйте метод `$this->getPromoType()`.

### Методы класса

- abstract protected loadFields() - загрузка данных акции. Результат - ассоциативный массив. Для получения значений используйте методы `$this->getField($fieldName)` и `$this->getFields()`;
- getField($name) - значение поля акции. Результат - mixed;
- getFields() - данные акции. Результат - ассоциативный массив;
- isActive() - активна ли акция без учёта даты активности. Результат - булевое значение;
- abstract protected detectPromoType() - определения типа акции, который будет выгружен на Яндекс.Маркет. Результат строка, возможные значения: `promo code`, `flash discount`, `n plus m`, `gift with purchase`. Не рекомендуется работать напрямую, используйте метод `$this->getPromoType()`;
- getPromoType() - тип акции, определенный в методе `$this->detectPromoType()`. Результат - строка;
- abstract public getPromoFields() - условия акции для Яндекс.Маркет. Результат - ассоциативый массив, ключи массива:
```php
START_DATE (Bitrix\Main\Type\DateTime|null) - Дата начала акции
FINISH_DATE (Bitrix\Main\Type\DateTime|null) - Дата завершения акции
DISCOUNT_UNIT (string|null) - правило применения скидки. Варианты: percent - процент, currency - валюта
DISCOUNT_CURRENCY (string|null) - валюта, в которой применяется скидка
DISCOUNT_VALUE (float|null) - размер скидки
PROMO_CODE (string|null) - промокод
GIFT_REQUIRED_QUANTITY (int|null) - количество товара, необходимое для получения товара
GIFT_FREE_QUANTITY (int|null) - количество товара, которое вы предоставляете в подарок
URL (string|null) - относительный или абсолютный url-адрес страницы, на которой представлена акция
DESCRIPTION (string|null) - описание акции, которое будет выгружено на Яндекс.Маркет
```
- abstract public getProductFilterList($context) - список фильтров в формате модуля для выбора Товаров по акции ** ***;
- abstract public getGiftFilterList($context) - список фильтров в формате модуля для выбора Подарков по акции ** ***;
- abstract public applyDiscountRules($productId, $price, $currency = null, $filterData = null) - результат число, стоимость товара со скидкой ****;
- isExportExternalGift($context) - выгружать ли подарки, которые отсутствуют в выгрузке **.

** $context - Контекст выгрузки;

*** Каждый фильтр представлен в виде ассоциативного массива `[ 'FILTER' => $filterQuery, 'DATA' => $data ]`. Формат описания запроса для ключа `FILTER` описан в следующей главе;

**** $filterData - ключ DATA из фильтра по товарам метода getProductFilterList. 

```php
class MyProvider extends Market\Export\Promo\Discount\AbstractProvider
{
    ....
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
    ....
}
```

### Описание запроса фильтра

Массив, ключи которого - строковые идентификатры Источников выгрузки, значения - массив с Параметрами запроса.

Стандартные Источники выгрузки (представлены ввиде констант класса `Yandex\Market\Export\Entity\Manager`):
- const TYPE_IBLOCK_ELEMENT_FIELD = 'iblock_element_field' - поля товара (пример поля - `ID`, `NAME`);
- const TYPE_IBLOCK_ELEMENT_PROPERTY = 'iblock_element_property' - свойства товара (пример поля - `3`, где 3 - ид свойства товара);
- const TYPE_IBLOCK_OFFER_FIELD = 'iblock_offer_field' - поля предложения (пример поля - `ID`, `NAME`);
- const TYPE_IBLOCK_OFFER_PROPERTY = 'iblock_offer_property' - свойства предложения (пример поля - `3`, где 3 - ид свойства предложения);
- const TYPE_CATALOG_PRICE = 'catalog_price' - цена товара (пример поля - `1.VALUE`, где 1 - ид типа цены);
- const TYPE_CATALOG_PRODUCT = 'catalog_product' - данные товара (пример поля - `QUANTITY`);
- const TYPE_CATALOG_STORE = 'catalog_store' - наличие товара на складах (пример поля - `AMOUNT_1`, где 1 - ид склада);

Параметр запроса - ассоциативный массив с ключами:
- FIELD - поле для фильтра;
- COMPARE - сравнение для фильтра. Поддерживаются варианты сравнения `CIBlockElement::GetList` (пример, `=`, `!`);
- VALUE - значения, по которым необходимо выполнить фильрацию.

Пример фильтра "Предложения, ид которых больше 100, и название товара содержит слово Распродажа"
```php
$filter = [
    Yandex\Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD => [ 
        [
            'FIELD' => 'NAME',
            'COMPARE' => '%',
            'VALUE' => '%Распродажа%'
        ] 
    ],
    Yandex\Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_FIELD => [ 
        [
            'FIELD' => 'ID',
            'COMPARE' => '>',
            'VALUE' => 100
        ] 
    ]
];
```

## Пример реализации

Папка file/promo-adapter - акции из инфоблоков.