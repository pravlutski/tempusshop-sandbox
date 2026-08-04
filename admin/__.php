<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">

<? 
C:\Users\pravl\AppData\Local\Temp\scp21161\royaltime-tempus_2025\var\www\bitrix\data\www\tempusshop.ru\bitrix\modules\sale\lib\orderhistory.php

	protected static function addRecord($entityName, $orderId, $type, $id = null, $entity = null, array $data = array())
	{
		global $USER;
		$userId = (is_object($USER)) ? intval($USER->GetID()) : 0;

		$fields = array(
			"ORDER_ID" => intval($orderId),
			"TYPE" => $type,
			"DATA" => (is_array($data) ? serialize($data) : $data),
			"USER_ID" => $userId,
			"ENTITY" => $entityName,
			"ENTITY_ID" => $id,
		);
sssssss
		if (!array_key_exists("DATE_CREATE", $fields))
		{
			$fields["DATE_CREATE"] = new \Bitrix\Main\Type\DateTime();
		}

		if (!array_key_exists("DATE_MODIFY", $fields))
		{
			$fields["DATE_MODIFY"] = new \Bitrix\Main\Type\DateTime();
		}

		static::addInternal($fields);
	}
if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

CModule::IncludeModule("ipol.sdek");

$pvz = new sdekHelper();
$asd = $pvz->getCity(26700);
prent($asd);
	//	$obj = new CPriceUpdate("s2");
	//	$obj->setAllPrice();
		
		
		
		die;
	$objCourier = new CCourier();
	
	$arFilter = array(
		"ids" => array("96930"),
	);

	$res = $objCourier->getOrderCrm($arFilter);
	
	
	prent($res);

die;
function AddOrderProperty33($prop_id, $value, $order) {
	if (!strlen($prop_id)) {
		return false;
	}
	if (CModule::IncludeModule('sale')) {
		if ($arOrderProps = CSaleOrderProps::GetByID($prop_id)) {
			$db_vals = CSaleOrderPropsValue::GetList(array(), array('ORDER_ID' => $order, 'ORDER_PROPS_ID' => $arOrderProps['ID']));
			if ($arVals = $db_vals->Fetch()) {
				return CSaleOrderPropsValue::Update($arVals['ID'], array(
					'NAME' => $arVals['NAME'],
					'CODE' => $arVals['CODE'],
					'ORDER_PROPS_ID' => $arVals['ORDER_PROPS_ID'],
					'ORDER_ID' => $arVals['ORDER_ID'],
					'VALUE' => $value,
				));
			} else {
				return CSaleOrderPropsValue::Add(array(
					'NAME' => $arOrderProps['NAME'],
					'CODE' => $arOrderProps['CODE'],
					'ORDER_PROPS_ID' => $arOrderProps['ID'],
					'ORDER_ID' => $order,
					'VALUE' => $value,
				));
			}
		}
	}
}
AddOrderProperty(56, "01.07.2021", 96529);
AddOrderProperty(10, "11:00", 96529);

$dateFrom = "27.03.2021 11:55:24";
$dateFrom = getNextBusinessDay($dateFrom, 3, "d.m.Y") . date(" H:i:s");
prent($dateFrom);
$objDateFrom = new Bitrix/Main/Type/DateTime($dateFrom);
//$objDateFrom->add("+3 day");
prent($objDateFrom->format("d.m.Y H:i:s"));
						
						die;
