# Пользовательский Источник данных

Модуль yandex.market позволяет регистрировать пользовательские классы, которые предоставляют информацию об товарах вашего сайта.

## Регистрация Источника данных

Регистрация производится на событие `onExportEntityTypeBuildList` модуля `yandex.market`. Вам необходимо вернуть успешный результат с параметрами:
- `TYPE` - строка, внутренний идентификатор для Источника данных;
- `SOURCE_CLASS_NAME` - строка, название класса Источника данных;
- `EVENT_CLASS_NAME` - строка, название класса Обработки событий.

```php
use Bitrix\Main;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportEntityTypeBuildList', function(Main\Event $event) {
	return new Main\EventResult(Main\EventResult::SUCCESS, [
		'TYPE' => 'demo_product',
		'SOURCE_CLASS_NAME' => 'PhpInterface\YandexMarket\Entity\Product\Source',
		'EVENT_CLASS_NAME' => 'PhpInterface\YandexMarket\Entity\Product\Event',
	]);
});
```

## Класс Источника данных

Источник данных - php-класс, наследованный от `Yandex\Market\Export\Entity\Reference\Source`. Назначение класса:
- Набор полей, которые Администратор может выбрать на шаге Сопоставление полей или создать группу товаров на шаге Выбор товаров и доставка;
- Установка фильтра по выбранным полям;
- Загрузка значений полей для выбранных товаров.

```php
namespace PhpInterface\YandexMarket\Entity\Product;

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
    // contents
}
```

### Вспомогательные методы класса   
- getLangPrefix() : string - префикс для языковых фраз. Ключ фразы будет сформирован по шаблону YANDEX_MARKET_#LANG_PREFIX##KEY#;
- buildFieldsDescription() : array - преобразование ассоциативного набор полей в результат функции getFields.

### Методы класса

- getTitle() : string - название источника данных в пользовательском интерфейсе;
- getFields(array $context = []) : array - набор полей, каждое поле описывается массивом:
    * ID : int|string - строка, идентификатор поля внутри источника данных;
    * VALUE : string - строка, название поля в пользовательском интерфейсе;
    * TYPE : string - тип данных (используйте константы Yandex\Market\Export\Entity\Data::TYPE_*)
    * FILTERABLE : bool - может использоваться на шаге Выбор товаров и доставка;
    * SELECTABLE : bool - может использоваться на шаге Сопоставление полей;
    * AUTOCOMPLETE : bool - поддерживает автодополнение.
- getFieldEnum($field, array $context = []) - варианты значений поля для выбора в пользовательском интерфейсе при создании фильтра **;
- getFieldAutocomplete($field, $query, array $context = []) - автодополнение значений поля для выбора в пользовательском интерфейсе при создании фильтра **;
- getFieldDisplayValue($field, $valueList, array $context = []) - текстовое представление значений поля при использовании автодополнения в уже созданной группе товаров **;

** результат выполнения функции array<int, array{ID: int|string, VALUE: string}>, где `ID` - идентификатор значения, `VALUE` - текстовое представление для пользователя.

```php
class Source extends Market\Export\Entity\Reference\Source
{
	// ... 

	/**
    * Поля ACTIVE и NAME источника, доступные для фильтрации и выбора
    *
    * @param array $context
    * @return array
    */
    public function getFields(array $context = [])
	{
		return $this->buildFieldsDescription([
			'ACTIVE' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_BOOLEAN,
				'SELECTABLE' => true,
				'FILTERABLE' => true
			],
			'NAME' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'SELECTABLE' => true,
				'FILTERABLE' => true
			],
		]);
	}
    
    /**
    * Варианты значений поля для фильтрации
    *
    * @param $field
    * @param array $context
    * @return array
    */
    public function getFieldEnum($field, array $context = [])
    {
        switch ($field['ID'])
        {
            case 'ACTIVE':
                $result = [
                   [ 'ID' => 'Y', 'VALUE' => 'Yes' ],
                   [ 'ID' => 'N', 'VALUE' => 'No' ],
                ];
            break;  
            
            default:
                $result = parent::getFieldEnum($field, $context);
            break;
        }
    
        return $result;
    }

    /**
    * Префикс языковых фраз
    * 
    * @return string
    */
    protected function getLangPrefix()
    {
        return 'DEMO_PRODUCT_';
    }

    // ... 
}
```

- isSelectable() : bool - может ли использоваться на шаге Сопоставление полей;
- getQuerySelect($select) : array<string, string[]> - формирование запрашиваемых значений по выбранным полям на шаге Сопоставление полей. Результат функции массив:
    * ключи указывают назначение `ELEMENT` - для элементов, `OFFERS` - для предложений;
    * значения - массив полей в формате параметра $arSelect для функции CIBlockElement::GetList.
