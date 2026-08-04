<?php
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\OrderItemTable;

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$timeManager = new TimeManager();

if ($_GET["token"] == $timeManager->token) {

	if($_GET["get_property_for_price_type"]) {
		print_r($timeManager->getPropertyForPriceType(16));
	} elseif($_GET["get_type_price"]) {
		print_r($timeManager->getProductPriceTypes(16));
	} elseif ($_GET["update_price"]){
		$requestBody = file_get_contents('php://input');
		// Декодируем JSON-данные
		$data = json_decode($requestBody, true);

		$elements = array();

		$counter = 1;
		foreach ($data as $item) {
			foreach ($item['products'] as $product) {
				$bitrixProperties = json_decode($item['bitrix_properties'], true) ?? [];
				$bitrixTypePrices = json_decode($item['bitrix_type_price'], true) ?? [];

				$elPriceType = [];
				foreach ($bitrixTypePrices as $key => $priceType) {
					$elPriceType[$key]["price_type_id"] = 8;
					$elPriceType[$key]["currency"] = $item['currency_name'];
				}

				$elements[] = [
					"article" => $product['article'],
					"price" => $product['price'],
					"bitrix_properties" => $bitrixProperties,
					"bitrix_type_price" => $elPriceType,
				];
			}
		}

		foreach ($elements as $element) {
			foreach ($element["bitrix_type_price"] as $elementPrice) {
				$timeManager->updateElementPrice(
					$element["article"],
					$elementPrice["price_type_id"],
					$element["price"],
					$elementPrice["currency"]
				);
			}
			$timeManager->updateElementProperties(
				$element["article"],
				$element["bitrix_properties"],
				$element["price"]
			);
		}

		echo "Цены успешно обновлены";
	} elseif($_REQUEST["get_orders"]) {
		$period = $_REQUEST["period"] ?? "N";
		$statusIds = $_REQUEST["status_ids"] ?? null;

		$filter = [
			"STATUS_ID" => $statusIds, // Только заказы с определенным статусом
		];

		try {
			$orders = $timeManager->getOrders($filter, [], ["ID", "DATE_INSERT", "PRICE", "CURRENCY", "STATUS_ID"], 20);;
			print_r(json_encode($orders));
		} catch (\InvalidArgumentException $e) {
			echo $e->getMessage();
		}
	} elseif($_REQUEST["get_orders_test"]) {
        $period = $_REQUEST["period"] ?? "N";
        $statusIds = $_REQUEST["status_ids"] ?? null;

        $filter = [
            "STATUS_ID" => $statusIds, // Только заказы с определенным статусом
        ];

        try {
            $orders = $timeManager->getOrders($filter, [], ["ID", "DATE_INSERT", "PRICE", "CURRENCY", "STATUS_ID"], 20);;
            print_r($orders);
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage();
        }
    } elseif($_REQUEST["get_orders_status"]) {
		print_r(json_encode($timeManager->getOrderStatuses()));
	} elseif($_REQUEST["get_list_site"]){
        print_r(json_encode($timeManager->getListSites()));
    } elseif($_REQUEST["get_ms_assortments"]){
        $articles = explode(',', $_REQUEST['articles']);
        $siteIds = explode(',', $_REQUEST['site_ids']);

        $result = [];
        foreach ($articles as $article) {
            foreach ($siteIds as $siteId) {
                $msUid = $timeManager->getMsUid($article, $siteId);
                $result[$article][$siteId] = $msUid['ms_uid'] ?? null;
            }
        }

        print_r(json_encode($result));
    } else {
		print_r($timeManager->getElements());
	}
} else {
	$timeManager->error();
}

class TimeManager
{
	public $token = "IzIZwLtyAQ66J4fN7f2vC4241aPwf3Mw";
	public $tempusUrl = "https://www.tempus.by";

	/**
	 * @return void
	 */
	#[NoReturn]
	public function error(): void
	{
		http_response_code(430);
		die("Error");
	}