/*
C:/Users/Олег/AppData/Local/Temp/scp04508/royaltime%2Ftempus_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/aspro.max/classes/general/CMax.php
$propValues = $item['PROPERTIES'][$propertyCode]['VALUE'];
                        if (!is_array($propValues))
                            $propValues = array($propValues);
						
						//op
						//обрезаю до 3 картинок макс
						$propValues = array_slice($propValues, 0, 3);
						
						
						

/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/service/marketplacedbs/action/cart/action.php
/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/entity/sale/listener.php

/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/entity/sale/delivery.php


/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/service/marketplacedbs/action/orderaccept/action.php


/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/service/common/action/sendstatus/action.php
		$result = [
			'id' => (string)$deliveryId,
			'type' => $calculationResult->getDeliveryType(),
			'serviceName' => $this->makeDeliveryOptionServiceName($calculationResult),
			'price' => $calculationResult->getPrice(),
			'vat' => 'NO_VAT',// op. без этой строки ошибка в маркете тестового заказа. для товаров до 3к с доставкой 300р. validate error: delivery vat is null
		];




закомментировал строки 1437-1458 в файле /bitrix/templates/.default/components/op/catalog.element/main_op/template.php

добавил строки 1216-1233 в файле /bitrix/templates/.default/components/op/catalog.element/main_op/template.php

закомментировал строки 347-358 в файле /bitrix/templates/.default/components/op/catalog.element/main_op/component_epilog.php

дописал стили 41-49 строка и 57-59 строка в файле /bitrix/templates/aspro_max/css/custom.css
						
						
						
						
CModule::IncludeModule('crm_courier');
CModule::IncludeModule('panel.manager');
global $DB; 

$asd = new CExchange();
$asd->updateProduct(138017, 16);


die;
$arSectionFilter = array(
	'IBLOCK_ID' => CProSet::IB_CATALOG,
	'ACTIVE' => 'Y',
	'GLOBAL_ACTIVE' => 'Y',
	'ACTIVE_DATE' => 'Y',
	'=DEPTH_LEVEL' => 2,
	"SECTION_ID" => 932
);
$arSectionSelect = array(
	'CODE',
	'NAME',
	'SECTION_PAGE_URL',
);

$arSections = CMaxCache::CIBlockSection_GetList(array('SORT' => 'ASC', 'NAME' => 'ASC', 'CACHE' => array('TAG' => CMaxCache::GetIBlockCacheTag($arParams['IBLOCK_ID']), 'MULTI' => 'Y')), $arSectionFilter, false, $arSectionSelect);
foreach($arSections as $key => $arItem){
	$aMenuLinksExt[] = array(
		$arItem["NAME"], 
		$arItem["SECTION_PAGE_URL"], 
	);
}
prent($aMenuLinksExt);

die;
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ACTIVE" => "Y", 
			">CATALOG_PRICE_3" => 0,
//			"=PROPERTY_SITE_ID" => "s3",
			">CATALOG_QUANTITY" => "0",
		);
		$rsAll = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
		$allCnt = $rsAll->SelectedRowsCount();
		prent($allCnt);
		die;
		$type_price = ($ar['PROPERTY_SITE_ID_VALUE'] == "s1" ? 1 : 2);
		$arFilterEl = Array(
			"IBLOCK_ID"	=> CProSet::IB_CATALOG,
			"ACTIVE"	=> "Y",
			"ID" =>999
		);

		$rsEl = CIBlockElement::GetList(array(), $arFilterEl, false, false, array("ID", "NAME", "IBLOCK_ID", "DETAIL_PAGE_URL"));
		if($arFields = $rsEl->GetNext()){
			prent($arFields);
		}
die;
		$arEventFields = array(
			"EMAIL_RAW"	=> "pravlutski@gmail.com",
			"PRODUCT_NAME"	=> "dddddd",
			"MESSAGE"	=> "xxxxxxx",
			"SUBJECT"	=> "Товар {$arItem["NAME"]} поступил на склад и доступен для заказа",
		);
					
		CEvent::Send("FORM_FILLING_ADDTOSUBSCRIBE", SITE_ID, $arEventFields, "N");
		
		
		
		die;
			foreach(GetModuleEvents("iblock", "OnAfterIBlockElementUpdate", true) as $arEvent)
			{
				
prent($arEvent);
				
			}
			
			die;
global $CACHE_MANAGER;


	//die;
	
	$db_old_groups = CIBlockElement::GetElementGroups(123404, true);
	$arOld = array();
	while($ar_group = $db_old_groups->Fetch()){
		if($ar_group["ID"] != 370)
			$arOld[] = $ar_group["ID"];
	}
	prent($arOld);
	//$arsec = array(271, 370);
	CIBlockElement::SetElementSection(123404, $arOld);
	
	clearElementCache(CProSet::IB_CATALOG, 123404);
$arrFilter = array(
"=PROPERTY_87]" => Array(122292),
    "IBLOCK_ID" => 16,
    "IBLOCK_LID" => "s2",
    "ACTIVE_DATE" => "Y",
    "ACTIVE" => "Y",
    "CHECK_PERMISSIONS" => "Y",
    "MIN_PERMISSION" => "R",
    "INCLUDE_SUBSECTIONS" => "Y",
    "CATALOG_AVAILABLE" => "Y",
    "SECTION_ID" => 370,
    "CATALOG_SHOP_QUANTITY_2" => 1,
	);

	$rsElements = CIBlockElement::GetList($arSort, $arrFilter, false, $arNavParams, $arSelect);
	while($arItem = $rsElements->GetNext())
	{
		prent($arItem);
	}
//$CACHE_MANAGER->ClearByTag('product_123404');
//CIBlock::clearIblockTagCache(16);
die;
die;
	
		$obj = new CPriceUpdate("s2");
		$obj->setAllPrice();



$asd = $DB->FormatDate(time(), FORMAT_DATETIME, 'YYYY-MM-DD HH:MI:SS');
prent($asd);
$asd = $DB->CurrentDateFunction();
prent($asd);
die;
	$db_old_groups = CIBlockElement::GetElementGroups(3845, true);
	$arOld = array();
	while($ar_group = $db_old_groups->Fetch()){
		$arOld[$ar_group["IBLOCK_ELEMENT_ID"]]["ID"] = $ar_group["IBLOCK_ELEMENT_ID"];
		if($ar_group["ID"] != 370)
			$arOld[$ar_group["IBLOCK_ELEMENT_ID"]]["SECTION_ID"][] = $ar_group["ID"];
	}
	$arsec = array(271, 370);
	CIBlockElement::SetElementSection(3845, $arsec);
	/Bitrix/Iblock/PropertyIndex/Manager::updateElementIndex(CProSet::IB_CATALOG, 3845);
	prent($arOld["3845"]["SECTION_ID"]);
die;



$arFilter = array(
	"BASKET_PRODUCT_ID" => 20467,
	"@STATUS_ID" => array("N","TA","CO","SE","SB")
);
$rsOrder = CSaleOrder::GetList(array('ID' => 'DESC'), $arFilter);
if(!$arOrder = $rsOrder->Fetch()){
	prent($arOrder);
}


die;
$objCourier = new CCourier();
		$arOrder = array(
			"id" => 75693,
			"customFields" => array("nosms" => 1),
		);
		$res2 = $objCourier->setOrder($arOrder, "id", "tempus-by");

prent($res2);
die;
			$in = array(
				"model" => "'MTP-1374L-1A'"
			);
			$DB->Insert("ci_items_diff", $in, $err_mess.__LINE__);
			
$asd = new CExchange();
$asd->updateCatalogDiff();
//prent($asd);
die;
$obj = new CCeneoParserURI();
$up = $obj->parse($lastID);



die;
$rsEl = CIBlockElement::GetList(array("ID" => "ASC"), array("IBLOCK_ID" => 16,"ACTIVE" => "Y"), false, false, array("ID", "PROPERTY_CML2_ARTICLE"));
while($arFields = $rsEl->GetNext()){
	$arArticle[$arFields["ID"]] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];

}

$strSql = "SELECT * FROM ci_ceneo_link WHERE PARSE = 'Y' AND PRICE > 0";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while($row = $results->Fetch()){
	$ar[] = array(
		"ARTICLE" => $arArticle[$row["bitrix_id"]],
		"BITRIX_ID" => $row["bitrix_id"],
		"CENEO_ID" => $row["ceneo_id"],
		"PRICE" => $row["PRICE"],
	);
}
prent($ar); 
die;
$obj1 = new CExchange("s1_order");
$res1 = $obj1->updateFromMoySkladOrder();
prent($res1);
die;
include_once("/var/www/bitrix/data/www/tempusshop.ru/bitrix/components/op/sale.export_pl.1c/class.php");
$export =  new CSaleExportPL();
$export::setLanguage('en');
//$arFilter["ID"] = 90111;
$arFilter["UPDATED_1C"] = 'N';
$arFilter["!EXTERNAL_ORDER"] = 'Y';
$arFilter["LID"] = 's3';
$arFilter[">=DATE_UPDATE"] = '11.02.2021 16:21:58';
  
            $arResultStat = $export::ExportOrders2Xml(
                $arFilter, false, "руб.", "Y", 5,
                NULL, array()
            );
//$arFilter[">DATE_UPDATE"] = ConvertTimeStamp($arParams["MODIFICATION_LABEL"], "FULL");

$curPage = substr("/bitrix/admin/1c_exchange_pl.php", 0, 20);
$curPage .= "_s3";

if(strlen(COption::GetOptionString("sale", "last_export_time_committed_".$curPage, ""))>0){
	$arFilter[">=DATE_UPDATE"] = ConvertTimeStamp(COption::GetOptionString("sale", "last_export_time_committed_".$curPage, "") - 3600, "FULL");
}


prent($curPage);
prent($arFilter[">=DATE_UPDATE"]);
die;
		    $r = $export->export(array(
					'filter'=>array("ID" => 90111),
					'limit'=>1)
			);
prent($r->getData()[0]);
die;
		$strSql = "SELECT el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, el.CODE as CODE, pr.PROPERTY_123 as ARTICLE FROM b_iblock_element el 
		LEFT JOIN 
		b_iblock_element_prop_s16 pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y' 
		AND ((el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''))";
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			//$arArticle[$row["ARTICLE"]] = $row;
			$arArticle[$row["ID"]] = $row;
		}

		$arSupp = $arWorking = array();
		
		$strSql = "SELECT id, settings_pricelist FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$arSupp["s1"][$row["id"]] = $tmp["day_delivery"];
			$arSupp["s2"][$row["id"]] = $tmp["day_delivery_by"];
			$arSupp["s3"][$row["id"]] = $tmp["day_delivery_pl"];
			
			$arWorking[$row["id"]] = $tmp["working_time"];
		}
			
		$arPrice = array();
		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active='Y' AND model = 'GST-B200-1A' ORDER BY price ASC";// GROUP BY model";MIN(price) as 
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s1"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s1"][$row["model"]]) || ($arSupp["s1"][$row["supplier_id"]] < $arPrice["s1"][$row["model"]]["day_delivery"] && (($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100) < 5)){
					$row["day_delivery"] = $arSupp["s1"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s1"][$row["model"]] = $row;
				}
				$ads = "({$row["price"]} - {$arMin[$row["model"]]}) / {$row["price"]} * 100) = " . ($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100;
				
				prent($ads);	
			}
		}
prent($arPrice);
die;
$obj = new OrderService();
$arOrder = $obj->getOrder(array(), array("ID" => 89484));
prent($arOrder);
die;
$asd = CWorkshift::getWorkshiftID(1);

$objCourier = new CCourier();
/*		$arFields = array(
			"USER_ID"       		=> CCourierDelivery::GetUserID(),
			"COURIER_ID"			=> $objCourier->courierID,
			"WORHSHIFT_ID"			=> CWorkshift::getWorkshiftID($objCourier->courierID),
			"ORDER_ID"  			=> 123123,
			"ORDER_NUMBER" 			=> $order["number"],
			"PRICE_ORDER"  			=> (float)$_POST["price_order"],
			"PRICE_DELIVERY"		=> (float)$_POST["price_delivery"],
			"COMMENT"         		=> $_POST["comment"],
			"STATUS" 				=> "delivery",
		);
$rsDelivery = new CCourierDelivery();
$nID = $rsDelivery->Add($arFields);

			
			$filter = array(
			//	"USER_ID" 		=> CCourierDelivery::GetUserID(),
			//	"COURIER_ID"	=> $objCourier->courierID,
				"ORDER_ID" 		=> $order["externalId"],
			);
AddMessage2Log($filter);
AddMessage2Log($arFields);*/

