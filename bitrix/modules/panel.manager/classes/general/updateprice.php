<?
/*
 * Обновление цен каталога на основании прайсов конкурентов
*/
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

\CBitrixComponent::includeComponentClass('admin:price.monitoring');

class CPriceUpdate{

	public $site_id = "";
	private $priceID = "";
	public $detail_item_log = "";
	public $set_price_level = null;
	public $CNT_PRICE_ALL = 0;
	public $sendMail = true;
	private $priceQuarantine;
	private $db;
	private $saleItems = [];
	private $indivMarkups;
    protected array $suppliers = [];

	public function __construct($priceID = "RU"){
		global $DB;
		$this->priceID = $priceID;
		$this->sendMail = true;
		$this->db = $DB;

		$this->logger = new TsLogger("/updatePrice/");
		$this->TsTriggers = new TsTriggers();
		$this->metric = new Metric();
		$this->loadModules();

		$this->monitoring = new PriceMonitoringComponent;
		switch($this->priceID){
			case "RU":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_ru";
				$this->key_price_discount = "price_discount_ru";
				$this->url = "https://tempusshop.ru";
				$this->PRICE_TYPE_ID = 5;

				$this->OPTION_UPDATE = "UPDATE_PRICE_RU";

				$this->siteR = "s1";
				$this->siteU = "s1";

				$this->monitoring->priceType = 'ru';

				break;
			case "BY":

				$this->round = -0;
				$this->currency = "BYN";
				$this->key_price = "price_by";
				$this->key_price_discount = "price_discount_by";
				$this->url = "https://tempus.by";
				$this->PRICE_TYPE_ID = 2;

				$this->OPTION_UPDATE = "UPDATE_PRICE_BY";


				$this->siteR = "s2";
				$this->siteU = "s2";
				break;
			case "PL":

				$this->round = 0;
				$this->currency = "PLN";
				$this->key_price = "price_pl";
				$this->key_price_discount = "price_discount_pl";
				$this->url = "https://tempusshop.pl";
				$this->PRICE_TYPE_ID = 3;

				$this->OPTION_UPDATE = "UPDATE_PRICE_PL";

				$this->siteR = "s3";
				$this->siteU = "s3";
				break;
			case "KZ":

				$this->round = 0;
				$this->currency = "KZT";
				$this->key_price = "price_kz";
				$this->url = "https://tempuswatch.kz";
				$this->PRICE_TYPE_ID = 6;

				$this->OPTION_UPDATE = "UPDATE_PRICE_KZ";

				$this->siteR = "s4";
				$this->siteU = "kz";

				break;
			case "YA":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_ya";
				$this->key_price_discount = "price_discount_ya";
				$this->url = "https://tempusshop.ru";
				$this->PRICE_TYPE_ID = 1;

				$this->OPTION_UPDATE = "UPDATE_PRICE_YA";

				$this->siteR = "ya";
				$this->siteU = "v1";
				break;
			case "OS":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_os";
				$this->key_price_discount = "price_os";
				$this->url = "https://tempusshop.ru";

				$this->OPTION_UPDATE = "UPDATE_PRICE_OS";

				$this->siteR = "s1";
				$this->siteU = "v2";

				break;
			case "WB":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_wb";
				$this->key_price_discount = "price_wb";
				$this->url = "https://tempusshop.ru";

				$this->OPTION_UPDATE = "UPDATE_PRICE_WB";

				$this->promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
				$this->sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");

				$this->siteR = "s1";
				$this->siteU = "wb";

				break;
				case "WBTL":

					$this->round = -1;
					$this->currency = "RUB";
					$this->key_price = "price_wbtl";
					$this->key_price_discount = "price_wbtl";
					$this->url = "https://tempusshop.ru";

					$this->OPTION_UPDATE = "UPDATE_PRICE_WBTL";

					$this->promo_per = (float)CProSet::getOption("CATALOG_PROMO_wbtl");
					$this->sale_per = (float)CProSet::getOption("CATALOG_SALE_wbtl");

					$this->siteR = "s1";
					$this->siteU = "wbtl";

					break;
			case "AV":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_av";
				$this->key_price_discount = "price_av";
				$this->url = "https://tempusshop.ru";

				$this->OPTION_UPDATE = "UPDATE_PRICE_AV";

				$this->siteR = "s1";
				$this->siteU = "av";

				break;
			case "SB":

				$this->round = -1;
				$this->currency = "RUB";
				$this->key_price = "price_sb";
				$this->key_price_discount = "price_sb";
				$this->url = "https://tempusshop.ru";

				$this->OPTION_UPDATE = "UPDATE_PRICE_SB";

				$this->siteR = "s1";
				$this->siteU = "sb";

				break;
			case "OZKZ":

				$this->round = 0;
				$this->currency = "RUB";
				$this->key_price = "price_ozkz";
				$this->key_price_discount = "price_ozkz";
				$this->url = "https://tempuswatch.kz";

				$this->OPTION_UPDATE = "UPDATE_PRICE_OZKZ";

				$this->siteR = "s4";
				$this->siteU = "kz";

				break;
			case "OZTI":
				$this->round = 0;
				$this->currency = "RUB";
				$this->key_price = "price_ozti";
				$this->key_price_discount = "price_ozti";
				$this->url = "https://tempusshop.ru";

				$this->OPTION_UPDATE = "UPDATE_PRICE_OZTI";

				$this->siteR = "s1";
				$this->siteU = "OZTI";

				break;
			default:
				break;
		}
		global $DB;
		$strSql = "SELECT * FROM individual_markups";
		$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
		$this->indivMarkups = [];
		while ( $row = $resultDB->Fetch() ){
			$this->indivMarkups[ mb_strtoupper($row['source']) ][ $row['bitrix_id'] ] = ['m'=>floatval($row['markup']),'model' => $row['model']];
		}
		if(!$this->siteR) die("NO site");
		$this->lowerPriceID = strtolower($this->priceID);

		$this->rev_min = COption::GetOptionString("panel.manager", "PRICEUPDATE_REV_MIN_{$this->priceID}"); // Мин. наценка, rub
		$this->min_per = COption::GetOptionString("panel.manager", "PRICEUPDATE_MIN_PER_{$this->priceID}"); // Мин. наценка, %
		$this->max_per = COption::GetOptionString("panel.manager", "PRICEUPDATE_MAX_PER_{$this->priceID}"); // Макс. наценка, %
		$this->rrcRequired = (COption::GetOptionString("panel.manager", "PRICELIST_REQUIRED_RRC_{$this->priceID}") == "Y" ? true : false); // Флаг обязательно ли наличие РРЦ
		$this->priceMarketRequired = (COption::GetOptionString("panel.manager", "PRICELIST_REQUIRED_MARKET_{$this->priceID}") == "Y" ? true : false); // Флаг обязательно ли наличие цен конкурентов

		$this->margin_platform_def = COption::GetOptionString("panel.manager", "PRICELIST_MARGIN_{$this->priceID}"); // Наценка
		$this->mpCommision = (int)COption::GetOptionString("panel.manager", "PRICEUPDATE_MP_COMMISSION_{$this->priceID}");

		$this->control_rrc = json_decode(CProSet::getOption("CONTROL_RRC"), true)[$this->priceID]; // РРЦ

		$this->arMargin = $this->getMarkup();
		$this->arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$this->lowerPriceID]; //