	/**
	 * @return mixed
	 */
	#[Pure]
	public function getElements(): mixed
	{
		$arSelect = ["*"];
		$arFilter = [
			"IBLOCK_ID"     => 16,
			"ACTIVE"        => "Y",
		];
		$arNav = [
			"nPageSize" => 50,
			"bDescPageNumbering" => FALSE
		];

		$elements = [];

		$result = CIBlockElement::GetList([], $arFilter, false, $arNav, $arSelect);

		while ($element = $result->GetNextElement()) {
			if($element->GetFields()["PREVIEW_PICTURE"]){
				$elements[$element->GetFields()["ID"]]["NAME"] = $element->GetFields()["NAME"];
				$elements[$element->GetFields()["ID"]]["PREVIEW_TEXT"] = $element->GetFields()["PREVIEW_TEXT"];
				$elements[$element->GetFields()["ID"]]["PREVIEW_PICTURE"] = $this->tempusUrl . CFile::GetPath($element->GetFields()["PREVIEW_PICTURE"]);
				$elements[$element->GetFields()["ID"]]["DETAIL_PICTURE"] = $this->tempusUrl . CFile::GetPath($element->GetFields()["DETAIL_PICTURE"]);
				$elements[$element->GetFields()["ID"]]["ARTICLE"] = $element->GetProperties()["CML2_ARTICLE"]["VALUE"];

				if ($element->GetProperties()["BRAND"]["VALUE"]){
					$elements[$element->GetFields()["ID"]]["BRAND"] = $this->getByNameBrand($element->GetProperties()["BRAND"]["VALUE"]);
				} else {
					$elements[$element->GetFields()["ID"]]["BRAND"] = "Не указано";
				}

				if($element->GetProperties()["MECHANISM"]["VALUE"]) {
					$elements[$element->GetFields()["ID"]]["MECHANISM"] = $element->GetProperties()["MECHANISM"]["VALUE"];
				} else {
					$elements[$element->GetFields()["ID"]]["MECHANISM"] = "Не указано";
				}

				if($element->GetProperties()["GLASS"]["VALUE"]) {
					$elements[$element->GetFields()["ID"]]["GLASS"] = $element->GetProperties()["GLASS"]["VALUE"];
				} else {
					$elements[$element->GetFields()["ID"]]["GLASS"] = "Не указано";
				}

				if ($element->GetProperties()["CASE"]["VALUE"][0]) {
					$elements[$element->GetFields()["ID"]]["CASE"] = $element->GetProperties()["CASE"]["VALUE"][0];
				} else {
					$elements[$element->GetFields()["ID"]]["CASE"] = "Не указано";
				}

				if($element->GetProperties()["FACE"]["VALUE"]) {
					$elements[$element->GetFields()["ID"]]["FACE"] = $element->GetProperties()["FACE"]["VALUE"];
				} else {
					$elements[$element->GetFields()["ID"]]["FACE"] = "Не указано";
				}

				if($element->GetProperties()["MATERIAL"]["VALUE"][0]) {
					$elements[$element->GetFields()["ID"]]["MATERIAL"] = $element->GetProperties()["MATERIAL"]["VALUE"][0];
				} else {
					$elements[$element->GetFields()["ID"]]["MATERIAL"] = "Не указано";
				}

				if($element->GetProperties()["COLOR"]["VALUE"][0]) {
					$elements[$element->GetFields()["ID"]]["COLOR"] = $element->GetProperties()["COLOR"]["VALUE"][0];
				} else {
					$elements[$element->GetFields()["ID"]]["COLOR"] = "Не указано";
				}
			}
		}

		return json_encode($elements);
	}

	/**
	 * @param $id
	 *
	 * @return mixed|string
	 */
	public function getByNameBrand($id): mixed
	{
		$arSelect = ["*"];
		$arFilter = [
			"IBLOCK_ID"     => 11,
			"ID"            => $id
		];
		$arNav = [];

		$result = CIBlockElement::GetList([], $arFilter, false, $arNav, $arSelect);

		$elements = '';

		while ($element = $result->GetNextElement()) {
			$elements = $element->GetFields()["NAME"];
		}

		return $elements;
	}