$rsDelivery = new CCourierDelivery();
$filter = array (
  'ORDER_ID' => '86396',
);
$rsList = $rsDelivery->GetList(false, $filter);
prent($rsList);
if(!$rsList) {
	prent("asdasd");
}
?>
номер заказа сразу писать в базу
C:/Users/prvl/AppData/Local/Temp/scp40602/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/sale/lib/orderbase.php




/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/mlife.smsservices/lib/handlers.php







 

/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/service/marketplacedbs/action/cart/action.php
/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/yandex.market/lib/trading/entity/sale/delivery.php
/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/ipol.sdek/classes/general/sdekdelivery.php
/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/ipol.sdek/classes/lib/sdekShipment.php
/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/ipol.sdek/classes/lib/sdekShipmentCollection.php


</div>






C:/Users/prvl/AppData/Local/Temp/scp28528/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/maxyss.wb/include.php

    public static function uploadAllStocks($Authorization = false, $SKLAD = '', $arrFilter = array()){
        // отправить все остатки (агент)
        if(!$Authorization) $Authorization = AUTHORIZATION;
        $arSettings = self::settings_wb();
        if($SKLAD != '') $arSettings["SKLAD"] = $SKLAD;
        $items = self::prepareAllItemsStock($arSettings, $arrFilter);

		$itemsAvail = self::getAllItemsAvail();
		foreach($items["stocks"] as $key => &$arItem){
			if($itemsAvail[$arItem["barcode"]]){
				$arItem["stock"] = 3;
			}else{
				$arItem["stock"] = 0;
			}
		}
		unset($arItem);
		
        if(!empty($items["prices"])) {

            if (Option::get(MAXYSS_WB_NAME, "PRICE_ON", "") == 'Y') {
                $result_price = CMaxyssWbprice::setPrices($Authorization, $items["prices"]);
            }

        }
        if(!empty($items["stocks"])) {
            $result = self::updateStock($Authorization, $items["stocks"]);
        }

        if(empty($arrFilter) && $Authorization == AUTHORIZATION) return "CMaxyssWb::uploadAllStocks();";
        elseif(empty($arrFilter)) return "CMaxyssWb::uploadAllStocks('".$Authorization."', '".$SKLAD."');";
        else return "CMaxyssWb::uploadAllStocks('".$Authorization."', '".$SKLAD."', ".var_export($arrFilter, true).");";
    }

	public static function getAllItemsAvail(){
		$arItems = array();
		if(CModule::IncludeModule("iblock")){
			$arFilter = Array(
				"IBLOCK_ID"	=> 16,
				"PROPERTY_AVAILABILITY_RU" => 512,
				"!PROPERTY_AEN" => false,
			);
			$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_AEN"));
			while($ar = $rs->GetNext()){
				$arItems[$ar["PROPERTY_AEN_VALUE"]] = $ar["PROPERTY_AEN_VALUE"];
			}
		}
		return $arItems;
	}


	
	

            // Ключевые слова
            $arTags = array();
            if($arFields["TAGS"] !='') {
                $arTags = explode(',', $arFields["TAGS"]);
                if(!empty($arTags)) {
                    foreach ($arTags as $tag) {
                        $tags[] = array("value" => str_replace('&nbsp;', ' ', trim($tag)));
                    }
                    if (!empty($tags)) {
						// op обрезаем до 3
						$tags = array_slice($tags, 0, 3);
                        $addin_card[] = array(
                            'type' => GetMessage('WB_MAXYSS_KEYWORD'),
                            'params' => $tags
                        );
                    }
                }
            }
	
	
	
	
	