- isFilterable() : bool - может ли использоваться на шаге Выбор товаров и доставка;
- getQueryFilter($filter, $select) : array<string, array> - формирование фильтра по группе товаров, созданной на шаге Выбор товаров и доставка. Результат функции массив:
    * ключи указывают назначение `ELEMENT` - для элементов, `OFFERS` - для предложений, `CATALOG` - по полям каталога;
    * значения - массив в формате параметра $arFilter для функции CIBlockElement::GetList.
    
```php
class Source extends Market\Export\Entity\Reference\Source
{
	// ... 
    
    /**
    * Доступен для выбора
    *
    * @return bool
    */  
    public function isSelectable()
    {
        return true;    
    }
    
    /**
    * Запрос значений для элементов и предложений
    *
    * @param $select
    * @return array
    */
    public function getQuerySelect($select)
    {
        return [ 
            'ELEMENT' => $select,
            'OFFERS' => $select,
        ];
    }

    /**
    * Доступен для фильтрации
    *
    * @return bool
    */  
    public function isFilterable()
    {
        return true;
    }

    /**
    * Фильтрация элементов по настроенное группе товаров
    *
    * @param $filter
    * @param $select
    * @return array[]
    */
    public function getQueryFilter($filter, $select)
    {
        $result = [
            'ELEMENT' => [],
        ];

        foreach ($filter as $filterItem)
        {
            $compare = $filterItem['COMPARE'];
            $field = $filterItem['FIELD'];
            $value = $filterItem['VALUE'];
    
            $result['ELEMENT'][$compare . $field] = $value;
        }
        
        return $result;
    }

    // ... 
}
```
    
- getOrder() : int - порядок выполнения относительно других источников при обработке запросов;
- initializeQueryContext($select, &$queryContext, &$sourceSelect) - инициализация контекста по запрашиваемым полям перед выполнение запросов;
- releaseQueryContext($select, $queryContext, $sourceSelect) - очистка контекста по запрашиваемым полям после выполнение запросов;
- initializeFilterContext($filter, &$queryContext, &$sourceFilter) - инициализация контекста по фильтру перед выполнение запросов;
- releaseFilterContext($filter, $queryContext, $sourceFilter) - очистка контекста по фильтру после выполнение запросов;
- getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues) : array<int, array<string, mixed>> - загрузка значений по запрошенным полям.
    * Параметры:
        * $elementList array<int, { IBLOCK_ID: int, ID: int, PARENT_ID: int|null, ...$row }> - массив значений предложений и элементов без предложений, запрошенных с помощью CIBlockElement::GetList. $row - поля запрошенные в методе getQuerySelect($select). Ключ массива - идентификатор элемента;
        * $parentList  array<int, { IBLOCK_ID: int, ID: int, ...$row } - массив значений элементов с предложениями, запрошенных с помощью CIBlockElement::GetList. $row - поля запрошенные в методе getQuerySelect($select). Ключ массива - идентификатор элемента;
        * $selectFields string[] - набор полей, результат по которым необходимо вернуть;
        * $queryContext array - контекст выполнения запроса;
        * $sourceValues array - значения, загруженные и других источников данных, структура хранения `$sourceValues[$elementId][$sourceType][$fieldName]`;
    * Результат - двумерный массив, в котором:
        * ключ первого уровня - идентификатор элемента;
        * ключ второго уровня - идентификатор поля;
        * значение - значение для выгрузки.
        
```php
class Source extends Market\Export\Entity\Reference\Source
{
	// ... 
    
    /**
    * Выбор значений элементов для выгрузки
    *
    * @param $elementList
    * @param $parentList
    * @param $selectFields
    * @param $queryContext
    * @param $sourceValues
    * @return array[]
    */
    public function getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues)
    {
        $result = [];

        foreach ($elementList as $elementId => $element)
        {
            $result[$elementId] = [];
    
            foreach ($selectFields as $fieldName)
            {
                if (!isset($element[$fieldName])) { continue; }
                    
                $result[$elementId][$fieldName] = $element[$fieldName];
            }
        }
        
        return $result;
    }

    // ... 
}
```

## Класс Обработки событий

Класс Обработки событий - php-класс, наследованный от `Yandex\Market\Export\Entity\Reference\Event`. Назначение класса:
- Отслеживание изменений;
- Внесение изменений.

События изменения элементов, свойств и цен обрабатываются встроенными источниками, поэтому для собственной реализации достаточно предоставить класс без реализации обработки событий.

```php
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
```

## Пример реализации

Папка file/export-source - экспорт характеристик и параметров доставки.