		if(isset($this->arDefaultRRC["supersale"]) && $this->arDefaultRRC["supersale"] > 0){
			//получаем все товары из категории СУПЕРЦЕНА
			$this->getSaleItems();
		}

		$objSupplier = new CPanelSupplier;

		foreach($objSupplier->getList() as $key => $arItem){
			$this->arSuppName[$arItem["id"]] = $arItem["name"];

			$this->suppliers[$arItem['id']] = [
				'id' => $arItem['id'],
				'name' => $arItem['name'],
				'settings' => json_decode($arItem['settings'], true),
			];
		}

		$objBrand = new CPanelBrand;

		foreach($objBrand->getList() as $key => $arItem){
			if ($arItem["margin_{$this->lowerPriceID}"] > 0)
				$this->arBrandMargin[$arItem["id"]][$this->priceID] = $arItem["margin_{$this->lowerPriceID}"];

		}

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
	}

	public function setAllPrice(){
		$this->logger->log("LOG", "Запуск для цены - " . $this->priceID);
		CProSet::setOption($this->OPTION_UPDATE, 0);

		if($this->rrcRequired === true && count_($this->control_rrc) <= 0) {
			$this->logger->log("LOG", "Обновление цен. Отмена. Нет РРЦ. Цена - " . $this->priceID);
			$this->TsTriggers->SetError(["Обновление цен. Отмена. Нет РРЦ. Цена - " . $this->priceID]);
			$this->TsTriggers->SendTriggerErrors();
			return false;
		}

		if ( $this->priceID == 'YA' ){ // БЛОК ЭКСПРЕСС ЯНДЕКС
			$arFilter = Array(
				"IBLOCK_ID" => 16,
				"PROPERTY_EX_YA_VALUE" => 'Да'
			);
			$arSelect = ['IBLOCK_ID', 'ID', 'PROPERTY_EX_YA'];
			$result = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
			while( $art = $result->GetNext() ){
				CIBlockElement::SetPropertyValuesEx( $art['ID'], 16, array('EX_YA' => 2085) );
			}
		}

		$page_size = 100;

		$arSettings = array(
			"round" => $this->round,
			"rate" => 1,
			"currency" => $this->currency
		);
		$objPricelist = new CPanelPricelist;
		$objSupplier = new CPanelSupplier;
		$objCurrency = new CPanelCurrency;

		// получаем валюту
		$arCurrency = $objCurrency->getDetail($this->currency);
		if($arCurrency){
			$arSettings["rate"] = $arCurrency["rate"];
		}
		/*	global $USER;
			if ($USER->isAdmin()) {
				$psFilter["article"] = array("960761");
			}*/
		//$psFilter["article"] = array("MQ-24MG-1E");
		global $USER;

		if($this->control_rrc)
			$psFilter["brand_id"] = $this->control_rrc;

		$psFilter["price_id"] = $this->lowerPriceID;
		//prent($psFilter);
		// получаем прайслист поставщиков
		$price = $objPricelist->getPriceByFilter($psFilter, false, false, "price asc");
		//prent($price);
		$arReserve = $this->getReserve(); // позиции в резерве
		// var_dump(count($arReserve));
		$arQuarantine = $this->getQuarantine(); // позиции в карантине
		foreach($price as $key => &$arItem){
			if ( $arItem['model'] == 'GA-2100-1A1' ) {
				// var_dump( $arReserve[$arItem['model']] );
				// var_dump( $arItem['supplier_id'] );
				// var_dump( $arItem['price'] );
			}
			if(isset($arReserve[$arItem["model"]])){
				if($arReserve[$arItem["model"]] >= $arItem["count"]){
					$arItem["can_buy"] = false;
					$arReserve[$arItem["model"]] -= $arItem["count"];
				}else{
					$arItem["can_buy"] = true;
				}
			}elseif(isset($arQuarantine[$arItem["model"]])){
				$arItem["can_buy"] = true;
			}else{
				$arItem["can_buy"] = true;
			}
			//$arItem["can_buy"] = true;
			if ( $arItem['model'] == 'GA-2100-1A1' ){
				$cehck[] = [
					'supplier_id' => $arItem['supplier_id'],
					'price' => $arItem['price'],
					'can_buy' => $arItem['can_buy']
				];
			}
		}
		var_dump($cehck);

		unset($arItem);

		$arArticle = array();//массив со всеми артикулами
		$tmpPrice = $arPrice = array();

		foreach($price as $key => &$arItem){


			if($arItem["model"] && $arItem["can_buy"] === true){
				// БЛОК ЭКСПРЕСС ЯНДЕКС
				// if ( $this->priceID == 'YA' ){
				// 	$allowedSuppliers = [47, 103, 116, 129];
				// 	if ( in_array($arItem['supplier_id'], $allowedSuppliers) ){
				// 		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog4.txt', print_r($arItem['bitrix_id'], 1) . PHP_EOL, FILE_APPEND);
				// 		CIBlockElement::SetPropertyValuesEx( $arItem["bitrix_id"], 16, array('EX_YA' => 2084) );
				// 	}
				// }

				// КОНЕЦ БЛОКА
				//$arItem["price"] = $arItem["price"] / $arSettings["rate"];
				//$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);
				if ($this->arDefaultRRC['price_type'] == 'price_n') {
					$arItem['price'] = $arItem['price_n'];
					//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog3.txt', print_r($arItem,1) . PHP_EOL, FILE_APPEND);
				}

				$arItem["price"] = (float) ($arItem["price"] / $arSettings["rate"]);
				$arArticle[$arItem["model"]] = $arItem["model"];

				// if ($this->priceID == "BY") {
				// 	if ($arItem['supplier_id'] == 44) {
				// 		$tmpPrice[$arItem["model"]] = $arItem;
				// 	}
				//
				// 	if(isset($tmpPrice[$arItem["model"]]) && $tmpPrice[$arItem["model"]]['supplier_id'] != 44){
				// 		if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
				// 			$tmpPrice[$arItem["model"]] = $arItem;
				// 	} else {
				// 		if ($tmpPrice[$arItem["model"]]['supplier_id'] != 44)
				// 			$tmpPrice[$arItem["model"]] = $arItem;
				// 	}
				//
				// } else {
					if(isset($tmpPrice[$arItem["model"]])){
						if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
							$tmpPrice[$arItem["model"]] = $arItem;
					}else {
						$tmpPrice[$arItem["model"]] = $arItem;
					}
				//}
			}
		}
		// var_dump( $tmpPrice[ 'GA-2100-1A1' ] );
		// die;
		$pounter = 0;
		// foreach($tmpPrice as $tmpp){
		// 	if( !empty($tmpp['bitrix_id']) ){
		// 		$pounter++;
		// 		// CExchange::updateProductTableNew($tmpp['bitrix_id']);
		// 	}
		// }

		unset($arItem);

		if(count($arArticle) <= 0){
			$this->logger->log("ERROR", "Обновление цен. Отмена. Прайс пустой. Цена - " . $this->priceID);
			$this->TsTriggers->SetError(["Обновление цен. Отмена. Прайс пустой. Цена - " . $this->priceID]);
			$this->TsTriggers->SendTriggerErrors();
			return false;
		}

		if($this->priceID == "YA"){
			/* ищем цены яндекса */
			$tmp = $objPricelist->getYandexPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "BY"){

			$arTmp = $arArticleAlt = array();
			//сразу выбираем артикулы из свойства товара "Артикул Онлайнера" для подмены
			$res = CIBlockElement::getList(
				array(),
				array(
					'IBLOCK_ID' => CProSet::IB_CATALOG,
					"PROPERTY_CML2_ARTICLE" => $arArticle,
					"!PROPERTY_MODEL_ONLINER" => false
					// "!PROPERTY_ARTICLE_ONLINER" => false
				),
				false,
				false,
				array(
					"ID",
					"PROPERTY_CML2_ARTICLE",
					"PROPERTY_ARTICLE_ONLINER",
					"PROPERTY_MODEL_ONLINER",
				)
			);
			while ($row = $res->getNext()) {
				$arTmp[$row["PROPERTY_CML2_ARTICLE_VALUE"]] = $row["PROPERTY_MODEL_ONLINER_VALUE"];
			}
			foreach($arArticle as $article){
				if($arTmp[$article]){
					if ( strripos($arTmp[$article], ' ') ){
						// $v = end( explode(' ', $v) );
						$arArticleAlt[] = end( explode(' ', $arTmp[$article]) );
					}else{
						$arArticleAlt[] = $arTmp[$article];
					}
				} else {
					$arArticleAlt[] = $article;
				}
			}
			// получаем цены онлайнера
			$tmp2 = $objPricelist->getOnlinerPriceByFilter(array("model" => $arArticleAlt), false);

			$tmp = array();
			foreach($tmp2 as $key => $arItem){
				if($name = array_search($arItem["name"], $arTmp)){
					$tmp[$key] = array(
						"name" => $name,
						"minPrice" => $arItem["minPrice"],
						"minPrice2" => $arItem["minPrice2"],
						"minPrice3" => $arItem["minPrice3"],
					);
				}else{
					$tmp[$key] = array(
						"name" => $arItem["name"],
						"minPrice" => $arItem["minPrice"],
						"minPrice2" => $arItem["minPrice2"],
						"minPrice3" => $arItem["minPrice3"],
					);
				}
			}
			//$tmp = $objPricelist->getOnlinerPriceByFilter(array("model" => $arArticle)); было только это
		}elseif($this->priceID == "PL"){
			/* ищем цены Ceneo */
			$tmp = $objPricelist->getCeneoPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "WB"){
			/* ищем цены wb */
			//$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
			$price = $objPricelist->getCompetitorPriceByFilter('wb', ["article" => $arArticle]);
			$this->monitoring->applyBrandDiscounts($price);

			$tmp = $objPricelist->prepareMinPrice($price);

		}elseif($this->priceID == "WBTL"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "AV"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "SB"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "KZ"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "OZKZ"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "OZTI"){
			/* ищем цены wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
		}elseif($this->priceID == "RU"){
			/* ищем цены wb */
			//$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));



			$price = $objPricelist->getCompetitorPriceByFilter('ru', ["article" => $arArticle]);
			$this->monitoring->applyBrandDiscounts($price);

			$tmp = $objPricelist->prepareMinPrice($price);
			//prent($tmp);
			//die;
		}
		$arPricePlatform = []; // массив с ценами конкурентов
		foreach($tmp as $arItem){
			$arPricePlatform[$arItem["name"]] = $arItem;
		}

		if($this->priceMarketRequired === true && count_($arPricePlatform) <= 0){
			CProSet::setOption($this->OPTION_UPDATE, 0);
			CLog::add2log(array("event" => "Y", "text" => "Цен конкурентов 0", "price_id" => $this->priceID));

			$this->logger->log("LOG", "Обновление цен. Отмена. Цен конкурентов 0. priceID - " . $this->priceID);
			$this->TsTriggers->SetError(["Обновление цен. Отмена. Цен конкурентов 0. priceID - " . $this->priceID]);
			$this->TsTriggers->SendTriggerErrors();

			return false;
		}

		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog1.txt', print_r($arArticle,1) . PHP_EOL, FILE_APPEND);
		$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle)); // цены из битры. из временной таблицы

		foreach($arCatalogPrice as  $key => $arItem){
			$tmp = $tmpPrice[$arItem["model"]];

			if ($tmp) {
				$tmp = $this->correctSupplierPrice($tmp);
			}

			$b_price = $arItem[$this->key_price_discount];

			$b_full_price = $arItem[$this->key_price];
			// if ($this->priceID == "BY" )  {
			// 	$price_platform1 = 0;
			// 	$price_platform2 = 0;
			// 	$price_platform3 = 0;
			// } else {
				$price_platform1 = ($arPricePlatform[$arItem["model"]]["minPrice"] ? $arPricePlatform[$arItem["model"]]["minPrice"] : 0);
				$price_platform2 = ($arPricePlatform[$arItem["model"]]["minPrice2"] ? $arPricePlatform[$arItem["model"]]["minPrice2"] : 0);
				$price_platform3 = ($arPricePlatform[$arItem["model"]]["minPrice3"] ? $arPricePlatform[$arItem["model"]]["minPrice3"] : 0);
			//}
			//if($_POST["price_competitors_act"] == "Y" && $price_platform === 0) continue;
			// формируем массив с нашими ценами, которые будем проверять обновлять или нет цену.
			$arPrice[] = array(
				"id" => $tmp["id"],
				"article" => $arItem["model"],
				"detail_page_url" => $arItem["detail_page_url"],
				"price" => $tmp["price"],
				"discPrice" => $tmp["discPrice"],
				"supplier_id" => $tmp["supplier_id"],
				"brand_id" => $tmp["brand_id"],
				"b_id" => $arItem["product_id"],//ID битрикс
				"b_sku_id" => $arItem["product_sku"],//SKU ID битрикс
				"b_price" => $b_price,//цена битрикс
				"b_price_full" => $b_full_price,
				"price_platform1" => $price_platform1,
				"price_platform2" => $price_platform2,
				"price_platform3" => $price_platform3,
				"price_timestamp" => $tmp["timestamp"],
			);
			//if($arItem["product_sku"]) prent($arItem);
		}

		//$arPrice = array_slice($arPrice, 0, 1000);

		$this->CNT_PRICE_ALL = count_($arPrice);
		//prent($arPrice);
		//prent($arPrice);die;
		$arItems = $arNoModify = array();
		$counter = 0;
		foreach($arPrice as $key => &$arItem){

			$new_price = $this->getNewPrice($arItem, 1); // смотрим есть ли подходящая цена для позиции
			if ( $this->priceID == "BY"){
				$ourPrice = $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]);
				if ( $ourPrice < $new_price ) $new_price = $ourPrice;
			}

			//$new_price = $this->finishPrice($new_price, $arItem);
			if($this->priceID == "WB" || $this->priceID == "WBTL"){
				$new_price = $new_price * (100 / (100 - $this->sale_per)) * (100 / (100 - $this->promo_per));
				if($new_price > 600000) $new_price = 0;
			}

			if ($arItem["b_id"] == 5739) {
				$_log = [
					'date' => date("Y-m-d H:i:s"),
					'priceID' => $this->priceID,
					'arItem' => $arItem,
					'new_price' => $new_price,
					'detail_item_log' => $this->detail_item_log,
				];
				file_put_contents('/var/www/bitrix_logs/updatePrice/5739.txt', print_r($_log, true), 8);
			}
			/*if ( !empty($this->priceID) && $this->priceID == "YA")
			{
			  for ($i = 1 ; $i < 3; $i++)
			  {
			    if ( $arItem['price_platform' . $i] != 0 )
			    {
			      if ( $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]) < $arItem['price_platform' . $i] )
			      {
			        $new_price = $arItem['price_platform' . $i] * 0.99;
			      }
			    }
			  }
			}*/

			// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog3.txt', print_r('START',1) . PHP_EOL, FILE_APPEND);
			// if ( !empty($this->priceID) && $this->priceID == "BY")
			// {
			//   for ($i = 1 ; $i < 3; $i++)
			//   {
			//     if ( $arItem['price_platform' . $i] != 0 )
			//     {
			// 				file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog3.txt', print_r($this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]),1) . PHP_EOL, FILE_APPEND);
			//       if ( $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]) < $arItem['price_platform' . $i] )
			//       {
			//         $new_price = $arItem['price_platform' . $i] * 0.99;
			//       }
			//     }
			//   }
			// }
			//
			// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog3.txt', print_r($arItem,1) . PHP_EOL, FILE_APPEND);
			// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/debLog3.txt', print_r($new_price,1) . PHP_EOL, FILE_APPEND);
			$new_price = round($new_price, $this->round);

			$arItem["b_price_full"] = round($arItem["b_price_full"], $this->round);
			$counter++;
			// if( $new_price >= 0 ){
			if($new_price >= 0 && $new_price != $arItem["b_price_full"]){ // Старое условие с проверкой на необходимость обновлять на такое же значение
				// массив с теми ценами которые надо перезаписать у позиций
				$arItems[] = array(
					"PRODUCT_ID" => $arItem["b_id"],
					"PRODUCT_SKU_ID" => $arItem["b_sku_id"],
					"ARTICLE" => $arItem["article"],
					"PRICE" => $new_price,
					"discPrice" => $arItem["discPrice"],

					"PRICE_OLD" => $arItem["b_price"],
					"PRICE_OLD_FULL" => $arItem["b_price_full"],
					"DETAIL_PAGE_URL" => $arItem["detail_page_url"],
					"DETAIL_ITEM_LOG" => $this->detail_item_log,
					"METRIC_LOG" => $this->metric_log,
					"SET_PRICE_LEVEL" => $this->set_price_level,
				);
			}elseif($new_price){
				$arNoModify[] = array(
					"PRODUCT_ID" => $arItem["b_id"],
					"PRODUCT_SKU_ID" => $arItem["b_sku_id"],
					"DETAIL_ITEM_LOG" => $this->detail_item_log,
					"SET_PRICE_LEVEL" => $this->set_price_level,
				);
			}
			CProSet::setOption($this->OPTION_UPDATE, round((($key + 1) / $this->CNT_PRICE_ALL) / 2 * 100, 2));
		}

		// die;
		//prent($arItems);die;
		unset($arItem);

		CProSet::setOption($this->OPTION_UPDATE, 50);
		// обновляем цены на товары

		/*$asd = [];
		foreach ($arItems as $v) {
			$asd[] = [
				'price' => $v['PRICE'],
				'price_old' => $v['PRICE_OLD'],
				'article' => $v['ARTICLE'],
			];
		}
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/sssssssssssssssss.txt', print_r($asd,1) . PHP_EOL);
		*/
		//prent($arItems);die;
		$this->setPrice($arItems, $arNoModify);

		CProSet::setOption($this->OPTION_UPDATE, 100);

		// обнуляем цены
		//$arCatalogPrice222 = $objPricelist->getCatalogPriceByFilter(array("!model" => $arArticle));
		//prent($arCatalogPrice222);
		$this->setZeroPrice($arArticle);

		$this->logger->log("LOG", "Конец обработки для Цен - " . $this->priceID);
	}

	public function setZeroPrice($arArticle = []){
		global $DB;

		// пока только авито
		if ($this->priceID != 'AV' || !$arArticle) {
			return;
		}

		$strSql = "SELECT * FROM ci_price_catalog WHERE model NOT IN ('".implode("','", $arArticle)."') AND product_id > 0 AND price_av > 0";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$ar = [];
		while ($row = $results->Fetch()){
			$ar[] = $row;
		}

		foreach ($ar as $item) {
			CIBlockElement::SetPropertyValuesEx($item["product_id"], false, array("AVITO_PRICE" => 0));

			//обновляем данные во временной таблице
			$in = array(
				"price_av" => 0,
			);

			$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$item["product_id"]."'", $err_mess.__LINE__);
		}

		//prent($ar);
	}

	protected function correctSupplierPrice($supplierItem)
	{
		$priceID = $this->priceID;
		// if ( $priceID == 'OS' ) $priceID = 'OZIP';

		$correctPrice = $this->suppliers[$supplierItem['supplier_id']]['settings']['correct_price'][$priceID] ?? false;
		if ($correctPrice) {
			$supplierItem['price'] = $supplierItem['price'] + ($supplierItem['price'] * $correctPrice / 100);
		}
		return $supplierItem;
	}

	public function getMarkup(){

		$objAnalysis = new CPanelAnalysis;
		$arProfile = $objAnalysis->getListByFilter(array("price_id" => $this->lowerPriceID));

		$arMargin = array();
		foreach($arProfile as $key => $arItem){
			$arMargin[$arItem["brand_id"]] = $arItem["settings"];
		}
		return $arMargin;
	}

	public function getItemMargin($brand_id){

		if(is_numeric($this->arBrandMargin[$brand_id][$this->priceID])){
			return $this->arBrandMargin[$brand_id][$this->priceID];
		}else{
			return $this->margin_platform_def;
		}

	}
	// ищем подходящую цену на основании цен конкурентов из площадок. цен площадок обычно 3
	public function getNewPrice($arItem, $level = 1){
		$new_price = false;
		$this->detail_item_log = "";

		if(isset($this->saleItems[$arItem["b_id"]])){
			$price_old = $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]);
			$this->detail_item_log = "Установлена РРЦ со скидкой. ({$this->arSuppName[$arItem["supplier_id"]]}) - {$arItem["price"]}\r\n";
			return $this->finishPrice($price_old, $arItem);
		}

		$this->set_price_level = $level;
		if($arItem["price_platform{$level}"] > 0 && $arItem["price"] > 0){

			$this->margin_platform = $this->getItemMargin($arItem["brand_id"]);

			$tmp_price = $arItem["price_platform{$level}"] + $arItem["price_platform{$level}"] * $this->margin_platform / 100;

			$revenue_p = (($tmp_price - $arItem["price"]) / $arItem["price"]) * 100;
			$revenue = $tmp_price - $arItem["price"];

			$min_per = $this->min_per;

			$goodMarginality = true;
			// проверка маржинальности
			if ($this->mpCommision > 0) {
				/*
				и от цены, которую мы хотим установить нужно отнимать 50%, а потом считать маржинальность по формуле

				(новая цена - себес) / себес

				если получается меньше, чем значение в поле Минимальная наценка, то устанавливаем РРЦ
				*/
				$marginality = (($tmp_price - ($tmp_price * $this->mpCommision / 100) - $arItem["price"]) / $arItem["price"]) * 100;
			/*global $USER;
			if ($USER->isAdmin()) {
				prent($marginality);prent($this->min_per);
			}*/
				if ($marginality < $this->min_per) {
					$goodMarginality = false;
				}
			}


			if($goodMarginality && $revenue > $this->rev_min && $revenue_p > $min_per && $revenue_p < $this->max_per){
				$new_price = $tmp_price;

				//подсчитываем цену без скидки для записи
				if($this->priceID != "WB" && $this->priceID != "OS"){

					$new_price = $this->modifyPriceWithoutSale($arItem["b_id"], $new_price);

				}

				$this->detail_item_log = "Установлена {$level} цена маркетплейса = " . $arItem["price_platform{$level}"] . "\r\n";
				$this->detail_item_log .= "tmp_price = " . $arItem["price_platform{$level}"] . " + " . $arItem["price_platform{$level}"] . " * " . $this->margin_platform . " / 100 = " . $tmp_price . "\r\n";
				$this->detail_item_log .= "revenue_p = ((" . $tmp_price . " - " . $arItem["price"] . ") / " . $arItem["price"] . ") * 100 = " . $revenue_p . "\r\n";
				$this->detail_item_log .= "revenue = " . $tmp_price . " - " . $arItem["price"] . " = " . $revenue . "\r\n";
				$this->detail_item_log .= "Мин. по прайсу - {$arItem["price"]} ({$this->arSuppName[$arItem["supplier_id"]]} - {$arItem["price_timestamp"]})" . "\r\n";

				if($this->priceID == "YA" || $this->priceID == "BY"){
					//если новая цена без скидки больше чем на $this->max_per процентов от закупочной то РРЦ
					$revenue_p2 = (($new_price - $arItem["price"]) / $arItem["price"]) * 100;
					if($revenue_p2 > $this->max_per){
						$level++;
						if($arItem["price_platform{$level}"] && $arItem["price_platform{$level}"] > 0) {
							$new_price = $this->getNewPrice($arItem, $level);

						}else{
							$this->detail_item_log = "Установлена ({$level},1) ({$this->arSuppName[$arItem["supplier_id"]]}) - {$arItem["price"]}";
							$new_price = $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]);

							$this->set_price_level = -1;
						}
					}else{

					}
				}

			}else{
				$level++;
				if($arItem["price_platform{$level}"] && $arItem["price_platform{$level}"] > 0){
					$new_price = $this->getNewPrice($arItem, $level);
				}else{
					$this->detail_item_log = "Установлена ({$level},2) ({$this->arSuppName[$arItem["supplier_id"]]}) - {$arItem["price"]} goodMarginality - {$goodMarginality}\r\n";
					$new_price = $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]);
					//prent($new_price);
					$this->set_price_level = -1;
				}
			}

		}else{
			$this->detail_item_log = "Установлена ({$level},3) ({$this->arSuppName[$arItem["supplier_id"]]}) - {$arItem["price"]}";
			//$this->detail_item_log .= serialize($arItem);
			$new_price = $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]);

			$this->set_price_level = -1;
		}
		$this->metric_log = "Себестоимость: ".round($arItem["price"],2)." от поставщика {$this->arSuppName[$arItem["supplier_id"]]}";

		return $new_price;
	}

	public function modifyPriceWithoutSale($ID = false, $price = false){
		if(!$ID || !$price > 0) return false;

		$ar = AHCatalog::OnGetOptimalPrice($ID, 1, array(), "N", array(), $this->siteR);
		if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
			$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;

			//цена без скидки
			//$price = $price + $price * $discount_per;
			$price = $price / (1 - $discount_per);
			$price = round($price, $this->round);
		}
		return $price;
	}

	// получаем цену РРЦ
	public function getOptimalPrice($brand_id = 0, $price = false, $productID = false){
		if(!$brand_id || !$price) return false;
		$price = round($price);
		$markup = 1;
		if($this->arMargin[$brand_id]){
			$profile["settings"] = json_decode($this->arMargin[$brand_id], true);
			foreach($profile["settings"] as $key => $arItem){
				if($price >= $arItem["price_from"] && $price <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}
		}elseif(is_array($this->arDefaultRRC["rules"])){
			foreach($this->arDefaultRRC["rules"] as $key => $arItem){
				if($price >= $arItem["price_from"] && $price <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}
		}

		if ( !empty($this->indivMarkups[$this->priceID][$productID]) ){
			$markupInd = $this->indivMarkups[$this->priceID][$productID]['m'];
			$price = $price * floatval($markupInd);

		}else{
			$price = $price * $markup;
		}
		return $price;

	}

	public function setPrice($arItems = array(), $arNoModify = array()){
		//if(count($arItems) <= 0) return false;
		global $DB;
		//AddMessage2Log($arItems);
		print_r('--- arItems');
		var_dump(count($arItems));

		$this->CNT_PRICE = count_($arItems);
		$this->CNT_PRICE_MOD = 0;

		$arLog = array();
		$_el = new CIBlockElement;

		$this->logger->log("LOG", "Количество товаров " . $this->CNT_PRICE);
		// $debLog = [];
		foreach($arItems as $key => $arItem){

			$text = $logSearch = "";
			$isNewPrice = false;
			/* если простой товар */

			// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/speedTest.txt', print_r(date('G:i:s') . ' --- 1', 1) . PHP_EOL, FILE_APPEND);
			// $debLog['ID'] = $arItem["PRODUCT_ID"];

			if($arItem["PRODUCT_SKU_ID"] == false && $this->priceID != "WB" && $this->priceID != "WBTL" && $this->priceID != "OS" && $this->priceID != "AV" && $this->priceID != "SB" && $this->priceID != "OZKZ" && $this->priceID != "OZTI"){
				$arFilter = array(
					"IBLOCK_ID" => CProSet::IB_CATALOG,
					"ID" => $arItem["PRODUCT_ID"]
				);
				$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "ACTIVE"));
				if ($el = $res->GetNext()){
					//wdhs price

					$arSection = getSectionsElement($el["ID"]);


					$arFields = Array(
						"PRODUCT_ID" => $el["ID"],
						"CATALOG_GROUP_ID" => $this->PRICE_TYPE_ID,
						"PRICE" => $arItem["PRICE"],
						"CURRENCY" => $this->currency,
					);
					// $debLog['arFields'] = $arFields;
					$p_res = CPrice::GetList(
						array(),
						array(
							"PRODUCT_ID" => $el["ID"],
							"CATALOG_GROUP_ID" => $this->PRICE_TYPE_ID
						)
					);





					if ($arr = $p_res->Fetch()){
						// $debLog['CPrice_result'] = $arr;
						//if($arItem["PRICE_OLD"] != $arItem["PRICE"])
						//	$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> ";
						CPrice::Update($arr["ID"], $arFields);
					}else{
						//$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " (добавлена) >> ";
						$isNewPrice = true;
						CPrice::Add($arFields);
					}

					if (!empty($arItem["PRICE"]) && intval($arItem["discPrice"]) != 0 && !empty($arItem["discPrice"])) {
						$propertyEnums = CIBlockPropertyEnum::GetList(
									array(),
									array(
											"IBLOCK_ID" => 16,
											"CODE" => "DP_DISCOUNT",
											"VALUE" => intval($arItem["discPrice"])."%"
									)
							);
							if ($enum = $propertyEnums->Fetch()) {
									$enumId = $enum["ID"];

								CIBlockElement::SetPropertyValuesEx($el["ID"], 16, array('DP_DISCOUNT' => $enumId));
							}

					} else {
							CIBlockElement::SetPropertyValuesEx($el["ID"], 16, array('DP_DISCOUNT' => array()));
							CIBlockElement::SetPropertyValuesEx($el["ID"], 16, array('DP_DISCOUNT' => false));
					}

					CExchange::updateProduct($el["ID"], CProSet::IB_CATALOG);
					$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$el["ID"]}'";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);

					if ($row = $results->Fetch()){
						$in = array(
							"set_price_{$this->lowerPriceID}" => $arItem["SET_PRICE_LEVEL"],
						);
						$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);

						//if(round($arItem["PRICE_OLD"], 2) != round($b_price_dis, 2)){
						$this->CNT_PRICE_MOD++;
						$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . ($isNewPrice === true ? " (добавлена)" : "") . " >> " . $row[$this->key_price_discount];
						//}
					}else{
						//if(round($arItem["PRICE_OLD"], 2) != round($arItem["PRICE"], 2)){
						$this->CNT_PRICE_MOD++;
						$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . ($isNewPrice === true ? " (добавлена)" : "") . " >> " . $arItem["PRICE"];
						//}
					}

					//обновляем данные во временной таблице
					/*$strSql = "SELECT * FROM ci_price_catalog WHERE product_id = '{$el["ID"]}'";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch()){

						$ar = AHCatalog::OnGetOptimalPrice($el["ID"], 1, array(), "N", array(), $this->siteR);

						$b_price = CCurrencyRates::ConvertCurrency($ar["PRICE"]["PRICE"], $ar["PRICE"]["CURRENCY"], $this->currency);
						$b_price = round($b_price, 0);
						$b_price_dis = CCurrencyRates::ConvertCurrency($ar["RESULT_PRICE"]["DISCOUNT_PRICE"], $ar["RESULT_PRICE"]["CURRENCY"], $this->currency);
						$b_price_dis = round($b_price_dis, 0);
						$in = array(
							"{$this->key_price}" => $b_price,
							"{$this->key_price_discount}" => $b_price_dis,
							"set_price_{$this->lowerPriceID}" => $arItem["SET_PRICE_LEVEL"],
							"timestamp" => "'".date("Y-m-d H:i:s")."'",
						);
						$DB->Update("ci_price_catalog", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);
						//обновляем элемент чтоб сработали все события
						//$rs = $_el->Update($el["ID"], array("ACTIVE" => $el["ACTIVE"]));
						//$arFields = array("ID" => $el["ID"], "IBLOCK_ID" => 16);
						//TsIblock::setPropAvailable($arFields);

						//if(round($arItem["PRICE_OLD"], 2) != round($b_price_dis, 2)){
							$this->CNT_PRICE_MOD++;
							$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . ($isNewPrice === true ? " (добавлена)" : "") . " >> " . $b_price_dis;
						//}
					}else{
						//if(round($arItem["PRICE_OLD"], 2) != round($arItem["PRICE"], 2)){
							$this->CNT_PRICE_MOD++;
							$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . ($isNewPrice === true ? " (добавлена)" : "") . " >> " . $arItem["PRICE"];
						//}
					}*/

				}else{
					$text = "Товар с ID - " . $arItem["PRODUCT_ID"] . " не найден";
				}
			}elseif($this->priceID == "WB"){
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("WBPRICE" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_wb" => $arItem["PRICE"],
				);



				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$pr1 = $arItem["PRICE_OLD"] / 100 * (100 - $this->sale_per) / (100 / (100 - $this->promo_per));
				$pr2 = $arItem["PRICE"] / 100 * (100 - $this->sale_per) / (100 / (100 - $this->promo_per));

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $pr1 . " >> " . $pr2;

			}elseif($this->priceID == "WBTL"){
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("WBTL_PRICE" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_wbtl" => $arItem["PRICE"],
				);


				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$pr1 = $arItem["PRICE_OLD"] / 100 * (100 - $this->sale_per) / (100 / (100 - $this->promo_per));
				$pr2 = $arItem["PRICE"] / 100 * (100 - $this->sale_per) / (100 / (100 - $this->promo_per));

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $pr1 . " >> " . $pr2;

			}elseif($this->priceID == "OS"){
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("OZSB_PRICE" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				//metrica
				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_os" => $arItem["PRICE"],
				);

				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> " . $arItem["PRICE"];
			}
			elseif($this->priceID == "AV"){
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("AVITO_PRICE" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				//metrica
				unset($dataMetrica);
				$someText = 'Установленно новое значение: <b style="color:#0fc609;">'.$arItem['PRICE'].'</b><br>Тип цены: <b style="color:#1ec0e6;">'.$this->priceID.'</b><br> '.$arItem['METRIC_LOG'].'';
				$dataMetrica = [
						'model' => $arItem['ARTICLE'],
						'name' => 'Обновление цены',
						'price_id' => $this->priceID,
						'code' => 'updatePrice',
						'result' => $someText,
				];
				$this->metric->Price($dataMetrica);

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_av" => $arItem["PRICE"],
				);

				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> " . $arItem["PRICE"];
			} elseif($this->priceID == "SB"){
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("SBER_PRICE" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				//metrica
				unset($dataMetrica);
				$someText = 'Установленно новое значение: <b style="color:#0fc609;">'.$arItem['PRICE'].'</b><br>Тип цены: <b style="color:#1ec0e6;">'.$this->priceID.'</b><br> '.$arItem['METRIC_LOG'].'';
				$dataMetrica = [
						'model' => $arItem['ARTICLE'],
						'name' => 'Обновление цены',
						'price_id' => $this->priceID,
						'code' => 'updatePrice',
						'result' => $someText,
				];
				$this->metric->Price($dataMetrica);

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_sb" => $arItem["PRICE"],
				);

				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> " . $arItem["PRICE"];
			} elseif($this->priceID == "OZKZ"){
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/kzprice.txt", print_r($arItem, true).PHP_EOL, FILE_APPEND);
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("PRICE_OZKZ" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_ozkz" => $arItem["PRICE"],
				);

				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> " . $arItem["PRICE"];
			}
			elseif($this->priceID == "OZTI"){
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/kzprice.txt", print_r($arItem, true).PHP_EOL, FILE_APPEND);
				CIBlockElement::SetPropertyValuesEx($arItem["PRODUCT_ID"], false, array("PRICE_OZTI" => ($arItem["PRICE"] > 0 ? $arItem["PRICE"] : false)));

				$this->CNT_PRICE_MOD++;

				//обновляем данные во временной таблице
				$in = array(
					"price_ozti" => $arItem["PRICE"],
				);

				$DB->Update("ci_price_catalog", $in, "WHERE product_id='".$arItem["PRODUCT_ID"]."'", $err_mess.__LINE__);

				$text = "<a href='".$this->url."".$arItem["DETAIL_PAGE_URL"]."' target='_blank'>" . $arItem["ARTICLE"] . "</a> " . $arItem["PRICE_OLD"] . " >> " . $arItem["PRICE"];
			}


			$logSearch = $arItem["ARTICLE"];

			if(strlen($text) > 0){
				CLog::add2log(array("event" => "UI", "text" => $text, "price_id" => $this->priceID, "detail" => $arItem["DETAIL_ITEM_LOG"], "search" => $logSearch));
				$arLog[] = $text;
				$this->logger->log("LOG", $text);
			}

			CProSet::setOption($this->OPTION_UPDATE, round(50 + (($key + 1) / $this->CNT_PRICE) / 2 * 100, 2));

			// смотрим надо ли добавлять товар в карантин
			if($isNewPrice === false && $arItem["PRICE_OLD"] > 0 && $arItem["PRICE"] > 0){
				$f = false;

				if($arItem["PRICE_OLD"] > $arItem["PRICE"] && ($arItem["PRICE_OLD"] / $arItem["PRICE"]) >= 2){
					$f = true;
				}

				if($f){
					$this->priceQuarantine[] = [
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arItem["ARTICLE"],
						"PRICE_ID" => $this->priceID,
						"PRICE" => round($arItem["PRICE"], 2),
						"PRICE_OLD" => round($arItem["PRICE_OLD"], 2),
					];

					//metrica
					unset($dataMetrica);
					$someText = '<b style="color:#f00;"> ТОВАР ПОПАЛ В КАРАНТИН СТАРАЯ ЦНЕА = '.round($arItem["PRICE_OLD"], 2).' => НОВАЯ ЦЕНА = '.round($arItem["PRICE"], 2).'<br>Тип цены: '.$this->priceID.'<br>'.$arItem['METRIC_LOG'];
					$dataMetrica = [
							'model' => $arItem['ARTICLE'],
							'price_id' => $this->priceID,
							'name' => 'Карантин',
							'code' => 'Quarantine',
							'result' => $someText,
					];
					$this->metric->Price($dataMetrica);
				}

			}

			//удаляем папки с композитом
			//clearDirCompositeCache($arItem["DETAIL_PAGE_URL"]);

		}

		$this->setQuarantine();

		// op comment 02-07-2020.