C:/Users/prvl/AppData/Local/Temp/scp32868/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/maxyss.ozon/include.php
                // we will collect general information for simple products and products with TP

                $img = array();
				/*
				op
                if ($arFields[$arSettings['BASE_PICTURE']] > 0) {
                    $img[] = $imgPath . CFile::GetPath($arFields[$arSettings['BASE_PICTURE']]);
                }
				*/
				
				
				
				
				

C:/Users/User/AppData/Local/Temp/scp30464/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/maxyss.ozon/include.php



			// op 
			$itemsAvail = self::getAllItemsAvail();
			foreach($arUpdateStock as $key => &$arItem){
				if($itemsAvail[$arItem["offer_id"]]){
					$arItem["stock"] = 3;
				}else{
					$arItem["stock"] = 0;
				}
			}
			unset($arItem);
			AddMessage2Log($arUpdateStock);
			
            if (!empty($arUpdateStock)) {
                self::update_stock($arUpdateStock, $ClientId, $ApiKey, $base_url, $filename);
            }
            if (!empty($arUpdatePrice) && $arOptions[$lid]['NO_UPLOAD_PRICE'] != "Y") {
                self::update_price($arUpdatePrice, $ClientId, $ApiKey, $base_url, $filename);
            }


            if($arOptions[$lid]['NO_UPLOAD_PRODUCT'] != "Y") {

                if (!empty($arAdd) && !isset($flags['f_false']) && $flags['f_true']) {
                    foreach ($arAdd as &$val) {
                        unset($val['stock']);
                    }
                    $arItemsIdChunk_import = array_chunk($arAdd, 100);
                    foreach ($arItemsIdChunk_import as $items_import) {
                        self::import(array_values($items_import), $ClientId, $ApiKey, $base_url, $filename);
                    }
                }
            }

        }
    }
	
	// op 
	public static function getAllItemsAvail(){
		$arItems = array();
		if(CModule::IncludeModule("iblock")){
			$arFilter = Array(
				"IBLOCK_ID"	=> 16,
				"PROPERTY_AVAILABILITY_RU" => 512,
				"!PROPERTY_WBARTICLE" => false,
			);
			$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_WBARTICLE"));
			while($ar = $rs->GetNext()){
				$arItems[$ar["PROPERTY_WBARTICLE_VALUE"]] = $ar["PROPERTY_WBARTICLE_VALUE"];
			}
		}
		return $arItems;
	}