	public function getPropertyForPriceType($iblockId)
	{
		$properties = [];

		$propertyFilter = [
			"IBLOCK_ID" => $iblockId,
			"PROPERTY_TYPE" => "S"
		];

		$propertyResult = CIBlockProperty::GetList([], $propertyFilter);
		while ($property = $propertyResult->GetNext()){
			$properties[$property["ID"]] = [
				"NAME" => $property["NAME"],
				"CODE" => $property["CODE"],
				"ID"   => $property["ID"]
			];
		}

		return json_encode($properties);
	}

	public function getProductPriceTypes(int $ibIblockId)
	{
		$priceTypes = [];

		$priceTypeResult = CCatalogGroup::GetList(
			[],
			["LID" => "s1"],
			false,
			false,
			["*"]
		);

		while ($priceType = $priceTypeResult->GetNext()) {

			if($priceType["NAME"] === "BASE") {
				$title = "Yandex";
			} elseif ($priceType["NAME"] === "BASE_BEL") {
				$title = "Цена Минск";
			} elseif ($priceType["NAME"] === "BASE_PL") {
				$title = "Польский злотый";
			} elseif ($priceType["NAME"] === "BASE_OPT") {
				$title = "Цена Опт";
			} elseif ($priceType["NAME"] === "BASE_SITE") {
				$title = "Цена сайта";
			} elseif ($priceType["NAME"] === "BASE_KZ") {
				$title = "Казахстанский тенге";
			} elseif ($priceType["NAME"] === "Цена продажи") {
				$title = "Цена продажи";
			} elseif ($priceType["NAME"] === "TEST") {
				$title = "Тестовый тип цен";
			}

			$priceTypes[$priceType["ID"]]["NAME"] = $priceType["NAME"];
			$priceTypes[$priceType["ID"]]["TITLE"] = $title;
			$priceTypes[$priceType["ID"]]["ID"] = $priceType["ID"];

		}

		return json_encode($priceTypes);
	}

	public function updateElementProperties($article, $properties, $price)
	{
		$elementsProporties = [];

		foreach ($properties as $k => $property) {
			$elementsProporties[$property] = $price;
		}

		$arFilter = array(
			"IBLOCK_ID" => 16,
			"PROPERTY_CML2_ARTICLE" => $article
		  );

		$rsElement = CIBlockElement::GetList(
			array(),
			$arFilter,
			false,
			false,
			array("ID")
		);

		while ($obElement = $rsElement->GetNextElement())
		{
			$arFields = $obElement->GetFields();

			CIBlockElement::SetPropertyValuesEx($arFields["ID"], 16, $elementsProporties);
		}
	}

	public function updateElementPrice($article, $priceType, $price, $currency)
	{
		$arFilter = array(
			"IBLOCK_ID" => 16,
			"PROPERTY_CML2_ARTICLE" => $article
		);

		$rsElement = CIBlockElement::GetList(
			array(),
			$arFilter,
			false,
			false,
			array("ID", "IBLOCK_ID")
		);

		while ($obElement = $rsElement->GetNextElement())
		{
			$arFields = $obElement->GetFields();
			$elementId = $arFields["ID"];

			$arFields = Array(
				"PRODUCT_ID" => $elementId,
				"CATALOG_GROUP_ID" => $priceType,
				"PRICE" => $price,
				"CURRENCY" => $currency,
			);

			$p_res = CPrice::GetList(
				array(),
				array(
					"PRODUCT_ID" => $elementId,
					"CATALOG_GROUP_ID" => $priceType
				)
			);

			if ($arr = $p_res->Fetch()){
				CPrice::Update($arr["ID"], $arFields);
			}else{
				CPrice::Add($arFields);
			}
		}
	}

