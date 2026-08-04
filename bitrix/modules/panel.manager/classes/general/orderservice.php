<?
//use Bitrix\Sale;

class OrderService{

	public $getPropOrderFlg = true;

	function __construct(){
		Cmodule::IncludeModule('main');
		Cmodule::IncludeModule('iblock');
		Cmodule::IncludeModule('catalog');
		Cmodule::IncludeModule('sale');
	}
	function getOrder($arOrder = array(), $filter = array(), $arNavStartParams = false){
		$arFilter = array("ACTIVE" => "Y",);
		$arResult = array();
		if($filter)	$arFilter = array_merge($arFilter, $filter);
		if(!$arOrder) $arOrder = array("DATE_INSERT" => "DESC");
//		if(!$arNavStartParams) $arNavStartParams = array("nTopCount" => 100);

//prent($arFilter);
		global $DB;
//		$arFilter = Array(
//		   ">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), mktime(0, 0, 0, date("n"), 1, date("Y")))
//		);

//prent($arFilter);
//$start = debug_microtime_float();
		$db_sales = CSaleOrder::GetList($arOrder, $arFilter, false, $arNavStartParams);
		while ($ar_sales = $db_sales->Fetch()){
			//prent($ar_sales);
			$arResult[] = $ar_sales;
		}
		$result = $this->prepareOrder($arResult);
//$end = debug_microtime_float();
//$asd = $end - $start;
//prent($asd);
		return $result;
	}
	function prepareOrder($arOrder){
		$res = array();

//
		$arID = array();
		foreach($arOrder as $order){
			$arID[] = $order["ID"];
		}
		$arOrderItemsAll = $this->getOrderItemsAll($arID);

		if($this->getPropOrderFlg)
			$arOrderProps = $this->getOrderPropDB($arID);

		//$arOrderPropsAll = $this->getOrderPropAll($arID);

		//prent($arOrderProps);
		//die;
		foreach($arOrder as $order){
			//if($this->getPropOrderFlg)
			//	$arOrderProps = $this->getOrderProp($order["ID"]);

			//MAXYSS_WB_NUMBER

			//$arOrderItems = $this->getOrderItems($order["ID"]);

			$res[] = array(
				"ID" 				=> $order["ID"],
				"XML_ID" 			=> $order["XML_ID"],
				"ORDER_ID"			=> $order["ACCOUNT_NUMBER"],
				"LID" 				=> $order["LID"],
				"PAYED" 			=> $order["PAYED"],
				"PAY_SYSTEM_ID" 	=> $order["PAY_SYSTEM_ID"],
				"DELIVERY_ID" 		=> $order["DELIVERY_ID"],
				"TRACKING_NUMBER"	=> $order["TRACKING_NUMBER"],
				"STATUS_ID" 		=> $order["STATUS_ID"],
				"CANCELED"			=> $order["CANCELED"],
				"DATE_INSERT" 		=> $order["DATE_INSERT"],
				"DATE_STATUS" 		=> $order["DATE_STATUS"],
				"DATE_UPDATE" 		=> $order["DATE_UPDATE"],
				"PRICE" 			=> $order["PRICE"],
				"USER_DESCRIPTION" 	=> $order["USER_DESCRIPTION"],
				"COMMENTS" 			=> $order["COMMENTS"],
				"FIO" => $arOrderProps[$order["ID"]]["FIO"],
				"EMAIL" => $arOrderProps[$order["ID"]]["EMAIL"],
				"PHONE" => $arOrderProps[$order["ID"]]["PHONE"],
				"ADDRESS" => $arOrderProps[$order["ID"]]["ADDRESS"],
				"LOCATION" => $arOrderProps[$order["ID"]]["LOCATION"],
				"DELIVERY_DATE" => $arOrderProps[$order["ID"]]["DELIVERY_DATE"],
				"MAXYSS_WB_NUMBER" => $arOrderProps[$order["ID"]]["MAXYSS_WB_NUMBER"],
				"MAXYSS_WB_STIKER" => $arOrderProps[$order["ID"]]["MAXYSS_WB_STIKER"],
				"MAXYSS_OP_STICKER" => $arOrderProps[$order["ID"]]["MAXYSS_OP_STICKER"],
				"YANDEX_NUMBER" => $arOrderProps[$order["ID"]]["YANDEX_NUMBER"],
				"OZON_NUMBER" => $arOrderProps[$order["ID"]]["OZON_NUMBER"],
				"ORDER_NUMBER_YA" => $arOrderProps[$order["ID"]]["ORDER_NUMBER_YA"],
				"OZON_LOWER_BARCODE" => $arOrderProps[$order["ID"]]["OZON_LOWER_BARCODE"],
				"SBER_ID" => $arOrderProps[$order["ID"]]["SBER_ID"],
				"ONLINER_ORDER_KEY" => $arOrderProps[$order["ID"]]["ONLINER_ORDER_KEY"],
				"STICKER_PRINT" => $arOrderProps[$order["ID"]]["STICKER_PRINT"],
				"MAXYSS_WB_CABINET" => $arOrderProps[$order["ID"]]["MAXYSS_WB_CABINET"],
				"OZON_ORDER_TYPE" => $arOrderProps[$order["ID"]]["OZON_ORDER_TYPE"],
				"AVITO_ORDER_NUMBER" => $arOrderProps[$order["ID"]]["AVITO_ORDER_NUMBER"],
				//"BASKET" => $arOrderItems,
				"BASKET" => $arOrderItemsAll[$order["ID"]],
			);
		}
		return $res;
	}
	function getOrderProp($order_id){
		if($order_id <= 0) return;
		/*$ar = array();
		$dbOrderProps = CSaleOrderPropsValue::GetList(array("SORT" => "ASC"),array("ORDER_ID" => $order_id));
		while ($res = $dbOrderProps->GetNext()){
			$ar[] = $res;
		}
		$arOrderProps = $this->prepareOrderProp($ar);
		*/
		$order = Bitrix\Sale\Order::load($order_id);
		$propertyCollection = $order->getPropertyCollection();
		$ar = $propertyCollection->getArray();
		$arOrderProps = $this->prepareOrderProp($ar["properties"]);

		//die;

		return $arOrderProps;
	}