C:/Users/User/AppData/Local/Temp/scp14897/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/aspro.max/classes/smartseo/admin/controllers/FilterConditionController.php


// op $data['CONDITION'] = $this->request->get('rule');
с этой строкой не сохраняет условие фильтра



/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/aspro.max/lib/functions/CAsproMaxItem.php

		public static function showSectionGallery( $params = array() ){
			$arItem = isset($params['ITEM']) ? $params['ITEM'] : array();
			$key = isset($params['GALLERY_KEY']) ? $params['GALLERY_KEY'] : 'GALLERY';
			$bReturn = isset($params['RETURN']) ? $params['RETURN'] : false;
			$arResize = isset($params['RESIZE']) ? $params['RESIZE'] : array('WIDTH' => 600, 'HEIGHT' => 600);
			
			if($params["SHOW_LINK"] === false) $arItem["DETAIL_PAGE_URL"] = "javascript:void(0)";// op
			if($arItem):?>
			

// /var/www/bitrix/data/www/tempusshop.ru/bitrix/components/bitrix/sale.order.ajax/class.php
// formatLocation

/var/www/bitrix/data/www/tempusshop.ru/bitrix/templates/aspro_max/components/bitrix/catalog/main_op/sort.php

								<?if($sort == "PROPERTY_AVAILABILITY_RU" || $sort == "PROPERTY_AVAILABILITY_BY" || $sort == "ID"):?>
									<?=/Bitrix/Main/Localization/Loc::getMessage('SECT_SORT_'.$sort)?>
								<?else:?>
									<?=/Bitrix/Main/Localization/Loc::getMessage('SECT_SORT_'.$sort)./Bitrix/Main/Localization/Loc::getMessage('SECT_ORDER_'.$sort_order)?>
								<?endif?>

C:/Users/Олег/AppData/Local/Temp/scp58450/royaltime%2Ftempus_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/templates/aspro_max/components/bitrix/catalog.search/main/lang/ru/template.php

	
$MESS["SECT_SORT_ID"] = "По новинкам";
$MESS["SECT_SORT_PROPERTY_AVAILABILITY_RU"] = "По популярности";
$MESS["SECT_SORT_PROPERTY_AVAILABILITY_BY"] = "По популярности";



/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/iblock/classes/mysql/iblockelement.php

				// op
				if($arFilter["SECTION_ID"] > 0){
					$cnt = CIBlockSection::GetSectionElementsCount($arFilter["SECTION_ID"], Array("CNT_ACTIVE"=>"Y"));
				}elseif ($el->sGroupBy == ""){
					$res_cnt = $DB->Query("



это уже убрал
C:/Users/User/AppData/Local/Temp/scp13535/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/iblock/admin/iblock_element_edit.php
		<?if($arIBlock["SECTION_PROPERTY"] === "Y" || defined("CATALOG_PRODUCT")):?>
// op		groupField = new JCIBlockGroupField(form, 'tr_IBLOCK_ELEMENT_PROPERTY', url);
// op		groupField.reload();
		<?endif;
при выборе раздела обновляется часть страницы на вкладке Товар. и пропадают свойства



C:/Users/User/AppData/Local/Temp/scp13681/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/main/classes/mysql/agent.php

			if ($arAgent["RETRY_COUNT"] >= 3)
			{
				// op
				$arEventFields = array(
					"EMAIL_TO"	=> "sales@tempus.by",
					"MESSAGE"	=> "Слетел агент. <a href='https://tempusshop.ru/bitrix/admin/agent_edit.php?ID={$arAgent["ID"]}&lang=ru'>Модуль - " . $arAgent["MODULE_ID"] . " NAME - " . $arAgent["NAME"] . "</a>",
					"SUBJECT"	=> "Ошибка. Слетел агент " . $arAgent["NAME"],
				);

				CEvent::Send("IM_NEW_MESSAGE", "s1", $arEventFields, "Y");
				
				$DB->Query("UPDATE b_agent SET ACTIVE='N' WHERE ID = ".$arAgent["ID"]);
				continue;
			}


/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/iblock/lib/elementtable.php
			'SORT_RU' => new ORM/Fields/FloatField('SORT_RU', array(
				'default_value' => 1,
				'title' => Loc::getMessage('ELEMENT_ENTITY_SORT_FIELD'),
			)),
			'SORT_BY' => new ORM/Fields/FloatField('SORT_BY', array(
				'default_value' => 1,
				'title' => Loc::getMessage('ELEMENT_ENTITY_SORT_FIELD'),
			)),
			'SORT_PL' => new ORM/Fields/FloatField('SORT_PL', array(
				'default_value' => 1,
				'title' => Loc::getMessage('ELEMENT_ENTITY_SORT_FIELD'),
			)),



C:/Users/Олег/AppData/Local/Temp/scp35465/royaltime%2Ftempus_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/iblock/classes/general/iblockelement.php

				elseif($by == "SORT_RU") $arSqlOrder[$by] = CIBlock::_Order("BE.SORT_RU", $order, "desc");
				elseif($by == "SORT_BY") $arSqlOrder[$by] = CIBlock::_Order("BE.SORT_BY", $order, "desc");
				elseif($by == "SORT_PL") $arSqlOrder[$by] = CIBlock::_Order("BE.SORT_PL", $order, "desc");


C:/Users/Олег/AppData/Local/Temp/scp36363/royaltime%2Ftempus_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/iblock/classes/mysql/iblockelement.php
				"SORT_RU"=>"BE.SORT_RU",
				"SORT_BY"=>"BE.SORT_BY",
				"SORT_PL"=>"BE.SORT_PL",


C:/Users/User/AppData/Local/Temp/scp18294/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/sale/lib/compatible/eventcompatibility.php
отследить кто тормозит обновление заказа


асинхронный css 24-05-2022 на тест
C:/Users/User/AppData/Local/Temp/scp32717/royaltime%2Ftempus_hoster_2020/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/main/lib/page/asset.php
	public function insertCss($css, $label = false, $inline = false)
	{
		if ($label === true)
		{
			$label = ' data-template-style="true" ';
		}
		elseif ($label === false)
		{
			$label = '';
		}

		if ($inline)
		{
			return "<style type=/"text/css/" {$label}>/n{$css}/n</style>/n";
		}
		else
		{
			//return "<link href=/"{$css}/" type=/"text/css/" {$label} rel=/"stylesheet/" {$this->xhtmlStyle}>/n";
			return "<link rel=/"preload/" href=/"{$css}/" as=/"style/" onload=/"this.rel='stylesheet'/">";
		}
	}
	
	

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>