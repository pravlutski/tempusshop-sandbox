<?php
/**
 * exchange
 */
class CExchange{
	public $CNT_AVAIL = 0;
	public $CNT_STOCK = 0;
	public $CNT_NO = 0;

	public $site_id;
	public $supplier_id;
	public $options_cnt;
	private $last_exch;
	public static $allCnt;
	public static $arPrice;

	public $_log;
	public $msStore;

	public static $arPriceMS = array();//массив с товарами МойСклад
	public static $MSPosition = array();

	public static $MSRetailDemand = array();
	public static $MSRetailReturn = array();

	public static $ms_login;
	public static $ms_pass;

	public function  __construct($site = "s1"){
		self::$arPriceMS = array();
		if($site == "s1"){
			$this->supplier_id = CProSet::ID_SupplierGoogleDocsRU;
			$this->options_cnt = "UPDATE_STOCK_RU";

			self::$ms_login = "admin@tempusint";
			self::$ms_pass = "48539dd3a8";

			$this->msStore = array("79ed7d71-0aa6-11ea-0a80-004200039aa4");//, "093c792f-0ae4-11ea-0a80-0256000b6f8f"
			$this->last_exch = "MOYSKLAD_LAST_EXCH_RU";
		}elseif($site == "s2"){
			$this->supplier_id = CProSet::ID_SupplierGoogleDocs;
			$this->options_cnt = "UPDATE_STOCK_BY";

			self::$ms_login = "admin@tempusby";
			self::$ms_pass = "107c779c77";

			$this->msStore = array("6f6d2169-180c-11ea-0a80-00b30004eaef");
			$this->last_exch = "MOYSKLAD_LAST_EXCH_BY";
		}elseif($site == "s3"){
			$this->supplier_id = CProSet::ID_SupplierGoogleDocsPL;
			$this->options_cnt = "UPDATE_STOCK_PL";

			self::$ms_login = "admin@tempuspl";
			//self::$ms_pass = "20a12579ee";
			self::$ms_pass = "07a21edaa6b9";
			$this->msStore = array("d25a4d55-3aa6-11ea-0a80-038400072f77");
			$this->last_exch = "MOYSKLAD_LAST_EXCH_PL";
		}elseif($site == "s1_order"){
			$this->supplier_id = 82;
			$this->options_cnt = "UPDATE_STOCK_ORDER_RU";

			self::$ms_login = "admin@tempusint";
			self::$ms_pass = "48539dd3a8";

			$this->last_exch = "MOYSKLAD_LAST_EXCH_ORDER_RU";
		}else{
			die("no site");
		}
		$this->site_id = $site;
		CModule::IncludeModule('panel.manager');

		self::getPrices();

	}
    static function init() {
        CModule::IncludeModule('iblock');
		CModule::IncludeModule('main');
        CModule::IncludeModule('catalog');
		CModule::IncludeModule('sale');
    }
	/**
     * функция для товара после удаления/добавления одного товара
     *
     */