//		$cache_manager = Bitrix\Main\Application::getInstance()->getTaggedCache();
//		$cache_manager->ClearByTag("iblock_id_".CProSet::IB_CATALOG);

		//if(count($arLog) > 0){
		if($this->sendMail === true){
			//отсылаем письмо
			$message = "<p>Обновление цен конкурентов '" . $this->priceID . "'</p>";
			foreach($arLog as $text){
				$message .= "<p>" . $text . "</p>";
			}
			$arFields = array(
				"SUBJECT" => "Обновление цен " . $this->priceID . ". " . $this->CNT_PRICE_MOD . " из " . $this->CNT_PRICE_ALL . " элементов.",
				"MESSAGE" => $message,
			);
			CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);
			//CEvent::Send("IM_NEW_MESSAGE", "s1", $arFields, "N");
		}
		$this->logger->log("LOG", "Обновление цен " . $this->priceID . ". " . $this->CNT_PRICE_MOD . " из " . $this->CNT_PRICE_ALL . " элементов.");

	}

	//установка РРЦ для моделей $arItems
	public function setOptimalPrice($arItems = array()){
		if(count_($arItems) <= 0) return false;
		$arSettings = array(
			"round" => $this->round,
			"rate" => 1,
			"currency" => $this->currency
		);
		$objPricelist = new CPanelPricelist;
		$objSupplier = new CPanelSupplier;
		$objCurrency = new CPanelCurrency;

		$arCurrency = $objCurrency->getDetail($this->currency);

		if($arCurrency){
			$arSettings["rate"] = $arCurrency["rate"];
		}

		//$psFilter["article"] = array("MTS-100GL-7A");

		$psFilter["price_id"] = $this->priceID;
		$psFilter["article"] = $arItems;

		$price = $objPricelist->getPriceByFilter($psFilter);

		$arArticle = array();//массив со всеми артикулами
		$tmpPrice = $arPrice = array();//
		foreach($price as $key => &$arItem){

			if($arItem["model"]){
				//$arItem["price"] = $arItem["price"] / $arSettings["rate"];
				//$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);

				$arItem["price"] = (float) ($arItem["price"] / $arSettings["rate"]);
				$arArticle[$arItem["model"]] = $arItem["model"];

				if(isset($tmpPrice[$arItem["model"]])){
					if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
						$tmpPrice[$arItem["model"]] = $arItem;
				}else
					$tmpPrice[$arItem["model"]] = $arItem;
			}
		}
		unset($arItem);

		if(count($arArticle) <= 0) return false;

		$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle));

		foreach($arCatalogPrice as  $key => $arItem){
			$tmp = $tmpPrice[$arItem["model"]];

			$b_price = $arItem[$this->key_price_discount];

			$b_full_price = $arItem[$this->key_price];

			//if($_POST["price_competitors_act"] == "Y" && $price_platform === 0) continue;
			$arPrice[] = array(
				"id" => $tmp["id"],
				"article" => $arItem["model"],
				"detail_page_url" => $arItem["detail_page_url"],
				"price" => $tmp["price"],
				"supplier_id" => $tmp["supplier_id"],
				"brand_id" => $tmp["brand_id"],
				"b_id" => $arItem["product_id"],//ID битрикс
				"b_sku_id" => $arItem["product_sku"],//SKU ID битрикс
				"b_price" => $b_price,//цена битрикс
				"b_price_full" => $b_full_price,
				"price_timestamp" => $tmp["timestamp"],
			);
			//if($arItem["product_sku"]) prent($arItem);
		}

		$this->CNT_PRICE_ALL = count_($arPrice);


		$arItems = $arNoModify = array();
		foreach($arPrice as $key => &$arItem){

			$new_price = $this->getNewPrice($arItem, 1);
			//$new_price = $this->finishPrice($new_price, $arItem);

			$arItem["b_price_full"] = round($arItem["b_price_full"], $this->round);
			if($new_price && $new_price != $arItem["b_price_full"]){
				//пишим цену
				$arItems[] = array(
					"PRODUCT_ID" => $arItem["b_id"],
					"PRODUCT_SKU_ID" => $arItem["b_sku_id"],
					"ARTICLE" => $arItem["article"],
					"PRICE" => $new_price,

					"PRICE_OLD" => $arItem["b_price"],
					"PRICE_OLD_FULL" => $arItem["b_price_full"],
					"DETAIL_PAGE_URL" => $arItem["detail_page_url"],
					"DETAIL_ITEM_LOG" => $this->detail_item_log,
					"METRIC_LOG" => $this->metric_log,
					"SET_PRICE_LEVEL" => $this->set_price_level,
				);

			}elseif($new_price){
				$arNoModify[] = array(
					"PRODUCT_ID" => $arItem["b_id"],
					"PRODUCT_SKU_ID" => $arItem["b_sku_id"],
					"DETAIL_ITEM_LOG" => $this->detail_item_log,
					"SET_PRICE_LEVEL" => $this->set_price_level,
				);
				//prent($arItem,0,1);
			}

		}
		//prent($arNoModify);die;
		unset($arItem);
		// обновляем цены на товары
		$this->setPrice($arItems, $arNoModify);
	}

	// пишим товары, если есть в карантин
	public function setQuarantine(){
		if(!$this->priceQuarantine || count_($this->priceQuarantine) <= 0) return false;
		$this->logger->log("LOG", "Есть товары в карантине", $this->priceQuarantine);
		foreach($this->priceQuarantine as $key => $arItem){

			$strSql = "SELECT ID FROM ci_price_quarantine WHERE PRODUCT_ID  = '{$arItem["PRODUCT_ID"]}' AND PRICE_ID = '{$arItem["PRICE_ID"]}'";

			$in = array(
				"PRODUCT_ID" => "'".$arItem["PRODUCT_ID"]."'",
				"ARTICLE" => "'".$arItem["ARTICLE"]."'",
				"PRICE_ID" => "'".$arItem["PRICE_ID"]."'",
				"PRICE" => "'".$arItem["PRICE"]."'",
				"PRICE_OLD" => "'".$arItem["PRICE_OLD"]."'",
			);

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$this->logger->log("LOG", "update");
				$this->db->Update("ci_price_quarantine", $in, "WHERE ID='".$row["ID"]."'", $err_mess.__LINE__);
			}else{
				$this->logger->log("LOG", "add");
				$this->db->Insert("ci_price_quarantine", $in, $err_mess.__LINE__);
			}
			$this->logger->log("LOG", "Карантин", $in);

			$productId = intval($arItem["PRODUCT_ID"]);
			CExchange::updateProduct($productId);
		}
	}

	//доставем все резервы
	 public function getReserve(){
		global $DB;
		if($this->siteR == "ya") $siteR = "s1"; else $siteR = $this->siteR;

		$strSql = "SELECT ARTICLE as ARTICLE, RESERVED_{$siteR} as RESERVED FROM ci_reserved WHERE RESERVED_{$siteR} > 0";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arReserve[$row["ARTICLE"]] = $row["RESERVED"];
		}
		return $arReserve;
	}
	public function getQuarantine(){
		global $DB;
		$arQuarantine = [];
		$strSql = "SELECT ARTICLE FROM ci_price_quarantine WHERE PRICE_ID = '{$this->priceID}'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arQuarantine[$row["ARTICLE"]] = true;
		}

		return $arQuarantine;
	}

	public function finishPrice($price = 0, $arItem = []){
		if(isset($this->saleItems[$arItem["b_id"]])){
			$price_old = $price;
			$price = $price - ($price * $this->arDefaultRRC["supersale"] / 100);
			$this->detail_item_log .= "Суперцена. {$price_old} -> {$price}" . "\r\n";
		}
		$price = round($price, $this->round);
		return $price;
	}

	public function getSaleItems(){
		//получаем все товары из категории СУПЕРЦЕНА
		$arSelect = array("ID");
		$arFilter = array("IBLOCK_ID" => CProSet::IB_CATALOG, "SECTION_ID" => 370);
		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while($arFields = $res->GetNext()){
			$this->saleItems[$arFields["ID"]] = $arFields["ID"];
		}
	}

}

?>