	function getOrderPropAll($arID = array()){
		if(count($arID) <= 0) return;

		$obProps = Bitrix\Sale\Internals\OrderPropsValueTable::getList(array('filter' => array('ORDER_ID' => $arID)));
		while($prop = $obProps->Fetch()){
			$ar[] = $prop;
			prent($ar);

		}
		die;

		return $arOrderProps;
	}
	function prepareOrderProp($ar){
		//prent($ar);
		$arOrderProps = array();
		foreach($ar as $key => $arProp){
			//if($arProp["CODE"] == "FIO") $arOrderProps["FIO"] = $arProp["VALUE"];
			//if($arProp["CODE"] == "EMAIL") $arOrderProps["EMAIL"] = $arProp["VALUE"];
			//if($arProp["CODE"] == "PHONE") $arOrderProps["PHONE"] = $arProp["VALUE"];
			//if($arProp["CODE"] == "ADDRESS") $arOrderProps["ADDRESS"] = $arProp["VALUE"];
			//if($arProp["CODE"] == "LOCATION") $arOrderProps["LOCATION"] = $this->getLocation($arProp["VALUE"]);

			//if($arProp["CODE"] == "DELIVERY_DATE") $arOrderProps["DELIVERY_DATE"] = $arProp["VALUE"];

			if($arProp["CODE"] == "FIO") $arOrderProps["FIO"] = $arProp["VALUE"][0];
			if($arProp["CODE"] == "EMAIL") $arOrderProps["EMAIL"] = $arProp["VALUE"][0];
			if($arProp["CODE"] == "PHONE") $arOrderProps["PHONE"] = $arProp["VALUE"][0];
			if($arProp["CODE"] == "ADDRESS") $arOrderProps["ADDRESS"] = $arProp["VALUE"][0];
//			if($arProp["CODE"] == "LOCATION") $arOrderProps["LOCATION"] = $this->getLocation($arProp["VALUE"][0]);

			if($arProp["CODE"] == "DELIVERY_DATE") $arOrderProps["DELIVERY_DATE"] = $arProp["VALUE"][0];
			if($arProp["CODE"] == "MAXYSS_WB_NUMBER") $arOrderProps["MAXYSS_WB_NUMBER"] = $arProp["VALUE"][0];
		}

		return $arOrderProps;
	}
	function getLocation($loc_id){
		global $DB;
		if(!$loc_id) return;
		$strSql = "SELECT NAME FROM b_sale_loc_name WHERE LOCATION_ID = " . $loc_id . " AND LANGUAGE_ID = 'ru'";

		$res = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $res->Fetch()){
			return $row["NAME"];
		}
		return false;
	}
	function getOrderItemsAll($arIDs){
		if(count($arIDs) <= 0) return;

		$basketRes = Bitrix\Sale\Internals\BasketTable::getList(array(
			'filter' => array(
				'ORDER_ID' => $arIDs,
			)
		));

		while ($item = $basketRes->fetch()) {
			$arBasketItems[$item["ORDER_ID"]][] = $item;
		}

		return $arBasketItems;
	}

	function getOrderPropDB($arIDs){
		if(count($arIDs) <= 0) return;
		global $DB;

		$strSql = "SELECT ORDER_ID, CODE, VALUE FROM b_sale_order_props_value WHERE ORDER_ID IN ('" . implode("','", $arIDs) . "')";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		$arProp = array();
		while($row = $results->Fetch()){
			$arProp[$row["ORDER_ID"]][$row["CODE"]] = $row["VALUE"];
		}

		return $arProp;
	}


	function getOrderItems($order_id){
		if($order_id <= 0) return;

		$arBasketItems = array();
		$dbBasket = CSaleBasket::GetList(Array("ID"=>"ASC"), Array("ORDER_ID"=>$order_id));
		while ($arItems = $dbBasket->Fetch()){
			$arBasketItems[] = $arItems;
		}

		//prent($arBasketItems);die;
		//if($order_id == 3901)prent($arBasketItems);
		return $arBasketItems;
	}

	public static function setStatusOrder($order_id, $status = "N"){
		if($order_id <= 0) return false;
		if (CSaleOrder::StatusOrder($order_id, $status))
			return true;
		return false;
	}

	public static function setStatusOrderD7($order_num, $status = "N", $byNumber = false){
		if ($byNumber === true) {
			$accountNumber = trim($order_num);
			if(!$accountNumber) return false;
			$order = Bitrix\Sale\Order::loadByAccountNumber($accountNumber);
		} else {
			$order_id = intval($order_num);
			if($order_id <= 0) return false;
			$order = Bitrix\Sale\Order::load($order_id);
		}

		if($order){
			$order->setField("STATUS_ID", $status);
			$r = $order->save();
			if($r->isSuccess()){
				return true;
			}
		}

		return false;
	}

	function updateOrder($order_id, $arFields = array()){
		if($order_id <= 0 || count($arFields) <= 0) return;
		if (CSaleOrder::Update($order_id, $arFields))
			return true;
		return false;
	}
	function getStatusOrderList(){
		$status = CSaleStatus::GetList(array("SORT" => "ASC"), array("LID" => "ru"));
        while ($row = $status->Fetch()) {
            $statuses[$row["ID"]] = $row;
        }
		return $statuses;
	}
	function getPaySystemList(){
		$db_ptype = CSalePaySystem::GetList($arOrder = Array("SORT"=>"ASC"), Array("LID" => "s1", "ACTIVE"=>"Y"));
		while ($row = $db_ptype->Fetch()){
			$statuses[$row["ID"]] = $row;
		}
		return $statuses;
	}
	function getDeliveryNameList(){
		$db_ptype = CSaleDelivery::GetList($arOrder = Array("SORT"=>"ASC"), Array());
		while ($row = $db_ptype->Fetch()){
			$statuses[$row["ID"]] = $row["NAME"];
		}
		$db_ptype = CSaleDeliveryHandler::GetList($arOrder = Array("SORT"=>"ASC"), Array());
		while ($row = $db_ptype->Fetch()){
			//prent($row);
			$statuses[$row["ID"]] = $row["NAME"];
		}

		return $statuses;
	}
	function getSearchResults($orderId = "", $phone = "", $city = "", $address = "", $date = "", $product = "", $statusId = "", $websiteId = "", $kurierNickname = ""){
		$arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_CREATE", "DETAIL_TEXT", "PROPERTY_*",);
		$arFilter = array("IBLOCK_ID" => CPanelSet::IB_ORDERS, "ACTIVE" => "Y",);
		if($orderId)
			$arFilter["CODE"] = "%" . $orderId . "%";
		if($phone)
			$arFilter["PROPERTY_CONTACT_PHONE_1"] = "%" . $phone . "%";
		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while($ob = $res->GetNextElement()){
			$arFields = $ob->GetFields();
			$arFields["PROPS"] = $ob->GetProperties();
			$arResult[$arFields["ID"]] = $arFields;
			$arResult[$arFields["ID"]]["PROPS"] = $arFields["PROPS"];

		};
		if(count($arResult) > 0){
			$result = self::validateSearchResults($arResult);
			return $result;
		}

	}

	function validateSearchResults($items = array()){
		assert(is_array($items));
//		prent($items);
		$res = array();
		foreach($items as $key => $arItem){
			$res[] = array(
				"orderId" 			=> $arItem["ID"],
				"phone" 			=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"city" 				=> $arItem["PROPS"]["SHIPPING_CITY"]["VALUE"],
				"address" 			=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"date" 				=> $arItem["PROPS"]["SHIPPING_DATE"]["VALUE"],
//				"orderItems" => [
//					new OrderItem()
//				],
				"paymentTypeId" 	=> $arItem["PROPS"]["PAYMENT_TYPE_ID"]["VALUE"],
				"totalPrice" 		=> $arItem["PROPS"]["TOTAL_AMOUNT"]["VALUE"],
				"orderStatusId" 	=> $arItem["PROPS"]["ORDER_STATUS_ID"]["VALUE"],
				"orderStatus" 		=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"website" 			=> $arItem["PROPS"]["WEBSITE"]["VALUE"],
				"kurierNickname" 	=> $arItem["PROPS"]["KURIER_NICK_NAME"]["VALUE"],
				"kurierPhone"		=> $arItem["PROPS"]["KURIER_PHONE"]["VALUE"],
				"created" 			=> $arItem["DATE_CREATE"],
				"shippingDate" 		=> $arItem["PROPS"]["SHIPPING_DATE"]["VALUE"],
				"name" 				=> $arItem["PROPS"]["CONTACT_NAME_1"]["VALUE"],
				"comment" 			=> $arItem["DETAIL_TEXT"],
			);
		}
		return $res;
	}

	function find($orderID){
		assert(intval($orderID) > 0);
		$arFilter = array(
			"IBLOCK_ID" => CPanelSet::IB_ORDERS,
			"ACTIVE" => "Y",
//			"PROPERTY_ACTIVE_MANAGER_ID" => $currentUserID,
			"ID" => $orderID
		);
		$arSelect = array("ID", "IBLOCK_ID", "NAME", "DETAIL_TEXT", "PREVIEW_TEXT", "PROPERTY_*",);
		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		if($ob = $res->GetNextElement()){
			$arFields = $ob->GetFields();
			$arFields["PROPS"] = $ob->GetProperties();

			if(isset($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"]) && count($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"]) > 0)
				$arFields["ITEMS"] = self::getOrderItems($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"], $arFields["ID"]);
			$result = self::validateOrder($arFields);
			return $result;
		};
		return false;
	}
	function getOrderItems222($arItemID = array(), $orderID){
		if(count($arItemID) <= 0 || intval($orderID) <= 0) return;
//		prent($arItemID);
		$arFilter = array("IBLOCK_ID" => CPanelSet::IB_ORDER_ITEMS, "ACTIVE" => "Y",
		"ID" => $arItemID, "PROPERTY_ORDER_ID" => $orderID);
		$arSelect = array(
			"ID", "IBLOCK_ID", "NAME",
			"PROPERTY_ITEM_ID", "PROPERTY_ITEM_NAME",
			"PROPERTY_ITEM_PRICE", "PROPERTY_ITEM_DISCOUNT",
			"PROPERTY_COUNT", "PROPERTY_ORDER_ID", "PROPERTY_CONDITION"
		);

		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while($arFields = $res->GetNext()){
			$arResult["ITEMS"][] = $arFields;
		};
		return $arResult["ITEMS"];
	}
	function getSearchResultsAdmin($arOrder = array(), $filter = array(), $arNavStartParams = false){
	/*
		$arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_CREATE", "DETAIL_TEXT", "PROPERTY_*",);
		$arFilter = array("IBLOCK_ID" => CPanelSet::IB_ORDERS, "ACTIVE" => "Y",);
		if($filter)	$arFilter = array_merge($arFilter, $filter);
		$res = CIBlockElement::GetList($arOrder, $arFilter, false, $arNavStartParams, $arSelect);
		while($ob = $res->GetNextElement()){
			$arFields = $ob->GetFields();
			$arFields["PROPS"] = $ob->GetProperties();
			if(isset($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"]) && count($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"]) > 0)
				$arFields["ITEMS"] = self::getOrderItems($arFields["PROPS"]["ORDER_ITEMS"]["VALUE"], $arFields["ID"]);
			$arResult[$arFields["ID"]] = $arFields;
			$arResult[$arFields["ID"]]["PROPS"] = $arFields["PROPS"];
		};
		if(count($arResult) > 0){
			$result = self::validateSearchResultsAdmin($arResult);
			return $result;
		}
		*/
	}

	function validateSearchResultsAdmin($items = array()){
		assert(is_array($items));
//		prent($items);
		$res = array();
		foreach($items as $key => $arItem){
			$orderItem = array();
			if(isset($arItem["ITEMS"]) && count($arItem["ITEMS"]) > 0){
				foreach($arItem["ITEMS"] as $key => $arProd){
					$arAvail = array();
					if($arProd["PROPERTY_ITEM_ID_VALUE"] > 0){
						$arAvail = self::getItemPrice($arProd["PROPERTY_ITEM_ID_VALUE"], $arItem["paymentTypeId"]);
					}
					//prent($arProd["PROPERTY_ITEM_ID_VALUE"]);
					$orderItem[] = array(
						"id" 				=> $arProd["ID"],
						"productId" 		=> $arProd["ID"],
						"productElsyID" 	=> ((intval($arProd["PROPERTY_ITEM_ID_VALUE"]) > 0) ? (int)$arProd["PROPERTY_ITEM_ID_VALUE"] : 0),
						"preOrderItemId"	=> ((intval($arProd["PROPERTY_ITEM_ID_VALUE"]) > 0) ? (int)$arProd["PROPERTY_ITEM_ID_VALUE"] : 0),
						"name" 				=> $arProd["PROPERTY_ITEM_NAME_VALUE"],
						"price" 			=> (float)$arProd["PROPERTY_ITEM_PRICE_VALUE"],
						"priceReal" 		=> (($arAvail["price"]) ? $arAvail["price"] : 0),
						"count" 			=> (int)$arProd["PROPERTY_COUNT_VALUE"],
						"isVirtual"			=> ((intval($arProd["PROPERTY_ITEM_ID_VALUE"]) > 0) ? false : true),
						"isSold" 			=> false,
						"condition" 		=> $arProd["PROPERTY_CONDITION_VALUE"],
						"inStock" 			=> (($arAvail["inStock"]) ? true : false),
						"isDefected" 		=> false,
						"hasGuaranteeIssue" => false,
						"defectKurierCount" => 0,
					);
				}
			}
			$res[] = array(
				"orderId" 			=> $arItem["ID"],
				"phone" 			=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"city" 				=> $arItem["PROPS"]["SHIPPING_CITY"]["VALUE"],
				"address" 			=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"date" 				=> $arItem["PROPS"]["SHIPPING_DATE"]["VALUE"],
				"orderItems" 		=> $orderItem,
				"paymentTypeId" 	=> $arItem["PROPS"]["PAYMENT_TYPE_ID"]["VALUE"],
				"totalPrice" 		=> $arItem["PROPS"]["TOTAL_AMOUNT"]["VALUE"],
				"orderStatusId" 	=> $arItem["PROPS"]["ORDER_STATUS_ID"]["VALUE"],
				"orderStatus" 		=> $arItem["PROPS"]["CONTACT_PHONE_1"]["VALUE"],
				"condition" 		=> $arItem["PROPS"]["CONDITION"]["VALUE"],
				"website" 			=> $arItem["PROPS"]["WEBSITE"]["VALUE"],
				"kurierNickname" 	=> $arItem["PROPS"]["KURIER_NICK_NAME"]["VALUE"],
				"kurierPhone"		=> $arItem["PROPS"]["KURIER_PHONE"]["VALUE"],
				"created" 			=> $arItem["DATE_CREATE"],
				"shippingDate" 		=> $arItem["PROPS"]["SHIPPING_DATE"]["VALUE"],
				"name" 				=> $arItem["PROPS"]["CONTACT_NAME_1"]["VALUE"],
				"comment" 			=> $arItem["DETAIL_TEXT"],
			);

		}
		return $res;
	}

	function cancelRequestedOrder($orderId = 0){
		assert($orderId > 0);
		$order = $this->find($orderId);
		if ($order == null || $order == "" || $order == false){
			return array("status" => "error", "reason" => "Такого заказа не существует.");
		}
		if ($order["orderStatusGroupId"] == 5 || $order["orderStatusGroupId"] == 4){
			if($order["orderStatusGroupId"] == 5)
				$txtStatus = "Закрыт";
			elseif($order["orderStatusGroupId"] == 4)
				$txtStatus = "Уехал";
			return array("status" => "error", "reason" => "Заказ в статусе '{$txtStatus}' не может быть отменен.");
		}

		CIBlockElement::SetPropertyValueCode($orderId, "ORDER_STATUS_ID", 10);
		CIBlockElement::SetPropertyValueCode($orderId, "ACTIVE_MANAGER_ID", false);
//return array("status" => "error", "reason" => $order["orderStatusId"]);
		return array("status" => "success");

	}

	function markItemRequested($itemId = 0){
		assert($itemId > 0);
		$arSelect = Array("ID", "PROPERTY_ORDER_ID");
		$arFilter = Array("ID" => $itemId, "IBLOCK_ID" => CPanelSet::IB_ORDER_ITEMS);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		if($ar_fields = $result->GetNext()){
			CIBlockElement::SetPropertyValueCode($ar_fields["ID"], "CONDITION", "Y");
			self::updateMarkOrderRequested($ar_fields["PROPERTY_ORDER_ID_VALUE"]);
//			return $ar_fields["Y"];
		}
//		return $itemId;
//		return false;
	}
	function updateMarkOrderRequested($orderId = 0){
		assert($orderId > 0);
		$arFlag = array();
		$arSelect = Array("ID", "PROPERTY_CONDITION");
		$arFilter = Array("IBLOCK_ID" => CPanelSet::IB_ORDER_ITEMS, "PROPERTY_ORDER_ID" => $orderId);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while($ar_fields = $result->GetNext()){
			$arFlag[] = ($ar_fields["PROPERTY_CONDITION_VALUE"] == "Y" ? true : false);
		}

		//
		if(!in_array(false, $arFlag)) $fl = "Y"; else $fl = "N";
//		prent($fl);
		CIBlockElement::SetPropertyValueCode($orderId, "CONDITION", $fl);
//		return $itemId;
//		return false;
	}


	function getOrderCache($arOrder = array(), $filter = array(), $arNavStartParams = false){
		$arFilter = array("ACTIVE" => "Y",);
		$arResult = array();
		if($filter)	$arFilter = array_merge($arFilter, $filter);
		if(!$arOrder) $arOrder = array("DATE_INSERT" => "DESC");

        /*$cache = new CPHPCache();

        $cache_time = 120; //
        $cache_id = md5(serialize($arOrder).serialize($filter).serialize($arNavStartParams).$this->getPropOrderFlg);
		//prent($cache_id,0,1);
		//$cache_id = md5(json_encode($arOrder) . json_encode($filter) . json_encode($arNavStartParams) . $this->getPropOrderFlg);
		//$cache_id = "asdasdasdsad";
        $cache_path = '/panel.manager/orderservice/';
		$orders = [];

        if($cache->InitCache($cache_time, $cache_id, $cache_path))
        {
            $vars = $cache->GetVars();
            $orders = $vars['orders'];
			//prent("asdasd");
        }
        else
        {
            $cache->StartDataCache($cache_time, $cache_id, $cache_path);

            $orders = $this->getOrder($arOrder, $filter, $arNavStartParams);

            $cache->EndDataCache(array(
                'orders' => $orders,
            ));
        }*/
		$cacheRedis = \RedisCache::getInstance();
		$cache_time = 120;
		$cache_key = 'orderservice:' . md5(serialize($arOrder) . serialize($filter) . serialize($arNavStartParams) . $this->getPropOrderFlg);

		$orders = [];

		if ($cacheRedis->exists($cache_key)) {
			$orders = $cacheRedis->get($cache_key);
		} else {
			$orders = $this->getOrder($arOrder, $filter, $arNavStartParams);
			$cacheRedis->set($cache_key, $orders, $cache_time);
		}
		
		
		return $orders;
	}

    public static function Update(){
        DeleteDirFilesEx('/bitrix/cache/panel.manager/orderservice/');
        return true;
    }


	public static function getPropOrder($ID, $code){
		$resProps = CSaleOrderPropsValue::GetOrderProps($ID);
		while ($arProp = $resProps->Fetch()){
			if($arProp["CODE"] == $code) return $arProp;
		}
	}

	public static function getPropOrderD7($ID, $code, $byID = true){
		if(!\Bitrix\Main\Loader::includeModule('sale')) return false;

		if($byID === true){
			$order = \Bitrix\Sale\Order::load($ID);
		}else{
			$order = \Bitrix\Sale\Order::loadByAccountNumber($ID);
		}

		if($order){
			$propertyCollection = $order->getPropertyCollection();
			$property = $propertyCollection->getItemByOrderPropertyCode($code);
			if($property)
				return $property->getValue();
		}
		return false;
	}

	//мишим номер резерва из crm себе в базу
	public static function setOrderCrmID($orderID = "", $orderCrmID = ""){
		if(strlen($orderCrmID) < 0 || strlen($orderID) < 0) return false;
		global $DB;

		$strSql = "SELECT * FROM ci_order_crm WHERE ORDER_ID = '".addslashes($orderID)."'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		if(!$row = $results->Fetch()){
			$arFields = array(
				"ORDER_ID" =>  addslashes($orderID),
				"ORDER_CRM_ID" =>  addslashes($orderCrmID),
			);
			//AddMessage2Log($arFields);
			$ID = $DB->Add("ci_order_crm", $arFields);
		}

	}

	public function getOrderCrmID($arFilter = array()) {
        global $DB;
        $w = array("1=1");

        // filtering
        foreach($arFilter as $field => $val) {
            $field = strtoupper($field);

            if($val === null) {
                $w[] = " ".$field." IS NULL ";
            } elseif(is_array($val)){
				$w[] = " ".$field." IN ('" . implode("','", $val) . "') ";
			} else{
                $w[] = " ".$field." = '".$val."' ";
            }
        }

        // executing

		$strSql = "SELECT * FROM ci_order_crm WHERE ".implode(" AND ", $w);

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$ar = false;
		while ($row = $results->Fetch()){
			$ar[$row["ORDER_ID"]] = $row["ORDER_CRM_ID"];
		}
		return $ar;
    }

	// сохраняем все позиции в прайсах по корзине
	public static function setOrderPrice($ID, $arFields){
		if(!$ID) return;
		global $DB;

		if(is_array($arFields["BASKET_ITEMS"]) && count($arFields["BASKET_ITEMS"]) > 0){
			$arBasketIDs = array_column($arFields["BASKET_ITEMS"], "ID");

			// смотрим позиции которые уже записаны
			$arSaved = [];
			$strSql = "SELECT ORDER_BASKET_ID FROM ci_order_price WHERE ORDER_BASKET_ID IN ('".implode("','", $arBasketIDs)."')";

			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arSaved[] = $row["ORDER_BASKET_ID"];
			}

			$arProductIDs = [];
			foreach($arFields["BASKET_ITEMS"] as $k => $v){
				if(in_array($v["ID"], $arSaved)) continue;
				$arProductIDs[] = $v["PRODUCT_ID"];
			}

			//file_put_contents("/home/bitrix/logs/___1111.txt", print_r(["date" => date("Y-m-d H:i:s"), "ID" => $ID, "arProductIDs" => $arProductIDs], true), 8);
			if(count($arProductIDs) > 0){
				// ищем источник заказа
				$tpId = false;
				$strSql = "SELECT TRADING_PLATFORM_ID FROM b_sale_tp_order WHERE ORDER_ID = '{$ID}'";

				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$tpId = $row["TRADING_PLATFORM_ID"];
				}

				/*
				1 - ymarket
				2 - ebay
				3 - yamarket_turbo
				4 - yamarket_marketplace
				5 - yamarket_marketp_dbs
				6 - wb
				7 - sber
				8 - ozon
				*/
				$arTp = [
					1 => "ya",
					3 => "ya",
					4 => "ya",
					5 => "ya",
					6 => "wb",
					7 => "sb",
					8 => "ozti",
				];
				$arUserTrade = [
					172147 => "ozti",
				]; // массив с подменаме источника по юзеру
				if($tpId && $arTp[$tpId]){
					$priceID = $arTp[$tpId];
				}elseif($arUserTrade[$arFields["USER_ID"]]){
					$priceID = $arUserTrade[$arFields["USER_ID"]];
				}else{
					switch($arFields["LID"]){
						case "s1":
							$priceID = "ru";
							break;
						case "s2":
							$priceID = "by";
							break;
						case "s3":
							$priceID = "pl";
							break;
						case "s4":
							$priceID = "kz";
							break;
						default:
							$priceID = "ru";
							break;
					}
				}

				$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
					FROM
						b_iblock_element el
					LEFT JOIN
						b_iblock_element_prop_s16 pr
					ON el.ID=pr.IBLOCK_ELEMENT_ID
					WHERE
						el.ID IN ('".implode("','", $arProductIDs)."') AND el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
				//file_put_contents("/home/bitrix/logs/___1111.txt", print_r(["strSql" => $strSql], true), 8);
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);

				$arArticle = [];
				while ($row = $results->Fetch()){
					$arArticle[] = $row["ARTICLE"];
				}

				if(count($arArticle) > 0){
					$data = [
						"article" => implode(",", $arArticle),
						"website" => $priceID,
						"price" => "discount",
						"price_competitors" => "N",
						"price_competitors_act" => "N",
						"remove_duplicates" => "Y",
						"hide_rrc" => "N",
						"without_competitors" => "N",
						"only_active" => "Y",
						"ajax" => "Y",
					];
					$params = "";
					foreach($data as $k => $v){
						$params .= " {$k}={$v}";
					}

					$url = "/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/get_list.php {$params}";
					//file_put_contents("/home/bitrix/logs/___1111.txt", print_r(["url" => $url], true), 8);
					try{
						$json = shell_exec("/usr/bin/php81 -f {$url}");
						//file_put_contents("/home/bitrix/logs/___1111.txt", print_r(["json" => $json], true), 8);
						$tmp = json_decode($json, true);
						foreach($tmp as $k => $v){
							$arPrice[$v["b_id"]] = $v;
						}

					}catch(Exception $e){
						$arPrice = [];
					}

					if($tpId == 8){
						require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/OzonAPI.php');
						$ozon = new OzonAPI();

						$arSelect = Array("ID", "PROPERTY_WBARTICLE");
						$arFilter = Array(
							"IBLOCK_ID" => CProSet::IB_CATALOG,
							"ID" => $arProductIDs,
						);

						$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

						while ($el = $result->GetNext()){
							$arElement[$el['PROPERTY_WBARTICLE_VALUE']] = $el['ID'];
						}

						$arFilter = [
							"offer_id" => array_keys($arElement),
						];
						$prices = $ozon->getPrice($arFilter);

						if(is_array($prices["result"]) && is_array($prices["result"]["items"])){
							foreach($prices["result"]["items"] as $v){
								if(isset($arElement[$v["offer_id"]]) && is_array($v["price"])){
									$ID = $arElement[$v["offer_id"]];

									$spp = ((floatval($v["price"]["marketing_seller_price"]) - floatval($v["price"]["marketing_price"])) / floatval($v["price"]["marketing_seller_price"])) * 100;

									if(is_array($arPrice[$ID])){
										$arPrice[$ID]["spp"] = $spp;
									}else{
										$arPrice[$ID] = [
											"spp" => $spp
										];
									}
									//
								}


							}

						}
					}
					//file_put_contents("/home/bitrix/logs/___1111.txt", print_r(["arPrice" => $arPrice], true), 8);
					foreach($arFields["BASKET_ITEMS"] as $arItem){
						if($arPrice[$arItem["PRODUCT_ID"]]){
							$in = array(
								"ORDER_ID" => "'".addslashes($ID)."'",
								"ORDER_BASKET_ID" => "'".addslashes($arItem["ID"])."'",
								"PRICES" => "'".addslashes(serialize($arPrice[$arItem["PRODUCT_ID"]]))."'",
							);
							$DB->Insert("ci_order_price", $in, $err_mess.__LINE__);
						}
					}
				}

			}


		}
		// END сохраняем все позиции в прайсах по корзине

	}
}

?>