	public function getProduct($ID = false, $IBLOCK_ID = false){
		global $DB;
		if(!$ID || !$IBLOCK_ID || !in_array($IBLOCK_ID, array(CProSet::IB_CATALOG, CProSet::IB_CATALOG_SKU))) return false;
		$PROP_ID = ($IBLOCK_ID == CProSet::IB_CATALOG ? CProSet::PROP_ID_ARTICLE : CProSet::PROP_ID_ARTICLE_SKU);

		//$strSql = "SELECT el.SORT as SORT, el.ID as ID, el.CODE as CODE, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ID = '{$ID}' AND el.IBLOCK_ID = '{$IBLOCK_ID}' AND pr.IBLOCK_PROPERTY_ID = '{$PROP_ID}'";
		$strSql = "SELECT el.SORT as SORT, el.SORT_RU as SORT_RU, el.SORT_BY as SORT_BY, el.SORT_PL as SORT_PL, el.SHOW_COUNTER as SHOW_COUNTER, el.ID as ID, el.CODE as CODE, pr.PROPERTY_123 as ARTICLE
		FROM
			b_iblock_element el
		LEFT JOIN
			b_iblock_element_prop_s16 pr
		ON el.ID=pr.IBLOCK_ELEMENT_ID
		WHERE
			el.ID = '{$ID}' AND el.IBLOCK_ID = '{$IBLOCK_ID}' AND pr.PROPERTY_123 <> ''";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	public function getIblockID($ID = false){
		global $DB;
		if(!$ID) return false;

		$strSql = "SELECT IBLOCK_ID FROM b_iblock_element WHERE ID = '{$ID}'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["IBLOCK_ID"];
		}
		return false;
	}
	public function updateProduct($ID = 0, $IBLOCK_ID = 16){
		global $DB;
		file_put_contents('/home/bitrix/logs/updateProductTable.txt', print_r(["ID" => $ID,], true), 8);
		if($_arFields = self::getProduct($ID, $IBLOCK_ID)){

			$arAvailability = array();
			$arQuantity = array();

			$sort_ru = $sort_by = $sort_pl = 500;

			if(strlen($_arFields["ARTICLE"])){
				$el = new CIBlockElement;
				//$strSql = "SELECT active, active_by, active_pl, id, store_id, supplier_id, count FROM ci_price WHERE model = '".$_arFields["ARTICLE"]."' AND (active='Y' OR active_by='Y' OR active_pl='Y') ORDER BY store_id DESC";
				$strSql = "SELECT active, active_by, active_pl, store_id FROM ci_price WHERE model = '".$_arFields["ARTICLE"]."' AND (active='Y' OR active_by='Y' OR active_pl='Y') ORDER BY store_id DESC";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				$flg = false;
				//$sort = 500;
				while ($row = $results->Fetch()){
					$flg = true;
					if($row["store_id"] == 1){
						if($row["active"] == "Y"){
							//if($sort_ru != 10) $sort_ru = 50;
							$sort_ru = 10;
							$arAvailability["ru"][] = "store";//В наличии (склад)
							break;
						}
						if($row["active_by"] == "Y"){
							$sort_by = 10;//OK
							$arAvailability["by"][] = "in_stock";//В наличии (Немига-3)
						}
						if($row["active_pl"] == "Y"){
							if($sort_pl != 10) $sort_pl = 50;
							//$sort_pl = 10;
							$arAvailability["pl"][] = "store";//В наличии (склад)
						}
						$arQuantity[] = 500;
					}elseif($row["store_id"] == 2){
						if($row["active"] == "Y"){
							$sort_ru = 10;//OK
							$arAvailability["ru"][] = "in_stock";//В наличии
							//break;
						}
						if($row["active_by"] == "Y"){
							$arAvailability["by"][] = "store";//В наличии (склад)
							$sort_by = 50;//OK
						}
						if($row["active_pl"] == "Y"){
							$arAvailability["pl"][] = "store";//В наличии (склад)
							$sort_pl = 10;//OK
						}
						$arQuantity[] = 50;
					}elseif($row["store_id"] == 3){

						$flg = true;

						if($row["active"] == "Y"){
							//$sort = 200;//у поставщика
							$sort_ru = 10;
							$arAvailability["ru"][] = "store";//В наличии (склад)
						}
						if($row["active_by"] == "Y"){
							$arAvailability["by"][] = "store";//В наличии (склад)
							$sort_by = 200;
						}
						if($row["active_pl"] == "Y"){
							$arAvailability["pl"][] = "store";//В наличии (склад)
							$sort_pl = 200;
						}
						$arQuantity[] = 50;
					}
				}

				$sort_ru -= (float)$_arFields["SHOW_COUNTER"] / 1000000;
				$sort_by -= (float)$_arFields["SHOW_COUNTER"] / 1000000;
				$sort_pl -= (float)$_arFields["SHOW_COUNTER"] / 1000000;
			//prent($sort_ru);
				if($flg && ($_arFields["SORT_RU"] != $sort_ru || $_arFields["SORT_BY"] != $sort_by || $_arFields["SORT_PL"] != $sort_pl)){
					$arLoadProductArray = Array(
						//"SORT"	=> $sort,
						"SORT_RU"	=> $sort_ru,
						"SORT_BY"	=> $sort_by,
						"SORT_PL"	=> $sort_pl,
					);
					$rs = $el->Update($_arFields["ID"], $arLoadProductArray);

					//$arFields = array("ID" => $_arFields["ID"], "IBLOCK_ID" => 16);
					//TsIblock::setPropAvailable($arFields);
				//prent($arFields);
				}

			}else{
				if($_arFields["SORT_RU"] != 500 || $_arFields["SORT_BY"] != 500 || $_arFields["SORT_PL"] != 500)
					$DB->Update("b_iblock_element", array("SORT_RU" => 500,"SORT_BY" => 500,"SORT_PL" => 500), "WHERE ID='".$_arFields["ID"]."'", $err_mess.__LINE__);
			}

			$arFields = array("ID" => $_arFields["ID"], "IBLOCK_ID" => 16);
			TsIblock::setPropAvailable($arFields);

			//обновляем доступное количество
			if(count($arQuantity) > 0)
				$quantity = max($arQuantity);
			else
				$quantity = -5;
			if($quantity > 0) $avail = "Y"; else $avail = "N";
			$in = array(
				"QUANTITY"	=> $quantity,
				"AVAILABLE"	=> "'".$avail."'",
			);

			$DB->Update("b_catalog_product", $in, "WHERE ID='".$_arFields["ID"]."'", $err_mess.__LINE__);

			//self::clearCacheElement($_arFields["CODE"]);
			clearElementCache($IBLOCK_ID, $ID);
			self::updateProductTable($_arFields["ID"]);
			//обновляем свойство наличия
			if($IBLOCK_ID == CProSet::IB_CATALOG)
				self::setAvailability($_arFields["ID"], $arAvailability, $arFields["SET_PRICES"]);
			return true;
		}

		CIBlockTools::Update();
		CProSet::setOption("RUN_CONTROL", "Y");//обновляем значения в кроне
		return false;
	}
    /**
     * функция для обновления каталога после обмена
     *
     */
	//обновляем только изменения
	public function updateCatalogDiff(){
		if(!CModule::IncludeModule('panel.manager')) return;
		global $DB;
		$arItems = array();

		$strSql = "SELECT model FROM ci_items_diff GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$flg = false;
		while ($row = $results->Fetch()){
			$arItems[] = $row["model"];
		}

		if(count($arItems) > 0){
			foreach($arItems as $model){

				$ar = CPanelProduct::findArticleAll($model);
				//prent($ar);
				foreach($ar as $key => $arItem){
					//prent($arItem);
					self::updateProduct($arItem["ID"], $arItem["IBLOCK_ID"]);
				}
				$DB->Query("DELETE FROM ci_items_diff WHERE model = '".addslashes($model)."'", false, $err_mess.__LINE__);
			}

			//CExchange::forceYmarket("s1");
			//CExchange::forceYmarket("s2");
			CExchange::forceYmarket("s3");

			return true;
		}
		return false;
		//$DB->Query("TRUNCATE TABLE ci_items_diff", false, $err_mess.__LINE__);
	}

	function clearCache() {
	}
	static function clearCacheElement($code = "") {
		BXClearCache(true, "/s1/op/catalog.element/{$code}/");
		BXClearCache(true, "/s2/op/catalog.element/{$code}/");
		BXClearCache(true, "/s3/op/catalog.element/{$code}/");
	}
	static public function getPrices() {
		global $DB;
		$arPrice = array();
//		$strSql = "SELECT store_id, supplier_id, model FROM ci_price ORDER BY store_id DESC";
		$strSql = "SELECT active, active_by, active_pl, active_wb, id, store_id, supplier_id, model FROM ci_price WHERE active='Y' OR active_by='Y' OR active_pl='Y' OR active_wb='Y' ORDER BY store_id DESC";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$flg = false;
		while ($row = $results->Fetch()){
			//$arPrice[$row["model"]][] = $row;
			self::$arPrice[$row["model"]][] = $row;
		}
		//$this->arPrice = $arPrice;
	}

	static private function getAllCnt(){
		global $DB;
		$strSql = "SELECT el.ID FROM b_iblock_element el WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') ORDER BY el.ID ASC";
		self::$allCnt = $DB->Query($strSql, false, $err_mess.__LINE__)->SelectedRowsCount();
	}
	/*
	* Обновляем значение во временной таблице ci_price_catalog с ценами
	*/

	static public function updateProductTable($ID) {
		global $DB;

		$ID = intval($ID);
		if($ID <= 0) return false;

		$arSelect = Array("ID", "CODE", "DETAIL_PAGE_URL", "PROPERTY_CML2_ARTICLE",
			"PROPERTY_WBPRICE", "PROPERTY_OZSB_PRICE",
			"PROPERTY_AVITO_PRICE", "PROPERTY_SBER_PRICE",
			"PROPERTY_PRICE_KZ", "PROPERTY_PRICE_OZKZ", "PROPERTY_WBTL_PRICE"
		);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"ID" 		=> $ID,
		);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);



		if ($el = $result->GetNext()){

			//удаляем строку
			$DB->Query("DELETE FROM ci_price_catalog WHERE product_id='" . $ID . "'");
			//берем цены
			$arS1 = AHCatalog::OnGetOptimalPrice($el["ID"], 1, array(), "N", array(), "s1");
			$arS2 = AHCatalog::OnGetOptimalPrice($el["ID"], 1, array(), "N", array(), "s2");
			$arS3 = AHCatalog::OnGetOptimalPrice($el["ID"], 1, array(), "N", array(), "s3");

			$b_price = CCurrencyRates::ConvertCurrency($arS1["PRICE"]["PRICE"], $arS1["PRICE"]["CURRENCY"], "RUB");
			$b_price = round($b_price, -1);
			$b_price_dis = CCurrencyRates::ConvertCurrency($arS1["RESULT_PRICE"]["DISCOUNT_PRICE"], $arS1["RESULT_PRICE"]["CURRENCY"], "RUB");
			$b_price_dis = round($b_price_dis, -1);

			$arTmp["PRICE1"] = array(
				"PRICE" => $b_price,
				"DISCOUNT_PRICE" => $b_price_dis,
			);

			$b_price = CCurrencyRates::ConvertCurrency($arS2["PRICE"]["PRICE"], $arS2["PRICE"]["CURRENCY"], "BYN");
			$b_price = round($b_price, 0);
			$b_price_dis = CCurrencyRates::ConvertCurrency($arS2["RESULT_PRICE"]["DISCOUNT_PRICE"], $arS2["RESULT_PRICE"]["CURRENCY"], "BYN");
			$b_price_dis = round($b_price_dis, 0);

			$arTmp["PRICE2"] = array(
				"PRICE" => $b_price,
				"DISCOUNT_PRICE" => $b_price_dis,
			);

			$b_price = CCurrencyRates::ConvertCurrency($arS3["PRICE"]["PRICE"], $arS3["PRICE"]["CURRENCY"], "PLZ");
			$b_price = round($b_price, 0);
			$b_price_dis = CCurrencyRates::ConvertCurrency($arS3["RESULT_PRICE"]["DISCOUNT_PRICE"], $arS3["RESULT_PRICE"]["CURRENCY"], "PLZ");
			$b_price_dis = round($b_price_dis, 0);

			$arTmp["PRICE3"] = array(
				"PRICE" => $b_price,
				"DISCOUNT_PRICE" => $b_price_dis,
			);

			$in = array(
				"product_id" => intval($el["ID"]),
				"product_code" => "'".addslashes($el["CODE"])."'",
				"model" => "'".addslashes($el["PROPERTY_CML2_ARTICLE_VALUE"])."'",
				"detail_page_url" => "'".addslashes($el["DETAIL_PAGE_URL"])."'",
				"price_by" => (float)$arTmp["PRICE2"]["PRICE"],
				"price_discount_by" => (float)$arTmp["PRICE2"]["DISCOUNT_PRICE"],
				"price_ru" => (float)$arTmp["PRICE1"]["PRICE"],
				"price_discount_ru" => (float)$arTmp["PRICE1"]["DISCOUNT_PRICE"],
				"price_pl" => (float)$arTmp["PRICE3"]["PRICE"],
				"price_discount_pl" => (float)$arTmp["PRICE3"]["DISCOUNT_PRICE"],
				"price_wb" => (float)$el["PROPERTY_WBPRICE_VALUE"],
				"price_wbtl" => (float)$el["PROPERTY_WBTL_PRICE_VALUE"],
				"price_os" => (float)$el["PROPERTY_OZSB_PRICE_VALUE"],
				"price_av" => (float)$el["PROPERTY_AVITO_PRICE_VALUE"],
				"price_sb" => (float)$el["PROPERTY_SBER_PRICE_VALUE"],
				"price_kz" => (float)$el["PROPERTY_PRICE_KZ_VALUE"],
				"price_ozkz" => (float)$el["PROPERTY_PRICE_OZKZ_VALUE"],
			);

//			prent($in);
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_price_catalog", $in, $err_mess.__LINE__);
		}

	}

	private function setNoAvailability(){
		global $DB;
		//492 - В наличии (Немига-3)
		//493 - В наличии (склад)
		//494 - Нет в наличии
		//$DB->Update("b_iblock_element_property", array("VALUE" => "'494'"), "WHERE IBLOCK_PROPERTY_ID='267'", $err_mess.__LINE__);
		//$DB->Update("b_iblock_element_prop_s16", array("PROPERTY_267" => "'494'"), "WHERE 1=1", $err_mess.__LINE__);
	}
	static private function setAvailability($ID, $arAvailability = array(), $arSet = array()){

		$arCheck = self::controlReserve($ID);

		//наличие BY
		//492 - В наличии (Немига-3)
		//493 - В наличии (склад)
		//494 - Нет в наличии
		//AddMessage2Log($arSet);
		if($ID <= 0) return;
		if(in_array("in_stock", $arAvailability["by"]) && $arSet["MINIMUM_PRICE_RB"] > 0 && $arCheck["s2"]){
			$val = 492;
		}elseif(in_array("store", $arAvailability["by"]) && $arSet["MINIMUM_PRICE_RB"] > 0 && $arCheck["s2"]){
			$val = 493;
		}else{
			$val = 494;
		}
		CIBlockElement::SetPropertyValuesEx($ID, CProSet::IB_CATALOG, array("AVAILABILITY_BY" => "{$val}"));

		//наличие RU
		//512 - В наличии
		//514 - Нет в наличии
		if((in_array("in_stock", $arAvailability["ru"]) || in_array("store", $arAvailability["ru"])) && $arSet["MINIMUM_PRICE"] > 0 && $arCheck["s1"]){
			$val = 512;
		}else{
			$val = 514;
		}
		CIBlockElement::SetPropertyValuesEx($ID, CProSet::IB_CATALOG, array("AVAILABILITY_RU" => "{$val}"));

		//наличие PL
		if(in_array("store", $arAvailability["pl"]) && $arSet["MINIMUM_PRICE_PL"] > 0 && $arCheck["s3"]){
			$val = 548;
		}else{
			$val = 549;
		}
		CIBlockElement::SetPropertyValuesEx($ID, CProSet::IB_CATALOG, array("AVAILABILITY_PL" => "{$val}"));

		\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CProSet::IB_CATALOG, $ID);
	}

	//проверяем доступен ли товар с учетом резервов
	static public function controlReserve($ID){

		if(!$ID) return false;
		global $DB;

		$arCheck = array("s1" => true, "s2" => true, "s3" => true);

		$strSql = "SELECT * FROM ci_reserved WHERE PRODUCT_ID = '{$ID}'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arCheck["s1"] = ($row["RESERVED"] < $row["AVAILABLE_RU"] ? true : false);
			$arCheck["s2"] = ($row["RESERVED"] < $row["AVAILABLE_BY"] ? true : false);
			$arCheck["s3"] = ($row["RESERVED"] < $row["AVAILABLE_PL"] ? true : false);
		}

		return $arCheck;
	}

	//обновляем список товаров в резерве
	static public function updateReserved(){

		if(!CModule::IncludeModule('panel.manager')) return false;
		global $DB;

		$objPricelist = new CPanelPricelist;
		$objService = new OrderService;

		$strSql = "SELECT * FROM ci_catalog_artnumbers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$arAlternative[$row["artnumber"]][] = $row["alternative"];
			$arLink[$row["alternative"]] = $row["artnumber"];
		}

		$arFilter = array(
			"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CL"),
			"!CANCELED" => "Y",
		);
		$ar = array();
		$arOrder = $objService->getOrder(array(), $arFilter);

		$arReserveAll = $arReserveSite = array();
		foreach($arOrder as $key => $arItem){
			foreach($arItem["BASKET"] as $k => $arBasket){
				$arReserveAll[$arBasket["PRODUCT_ID"]] += $arBasket["QUANTITY"];
				$arIDs[$arBasket["PRODUCT_ID"]] = $arBasket["PRODUCT_ID"];

				$arReserveSite[$arBasket["PRODUCT_ID"]][$arItem["LID"]] += $arBasket["QUANTITY"];
			}
		}

		if(count($arIDs) > 0){

			$strSql = "SELECT product_id as ID, model as ARTICLE
				FROM
					ci_price_catalog
				WHERE
					product_id IN ('" . implode("','", $arIDs) . "')";

				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$arArticleMain[$row["ID"]] = $row["ARTICLE"];

					$arArticle[] = $row["ARTICLE"];

					if($arAlternative[$row["ARTICLE"]]) {
						foreach($arAlternative[$row["ARTICLE"]] as $model){
							$arArticle[] = $model;
						}
					}
				}
		}


		if(count($arReserveAll) > 0){

			$arFilter["article"] = $arArticle;

			$tmp = $objPricelist->getPriceByFilter($arFilter, false, array("active", "active_by", "active_pl", "model", "count"));

			foreach($tmp as $k => $v){
				$model = ($arLink[$v["model"]] ? $arLink[$v["model"]] : $v["model"]);
				if($v["active"] == "Y")
					$arPrice["s1"][$model] += $v["count"];
				if($v["active_by"] == "Y")
					$arPrice["s2"][$model] += $v["count"];
				if($v["active_pl"] == "Y")
					$arPrice["s3"][$model] += $v["count"];
			}

		}
		//prent($arPrice);die;
		$arAdd = array();
		foreach($arReserveAll as $product_id => $cnt_reserve){

				$article = $arArticleMain[$product_id];
				$arAdd[] = array(
					"PRODUCT_ID" => $product_id,
					"ARTICLE" => $article,
					"AVAILABLE_RU" => intval($arPrice["s1"][$article]),
					"AVAILABLE_BY" => intval($arPrice["s2"][$article]),
					"AVAILABLE_PL" => intval($arPrice["s3"][$article]),
					"RESERVED" => $cnt_reserve,
					"RESERVED_s1" => $arReserveSite[$product_id]["s1"],
					"RESERVED_s2" => $arReserveSite[$product_id]["s2"],
					"RESERVED_s3" => $arReserveSite[$product_id]["s3"],
				);

		}

		//запоминаем что было в массиве
		$strSql = "SELECT * FROM ci_reserved";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row["ARTICLE"])
				$arHistory[$row["ARTICLE"]] = md5($row["AVAILABLE_RU"].$row["AVAILABLE_BY"].$row["AVAILABLE_PL"].$row["RESERVED"]);
		}

		$DB->Query("TRUNCATE TABLE ci_reserved", false, $err_mess.__LINE__);

		foreach($arAdd as $key => $arItem){
			//if($arItem["RESERVED"] >= $arItem["AVAILABLE_RU"] || $arItem["RESERVED"] >= $arItem["AVAILABLE_BY"] || $arItem["RESERVED"] >= $arItem["AVAILABLE_PL"]){

				$in = array(
					"PRODUCT_ID" => "'".addslashes($arItem["PRODUCT_ID"])."'",
					"ARTICLE" => "'".addslashes($arItem["ARTICLE"])."'",
					"AVAILABLE_RU" => "'".addslashes($arItem["AVAILABLE_RU"])."'",
					"AVAILABLE_BY" => "'".addslashes($arItem["AVAILABLE_BY"])."'",
					"AVAILABLE_PL" => "'".addslashes($arItem["AVAILABLE_PL"])."'",
					"RESERVED" => "'".addslashes($arItem["RESERVED"])."'",
					"RESERVED_s1" => "'".addslashes($arItem["RESERVED_s1"])."'",
					"RESERVED_s2" => "'".addslashes($arItem["RESERVED_s2"])."'",
					"RESERVED_s3" => "'".addslashes($arItem["RESERVED_s3"])."'",
				);

				//пишем всё во временную таблицу сразу
				$DB->Insert("ci_reserved", $in, $err_mess.__LINE__);

			//}
		}

		//смотрим что стало в массиве
		$strSql = "SELECT * FROM ci_reserved";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row["ARTICLE"])
				$arNew[$row["ARTICLE"]] = md5($row["AVAILABLE_RU"].$row["AVAILABLE_BY"].$row["AVAILABLE_PL"].$row["RESERVED"]);
		}

		//смотрим разницу и добавляем в таблицу для обновления элементов
		foreach($arHistory as $article => $val){
			if(!$arNew[$article] || $arNew[$article] != $val){
				$arAll[$article] = $article;
			}
		}
		foreach($arNew as $article => $val){
			if(!$arHistory[$article] || $arHistory[$article] != $val){
				$arAll[$article] = $article;
			}
		}

		if(count($arAll) > 0){
			//prent($arAll);
			$objPricelist->addItemsDiff($arAll);
		}
	}

	static private function createIndexAll(){
		$index = \Bitrix\Iblock\PropertyIndex\Manager::createIndexer(CProSet::IB_CATALOG);
		$index->startIndex();
		$index->continueIndex(0); // создание без ограничения по времени
		$index->endIndex();

		$index = \Bitrix\Iblock\PropertyIndex\Manager::createIndexer(CProSet::IB_CATALOG_SKU);
		$index->startIndex();
		$index->continueIndex(0); // создание без ограничения по времени
		$index->endIndex();
	}
	public function getBrandName(){
		$ar = array();
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_BRANDS,
		);
		$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
		while($arFields = $result->GetNext()){
			$ar[$arFields["ID"]] = $arFields["NAME"];
		}
		return $ar;
	}

	//удаление папки с композитом
	static public function clearCacheComposite(){

		system("rm -rf /var/www/bitrix/data/www/tempusshop.ru/bitrix/html_pages/www.tempus.by/catalog >/dev/null 2>&1 &");
		system("rm -rf /var/www/bitrix/data/www/tempusshop.ru/bitrix/html_pages/tempusshop.ru/catalog >/dev/null 2>&1 &");

	}

	function updateFromMoySklad(){
		CModule::IncludeModule('panel.manager');
		global $DB;

		$this->MScheckLastChanges();

		$objMS = new MoyskladAPI($this->site_id);

		foreach($this->msStore as $store_id){
			//if(strlen($store_id) > 0) $filter = "store.id=" . $store_id; else $filter = "";
			//$objMS->getStock(0, $filter);
			if(strlen($store_id) > 0)
				$filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id}";
			else
				$filter = "";
			$objMS->getStock(0, $filter);

		}
		$arBrandName = $this->getBrandName();