    public function getOrders($filter = [], $sort = [], $select = [], $limit = 0)
    {
        $orders = [];

        // Формируем фильтр для запроса
        $arFilter = [
            "ACTIVE" => "N",  // Только активные заказы
        ];

        // Добавляем фильтр по дате за последний месяц
        $currentDate = new \Bitrix\Main\Type\DateTime();
        $oneMonthAgo = clone $currentDate;
        $oneMonthAgo->add("-1 month");

        $arFilter[">=DATE_INSERT"] = $oneMonthAgo->format("d.m.Y H:i:s");
        $arFilter["<=DATE_INSERT"] = $currentDate->format("d.m.Y H:i:s");

        if (!empty($filter)) {
            if (is_string($filter['STATUS_ID'] ?? null)) {
                $arFilter['=STATUS_ID'] = explode(',', $filter['STATUS_ID']);
            } else {
                $arFilter = array_merge($arFilter, $filter);
            }
        }

        // Формируем параметры сортировки
        $arSort = [
            "ID" => "DESC", // Сортировка по ID в порядке убывания
        ];

        if (!empty($sort)) {
            $arSort = array_merge($arSort, $sort);
        }

        // Формируем список полей для выборки
        $arSelect = [
            "ID",
            "DATE_INSERT",
            "PRICE",
            "CURRENCY",
            "PERSON_TYPE_ID",
            "USER_ID",
            "STATUS_ID",
            "PAYED",
            "LID", // Добавляем ID сайта
            "ACCOUNT_NUMBER",
            "SOURCE_ID" // Добавляем поле для источника заказа
        ];

        if (!empty($select)) {
            $arSelect = array_merge($arSelect, $select);
        }

        // Получаем список заказов
        $rsOrders = CSaleOrder::GetList(
            $arSort,
            $arFilter,
            false,
            false,
            $arSelect
        );

        while ($order = $rsOrders->Fetch()) {
            // Получаем информацию о позициях заказа
            $orderProducts = $this->getOrderProducts($order["ID"]);
            $order["PRODUCTS"] = $orderProducts;

            // Получаем информацию о покупателе
            $order["BUYER"] = $this->getOrderBuyer($order["USER_ID"], $order["LID"]);

            // Получаем информацию об источнике заказа
            /**
             * ozon fbsozon@tempusshop.ru
             * yandex anonymous@market.yandex.ru
             * wb emailwb.ru
             * sber SBER-Marketplace
             */

            if ($this->getOrderBuyer($order["USER_ID"], $order["LID"])["LAST_NAME"] == "SBER-Marketplace") {
                $source = "sber";
            } elseif ($this->getOrderBuyer($order["USER_ID"], $order["LID"])["EMAIL"] == "fbsozon@tempusshop.ru") {
                $source = "ozon";
            } elseif ($this->getOrderBuyer($order["USER_ID"], $order["LID"])["EMAIL"] == "anonymous@market.yandex.ru") {
                $source = "yandex_market";
            } elseif (str_contains($this->getOrderBuyer($order["USER_ID"], $order["LID"])["EMAIL"], "@emailwb.ru")) {
                $source = "wb";
            } else {
                $source = "test";
            }

            $order["SOURCE"] = $source;

            $orders[] = $order;
        }

        return $orders;
    }

    private function getOrderSource($orderId)
    {
        // Пример получения источника заказа
        $dbOrderProps = CSaleOrderPropsValue::GetList(
            [],
            ["ORDER_ID" => $orderId, "CODE" => "SOURCE_ID"]
        );

        if ($arOrderProps = $dbOrderProps->Fetch()) {
            return $arOrderProps["VALUE"];
        }

        return null;
    }

	public function getOrderBuyer($userId, $siteId)
	{
		$buyer = [
			"ID" => $userId,
		];

		$userResult = CUser::GetByID($userId)->Fetch();
		if ($userResult) {
			$buyer["NAME"] = $userResult["NAME"];
			$buyer["LAST_NAME"] = $userResult["LAST_NAME"];
			$buyer["EMAIL"] = $userResult["EMAIL"];
		}

		$siteResult = CSite::GetByID($siteId)->Fetch();
		if ($siteResult) {
			$buyer["SITE_ID"] = $siteId;
			$buyer["SITE_NAME"] = $siteResult["NAME"];
		}

		return $buyer;
	}