//if($this->supplier_id == CProSet::ID_SupplierGoogleDocsRU){
//	file_put_contents("/home/bitrix/price.ru.txt", print_r($objMS->MSPosition, true));
//}
		$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
			FROM
				b_iblock_element el
			LEFT JOIN
				b_iblock_element_prop_s16 pr
			ON el.ID=pr.IBLOCK_ELEMENT_ID
			WHERE
				el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

		$bxID = array();
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			if(strlen($row["ARTICLE"]) > 0)
				$bxID[$row["ARTICLE"]] = $row["ID"];
		}

		if (count($objMS->MSPosition) > 0){
			global $DB;
			/* получаем все артнамберы */
			$arFilter = Array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_CML2_ARTICLE" => false,
				"XML_ID" => array_keys($objMS->MSPosition),
			);
			$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"));
			while($arFields = $result->GetNext()){
				$arModels[$arFields['XML_ID']] = array(
					"ARTICLE" => $arFields['PROPERTY_CML2_ARTICLE_VALUE'],
					"BRAND" => mb_strtoupper($arBrandName[$arFields['PROPERTY_BRAND_VALUE']]),
					"BRAND_ID" => $arFields['PROPERTY_BRAND_VALUE'],
				);
			}

			$arBrandName = array();
			foreach ($objMS->MSPosition as $key => $arItem){
				if (isset($arModels[$arItem['XML_ID']])){
					$price = round($arItem["PRICE"] / 100, 2);

					$brand = $arModels[$arItem['XML_ID']]["BRAND"];
					$arBrandName[$brand] = array(
						"ID" => $arModels[$arItem['XML_ID']]["BRAND_ID"],
						"NAME" => $brand,
					);
					//$brand;

					$avl_models[] = array(
						"ARTICLE" => $arModels[$arItem['XML_ID']]["ARTICLE"],
						"BRAND" => $brand,
						"BRAND_ID" => $arModels[$arItem['XML_ID']]["BRAND_ID"],
						"PRICE" => (double)$price,
						"COUNT" => $arItem["COUNT"],
					);
				}
			}

			foreach($arBrandName as $brand){
				/* Получаем поставщика Google Docs */
				$strSql = "SELECT * FROM ci_brands WHERE name = '" . $brand["NAME"] . "' OR bitrix_id = '{$brand["ID"]}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$base_brand[] = $row;
				}
			}

			/* Получаем поставщика Google Docs */
			$strSql = "SELECT * FROM ci_suppliers WHERE id = '" . $this->supplier_id . "'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arSupp = $row;
				$arSupp["settings"] = json_decode( $arSupp["settings"], true );
			}

			foreach($base_brand as $f_brand){
				$br = $f_brand;
				if($arSupp["settings"]["brand"][$f_brand["id"]]){
					$tmp = $arSupp["settings"]["brand"][$f_brand["id"]];

					$br["sale"] = $tmp["sale"];
					$br["priority"] = $tmp["priority"];

					$br["active"] = $tmp["active"];
					$br["active_by"] = $tmp["active_by"];
					$br["active_pl"] = $tmp["active_pl"];
					$br["active_wb"] = $tmp["active_wb"];

				}else{
					$br["sale"] = 0;
					$br["priority"] = 1;

					$br["active"] = ($arSupp["active"] == "Y" ? "Y" : "N");
					$br["active_by"] = ($arSupp["active_by"] == "Y" ? "Y" : "N");
					$br["active_pl"] = ($arSupp["active_pl"] == "Y" ? "Y" : "N");
					$br["active_wb"] = ($arSupp["active_wb"] == "Y" ? "Y" : "N");
				}
				$br["name"] = mb_strtoupper($br["name"]);

				$arBrand[] = $br;
			}

			if(count($arBrand) == 0){
				return array("status" => "N", "info" => "Нет выбранных брендов");
			}

			if($arSupp["settings"]["currency"] != "RUB"){
				// Получаем курс валюты
				$strSql = "SELECT * FROM ci_currency WHERE id = '" . $arSupp["settings"]["currency"] . "'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$rate = $row["rate"];
				}else{
					$rate = 1;
				}
			}else{
				$rate = 1;
			}

			$arError = array();
			foreach ($avl_models as $key => $arItem){
				$brand_id = 0;
				$sale = 0;

				$active = $active_by = $active_pl = $active_wb = false;

				foreach($arBrand as $key => $brand){
					if($brand["name"] == $arItem["BRAND"] || $brand["bitrix_id"] == $arItem["BRAND_ID"]){
						$brand_id = $brand["id"];
						$sale = $brand["sale"];

						$active = $brand["active"];
						$active_by = $brand["active_by"];
						$active_pl = $brand["active_pl"];
						$active_wb = $brand["active_wb"];
					}
				}

				$article = $arItem["ARTICLE"];
				$price = $arItem["PRICE"];
				$count = intval($arItem["COUNT"]);

				if(strlen($article) > 0 && $price > 0 && $brand_id > 0 && $count > 0){
					$arResult[] = array(
						"active" 		=> ($active ? $active : "N"),
						"active_by"		=> ($active_by ? $active_by : "N"),
						"active_pl"		=> ($active_pl ? $active_pl : "N"),
						"active_wb"		=> ($active_wb ? $active_wb: "N"),
						"article" 		=> $article,
						"brand_id" 		=> $brand_id,
						"brand" 		=> $arItem["BRAND"],
						"supplier_id" 	=> $this->supplier_id,
						"price" 		=> $price,
						"sale" 			=> $sale,
						"count" 		=> $count,
					);
				}else{
					$arError[] = $arItem;
				}

			}


			foreach($arResult as $key => &$arItem){
				//prent($arItem["price"]);
				$arItem["price"] = $arItem["price"] * $rate;
				if($arItem["sale"] > 0 && $arItem["sale"] < 100){
					$arItem["price"] = $arItem["price"] * ( 100 - $arItem["sale"] ) / 100;
				}
			}
			unset($arItem);

			if(count($arResult) > 0){

				//получаем товары которые были (для отчета)
				$arHistory = CPanelPricelist::getPriceByFilter(array("supplier_id" => $this->supplier_id));
				//удаляем прайслист
				$DB->Query("DELETE FROM ci_price WHERE supplier_id='" . $this->supplier_id . "'");
				$DB->Query("DELETE FROM ci_pricelist WHERE supplier_id='" . $this->supplier_id . "'");
				$brd = array();
				$cnt = 0;
				foreach($arResult as $key => $arItem){
					//$brd[$arItem["brand_id"]] = $arItem["brand_id"];
					$brd[$arItem["brand_id"]] = array(
						"id" 		=> $arItem["brand_id"],
						"active" 	=> $arItem["active"],
						"active_by" => $arItem["active_by"],
						"active_pl" => $arItem["active_pl"],
						"active_wb" => $arItem["active_wb"],
					);
					$in = array(
						"active" => "'".$arItem["active"]."'",
						"active_by" => "'".$arItem["active_by"]."'",
						"active_pl" => "'".$arItem["active_pl"]."'",
						"active_wb" => "'".$arItem["active_wb"]."'",
						"model" => "'".$arItem["article"]."'",
						"brand_id" => "'".$arItem["brand_id"]."'",
						"supplier_id" => "'".$arItem["supplier_id"]."'",
						"store_id" => ($arItem["supplier_id"] == 44 ? "1" : "2"),
						"price" => "'".$arItem["price"]."'",
						"count" => "'".$arItem["count"]."'",
						"bitrix_id" => "'".$bxID[$arItem["article"]]."'",
					);
					$DB->Insert("ci_price", $in, $err_mess.__LINE__);
					$this->_log .= "Добавлен - " . $in["model"] . " поставщик - " . $in["store_id"] . " склад - " . $in["store_id"] . " - цена - " . $in["price"] . "\r\n";
					$cnt++;
					//обновляем время поступления товара. вроде не используется
					//CPanelPricelist::updateDateReceipt($arItem["article"]);
				}
				foreach($brd as $brand){
					$in = array(
						"active" 		=> "'".$brand["active"]."'",
						"active_by" 	=> "'".$brand["active_by"]."'",
						"active_pl" 	=> "'".$brand["active_pl"]."'",
						"active_wb" 	=> "'".$brand["active_wb"]."'",
						"brand_id" 		=> "'".$brand["id"]."'",
						"supplier_id" 	=> "'".$this->supplier_id."'",
					);
					$DB->Insert("ci_pricelist", $in, $err_mess.__LINE__);
				}

				//получаем товары которые стали после добавления (для отчета)
				$arAdd = CPanelPricelist::getPriceByFilter(array("supplier_id" => $this->supplier_id));
				$rs = CPanelPricelist::priceDiff($arHistory, $arAdd);
			}

			if(count($arError) > 0){
				$message = "<p>Ошибки при обмене с МС {$this->site_id}</p>";
				foreach($arError as $k => $arItem){
					$message .= "<p>ARTICLE - '" . $arItem["ARTICLE"] . "' BRAND - '" . $arItem["BRAND"] . "' PRICE - '" . $arItem["PRICE"] . "' COUNT - '" . $arItem["COUNT"] . "'</p>";
				}

				$arFields = array(
					"EMAIL_TO" => "",
					"SUBJECT" => "Ошибки при обмене с МС",
					"MESSAGE" => $message,
				);

				CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);
			}
			CProSet::setOption($this->options_cnt, $cnt);

			//CExchange::forceYmarket($this->site_id);

			return array("status" => "Y", "info" => "Обновление завершено", "cnt" => $cnt);

		}else{
			return array("status" => "N", "info" => "Не удалось получить файл");
		}
	}

	static private function getPurchaseorderMS($offset = 0){
		$urlrest = "https://api.moysklad.ru/api/remap/1.1/entity/purchaseorder?limit=100&offset={$offset}";
//prent($urlrest);
		$ch = curl_init($urlrest);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;charset=utf-8', 'Accept-Encoding: gzip'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_USERPWD, self::$ms_login . ':' . self::$ms_pass);
		$res = curl_exec($ch);
		curl_close($ch);

		$purchase = json_decode($res, true);
		//prent($purchase);
		foreach($purchase["rows"] as $key => $arItem){
			if(!$arItem["applicable"]) continue;
			self::$MSPosition[$arItem["id"]] = $arItem["id"];
		}
		if($purchase["meta"]["size"] > $purchase["meta"]["limit"] + $offset){
			self::getPurchaseorderMS($offset + 100);
		}
	}

	static private function getMoySkladOrder(){
		self::getPurchaseorderMS(0);

		$arProduct = array();
		prent(self::$MSPosition);die;
		//foreach($arPosition as $key => $id){
		foreach(self::$MSPosition as $key => $id){
			$urlrest = "https://api.moysklad.ru/api/remap/1.1/entity/purchaseOrder/{$id}/positions";
			$ch = curl_init($urlrest);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;charset=utf-8', 'Accept-Encoding: gzip'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_USERPWD, self::$ms_login . ':' . self::$ms_pass);
			$res = curl_exec($ch);


			$result = json_decode($res, true);
			foreach($result["rows"] as $k => $v){
				$arProduct[] = array(
					"SRC" => $v["assortment"]["meta"]["href"],
					"PRICE" => $v["price"],
					"QUANTITY" => $v["quantity"],
				);
			}

			curl_close($ch);
		}

		foreach($arProduct as $key => $arItem){

			//$urlrest = "https://api.moysklad.ru/api/remap/1.1/entity/product/6aea0a6a-93aa-11ea-0a80-025e000e80e7";
			$urlrest = $arItem["SRC"];
			$ch = curl_init($urlrest);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;charset=utf-8', 'Accept-Encoding: gzip'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_USERPWD, self::$ms_login . ':' . self::$ms_pass);
			$res = curl_exec($ch);

			$info = curl_getinfo($ch);
			$result = json_decode($res, true);

			self::$arPriceMS[$result["externalCode"]] = array(
				"XML_ID" => $result["externalCode"],
				"PRICE" => $arItem["PRICE"],
				"COUNT" => $arItem["QUANTITY"],
			);

			curl_close($ch);
		}

	}

	function updateFromMoySkladOrder(){
		CModule::IncludeModule('panel.manager');

		$this->MScheckLastChanges();

		self::getMoySkladOrder();

		$arBrandName = $this->getBrandName();

		if (count(self::$arPriceMS) > 0){
			global $DB;
			/* получаем все артнамберы */
			$arFilter = Array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_CML2_ARTICLE" => false,
				"XML_ID" => array_keys(self::$arPriceMS),
			);
			$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"));
			while($arFields = $result->GetNext()){
				$arModels[$arFields['XML_ID']] = array(
					"ARTICLE" => $arFields['PROPERTY_CML2_ARTICLE_VALUE'],
					"BRAND" => $arBrandName[$arFields['PROPERTY_BRAND_VALUE']],
				);
			}

			$arBrandName = array();
			foreach (self::$arPriceMS as $key => $arItem){
				if (isset($arModels[$arItem['XML_ID']])){
					$price = round($arItem["PRICE"] / 100, 2);

					$brand = $arModels[$arItem['XML_ID']]["BRAND"];
					$arBrandName[$brand] = $brand;

					$avl_models[] = array(
						"ARTICLE" => $arModels[$arItem['XML_ID']]["ARTICLE"],
						"BRAND" => $brand,
						"PRICE" => (double)$price,
						"COUNT" => $arItem["COUNT"],
					);
				}
			}

			foreach($arBrandName as $brand){
				/* Получаем поставщика Google Docs */
				$strSql = "SELECT * FROM ci_brands WHERE name = '" . $brand . "'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$base_brand[] = $row;
				}
			}

			/* Получаем поставщика Google Docs */
			$strSql = "SELECT * FROM ci_suppliers WHERE id = '" . $this->supplier_id . "'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arSupp = $row;
				$arSupp["settings"] = json_decode( $arSupp["settings"], true );
			}

			//проверяем какие бренды пришли из мойСклад с теми что разрешены в настройках. оставляем только разрешенные
			/*
			foreach($base_brand as $f_brand){
				foreach($arSupp["settings"]["brand"] as $s_brand){
					if($s_brand["id"] == $f_brand["id"]){

						$br = $f_brand;
						$br["sale"] = (float) $s_brand["sale"];
						$br["priority"] = (float) $s_brand["priority"];

						$br["active"] = $s_brand["active"];
						$br["active_by"] = $s_brand["active_by"];
						$br["active_pl"] = $s_brand["active_pl"];

						$arBrand[] = $br;
					}
				}
			}
			*/
			//21052020 не учитывается активность бренда. доступны все бренды
			foreach($base_brand as $f_brand){
				$br = $f_brand;
				$br["sale"] = 0;
				$br["priority"] = 1;

				$br["active"] = ($arSupp["active"] == "Y" ? "Y" : "N");
				$br["active_by"] = ($arSupp["active_by"] == "Y" ? "Y" : "N");
				$br["active_pl"] = ($arSupp["active_pl"] == "Y" ? "Y" : "N");
				$br["active_wb"] = ($arSupp["active_wb"] == "Y" ? "Y" : "N");

				$arBrand[] = $br;
			}


			if(count($arBrand) == 0){
				return array("status" => "N", "info" => "Нет выбранных брендов");
			}

			if($arSupp["settings"]["currency"] != "RUB"){
				// Получаем курс валюты
				$strSql = "SELECT * FROM ci_currency WHERE id = '" . $arSupp["settings"]["currency"] . "'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$rate = $row["rate"];
				}else{
					$rate = 1;
				}
			}else{
				$rate = 1;
			}
			foreach ($avl_models as $key => $arItem){
				$brand_id = 0;
				$sale = 0;

				$active = $active_by = $active_pl = $active_wb = false;

				foreach($arBrand as $key => $brand){
					if($brand["name"] == $arItem["BRAND"]){
						$brand_id = $brand["id"];
						$sale = $brand["sale"];

						$active = $brand["active"];
						$active_by = $brand["active_by"];
						$active_pl = $brand["active_pl"];
						$active_wb = $brand["active_wb"];
					}
				}

				$article = $arItem["ARTICLE"];
				$price = $arItem["PRICE"];
				$count = intval($arItem["COUNT"]);
				//$price = (float) trim(str_replace(array(" ", ","), array("", "."), $price));

				if(strlen($article) > 0 && $price > 0 && $brand_id > 0 && $count > 0){
					$arResult[] = array(
						"active" 		=> ($active ? $active : "N"),
						"active_by"		=> ($active_by ? $active_by : "N"),
						"active_pl"		=> ($active_pl ? $active_pl : "N"),
						"active_wb"		=> ($active_wb ? $active_wb : "N"),
						"article" 		=> $article,
						"brand_id" 		=> $brand_id,
						"brand" 		=> $arItem["BRAND"],
						"supplier_id" 	=> $this->supplier_id,
						"price" 		=> $price,
						"sale" 			=> $sale,
						"count" 		=> $count,
					);
				}

			}

			foreach($arResult as $key => &$arItem){
				//prent($arItem["price"]);
				$arItem["price"] = $arItem["price"] * $rate;
				if($arItem["sale"] > 0 && $arItem["sale"] < 100){
					$arItem["price"] = $arItem["price"] * ( 100 - $arItem["sale"] ) / 100;
				}
			}
			unset($arItem);//prent($arResult);die;
			//prent($arResult);
			//die;
		//prent($arResult);
		//die;
			if(count($arResult) > 0){

				//получаем товары которые были (для отчета)
				$arHistory = CPanelPricelist::getPriceByFilter(array("supplier_id" => $this->supplier_id));
				//удаляем прайслист
				$DB->Query("DELETE FROM ci_price WHERE supplier_id='" . $this->supplier_id . "'");
				$DB->Query("DELETE FROM ci_pricelist WHERE supplier_id='" . $this->supplier_id . "'");
				$brd = array();
				$cnt = 0;
				foreach($arResult as $key => $arItem){
					//$brd[$arItem["brand_id"]] = $arItem["brand_id"];
					$brd[$arItem["brand_id"]] = array(
						"id" 		=> $arItem["brand_id"],
						"active" 	=> $arItem["active"],
						"active_by" => $arItem["active_by"],
						"active_pl" => $arItem["active_pl"],
						"active_wb" => $arItem["active_wb"],
					);
					$in = array(
						"active" => "'".$arItem["active"]."'",
						"active_by" => "'".$arItem["active_by"]."'",
						"active_pl" => "'".$arItem["active_pl"]."'",
						"active_wb" => "'".$arItem["active_wb"]."'",
						"model" => "'".$arItem["article"]."'",
						"brand_id" => "'".$arItem["brand_id"]."'",
						"supplier_id" => "'".$arItem["supplier_id"]."'",
						"store_id" => ($arItem["supplier_id"] == 44 ? "1" : "2"),
						"price" => "'".$arItem["price"]."'",
						"count" => "'".$arItem["count"]."'",
					);
					$DB->Insert("ci_price", $in, $err_mess.__LINE__);
					$this->_log .= "Добавлен - " . $in["model"] . " поставщик - " . $in["store_id"] . " склад - " . $in["store_id"] . " - цена - " . $in["price"] . "\r\n";
					$cnt++;
					//обновляем время поступления товара. вроде не используется
					//CPanelPricelist::updateDateReceipt($arItem["article"]);
				}
				foreach($brd as $brand){
					$in = array(
						"active" 		=> "'".$brand["active"]."'",
						"active_by" 	=> "'".$brand["active_by"]."'",
						"active_pl" 	=> "'".$brand["active_pl"]."'",
						"active_wb" 	=> "'".$brand["active_wb"]."'",
						"brand_id" 		=> "'".$brand["id"]."'",
						"supplier_id" 	=> "'".$this->supplier_id."'",
					);
					$DB->Insert("ci_pricelist", $in, $err_mess.__LINE__);
				}

				//получаем товары которые стали после добавления (для отчета)
				$arAdd = CPanelPricelist::getPriceByFilter(array("supplier_id" => $this->supplier_id));
				$rs = CPanelPricelist::priceDiff($arHistory, $arAdd);
			}

			CProSet::setOption($this->options_cnt, $cnt);

			//CExchange::forceYmarket($this->site_id);

			return array("status" => "Y", "info" => "Обновление завершено", "cnt" => $cnt);

		}else{
			return array("status" => "N", "info" => "Не удалось получить файл");
		}
	}

	function MScheckLastChanges() {
		CProSet::setOption($this->last_exch, time());
    }


	public function updateCatalog($ID = 0, $flgAll = false){
		global $DB;
		$flgAll = true;
		$el = new CIBlockElement;

		$limit = 50;

		$ID = intval($ID);

		if($ID == 0 || CProSet::getOption("UPDATE_CATALOG") == "Y") {
			self::getAllCnt();
			CProSet::setOption("UPDATE_CATALOG", "IN_PROCESS");
		}

		$cnt_avail = $cnt_stock = $cnt_no = 0;

		//$strSql = "SELECT el.SORT as SORT, el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> '' AND el.ID > {$ID} ORDER BY el.ID ASC LIMIT 0,{$limit}";
		//$strSql = "SELECT el.SORT as SORT, el.CODE as CODE, el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.VALUE as ARTICLE, cat.QUANTITY as QUANTITY, cat.TYPE as TYPE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID LEFT JOIN b_catalog_product cat ON el.ID=cat.ID WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> '' AND cat.TYPE <> '3' AND el.ID > {$ID} ORDER BY el.ID ASC LIMIT 0,{$limit}";
		$strSql = "SELECT el.SORT as SORT, el.SORT_RU as SORT_RU, el.SORT_BY as SORT_BY, el.SORT_PL as SORT_PL, el.SHOW_COUNTER as SHOW_COUNTER, el.CODE as CODE, el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.PROPERTY_123 as ARTICLE,
						cat.QUANTITY as QUANTITY, cat.TYPE as TYPE FROM b_iblock_element el
					LEFT JOIN
						b_iblock_element_prop_s16 pr
					ON el.ID=pr.IBLOCK_ELEMENT_ID
					LEFT JOIN
						b_catalog_product cat ON el.ID=cat.ID
					WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.PROPERTY_123 <> ''
						AND cat.TYPE <> '3' AND el.ID > {$ID} ORDER BY el.ID ASC LIMIT 0,{$limit}";

		//cat.TYPE - 1 - простой товар, 3 - имеет торг предложения, 4 - это и есть торг предложение
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($_arFields = $results->Fetch()){

			$arAvailability = array();
			$arQuantity = array();

			$sort = 500;
			$sort_ru = $sort_by = $sort_pl = 500;

			if(isset(self::$arPrice[$_arFields["ARTICLE"]])){
				$flg = false;

				foreach(self::$arPrice[$_arFields["ARTICLE"]] as $key => $arItem){
					$flg = true;
					if($arItem["store_id"] == 1){
						$cnt_avail++;
						if($arItem["active"] == "Y"){
							if($sort != 10) $sort = 100;
							$sort_ru = 10;
							$arAvailability["ru"][] = "store";//В наличии (склад)
						}
						if($arItem["active_by"] == "Y"){
							$sort_by = 10;
							$arAvailability["by"][] = "in_stock";//В наличии (Немига-3)
						}
						if($arItem["active_pl"] == "Y"){
							$sort_pl = 10;
							$arAvailability["pl"][] = "store";//В наличии (склад)
						}
						$arQuantity[] = 500;

					}elseif($arItem["store_id"] == 2){
						$cnt_avail++;
						if($arItem["active"] == "Y"){
							$sort = 10;
							$sort_ru = 100;
							$arAvailability["ru"][] = "in_stock";//В наличии
						}
						if($arItem["active_by"] == "Y"){
							$arAvailability["by"][] = "store";//В наличии (склад)
							$sort_by = 100;
						}
						if($arItem["active_pl"] == "Y"){
							$arAvailability["pl"][] = "store";//В наличии (склад)
							$sort_pl = 100;
						}
						$arQuantity[] = 50;
					}elseif($arItem["store_id"] == 3){
						//у поставщика


						$cnt_stock++;

						if($arItem["active"] == "Y"){
							$sort = 200;
							$arAvailability["ru"][] = "store";//В наличии (склад)
							$sort_ru = 200;
						}
						if($arItem["active_by"] == "Y"){
							$arAvailability["by"][] = "store";//В наличии (склад)
							$sort_by = 200;
						}
						if($arItem["active_pl"] == "Y"){
							$arAvailability["pl"][] = "store";//В наличии (склад)
							$sort_pl = 200;
						}
						$arQuantity[] = 50;
					}

				}

				if(!$flg){
					$cnt_no++;
//					$this->_log .= $_arFields["ID"] . " - " . $_arFields["ARTICLE"] . " - не найден у поставщиков. не задан склад.\r\n";
				}else{
//					$this->_log .= $_arFields["ID"] . " - " . $_arFields["ARTICLE"] . ". Количество - ".max($arQuantity).", сортировка - {$sort}.\r\n";
				}

				$sort_ru -= (float)$_arFields["SHOW_COUNTER"] / 1000000;
				$sort_by -= (float)$_arFields["SHOW_COUNTER"] / 1000000;
				$sort_pl -= (float)$_arFields["SHOW_COUNTER"] / 1000000;

				if($_arFields["SORT_RU"] != $sort_ru || $_arFields["SORT_BY"] != $sort_by || $_arFields["SORT_PL"] != $sort_pl){
					$arLoadProductArray = Array(
						//"SORT"	=> $sort,
						"SORT_RU"	=> $sort_ru,
						"SORT_BY"	=> $sort_by,
						"SORT_PL"	=> $sort_pl,
					);
					$rs = $el->Update($_arFields["ID"], $arLoadProductArray);
				}


			}else{

				if($_arFields["SORT_RU"] != 500 || $_arFields["SORT_BY"] != 500 || $_arFields["SORT_PL"] != 500){
					$DB->Update("b_iblock_element", array("SORT_RU" => 500,"SORT_BY" => 500,"SORT_PL" => 500), "WHERE ID='".$_arFields["ID"]."'", $err_mess.__LINE__);
/*
					$arLoadProductArray = Array(
						"SORT"	=> 1000,
					);
					$rs = $el->Update($_arFields["ID"], $arLoadProductArray);*/
				}

				$cnt_no++;
//				$this->_log .= $_arFields["ID"] . " - " . $_arFields["ARTICLE"] . " - не найден у поставщиков.\r\n";
			}

			//обновляем доступное количество
			if(count($arQuantity) > 0)
				$quantity = max($arQuantity);
			else
				$quantity = -5;
			if($quantity > 0) $avail = "Y"; else $avail = "N";
			// || 1==1
			if($_arFields["QUANTITY"] != $quantity || $flgAll === true){
				$in = array(
					"QUANTITY"	=> $quantity,
					"AVAILABLE"	=> "'".$avail."'",
				);

				$DB->Update("b_catalog_product", $in, "WHERE ID='".$_arFields["ID"]."'", $err_mess.__LINE__);

				$arLoadProductArray = Array(
					"SORT"	=> $sort,
				);
//				$rs = $el->Update($_arFields["ID"], $arLoadProductArray); op 29062020
				$arFields = array("ID" => $_arFields["ID"], "IBLOCK_ID" => 16);
				TsIblock::setPropAvailable($arFields);

				//обновляем свойство наличия
				if($_arFields["IBLOCK_ID"] == CProSet::IB_CATALOG)
					self::setAvailability($_arFields["ID"], $arAvailability, $arFields["SET_PRICES"]);

				clearElementCache($_arFields["IBLOCK_ID"], $_arFields["ID"]);
//				self::clearCacheElement($_arFields["CODE"]);
			}

			$lastID = intval($_arFields["ID"]);
		}

		$strSql = "SELECT el.ID FROM b_iblock_element el WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND el.ID <= {$ID} ORDER BY el.ID ASC";
		$leftBorderCnt = $DB->Query($strSql, false, $err_mess.__LINE__)->SelectedRowsCount();

		$p = round(100 * $leftBorderCnt / self::$allCnt, 2);
		if($p > 100) $p = 100;
		CProSet::setOption("UPDATE_CATALOG_PER", $p);// . " - " . memory_get_usage());
		CProSet::setOption("UPDATE_CATALOG_LAST_ID", $lastID);

		if($p >= 100){

//			self::updateCatalogSKU();
			//ставим метку если успешно обновлено
			CProSet::setOption("UPDATE_CATALOG", "N");
			CIBlockTools::Update();

			// op comment 02-07-2020.
//			$cache_manager = Bitrix\Main\Application::getInstance()->getTaggedCache();
//			$cache_manager->ClearByTag("iblock_id_".CProSet::IB_CATALOG);

//			$cache_manager->ClearByTag("iblock_id_".CProSet::IB_CATALOG_SKU);

			//self::createIndexAll();

			CProSet::setOption("RUN_CONTROL", "Y");//обновляем значения в кроне

			//CExchange::forceYmarket("s1");
			//CExchange::forceYmarket("s2");
			CExchange::forceYmarket("s3");

		}else{

		}

		return array("PERCENT" => $p, "LAST_ID" => $lastID, "CNT_AVAIL" => $cnt_avail, "CNT_STOCK" => $cnt_stock, "CNT_NO" => $cnt_no);

	}


	public function updateCatalogSKU($ID = 0){
		global $DB;

		$el = new CIBlockElement;

		$limit = 50;

		$ID = intval($ID);

		//$strSql = "SELECT el.SORT as SORT, el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> '' AND el.ID > {$ID} ORDER BY el.ID ASC LIMIT 0,{$limit}";
		$strSql = "SELECT MIN(el.SORT) as SORT, pr.VALUE as ROOT_ID,
		MAX(cat.QUANTITY) as QUANTITY FROM b_iblock_element el
		LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID
		LEFT JOIN b_catalog_product cat ON el.ID=cat.ID WHERE el.ACTIVE = 'Y'
		AND el.IBLOCK_ID = '17' AND pr.IBLOCK_PROPERTY_ID = '118' AND pr.VALUE <> '' AND cat.TYPE = '4' GROUP BY pr.VALUE";
		//cat.TYPE - 1 - простой товар, 3 - имеет торг предложения, 4 - это и есть торг предложение
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($arFields = $results->Fetch()){
			$arLoadProductArray = Array(
				"SORT"	=> $arFields["SORT"] + 5,
			);
			//prent($arLoadProductArray);
			$rs = $el->Update($arFields["ROOT_ID"], $arLoadProductArray);
		}

	}

	static function forceYmarket($site_id = false){
		if($site_id == false) return;
	}

	public function getDocumentsMS($id){
		if(!$id) return false;
		$urlrest = "https://api.moysklad.ru/api/remap/1.1/entity/retaildemand/{$id}/documents";
		$ch = curl_init($urlrest);
		prent($urlrest);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json;charset=utf-8', 'Accept-Encoding: gzip'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_USERPWD, self::$ms_login . ':' . self::$ms_pass);
		$res = curl_exec($ch);

		$rs = json_decode($res, true);
		//prent($rs);
		return $rs;
	}

	static function add2log($log){
		$file_log = "/userscripts/logs/exchange_mc_" . date("Y-m-d") . ".txt";
		file_put_contents($file_log, var_export($log, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

}