    public function getOrderProducts($orderId)
    {
        $orderProducts = [];

        $order = \Bitrix\Sale\Order::load($orderId);
        $basket = $order->getBasket();

        foreach ($basket as $basketItem) {
            $product = [
                'ID' => $basketItem->getProductId(),
                'QUANTITY' => $basketItem->getQuantity(),
                'ARTICLE' => $this->getProductProperty($basketItem->getProductId(), 'CML2_ARTICLE'),
                'PRICE' => $basketItem->getPrice(),
                'CURRENCY' => $basketItem->getCurrency(),
            ];
            $orderProducts[] = $product;
        }

        return $orderProducts;
    }

	public function getProductProperty($productId, $propertyCode)
	{
		$propertyValue = null;
		$arFilter = [
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y",
			"ID" => $productId
		];
		$rsElement = CIBlockElement::GetList(
			array(),
			$arFilter,
			false,
			false,
			array("PROPERTY_" . $propertyCode)
		);

		if ($element = $rsElement->GetNextElement()) {
			$properties = $element->GetProperties();
			if (array_key_exists($propertyCode, $properties)) {
				$propertyValue = $properties;
			}
		}

		return $element->fields["PROPERTY_CML2_ARTICLE_VALUE"];
	}


	public function getOrderStatuses()
	{
		$statuses = [];

		// Получаем список всех статусов заказов
		$statusesList = \Bitrix\Sale\OrderStatus::getAllStatuses();

		// Получаем локализованные названия статусов
		$statusesLang = $this->getOrderStatusesLang(array_keys($statusesList));

		// Проходим по списку статусов и добавляем их в результирующий массив
		foreach ($statusesList as $statusId => $statusName) {
			$statuses[$statusId] = [
				"ID" => $statusId,
				"NAME" => $statusesLang[$statusId] ?? $statusName, // Используем локализованное название, если есть
			];
		}

		return $statuses;
	}

	protected function getOrderStatusesLang(array $statusIds)
	{
		$statusesLang = [];

		$statusLangResult = \Bitrix\Sale\Internals\StatusLangTable::getList([
			"filter" => [
				"=STATUS_ID" => $statusIds,
				"=LID" => 'ru', // ID текущего языка сайта
			],
			"select" => ["STATUS_ID", "NAME"],
		]);

		while ($statusLang = $statusLangResult->fetch()) {
			$statusesLang[$statusLang["STATUS_ID"]] = $statusLang["NAME"];
		}

		return $statusesLang;
	}

    public function getListSites()
    {
        $list = [];

        $list[1]["id"] = "s1";
        $list[1]["name"] = "tempusshop.ru";
        $list[2]["id"] = "s2";
        $list[2]["name"] = "tempus.by";
//        $list[3]["id"] = "s3";
//        $list[3]["name"] = "tempusshop.pl";
//        $list[4]["id"] = "s4";
//        $list[4]["name"] = "tempuswatch.kz";

        return $list;
    }

    public function getMsUid($article, $siteId){
        $bxId = '';
        $msId = [];

        $arFilter = array(
            "IBLOCK_ID" => 16,
            "PROPERTY_CML2_ARTICLE" => $article
        );

        $rsElement = CIBlockElement::GetList(
            array(),
            $arFilter,
            false,
            false,
            array("ID")
        );

        while ($obElement = $rsElement->GetNextElement())
        {
            $arFields = $obElement->GetFields();
            $bxId = $arFields["ID"];
            break; // Предполагаем, что нам нужен только первый найденный элемент
        }

        if ($bxId) {
            global $DB;
            $strSql = "SELECT MS_ID FROM `ci_ms_assortment` WHERE `BX_ID` = '{$bxId}' AND `SITE_ID` = '{$siteId}' LIMIT 1";
            $el = $DB->Query($strSql, false);
            if ($row = $el->Fetch()) {
                $msId["ms_uid"] = $row["MS_ID"];
            }
        }

        return $msId;
    }
}
