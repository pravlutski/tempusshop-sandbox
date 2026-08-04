<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Генерация csv Название модели - производитель - XML_ID");
//header("Access-Control-Allow-Origin: *");

?>
<div class="content wrap">
  
<?
//die;
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;
CModule::IncludeModule("crm_courier");
CModule::IncludeModule("intaro.retailcrm");
CModule::IncludeModule("sale");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule('maxyss.ozon');
CModule::IncludeModule('yandex.market');
CModule::IncludeModule('aspro.smartseo');
global $DB;

if(!CModule::IncludeModule("maxyss.wb")) return false;
if(!CModule::IncludeModule("maxyss.ozon")) return false;

require($_SERVER['DOCUMENT_ROOT'] . '/local/classes/HighloadApi.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/classes/OzonAPI.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php');
RCrmActions::orderAgent();
die; 
$wb = new WildberriesAPI();
//$asd = $wb->getSales("2024-12-23", 1);
$asd = $wb->test(); 
//$asd = sort_nested_arrays($asd, ["date" => "DESC"], true);  

prent($asd);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/dev/WildberriesAPI.txt", print_r([date("Y-m-d H:i:s"), $dateFrom, $asd], true));
		
/*
$order = \Bitrix\Sale\Order::load(542285);
$propertyCollection = $order->getPropertyCollection();
$prop = $propertyCollection->getItemByOrderPropertyId(59); // OZON_NUMBER
if($prop){
	$val = $prop->getValue();
	prent($val);
}
$externalID = $order->getField('OZON_NUMBER');
*/
die;

$order = new HighloadApi(8);

$lel = $order->getList();
prent($lel);
$api = new OzonAPI();

$arFilter = [
	//"status" => $arFilter["status"],
	"since" => "2024-11-23T00:00:00Z",
	//"to" => date("Y-m-d"),
];
$orders = $api->getShipmentsFBO($arFilter);
prent($orders); 
$arOrders = [];
$i = 0;
foreach($orders as $arItem){
	if(!is_array($arItem["products"]) || count($arItem["products"]) == 0) continue;
	
	foreach($arItem["products"] as $arProduct){
		$arOrders[$i] = [
			"ORDER_ID" => $arItem["order_id"],
			"SOURCE" => "OZON_FBO",
			"ORDER_NUMBER" => $arItem["order_number"],
			"POSTING_NUMBER" => $arItem["posting_number"],
			"STATUS" => $arItem["status"],
			"CREATED" => $arItem["created_at"],
			"PRODUCT_NAME" => $arProduct["name"],
			"PRODUCT_ARTICLE_OZON" => $arProduct["offer_id"],
			"CURRENCY" => $arProduct["currency_code"],
			"PRICE" => $arProduct["price"],
			"QUANTITY" => $arProduct["quantity"],
		];
		$i++;
	}
}
prent($arOrders); 
die;
		//$order = $event->getParameter("ENTITY");
$order = Bitrix\Sale\Order::load(541899);
		if($order->getId()){
			$ID = $order->getId();
			$basket = [];
			foreach($order->getBasket() as $obj){
				$basket[] = [
					"ID" => $obj->getField('ID'),
					"PRODUCT_ID" => $obj->getField('PRODUCT_ID'),
				];
				//prent($arItem->value);
			}
			$arFields = [
				"LID" => $order->getSiteId(),
				"USER_ID" => $order->getUserId(),
				"BASKET_ITEMS" => $basket,
			];
			//prent($orderBasket);
			prent([$ID, $arFields]);
			//$order->getSiteId();
			//$order->getUserId();
			//$basket = $order->getBasket();
		}
		
die;

global $DB;
$ORDER_ID = 540906;
//$ORDER_ID = 540353;

$order = \Bitrix\Sale\Order::load($ORDER_ID);
$tpId = false;

if(!$tpId){
	$tradeBindingCollection = $order->getTradeBindingCollection();

	/** @var Bitrix\Sale\TradeBindingEntity $item */
	/* получаем значение свойства "Источник заказа" */
	foreach ($tradeBindingCollection as $item) {
		/* записываем id источника заказа в переменную $tpId */
		$tpId = $item->getField('TRADING_PLATFORM_ID');
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
		6 => "wbtl",
		7 => "sb",
		8 => "ozti",
	];
	if($tpId && $arTp[$tpId]){
		$priceID = $arTp[$tpId];
	}else{
		$lid = $order->getField('LID');
		switch($lid){
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
				$priceID = "ru";
				break;
			default:
				$priceID = "kz";
				break;
		}
	}
}

$rs = \Bitrix\Sale\Internals\BasketTable::getList(array(
	'filter' => array(
		'ORDER_ID' => $ORDER_ID,
	)
));

$arBasket = [];
while ($ar = $rs->fetch()) {
	$arBasket[] = [
		"ORDER_BASKET_ID"	=> $ar["ID"],
		"PRODUCT_ID" 		=> $ar["PRODUCT_ID"],
	];
}
if(count($arBasket) > 0){
	$arIDs = array_column($arBasket, "PRODUCT_ID");
	$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
		FROM
			b_iblock_element el
		LEFT JOIN
			b_iblock_element_prop_s16 pr
		ON el.ID=pr.IBLOCK_ELEMENT_ID
		WHERE
			el.ID IN ('".implode("','", $arIDs)."') AND el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	$arArticle = [];
	while ($row = $results->Fetch()){
		$arArticle[] = $row["ARTICLE"];
	}
	
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
	try{
		$json = shell_exec("/usr/bin/php81 -f {$url}");
		$tmp = json_decode($json, true);
		foreach($tmp as $k => $v){
			$arPrice[$v["b_id"]] = $v;
		}
		
	}catch(Exception $e){
		$arPrice = [];
	}
	
	foreach($arBasket as $arItem){
		if($arPrice[$arItem["PRODUCT_ID"]]){
			$in = array(
				"ORDER_ID" => "'".addslashes($ORDER_ID)."'",
				"ORDER_BASKET_ID" => "'".addslashes($arItem["ORDER_BASKET_ID"])."'",
				"PRICES" => "'".addslashes(serialize($arPrice[$arItem["PRODUCT_ID"]]))."'",
			);
			prent($in);
		}
	}

	die;
	
	/*$arPrice = [];
	if(count($arMatchArticle) > 0){
		$strSql = "SELECT * FROM ci_price WHERE model IN ('".implode("','", $arMatchArticle)."')";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		
		while ($row = $results->Fetch()){
			$arPrice[$row["model"]][] = $row;
		}
	}
	
	foreach($arBasket as &$arItem){
		if($arItem["PRODUCT_ID"] && $arMatchArticle[$arItem["PRODUCT_ID"]]){
			$article = $arMatchArticle[$arItem["PRODUCT_ID"]];
			if($arPrice[$article]){
				$in = array(
					"ORDER_ID" => "'".addslashes($ORDER_ID)."'",
					"ORDER_BASKET_ID" => "'".addslashes($arItem["ORDER_BASKET_ID"])."'",
					"PRICES" => "'".addslashes(serialize($arPrice[$article]))."'",
				);
				prent($in);
			}
		}
	}
	unset($arItem);
	
CREATE TABLE `ci_order_price` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` int(15) COLLATE utf8_unicode_ci NOT NULL,
  `ORDER_BASKET_ID` int(15) COLLATE utf8_unicode_ci NOT NULL,
  `PRICES` TEXT COLLATE utf8_unicode_ci,
  PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
	*/
		
}
die;
$obj = new CPriceUpdate("YA");
$obj->setAllPrice();
		

die;
$arSelect = [
	"ID"
];

$arFilter = [
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"PROPERTY_ARTICLE_ONLINER" => "0",
];

$rs = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

$arGoods = [];
while($arItem = $rs->GetNext()) {
	$arGoods[] = $arItem["ID"];
}
foreach($arGoods as $product_id){
	//CIBlockElement::SetPropertyValueCode($product_id, "ARTICLE_ONLINER", false);
}
prent(count($arGoods));
die;

$ms = new MoyskladAPI("msk2");
//$ms->getStock(0, "stockMode=positiveOnly&filter=stockDaysFrom=14;store=https://online.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4");
$data = [];
//, $header, $all, $getParams, $items
$asd = $ms->send("https://online.moysklad.ru/app/services/r1396/StockService", "POST", $data);
prent($asd);

die;

			$arFields = array(
				"EMAIL_TO" => "sales@tempus.by",
				"SUBJECT" => "Парсер онлайнера сломан",
				"MESSAGE" => "aasdasdasdasd",
			);
			$arFiles = [
				"https://tempusshop.ru/upload/tmp/onliner/onliner_catalog_prices2_0.911026929738.png",
				//str_replace($_SERVER['DOCUMENT_ROOT'], "https://tempusshop.ru", $this->file_tmp),
			]; 
			print_r($arFiles, true);
			CEvent::SendImmediate("IM_NEW_MESSAGE", array("s2"), $arFields, "N", 456, $arFiles);
			$attempt++;
			
			
			
			die;
		$obj = new CPriceUpdate("BY");
		$obj->setAllPrice();
		die;
die;
// прогонять будем весь каталог
$arSelect = [
	"ID", "ACTIVE", "PROPERTY_CML2_ARTICLE"
];

$arFilter = [
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"!PROPERTY_CML2_ARTICLE" => false,
];

$rs = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

$arGoods = [];
while($arItem = $rs->GetNext()) {
	$arGoods[] = $arItem;
}


// WHERE DATEDIFF(CURDATE(), CreatedAt) = 5;
$strSql = "SELECT CODE ci_items_date WHERE DATEDIFF(CURDATE(), DATE_DISAPPEAR) > 365";
$strSql = "SELECT CODE, DATE_DISAPPEAR, DATEDIFF(CURDATE(), DATE_DISAPPEAR) as CNT_DATE_DISAPPEAR FROM ci_items_date";

// WHERE el.ID = '13437' 
// выбираем все цены с максимальным временем обновления по сайтам.
$strSql = "SELECT 
		cat.PRODUCT_ID as PRODUCT_ID, DATEDIFF(CURDATE(), MAX(cat.TIMESTAMP_X)) as LAST_UPDATE_PRICE, el.ACTIVE as ACTIVE 
	FROM 
		b_catalog_price cat 
	LEFT JOIN 
		b_iblock_element el 
	ON cat.PRODUCT_ID=el.ID 
		GROUP BY cat.PRODUCT_ID";//  LIMIT 0,10
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arDeact = $arActivate = [];
while ($row = $results->Fetch()){
	//если больше полугода, то в массив для деактивации
	if($row["LAST_UPDATE_PRICE"] >= 365 && $row["ACTIVE"] == "Y"){
		$arDeact[] = $row;
	}elseif($row["LAST_UPDATE_PRICE"] < 365 && $row["ACTIVE"] == "N"){
		$arActivate[] = $row;
	}
	
}
prent(count($arDeact));
prent($arActivate);die;
foreach($arGoods as $arItem){
	/*if($arItem["ACTIVE"] == "Y" && $arDate[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]){
		// если элемент активен и есть в массиве старше 365 дней
		$arDeact[] = $arItem;
	}elseif($arItem["ACTIVE"] == "N" && !$arDate[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]){
		// если элемент не активен и нет в массиве старше 365 дней
		$arActivate[] = $arItem;
	}*/
	if($arDate[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]){
		$cntDay = $arDate[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]["CNT_DATE_DISAPPEAR"];
		if($cntDay >= 365 && $arItem["ACTIVE"] == "Y")
			$arDeact[] = $arItem;
		elseif($cntDay < 365 && $arItem["ACTIVE"] == "N")
			$arActivate[] = $arItem;
	}
}

$strSql = "SELECT * FROM ci_price WHERE model IN ('".implode("','", array_column($arActivate, "PROPERTY_CML2_ARTICLE_VALUE"))."')";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arDate = [];
while ($row = $results->Fetch()){
	prent($row);
}

prent($arDeact[1112]);
prent(($arActivate));

die;


		$obj = new CPriceUpdate("BY");
		$obj->setAllPrice();
		die;
$file = file_get_contents("/home/bitrix/logs/ozon/OzonUploadProduct/.23.07.2023_loggerLog");


$arFile = explode("\r\n", $file);
$arFile = array_diff($arFile, array(''));
$arFile = array_reverse($arFile);
// берем 2 последних 2 лога. делаем реверс и проходим по строкам
//prent($arFile);

$arConn = [1 => "CURR", 2 => "PREV"];
$cnt = 0;
foreach($arFile as $str){
	if(strripos($str, "START") !== false){
		$cnt++;
		continue;
	}

	if($cnt == 3) break;
	
	if($cnt > 0){
		$tmp = explode(" - ", $str);
		if(count($tmp) == 4){
			//$arResult[$arConn[$cnt]]
		}
		prent($tmp);
	}
	
}


die;

$ms = new MoyskladAPI("s1");
$ms->getStock(0, "stockMode=positiveOnly&filter=stockDaysFrom=14;store=https://online.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4");
prent($ms->MSPosition);
		
		die;
		
//sCExchange::updateProductTable(5397);
//CPanelPricelist::updateDateDelivery();
$obj3 = new CExchange("s1");
$res3 = $obj3->updateFromMoySklad();


die;
CExchange::updateReserved(); 
CExchange::updateProduct(85597);

die;
prent($in);

		
$asd = getAllPrice(1254);prent($asd);
$asd = CExchange::updateProductTableNew(12481);


die;
$f = file_get_contents("/home/bitrix/logs/add_product_2023_06_06.txt");
$asd = unserialize($f);

$o = new CPanelProduct;
$model = $o->getProduct(46193);




prent($model);
die;
$arFields = [
	"USER_ID" => 153818
];
Intaro\RetailCrm\Component\Handlers\EventsHandlers::OnAfterUserRegisterHandler($arFields);




die;
$localFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/partner_yandex/22-30-2023-05-29_competitor_prices_1357715.xlsx";
		$obj = new CYandexParser($localFile);
		$res = $obj->parse();
		
		die;
$ms = new MoyskladAPI("s1");

		$obj = new CPriceUpdate("BY");
		$obj->setAllPrice();
		
		
		die;
CMaxyssOzonAgent::OzonUploadProduct('s1',2005);	



die;
$from = (new DateTime('-1 month'))->format('d.m.Y');

$period = new DatePeriod(
	new DateTime($from),
	new DateInterval('P1D'),
	new DateTime(date("d.m.Y") . ' 23:59')
);
$dates = array();
foreach ($period as $key => $value) {
	$dates[] = $value->format('Y-m-d');     
}
prent($dates);die;


$res = $ms->getListDemand();


$filepath = "/var/www/bitrix/data/www/tempusshop.ru/upload/ms/demand.csv";


foreach($res as $key => $arItem){

	$delivery = "";
	
	foreach($arItem["attributes"] as $k => $v){
		if($v["id"] == "31f38375-3d0f-11ea-0a80-03a20007e5a3"){
			$delivery = $v["value"]["name"];
		}
	}
	
	$arCsv[] = [
		"id" => '"'.$arItem["id"] . '"',
		"accountId" => '"'.$arItem["accountId"] . '"',
		"updated" => '"'.$arItem["updated"] . '"',
		"name" => '"'.$arItem["name"] . '"',
		"description" => '"'.$arItem["description"] . '"',
		"code" => '"'.$arItem["code"] . '"',
		"externalCode" => '"'.$arItemv["externalCode"] . '"',
		"moment" => '"'.$arItem["moment"] . '"',
		"applicable" => '"'.$arItem["applicable"] . '"',
		"sum" => '"'.$arItem["sum"] . '"',
		"created" => '"'.$arItem["created"] . '"',
		"printed" => '"'.$arItem["printed"] . '"',
		"published" => '"'.$arItem["published"] . '"',
		"vatEnabled" => '"'.$arItemv["vatEnabled"] . '"',
		"vatIncluded" => '"'.$arItem["vatIncluded"] . '"',
		"vatSum" => '"'.$arItem["vatSum"] . '"',
		"payedSum" => '"'.$arItem["payedSum"] . '"',
		"delivery" => '"'.$delivery . '"',
	];

}
unlink($filepath);

$arColumn = array_keys($arCsv[0]);
$str_csv = implode(",", $arColumn) . "\r\n";
file_put_contents($filepath , $str_csv, FILE_APPEND);

foreach($arCsv as $k => $arItem){
	$str_csv = implode(",", $arItem) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}
die;







$arKey = ["id","accountId","updated","name","description","code","externalCode","moment","applicable","sum","created","printed","published","vatEnabled","vatIncluded","vatSum","payedSum", "delivery"];


$arKey = [];
foreach($res[0] as $k => $ar){
	if(is_array($ar)){
		$result = [];
		array_walk_recursive($ar, function($v,$k) use(&$result){
			$result[] = $k;
		});
		
		foreach($result as $val){
			$arKey[] = '"'.$k . "_" . $val . '"';
		}
	}else{
		$arKey[] = '"'.$k . '"';
	}
}

$filepath = "/var/www/bitrix/data/www/tempusshop.ru/upload/ms/demand.csv";

$str_csv = implode(",", $arKey) . "\r\n";
file_put_contents($filepath , $str_csv);
	prent($res);
foreach($res as $key => $arItem){
	
	$result = [];
	array_walk_recursive($arItem, function($item) use (&$result) {
		$result[] = '"'. htmlspecialchars($item) . '"';
	});
	$str_csv = implode(",", $result) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}



//prent($result);
die;
//DW-5600BB-1A будет BB-1A
//GA-110-1A будет 1A


preg_match("/([a-zA-Z0-9]{1,3}-[a-zA-Z0-9]{1}[0-9]+).+/i", "GA-110-1A", $matches);


prent($matches);

die;
$period = new DatePeriod(
	new DateTime('16.04.2022'),
	new DateInterval('P1D'),
	new DateTime('16.04.2023 23:59')
);
 
$dates = array();
foreach ($period as $key => $value) {
	$dates[] = $value->format('Y-m-d');     
}
//	$file = "/home/bitrix/logs/ms/profit_days/{$date}.txt";
//		$res  = file_get_contents($file);
//		$res  = unserialize($res, ['allowed_classes' => false]);
//		prent($res);die;
		
foreach($dates as $date){
	$file = "/home/bitrix/logs/ms/profit_days/{$date}.txt";
	if(file_exists($file)){
		$res  = file_get_contents($file);
		$res  = unserialize($res, ['allowed_classes' => false]);
		
		foreach($res as $k => $v){
			$arCsv[] = [
				"date" => '"'.$date . '"',
				"channelName" => '"'.$v["salesChannel"]["name"] . '"',
				"channelType" => '"'.$v["salesChannel"]["type"] . '"',
				"salesCount" => '"'.$v["salesCount"] . '"',
				"salesAvgCheck" => '"'.$v["salesAvgCheck"] . '"',
				"sellSum" => '"'.$v["sellSum"] . '"',
				"sellCostSum" => '"'.$v["sellCostSum"] . '"',
				"returnAvgCheck" => '"'.$v["returnAvgCheck"] . '"',
				"returnSum" => '"'.$v["returnSum"] . '"',
				"returnCostSum" => '"'.$v["returnCostSum"] . '"',
				"profit" => '"'.$v["profit"] . '"',
				"margin" => '"'.$v["margin"] . '"',
			];

		}
	}
	
}
$filepath = "/home/bitrix/logs/ms/profit_days/profit_year.csv";
unlink($filepath);
//$handle = fopen($filepath, "r");

foreach($arCsv as $k => $arItem){
	//$barcode = explode(",", $arBarcode);
	$str_csv = implode(";", $arItem) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}


die;
$date = "2023-04-14";
	$obj = new MoyskladAPI("s1");
	$arFilter = array(
		"momentFrom" => "{$date} 00:00:01",
		"momentTo" => "{$date} 23:59:59",
	);
	prent($arFilter);
	$res = $obj->getListProfitChannel($arFilter);
	
	//file_put_contents("/home/bitrix/logs/ms/profit_days/{$date}.txt", serialize($res));
	
prent($res);
            /*[salesChannel] => Array
                (
                    [meta] => Array
                        (
                            [href] => https://online.moysklad.ru/api/remap/1.2/entity/saleschannel/5cf485c8-dad1-11ed-0a80-11b70011b2ff
                            [metadataHref] => https://online.moysklad.ru/api/remap/1.2/entity/saleschannel/metadata
                            [type] => saleschannel
                            [mediaType] => application/json
                            [uuidHref] => https://online.moysklad.ru/app/#saleschannel/edit?id=5cf485c8-dad1-11ed-0a80-11b70011b2ff
                        )

                    [name] => Внутреннее перемещение
                    [type] => OTHER
                )

            [salesCount] => 1
            [salesAvgCheck] => 2458800
            [sellSum] => 2458800
            [sellCostSum] => 2458800
            [returnCount] => 0
            [returnAvgCheck] => 0
            [returnSum] => 0
            [returnCostSum] => 0
            [profit] => 0
            [margin] => 0*/

die;

$barcode = "2029041542629";
$strSql = "SELECT IBLOCK_ELEMENT_ID FROM b_iblock_element_prop_s16 WHERE PROPERTY_2821 = '{$barcode}'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	CIBlockElement::SetPropertyValuesEx($row["IBLOCK_ELEMENT_ID"], false, array("PROP_MAXYSS_CARDID_WB" => false, "PROP_MAXYSS_NMID_WB" => false, "PROP_MAXYSS_CHRTID_WB" => false, "PROP_MAXYSS_NMID_CREATED_WB" => false, "PROP_MAXYSS_CHRTID_CREATED_WB" => false));
}




die;
//

CMaxyssOzonAgent::OzonUploadProduct('s1',177208);
			die;
/*
$from = date("d.m.Y H:i:s", strtotime("-6 month"));
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	">DATE_CREATE" => $from,
	"!PROPERTY_CML2_ARTICLE" => false,
	"PROPERTY_AVAILABILITY_RU" => array(512),
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
while($ar_fields = $rs->GetNext()){
	if(!$arPosition[$ar_fields["XML_ID"]]){
		$arPosition[$ar_fields["XML_ID"]] = array(
			"BITRIX_ID" => $ar_fields["ID"],
			"XML_ID" => $ar_fields["XML_ID"],
			"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
		);
	}
	
}
prent($arPosition);
die;
$obj = new CPanelSupplier();
$supp = $obj->getList();

foreach($supp as $key => $arItem){
	$settings = json_decode($arItem["settings"], true);
	foreach($settings["brand"] as $k => &$v){
		$v["active_ru"] = $v["active"];
		$v["active_ya"] = $v["active_v1"];
		$v["active_os"] = $v["active_v2"];
	}
	unset($v);
	
	$in = array(
		"settings" => "'".json_encode( $settings, JSON_UNESCAPED_UNICODE )."'"
	);
	//prent($in);
	//prent($arItem["id"]);
	$DB->Update("ci_suppliers", $in, "WHERE id = '".$arItem["id"]."'", $err_mess.__LINE__);
	
}

					
//prent($supp);

die;

		$obj = new CPriceUpdate("RU");
		$obj->setAllPrice();
prent($res);
die;

$arPriceID = ["RU","BY","PL","YA","OS","WB","s1","s2","s3","v1","v2","wb"];

$arTabPrice = [];
foreach($arPriceID as $ID){
	$arTabPrice["PRICELIST_MARGIN_{$ID}"] = array(GetMessage("PM_OPTION_PRICELIST_MARGIN") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICELIST_AUTO_SET_{$ID}"] = array(GetMessage("PM_OPTION_PRICELIST_AUTO_SET") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICEUPDATE_REV_MIN_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_REV_MIN") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICEUPDATE_MIN_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MIN_PER") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICEUPDATE_MAX_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MAX_PER") . " " . $ID, Array("text", "10"), "break" => "Y");
}
prent($arTabPrice);

die;
CExchange::updateProduct(171528, 16);
die;
CExchange::updateProductTable(71294);
*/

$strSql = "SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2796'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if($row["DESCRIPTION"] != "WR"){
		prent($row);
		//$DB->Query("DELETE FROM b_iblock_element_prop_m16 WHERE ID = '{$row["ID"]}'");
	}
	
}

die;

	$strSql = "SELECT * FROM ci_analysis WHERE site_id = 's1'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arr[] = $row;
	}
	if(count($arr) > 0){
		
		foreach($arr as $key => $arItem){
			$in = array(
				"brand_id" => intval($arItem["brand_id"]),
				"settings" => "'".addslashes($arItem["settings"])."'",
				"site_id" => "'v1'",
				
			);
			prent($in); 
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_analysis", $in, $err_mess.__LINE__);
		}

	}

die;
//$arOrder = array(185576637); 
$objService = new OrderService;
$resBX = $objService->getOrderCache(array("ID" => "DESC"), array(), array("nTopCount" => 10000));
$bxOrder = $arOrderCRM = array();
foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ID" => $arItem["ID"],
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
		"CANCELED" => $arItem["CANCELED"],
		"DATE_UPDATE" => $arItem["DATE_UPDATE"],
		"PRICE" => $arItem["PRICE"],
		"MAXYSS_WB_STIKER" => $arItem["MAXYSS_WB_STIKER"],
		"MAXYSS_OP_STICKER" => $arItem["MAXYSS_OP_STICKER"],
		"SBER_ID" => $arItem["SBER_ID"],
		"OZON_NUMBER" => $arItem["OZON_NUMBER"],
		"MAXYSS_WB_NUMBER" => $arItem["MAXYSS_WB_NUMBER"],
		"ONLINER_ORDER_KEY" => $arItem["ONLINER_ORDER_KEY"],
	);
}

foreach($bxOrder as $key => $arItem){
	
	/* 
	смотрим дубли в свойствах заказа 
	MAXYSS_WB_STIKER
	MAXYSS_OP_STICKER
	SBER_ID
	OZON_NUMBER
	MAXYSS_WB_NUMBER,
	ONLINER_ORDER_KEY
	*/
	if($arItem["MAXYSS_WB_STIKER"]){
		$arAll["MAXYSS_WB_STIKER"][$arItem["MAXYSS_WB_STIKER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["MAXYSS_OP_STICKER"]){
		$arAll["MAXYSS_OP_STICKER"][$arItem["MAXYSS_OP_STICKER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["SBER_ID"]){
		$arAll["SBER_ID"][$arItem["SBER_ID"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["OZON_NUMBER"]){
		$arAll["OZON_NUMBER"][$arItem["OZON_NUMBER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["MAXYSS_WB_NUMBER"]){
		$arAll["MAXYSS_WB_NUMBER"][$arItem["MAXYSS_WB_NUMBER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["ONLINER_ORDER_KEY"]){
		$arAll["ONLINER_ORDER_KEY"][$arItem["ONLINER_ORDER_KEY"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
}

foreach($arAll as $key => $ar){
	foreach($ar as $num => $arItem){
		if(count($arItem) > 1){
			$txt = "Дубль " . $key;
			foreach($arItem as $v){
				$txt .= " <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$v["ID"]}' target='_blank'>{$v["ORDER_NUMBER"]}</a>";
			}
			$arError["DOUBLE_ORDER"][] = $txt;
		}
	}
}
prent($arError);
die;
/*
22181308118000		OZON
1020000286793000	2D
1020000289872000	express
*/
$asd = CMaxyssOzonStockUpdate::getWarehouseItem(5481, "s1");
prent($asd);
$arVHstock = CMaxyssOzonAgent::arStock(array('QUANTITY'=>1), 72769, $arOptions["s1"]);

prent($arVHstock);
die;
$rsPrices = CPrice::GetList(array(), array('PRODUCT_ID' => 71130, 'CATALOG_GROUP_ID' => 5)); 
		$obj = new CPriceUpdate("v1");

			$obj->setAllPrice();
		
prent($ar);
prent($PRICE_ARRAY);
die; 
	$strSql = "SELECT * FROM ci_analysis WHERE site_id = 's1'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arr[] = $row;
	}
	if(count($arr) > 0){
		
		foreach($arr as $key => $arItem){
			$in = array(
				"brand_id" => intval($arItem["brand_id"]),
				"settings" => "'".addslashes($arItem["settings"])."'",
				"site_id" => "'v1'",
				
			);
			prent($in); 
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_analysis", $in, $err_mess.__LINE__);
		}

	}
	
	die;
	
	$arSettings = CMaxyssWb::settings_wb($cabinet);

$supply = "WB-GI-39175465";
$order = "657840052";

$cabinet = "WR";

    $supplies = new CMaxyssWbSupplies($cabinet);
//$res = $supplies->addOrderToSupplie($order, $supply);

prent($res);

$arSupplies = array_reverse($supplies->getSupplies());
prent($arSupplies);


die;




	$result = CRestQueryWB::rest_query_na("https://openapi.wb.ru", $data_string, "/api/v3/supplies/{$order}/orders", $arSettings["AUTHORIZATION"]);
$res = json_decode($result,true);
prent($result);prent($res);
	
die;

CMaxyssOzonAgent::OzonUploadProduct('s1',68455);
die;
$needSyncProduct = (date("H") % 12 == 0 && date("i") >= 30 ? true : false);

prent($needSyncProduct);

die;
$orderH = array(
            "0" => array
                (
                    "id" => "8046699",
                    "createdAt" => "2022-12-23 15:05:07",
                    "source" => "api",
                    "field" => "discount_manual_percent",
                    "apiKey" => array
                        (
                            "current" => "1",
                        ),

                    "oldValue" => "",
                    "newValue" => "0",
                    "order" => array
                        (
                            "id" => "249517",
                            "externalId" => "247981",
                            "site" => "tempusshop-ru",
                            "status" => "collect",
                        ),

                ),


            "6" => array
                (
                    "id" => "8046705",
                    "createdAt" => "2022-12-23 15:05:12",
                    "source" => "api",
                    "field" => "custom_moyskladexternalid",
                    "apiKey" => array
                        (
                            "current" => "",
                        ),

                    "oldValue" => "",
                    "newValue" => "0c94ecf0-82ba-11ed-0a80-027e0017ed01",
                    "order" => array
                        (
                            "id" => "249517",
                            "externalId" => "247981",
                            "site" => "tempusshop-ru",
                            "status" => "collect",
                        ),

                ),

            "7" => array
                (
                    "id" => "8046706",
                    "createdAt" => "2022-12-23 15:05:12",
                    "source" => "api",
                    "field" => "integration_delivery_data.locked",
                    "apiKey" => array
                        (
                            "current" => "1",
                        ),

                    "oldValue" => "",
                    "newValue" => "1",
                    "order" => array
                        (
                            "id" => "249517",
                            "externalId" => "247981",
                            "site" => "tempusshop-ru",
                            "status" => "noorder",
                        ),

                ),

            "8" => array
                (
                    "id" => "8046707",
                    "createdAt" => "2022-12-23 15:05:12",
                    "source" => "api",
                    "field" => "status",
                    "apiKey" => array
                        (
                            "current" => "1",
                        ),

                    "oldValue" => array
                        (
                            "code" => "collect",
                        ),

                    "newValue" => array
                        (
                            "code" => "noorder",
                        ),

                    "order" => array
                        (
                            "id" => "249517",
                            "externalId" => "247981",
                            "site" => "tempusshop-ru",
                            "status" => "noorder",
                        ),

                ),




);
$orders = RetailCrmHistory::assemblyOrder($orderH);
prent($orders);

				die;
				
				
prent($asd);
die;
$logger = new TsLogger("/onliner/getOrders/");
$logger->log("LOG", "Запуск обработчика");

die;
$minPrice = 2538.55;
$minPrice = round($minPrice, -1);
prent($minPrice);

die;
$strSql = "SELECT * FROM ci_catalog_barcode";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	//1) Нужно удалить все ШК, которые начинаются с 2
	if($row["BARCODE"][0] == "2"){
		$arDelete[$row["ARTICLE"]][$row["ID"]] = $row;
	}
	//2) Нужно удалить все ШК с пробелами
	if (preg_match("/\\s/", $row["BARCODE"])) {
		$arDelete[$row["ARTICLE"]][$row["ID"]] = $row;
	}
	//3) Нужно удалить все ШК с кириллицей
	if (preg_match("/[а-яА-Я]/", $row["BARCODE"])) {
		$arDelete[$row["ARTICLE"]][$row["ID"]] = $row;
	}
	//4) Нужно удалить все дубли 
	if($arArticle[$row["ARTICLE"]] && in_array($row["BARCODE"], $arArticle[$row["ARTICLE"]])){
		$arDelete[$row["ARTICLE"]][$row["ID"]] = $row;
	}
	
	// 5) Нужно удалить все ШК, которые начинаются с 8 и состоят из 11 цифр
	if($row["BARCODE"][0] == "8" && strlen($row["BARCODE"]) == 11){
		$arDelete[$row["ARTICLE"]][$row["ID"]] = $row;
	}
	
	$arArticle[$row["ARTICLE"]][] = $row["BARCODE"];
	$arCntArticle[$row["ARTICLE"]] += 1;
}

$arProductIDs = $arDelIDs = $arList = array();
foreach($arDelete as $article => $arItem){
	foreach($arItem as $k => $ar){
		//$DB->Query("DELETE FROM ci_catalog_barcode WHERE ID = '{$ar["ID"]}'");
		$arDelIDs[] = $ar["ID"];
		$arProductIDs[] = $ar["PRODUCT_ID"];
		$arCntArticle[$ar["ARTICLE"]] -= 1;
	}
	
}

$arDelArticle = array_keys($arDelete);

$arProductIDs = array_unique($arProductIDs);

$strSql = "SELECT * FROM ci_catalog_barcode WHERE PRODUCT_ID IN ('".implode("','", $arProductIDs)."') AND ID NOT IN ('".implode("','", $arDelIDs)."')";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	$arList[$row["ARTICLE"]][] = $row["BARCODE"];
}

$filepath = $_SERVER['DOCUMENT_ROOT'] . "/dev/modifyBarcode.txt";
$handle = fopen($filepath, "r");

foreach($arList as $article => $arBarcode){
	//$barcode = explode(",", $arBarcode);
	$str_csv = $article . ";" . implode(",", $arBarcode) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}

foreach($arCntArticle as $article => $cnt){
	if($cnt == 0){
		$str_csv = $article . ";\r\n";
		file_put_contents($filepath , $str_csv, FILE_APPEND);
	}

}

prent($arDelArticle);
/*
$filepath = $_SERVER['DOCUMENT_ROOT'] . "/dev/ci_catalog_barcode.csv";
$handle = fopen($filepath, "r");
	
//prent($arCsv);

$arClient = array();
foreach($arCsv as $key => $arItem){
	$ar = array();
	$ar[0] = '"'.$arItem["ID"].'"';
	$ar[1] = '"'.$arItem["PRODUCT_ID"].'"';
	$ar[2] = '"'.$arItem["ARTICLE"].'"';
	$ar[3] = '"'.$arItem["BARCODE"].'"';
	$ar[4] = '"'.$arItem["TIMESTAMP"].'"';
	$str_csv = implode(";", $ar) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}
fclose($handle); //Закрываем файл
*/

//prent($arArticle);
prent($arDelete);
$arIDs = array_keys($arDelete);

die;
$arSuppList = CMaxyssWb::getSuppliesList("ACTIVE");

prent($arSuppList);


$arSuppList = CMaxyssWb::setOrdersSupplies("WB-GI-17655727", array("517386656"));
prent($arSuppList);
die;
$data_string = array(
	"status" => "ACTIVE",
);
		
$data_string = \Bitrix\Main\Web\Json::encode($data_string);
				
$result = CRestQueryWB::rest_query_na("https://suppliers-api.wildberries.ru", $data_string, "/api/v2/supplies");
$res = \Bitrix\Main\Web\Json::decode($result);
prent($res);
die;
		$obj = new MoyskladAPI("s1");
		$arFilter = array(
			"momentFrom" => date("Y-m-d", strtotime("-1 day")),
			"momentTo" => date("Y-m-d"),
		);
	
		$arResult["ITEMS"] = $obj->getListProfitNew($arFilter);
prent($arResult["ITEMS"]);


die;


$string="This Was Cool to visit you again.Уыв чсм .WQF";

//(\s)|

$description = preg_replace('/(\.)([A-ZА-Я])/','. $2', $string);
prent($description);
$string = join(' ',$temp);


prent($temp);
	die;
CCatalogProduct::setUsedCurrency("BYN");
$product_id = 79310;
$arPrice = AHCatalog::OnGetOptimalPrice($product_id, 1, array(), "N", array(), "s1");

					$arSku['price'] = $arPrice["RESULT_PRICE"]["DISCOUNT_PRICE"];
					$arSku['old_price'] = ($arPrice["RESULT_PRICE"]["BASE_PRICE"] > $arPrice["RESULT_PRICE"]["DISCOUNT_PRICE"] ? $arPrice["RESULT_PRICE"]["BASE_PRICE"] : "");
					$arSku['min_price'] = "";
prent($arSku);
$asd = CCatalogProduct::getUsedCurrency();
prent($asd);


die;
		$strSql = "SELECT * FROM ci_ms_agent";
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$arAgent[md5($row["NAME"])] = $row["MS_ID"];
		}
		
		$strSql = "SELECT ID,AGENT,AGENT_ID FROM ci_ms_history";
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			if(!$row["AGENT_ID"]){
				$md5 = md5($row["AGENT"]);
				if($arAgent[$md5]){
					$agentID = $arAgent[$md5];
					$DB->Update("ci_ms_history", array("AGENT_ID" => "'{$agentID}'"), "WHERE ID = '".$row["ID"]."'", $err_mess.__LINE__);
					//prent($row);
					//$arUpdate[$row["DOCUMENT_ID"]] = $row["CHECK_ID"];
				}
			}
			
		}
		
		
die;
//$res = CMaxyssWb::prepareAllItemsPrice($arSettings, $filter = array(), $cabinet);


			$arFilter = Array(
				"IBLOCK_ID"	=> 16,
				"ID" => 172382,
				"PROPERTY_AVAILABILITY_RU" => 512,
				"!PROPERTY_AEN2" => false,
				"!PROPERTY_CML2_ARTICLE" => false,
			);
			
			$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_AEN2","PROPERTY_WBPRICE","PROPERTY_CML2_ARTICLE", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB"));
			while($ar = $rs->GetNext()){
				prent($ar);
				if($arPrice[$ar["PROPERTY_CML2_ARTICLE_VALUE"]])
					$arItems[$ar["PROPERTY_{$prop_barcode}_VALUE"]] = $ar;
			}
			
			die;
$item_info = CAddinMaxyssWB::PrepareItemNewApiContent(172382, 'WR');
prent($item_info);
die;
$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

CMaxyssWb::uploadAllStocks('WR');
$str = "на следующие номенклатуры указана слишком высокая цена. Рост более 30 процентов: [75448218 75451363 75451416 75451425 75451503 75459526 75460574 75460613 75462809 75462813 75466969 75469314 75469665 75469733 75469774 75470135 75470335 75470788 75470793 75491822 85969707 85969710]";
						if(strripos($str, "Рост более 30 процентов")){
							preg_match("/\[(.*?)\]/ism", $str, $output);
							if($output[1]){
								$list = explode(" ", $output[1]);
								prent($list);
							}
							
						}


die;
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
//	"PROPERTY_WBARTICLE" => false,
	"!PROPERTY_CML2_ARTICLE" => false,
//	"!CODE" => false,
);

$asd = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/dev/1.txt");
$asd2 = unserialize($asd);
file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/dev/2.txt", print_r($asd2, true));
prent("asd");
die;
//$arFilter["ID"] = CMaxyssWb::getItemsWB();
//prent($arFilter);die;

  

$response = $api->getListOrder(0, 1, array("numbers" => array(220119)));

//$response = $api->getListOrder(20, 1);
$res = objectToArray($response);
prent($res);
	die;

	$objMS = new MoyskladAPI("s1");
	$objMS->MSPosition = array();
	$objMS->getListOrder(0, "", true); 
	
	
prent($objMS->MSPosition);
	die;
$obj->getPropOrderFlg = true;

$arItem["DATE_UPDATE"] = "2022-08-05";
	$diff = strtotime($arItem["DATE_UPDATE"]) - strtotime($arOrderCRM[$arItem["ORDER_NUMBER"]]["statusUpdatedAt"]);
	$diff = abs($diff);
	
	
	prent($diff);die;
	
$arResult["ORDER_STATUS_SEND_MS"] = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);

$arFilter = array(
	"STATUS_ID" => $arResult["ORDER_STATUS_SEND_MS"]["SALES_RETURN"], 
	//">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time() - 30 * 86400),
	"LID" => "s1",
);

prent($arFilter);
$order = $obj->getOrder(array(), $arFilter);
$arOrder = array();
foreach($order as $key => $arItem){
	$arOrder[$arItem["ORDER_ID"]] = $arItem["ORDER_ID"];
}

prent($arOrder);
die;
$localFile = "/var/www/bitrix/data/www/tempusshop.ru/upload/partner_yandex/competitor_prices_1357715_18-08-2022.xlsx";
$obj = new CYandexParser($localFile);
$obj->parse();

die;
$orders["123"] = array("id" => "206301111","externalId" => "204775111","site" => "tempusshop-ru111","status" => "noorder111",);
$orders["123"] = array_merge($orders["123"], array("id" => "206301","externalId" => "204775","site" => "tempusshop-ru","status" => "noorder",));
prent($orders);

die;
	$objMS = new MoyskladAPI("s1");
	$objMS->getListOrder(0, "", true);
	
$objService = new OrderService;
$resBX = $objService->getOrderCache(array("ID" => "ASC"), array(), array("nTopCount" => 10));
		
$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$response = $api->getListOrder($cntStep, $i);
prent($objMS->MSPosition);
		die;
$Logger = new TsLogger("/MoyskladAPI/");

$workersChecker = new WorkersChecker("createDocumentsMS");

if (!$workersChecker->CheckStatus()) {
	exit();
}

$workersChecker->UpdateStatus(1);

prent("asdsad");

die;
$strSql = "SELECT * FROM ci_ms_order WHERE SITE_ID = 's1' ORDER BY ID DESC LIMIT 0,10";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				
while ($row = $results->Fetch()){
	$arResult["ORDER_MS"][$row["ORDER_NUMBER"]] = $row;
}
$objMS = new MoyskladAPI("s1");
foreach($arResult["ORDER_MS"] as $key => $arItem){
					
	$resOrder = $objMS->customRequest("https://online.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}");
					
	if($resOrder["demands"] && count($resOrder["demands"]) > 1){
		$arLog[$arItem["ORDER_NUMBER"]] = $arItem["ORDER_NUMBER"];
	}
}
prent($arLog);

foreach($arLog as $log){
	file_put_contents("/home/bitrix/logs/___.txt", $log . "\r\n", FILE_APPEND | LOCK_EX);
}
			die;
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/TsTriggers.php');

use TsHeplers\TsLogger;
if (!class_exists("TsHeplers\TsLogger")) {
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/TsLogger.php');
}
$log = new TsLogger("/price/");
$log->log("ERROR", 'Ошибка авторизации');
$triggers = new TsTriggers();
            $triggers->SetError(["Прайс Test: Ошибка авторизации"]);
            $triggers->SendTriggerErrors();
die;
$arResult["ORDER_STATUS_SEND_MS"] = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);

function insertDocMS($arItem = array()){
	global $DB;
	$in = array(
		"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
		"STATUS" => "'".addslashes($arItem["STATUS"])."'",
		"TYPE" => "'".addslashes($arItem["TYPE"])."'",
	);
	
	$strSql = "SELECT * FROM ci_ms_create_documents WHERE ORDER_NUMBER = '{$arItem["ORDER_NUMBER"]}' AND TYPE = '{$arItem["TYPE"]}'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		
	if (!$row = $results->Fetch()){
		$DB->Insert("ci_ms_create_documents", $in, $err_mess.__LINE__);
	}
	
}

	
function createDocumentMS($arOrder = array(), $type = "DEMAND"){
	if(!$arOrder) return false;
	if(!CModule::IncludeModule("panel.manager")) return;

	global $DB;
	$objMS = new MoyskladAPI("s1");
	$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER IN ('" . implode("','", array_keys($arOrder)) . "') AND SITE_ID = 's1'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				
	while ($row = $results->Fetch()){
		$arResult["ORDER_MS"][$row["ORDER_NUMBER"]] = $row;
	}

	if(count($arResult["ORDER_MS"]) != count($arOrder)){
		foreach($arOrder as $find => $ar){
			if(!$arResult["ORDER_MS"][$find]){
				$arResult["ERROR"][] = "<p style='color:red;'>MS. {$find} - не найден</p>";
				$arResult["LOG"][$find]["TEXT"] .= " {$find} - MS. Заказ не найден для отправки";
				$arResult["LOG"][$find]["STATUS"] = "ERROR";
			}
		}
	}
			
	if(count($arResult["ORDER_MS"]) > 0){
		$arSend = array();
		foreach($arResult["ORDER_MS"] as $key => $arItem){
					
			$resOrder = $objMS->customRequest("https://online.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}");
			if($type == "DEMAND" && $resOrder["demands"] && count($resOrder["demands"]) > 0){
				$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть отгрузка</p>";

				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть отгрузка";
				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";

				insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => $type));				
				
				continue;
			}
			
			if($type == "DEMAND"){
				$data = array(
					"href" => "https://online.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}",
					"metadataHref" => "https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata",
					"type" => "customerorder",
					"mediaType" => "application/json",
				);
							
				$arTemplate = $objMS->getDemandTemplate($data);
							
				if($arTemplate["customerOrder"]){

					$resMS = $objMS->setDemand(array($arTemplate));

					foreach($resMS as $k => $arMS){
						if(!$arMS["id"]){
							if($arMS["errors"]){
								foreach($arMS["errors"] as $k => $v){
									$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$v["error"]}</p>";

									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$v["error"]}";
									$arResult["LOG"][$key]["STATUS"] = "ERROR";
												
								}
							}elseif($arMS["error"]){
								$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$arMS["error"]}</p>";
																
								$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$arMS["error"]}";
								$arResult["LOG"][$key]["STATUS"] = "ERROR";
							}else{
								$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Ошибка не определена";
							}				
						}else{
							$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создана отгрузка";
							
							insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "DEMAND"));				
					
						}
					}
				}else{
					$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. Шаблон создания не удалось получить</p>";
					$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Шаблон создания не удалось получить";
					$arResult["LOG"][$key]["STATUS"] = "ERROR";
				}
			}elseif($type == "SALES_RETURN"){
						
				if($resOrder["demands"]){
					$demand = $resOrder["demands"][0];
					//смотрим есть ли возврат
					$resDemand = $objMS->customRequest($demand["meta"]["href"]);
					if($resDemand["returns"] && count($resDemand["returns"]) > 0){
						$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть возврат</p>";

						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть возврат";
						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
						
						insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "SALES_RETURN"));
						
						continue;
					}else{
											
						$arTemplate = $objMS->getSalesReturnTemplate($demand["meta"]);
											
						if($arTemplate["demand"]){

							$resMS = $objMS->setSalesReturn(array($arTemplate));

							foreach($resMS as $k => $arMS){
								if(!$arMS["id"]){
									if($arMS["errors"]){
										foreach($arMS["errors"] as $k => $v){
											$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$v["error"]}</p>";

											$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$v["error"]}";
											$arResult["LOG"][$key]["STATUS"] = "ERROR";
																			
										}
									}elseif($arMS["error"]){
										$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$arMS["error"]}</p>";
																					
										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$arMS["error"]}";
										$arResult["LOG"][$key]["STATUS"] = "ERROR";
									}else{
										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Ошибка не определена";
									}				
								}else{
									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создан возврат";
									insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "SALES_RETURN"));
								}
							}
							
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. Шаблон создания не удалось получить</p>";
							$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Шаблон создания не удалось получить";
							$arResult["LOG"][$key]["STATUS"] = "ERROR";
						}
											
					}
				}else{
					$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} нет отгрузки</p>";

					$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " MS. {$arItem["ORDER_NUMBER"]} - нет отгрузки для создания возврата";
					$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
				}
			}

		}

	}

	foreach($arResult["LOG"] as $key => $arItem){
		if($arItem["STATUS"] == "OK"){
			$txt = "<p>{$arItem["TEXT"]}</p>";
		}else{
			$txt = "<p style='color:red;'>{$arItem["TEXT"]}</p>";
		}
		file_put_contents("/home/bitrix/logs/utils_set_status_order.txt", $txt . "\r\n", FILE_APPEND | LOCK_EX);
	}
}
//берем заказы для создания отгрузок
$obj = new OrderService;
$obj->getPropOrderFlg = true;
$arFilter = array(
	"STATUS_ID" => $arResult["ORDER_STATUS_SEND_MS"]["DEMAND"], 
	">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time() - 30 * 86400),
	"LID" => "s1",
);

$order = $obj->getOrder(array(), $arFilter);

foreach($order as $key => $arItem){
	$arOrder[$arItem["ORDER_ID"]] = $arItem;
}
unset($order);

//очищаем от того что уже по чему отгрузки создавались
$strSql = "SELECT * FROM ci_ms_create_documents WHERE TYPE = 'DEMAND'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
while ($row = $results->Fetch()){
	if($arOrder[$row["ORDER_NUMBER"]]){
		unset($arOrder[$row["ORDER_NUMBER"]]);
	}
}
//если что то осталось, то работаем дальше
if(count($arOrder) > 0){
	//prent($arOrder);
	createDocumentMS($arOrder, "DEMAND");
}

/******************* возвраты **************************/
$arFilter = array(
	"STATUS_ID" => $arResult["ORDER_STATUS_SEND_MS"]["SALES_RETURN"], 
	">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time() - 30 * 86400),
	"LID" => "s1",
);

$order = $obj->getOrder(array(), $arFilter);

foreach($order as $key => $arItem){
	$arOrder[$arItem["ORDER_ID"]] = $arItem;
}
unset($order);
//prent($arOrder);die;
//очищаем от того что уже по чему отгрузки создавались
$strSql = "SELECT * FROM ci_ms_create_documents WHERE TYPE = 'SALES_RETURN'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
while ($row = $results->Fetch()){
	if($arOrder[$row["ORDER_NUMBER"]]){
		unset($arOrder[$row["ORDER_NUMBER"]]);
	}
}
//если что то осталось, то работаем дальше
if(count($arOrder) > 0){
	//prent($arOrder);
	createDocumentMS($arOrder, "SALES_RETURN");
}
die;

foreach($arOrder as $key => $arItem){
	$in = array(
		"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_ID"])."'",
		"STATUS" => "'".addslashes($arItem["STATUS_ID"])."'",
		"TYPE" => "'DEMAND'",
	);

	$DB->Insert("ci_ms_create_documents", $in, $err_mess.__LINE__);
}

$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$objService = new OrderService;

$cntStep = 100;
// выбираем последнюю тысячу из битрикса и шлем в црм
$resBX = $objService->getOrderCache(array("ID" => "DESC"), array(), array("nTopCount" => 1000));
$bxOrder = $arOrderCRM = array();
foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
	);
}

$arIDs = array_keys($bxOrder);
$cnt = ceil(count($arIDs) / 100);
for($i = 0; $i < $cnt; $i++){
	$ids = array_slice($arIDs, $i * 100, 100);
	//prent($ids,0,1);
	$response = $api->getListOrder($cntStep, 1, array("numbers" => $ids));
	$res = objectToArray($response);
	prent($ids);prent($res);
	foreach($res["response"]["orders"] as $key => $arItem){
		$arOrderCRM[$arItem["number"]] = array(
			"number" => $arItem["number"],
			"status" => $arItem["status"],
			"phone" => $arItem["phone"],
		);
	}
}
die;
foreach($bxOrder as $key => $arItem){
	if($arOrderCRM[$arItem["ORDER_NUMBER"]]){
		$order = $arOrderCRM[$arItem["ORDER_NUMBER"]];
		if(preg_replace('~[^0-9]~', '', $arItem["PHONE"]) != preg_replace('~[^0-9]~', '', $order["phone"]))
			$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " телефоны не совпадают. BX - {$arItem["PHONE"]}, CRM - {$order["phone"]}";
		if($arStatusCRM[$order["status"]]["BITRIX"] != $arItem["STATUS_ID"])
			$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " статус не совпадают. CRM - {$arItem["STATUS_ID"]}, CRM - {$order["status"]}";
	}else{
		$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " не найден в crm";
	}
}

$statusResult = \Bitrix\Sale\Internals\StatusLangTable::getList(array(
    'select' => array('STATUS_ID','NAME'),
));

while($status = $statusResult->fetch()){
	$bxStatus[$status["STATUS_ID"]] = $status["NAME"];
}
$statusCrm = \Bitrix\Main\Config\Option::get("intaro.retailcrm", "pay_statuses_arr");
$statusCrm = unserialize($statusCrm);
foreach($statusCrm as $bx_status => $crm_status){
	$arStatusCRM[$crm_status] = array(
		"BITRIX" => $bx_status,
		"NAME" => $bxStatus[$bx_status],
	);
}
prent($arStatusCRM);
prent($bxStatus);

die;
$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$objService = new OrderService;
//запрашиваем заказы в црм
$arOrder = array();
for($i = 1; $i < 5; $i++){
	$response = $api->getListOrder(20, $i);
	$res = objectToArray($response);
		
	foreach($res["response"]["orders"] as $key => $arItem){
		$arOrder[$arItem["number"]] = array(
			"number" => $arItem["number"],
			"status" => $arItem["status"],
			"phone" => $arItem["phone"],
		);
		
		$arOrderID[$arItem["number"]] = $arItem["number"];
	}
}


$resBX = $objService->getOrderCache(array("ID" => "DESC"), array("ACCOUNT_NUMBER" => array_keys($arOrderID)), array("nTopCount" => 20));
//prent($resBX);
foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
	);
	
	//$arOrderID[$arItem["ORDER_ID"]] = $arItem["ORDER_ID"];
}


//ищем все заказы в МС
if(count($arOrderID) > 0){
	//s1
	$objMS = new MoyskladAPI("s1");
	$objMS->getListOrder(0, "", true);
	
	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s1"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"STATE_META" => $arItem["state"]["meta"]["href"]
			);
		}
	}
	//s2
	/*$objMS = new MoyskladAPI("s2");
	$objMS->getListOrder(0, "", true);
	
	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s2"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"STATE_META" => $arItem["state"]["meta"]["href"]
			);
		}
	}
	//s3
	$objMS = new MoyskladAPI("s3");
	$objMS->getListOrder(0, "", true);
	
	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s3"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"STATE_META" => $arItem["state"]["meta"]["href"]
			);
		}
	}*/
	//prent($msOrderAll);die;
	/*$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER IN ('" . implode("','", $arOrderID) . "')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	while ($row = $results->Fetch()){
		$msOrderAll[$row["SITE_ID"]][$row["ORDER_NUMBER"]] = array(
			"ORDER_NUMBER" => $row["ORDER_NUMBER"],
			"STATE_META" => json_decode($row["DATA"], true)["state"]["meta"]["href"]
		);
	}*/
	
	foreach($msOrderAll as $site_id => $arItem){
		$objMS = new MoyskladAPI($site_id);
		foreach($arItem as $key => $ar){
			$status = "unknown";
			if($ar["STATE_META"] && $rs = $objMS->customRequest($ar["STATE_META"])){
				$status = $rs["name"];
			}
			$msOrder[$ar["ORDER_NUMBER"]] = array(
				"ORDER_NUMBER" => $ar["ORDER_NUMBER"],
				"STATUS" => $status,
			);
		}
	}
}

$arStatusCRM = array(
	"new" => array("BITRIX" => "N", "MS" => ""),
	"take-away" => array("BITRIX" => "TA", "MS" => ""),
	"ready-to-delivery" => array("BITRIX" => "CO", "MS" => "Готов к доставке"),
	"sending" => array("BITRIX" => "SE", "MS" => "Готов к отправке"),
	"collect" => array("BITRIX" => "CL", "MS" => "Сборка"),
	"prepayed" => array("BITRIX" => "SB", "MS" => "Ожидаем оплату"),
	"delivering" => array("BITRIX" => "CR", "MS" => ""),
	"undelivered" => array("BITRIX" => "NK", "MS" => ""),
	"delivededbycourier" => array("BITRIX" => "DK", "MS" => ""),
	"send-to-delivery" => array("BITRIX" => "DS", "MS" => ""),
	"pvz" => array("BITRIX" => "P", "MS" => ""),
	"redirect" => array("BITRIX" => "PW", "MS" => ""),
	"returns" => array("BITRIX" => "R", "MS" => ""),
	"returninpvz" => array("BITRIX" => "rp", "MS" => ""),
	"complete" => array("BITRIX" => "F", "MS" => "Выполнен"),
	"no-product" => array("BITRIX" => "WT", "MS" => ""),
	"already-buyed" => array("BITRIX" => "NZ", "MS" => ""),
	"return-after-delivery" => array("BITRIX" => "RD", "MS" => ""),
	"no-call" => array("BITRIX" => "NA", "MS" => ""),
	"double" => array("BITRIX" => "DB", "MS" => ""),
);

foreach($arOrder as $key => $arItem){
	if($bxOrder[$arItem["number"]]){
		$order = $bxOrder[$arItem["number"]];
		if($arItem["phone"] != $order["PHONE"])
			$arError["BX"][] = $arItem["number"] . " телефоны не совпадают. CRM - {$arItem["phone"]}, BX - {$order["PHONE"]}";
		if($arStatusCRM[$arItem["status"]]["BITRIX"] != $order["STATUS_ID"])
			$arError["BX"][] = $arItem["number"] . " статус не совпадают. CRM - {$arItem["status"]}, BX - {$order["STATUS_ID"]}";
	}else{
		$arError["BX"][] = $arItem["number"] . " не найден в битриксе";
	}
	
	// ms
	if($msOrder[$arItem["number"]]){
		$order = $msOrder[$arItem["number"]];
		if($arStatusCRM[$arItem["status"]]["MS"] != $order["STATUS"])
			$arError["MS"][] = $arItem["number"] . " статус не совпадают. CRM - {$arItem["status"]}, MS - {$order["STATUS"]}";
	}else{
		$arError["MS"][] = $arItem["number"] . " не найден в MS";
	}
}
if(count($arError["BX"]) > 0){
	foreach($bxOrder as $key => $arItem){
		if(!$arOrder[$arItem["ORDER_NUMBER"]]){
			$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " не найден в crm";
		}
	}	
}
prent($arError);die;
$txt = "";
if(count($arError) > 0){
	foreach($arError as $cabinet => $error){
		$txt .= "<hr><p>Ошибки {$cabinet}</p><hr>";
		foreach($error as $k => $er){
			$txt .= "<p>{$er}</p>";
		}
	}
				// op
	$arEventFields = array(
		"EMAIL_TO"	=> "sales@tempus.by",//"pravlutski@gmail.com",//
		"MESSAGE"	=> $txt,
		"SUBJECT"	=> "Ошибки в заказах",
	);

	CEvent::Send("IM_NEW_MESSAGE", "s1", $arEventFields, "Y");
}
prent($arError);
prent($txt);
//prent($bxOrder);
	
//prent($arOrder);
//prent($res);
die;
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	"PROPERTY_HIT" => array(164),//Распродажа
);
$cntAll = 0;
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","PROPERTY_CML2_ARTICLE"));
while($ar_fields = $rs->GetNext()){
	prent($ar_fields);
}

die;
$res = $DB->Query("SELECT IBLOCK_ELEMENT_ID,VALUE FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2795' && DESCRIPTION = 'WR'");
while ($ar = $res->Fetch()){
	if(strlen($ar["VALUE"]) > 30)
		$bxItems[$ar["IBLOCK_ELEMENT_ID"]] = $ar["VALUE"];
}
prent($bxItems);
die;

$obj = new CPanelPricelist;
$price = $obj->getPriceByFilter(array("website" => "Y"), "bitrix_id", array("model","bitrix_id",));
prent($price);
die;
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
		
foreach($bxID as $article => $ID){
	$DB->Update("ci_price", array("bitrix_id" => "'{$ID}'"), "WHERE model = '".$article."'", $err_mess.__LINE__);
}
die;
CExchange::updateProduct(1008, 16);


die;
CCatalogProduct::Update(166592, Array("WEIGHT" => 1, "LENGTH" => 2, "WIDTH" => 3, "HEIGHT" => 4));


die;
RCrmActions::orderAgent();
die;
echo BX_TEMPORARY_FILES_DIRECTORY;
foreach(GetModuleEvents("sale", "OnSaleStatusOrderChange", true) as $arEvent){
	 prent($arEvent);
}

foreach(GetModuleEvents("sale", "OnSaleStatusOrder", true) as $arEvent){
//	 prent($arEvent);
}			
		
die;
$arProp = array(
			2793 => "PROP_MAXYSS_CARDID_WB",
			2794 => "PROP_MAXYSS_NMID_WB",
			2795 => "PROP_MAXYSS_CHRTID_WB",
			2796 => "PROP_MAXYSS_NMID_CREATED_WB",
			2797 => "PROP_MAXYSS_CHRTID_CREATED_WB",
			2814 => "PROP_MAXYSS_DISCOUNTS_WB",
			2815 => "PROP_MAXYSS_PROMOCODES_WB",
);
$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID IN ('" . (implode("','", array_keys($arProp))) . "') AND DESCRIPTION = 'WR'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]][$arProp[$ar["IBLOCK_PROPERTY_ID"]]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}
prent($VALUES[1062]);
die;
$VALUES = array();


$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2793'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]]["PROP_MAXYSS_CARDID_WB"][$ar["VALUE"]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}

$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2794'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]]["PROP_MAXYSS_NMID_WB"][$ar["VALUE"]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}
$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2795'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]]["PROP_MAXYSS_CHRTID_WB"][$ar["VALUE"]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}

$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2796'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]]["PROP_MAXYSS_NMID_CREATED_WB"][$ar["VALUE"]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}


$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2797'");
while ($ar = $res->Fetch()){
	if($ar["VALUE"]){
		$VALUES[$ar["IBLOCK_ELEMENT_ID"]]["PROP_MAXYSS_CHRTID_CREATED_WB"][$ar["VALUE"]] = array(
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}

foreach($VALUES as $ID => &$arItem){
	if($arItem["PROP_MAXYSS_CARDID_WB"]){
		$arItem["PROP_MAXYSS_CARDID_WB"] = array_values($arItem["PROP_MAXYSS_CARDID_WB"]);
	}
	if($arItem["PROP_MAXYSS_NMID_WB"]){
		$arItem["PROP_MAXYSS_NMID_WB"] = array_values($arItem["PROP_MAXYSS_NMID_WB"]);
	}
	if($arItem["PROP_MAXYSS_CHRTID_WB"]){
		$arItem["PROP_MAXYSS_CHRTID_WB"] = array_values($arItem["PROP_MAXYSS_CHRTID_WB"]);
	}
	if($arItem["PROP_MAXYSS_NMID_CREATED_WB"]){
		$arItem["PROP_MAXYSS_NMID_CREATED_WB"] = array_values($arItem["PROP_MAXYSS_NMID_CREATED_WB"]);
	}
	if($arItem["PROP_MAXYSS_CHRTID_CREATED_WB"]){
		$arItem["PROP_MAXYSS_CHRTID_CREATED_WB"] = array_values($arItem["PROP_MAXYSS_CHRTID_CREATED_WB"]);
	}
}
unset($arItem);


foreach($VALUES as $ID => $arItem){
	if($ID != 1062) continue;
	prent($arItem);
	prent($ID);
	CIBlockElement::SetPropertyValuesEx($ID, false, $arItem);
	die;
}
prent($VALUES[1062]);

$localFile = "/var/www/bitrix/data/www/tempusshop.ru/upload/partner_yandex/competitor_prices_940142_17-05-2022.xlsx";
//		$obj = new CYandexParser($localFile);
//		$obj->parse();
		
die;
function getUrlCurl($urlrest){

	
		$process = curl_init($urlrest);
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
			)
		);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);

	return $result;
}

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"PROPERTY_AVAILABILITY_RU" => 512
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_WBARTICLE"));
while($ar = $rs->GetNext()){
	$bxItems[$ar["ID"]] = $ar["ID"];
}

$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2796'");
while ($ar = $res->Fetch()){
	if($bxItems[$ar["IBLOCK_ELEMENT_ID"]] && $ar["VALUE"]){
		$nmID[$ar["IBLOCK_ELEMENT_ID"]][] = array(
			"IBLOCK_ELEMENT_ID" => $ar["IBLOCK_ELEMENT_ID"],
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}

foreach($nmID as $id => $arData){
	$arProp = array();
	foreach($arData as $key => $arItem){
		$rs = getUrlCurl("https://wbx-content-v2.wbstatic.net/ru/{$arItem["VALUE"]}.json");
		$rs = json_decode($rs, true);
		if($rs["imt_id"]){
			$arProp[] = array("VALUE" => $rs["imt_id"], "DESCRIPTION" => $arItem["DESCRIPTION"]);
		}
		
		//
	}
	if($arProp){
		CIBlockElement::SetPropertyValuesEx($id, false, array("PROP_MAXYSS_CARDID_WB" => $arProp));
	}
}
prent($nmID);die;
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"PROPERTY_AVAILABILITY_RU" => 512
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_WBARTICLE"));
while($ar = $rs->GetNext()){
	$article = $ar["PROPERTY_CML2_ARTICLE_VALUE"];
	$bxItems[$ar["PROPERTY_WBARTICLE_VALUE"]] = $ar["ID"];
}

prent($bxItems);

$arProp = array();
$arProp[] = array("VALUE" => 47060879, "DESCRIPTION" => "DEFAULT");
$arProp[] = array("VALUE" => 75586633, "DESCRIPTION" => "WR");
CIBlockElement::SetPropertyValuesEx(160989, false, array("PROP_MAXYSS_NMID_CREATED_WB" => $arProp));
die;


		$urlrest = "https://seller.wildberries.ru/ns/card/suppliers-portal-card/card/tableList";
//prent($urlrest);
		$ch = curl_init($urlrest);
//		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, 1);  
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//			"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36",
//			"Accept: */*",
//			"Accept-Language: en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7",
//			"Connection: keep-alive",
                    'Content-Type: application/json',
 //                   'Content-Length: ' . strlen($data_string),
					'X-Fingerprint-Id: c344718b21f0247a0860d077312fcd6a',
 //                   'Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6ImUzNmRjMzQ3LTIyYzAtNGIxZS05ZThjLWIwYWY3YWM3ZGMwNyJ9.Z6OODK2x0Xghs2ObN4lJgJHfDYMotBanv9SZ9vb-URI',
                    'X-Supplier-ID: e43c8829-c9ad-4cc7-beed-547f4ffe232b'

                ));
		$res = curl_exec($ch);
		$info = curl_getinfo($ch);
		prent($res);
		prent($info);
die;
$arSettings = CMaxyssWb::settings_wb("WR");
prent($arSettings);
die;


$host = '91.215.152.251';
$usr = 'root';
$pwd = 'hDfQ579M';
	 
$sftp = new SFTPConnection($host, 22);
$sftp->login($usr, $pwd);
$filelist = $sftp->scanFilesystem("/home/partner_yandex/");
krsort($filelist);
$filelist = array_values($filelist);
$curFile = $filelist[0];

$lastFile = CProSet::getOption("YANDEX_LAST_FILE");
if($curFile != $lastFile){
	//копируем файл себе
	$localFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/partner_yandex/{$curFile}";
	$sftp->receiveFile("/home/partner_yandex/{$curFile}", $localFile);
	
	if(file_exists($localFile)){
		$obj = new CYandexParser($localFile);
		$obj->parse();
	}else{
		$arLog = array(
			"event" => "E",
			"text" => "Yandex. файл не удалось скопировать",
			"detail" => array("file" => $localFile),
		);
		CLog::add2log($arLog);
	}
}

	prent($lastFile);
die;
if (!class_exists('MoyskladAPI')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_moysklad.php');
}

$ex = new CExchange("s1");
$pr = $ex->updateProduct(1008, 16);
prent($pr);die;
$el = new CIBlockElement; 
					$arLoadProductArray = Array(
						"SORT_RU"	=> 100,
					);
					$rs = $el->Update(128539, $arLoadProductArray);

\Bitrix\Main\Loader::includeModule('iblock');
$start = debug_microtime_float();
\Bitrix\Main\Application::getConnection()->startTracker(true);
$elements = \Bitrix\Iblock\ElementTable::getList([
	'filter' => [
		"IBLOCK_ID" => 16,
		'ROOT.ID' => 2289, // Раздел, в котором ищем включая подразделы.
		'==LINK.ADDITIONAL_PROPERTY_ID' => NULL, // Основная привязка элементов к группам.
		'ACTIVE' => 'Y' // Активные элементы.
	],
	'select' => [
		'CNT'
	],
	//'group' => [
	//	'ID'
	//],
	//'order' => [
	//	'SORT' => 'ASC'
	//],
	'runtime' => [
		'LINK' => [
			'data_type' => \Bitrix\Iblock\SectionElementTable::class,
			'reference' => [
				'=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
			],
			'join_type' => 'inner',
		],
                'PARENT' => [
                    'data_type' => \Bitrix\Iblock\SectionTable::class,
                    'reference' => [
                        '=this.LINK.IBLOCK_SECTION_ID' => 'ref.ID',
                    ],
          'join_type' => 'inner',
                ],
                'ROOT' => [
                    'data_type' => \Bitrix\Iblock\SectionTable::class,
                    'reference' => [
                        '=this.PARENT.IBLOCK_ID' => 'ref.IBLOCK_ID',
                        '<=ref.LEFT_MARGIN' => 'this.PARENT.LEFT_MARGIN',
                        '>=ref.RIGHT_MARGIN' => 'this.PARENT.RIGHT_MARGIN',
                    ], 
                    'join_type' => 'inner',
                ],
		new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
	]
        ]);

\Bitrix\Main\Application::getConnection()->stopTracker();

$arIDs = array();
while($ar = $elements->fetch()){
	//prent($ar);
	$arIDs[] = $ar["ID"];
}

//prent(count($arIDs));
prent(debug_microtime_float() - $start);
//print_r(\Bitrix\Main\Application::getConnection()->getTracker());



function getCountElement($arFilter){
	$res = \Bitrix\Iblock\Elements\ElementCatalogTable::getList([
		'select' => ['CNT'],
		'filter' => $arFilter,
		'runtime' => [
			new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)'),
			'LINK' => [
				'data_type' => \Bitrix\Iblock\SectionElementTable::class,
				'reference' => [
					'=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
				],
				'join_type' => 'inner',
			],
			'PARENT' => [
				'data_type' => \Bitrix\Iblock\SectionTable::class,
				'reference' => [
					'=this.LINK.IBLOCK_SECTION_ID' => 'ref.ID',
				],
				'join_type' => 'inner',
			],
			'ROOT' => [
				'data_type' => \Bitrix\Iblock\SectionTable::class,
				'reference' => [
					'=this.PARENT.IBLOCK_ID' => 'ref.IBLOCK_ID',
					'<=ref.LEFT_MARGIN' => 'this.PARENT.LEFT_MARGIN',
					'>=ref.RIGHT_MARGIN' => 'this.PARENT.RIGHT_MARGIN',
				], 
				'join_type' => 'inner',
			],
		],
	])->fetchAll();
	return $res[0]["CNT"];
}
$arFilter = array(
	"IBLOCK_ID" => 16,
	"ACTIVE" => "Y",
	'ROOT.ID' => 932,
);
$asd = getCountElement($arFilter);
prent($asd);
$start = debug_microtime_float();
//, 'NAME', 'PREVIEW_TEXT', 'DETAIL_PICTURE', 'TYPE', 'HIT'
$products = \Bitrix\Iblock\Elements\ElementCatalogTable::getList([
    'select' => ['ID'],
    'filter' => $arFilter,
	'group' => [
		'ID'
	],
	'order' => [
		'SORT_RU' => 'DESC'
	],
	'runtime' => [
                'LINK' => [
                    'data_type' => \Bitrix\Iblock\SectionElementTable::class,
                    'reference' => [
                        '=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                    ],
          'join_type' => 'inner',
                ],
                'PARENT' => [
                    'data_type' => \Bitrix\Iblock\SectionTable::class,
                    'reference' => [
                        '=this.LINK.IBLOCK_SECTION_ID' => 'ref.ID',
                    ],
          'join_type' => 'inner',
                ],
                'ROOT' => [
                    'data_type' => \Bitrix\Iblock\SectionTable::class,
                    'reference' => [
                        '=this.PARENT.IBLOCK_ID' => 'ref.IBLOCK_ID',
                        '<=ref.LEFT_MARGIN' => 'this.PARENT.LEFT_MARGIN',
                        '>=ref.RIGHT_MARGIN' => 'this.PARENT.RIGHT_MARGIN',
                    ], 
                    'join_type' => 'inner',
                ],
            ]
])->fetchAll();
prent(count($products));
foreach ($products as $product) {
//	echo $product->getName();
}
prent(debug_microtime_float() - $start);
die;
$obj = new MoyskladAPI("s1");
$asd = $obj->getListProfit(0, true);
prent($asd);
die;
//CIBlockElement::SetPropertyValuesEx(165818, false, array("FAVORIT_ITEM" => false));
$rs = CIBlockElement::GetList(array(), array("PROPERTY_FAVORIT_ITEM" => 1125), false, false, array("ID", "NAME"));

while($ar = $rs->GetNext()){
	prent($ar);
}

die;

$product_id = 40091;

$asd = CMaxyssOzonAgent::get_priceOP($product_id, "N", "", "", "");
prent($asd);

die;

CMaxyssOzonAgent::OzonUploadProduct("s1", 1);



$lid = "s1";
$arS1 = AHCatalog::OnGetOptimalPrice($product_id, 1, array(), "N", array(), "s1");

//
$selectedPriceType = 3;
                    $priceIterator = Bitrix\Catalog\GroupAccessTable::getList([
                        'select' => ['CATALOG_GROUP_ID'],
                        'filter' => ['=CATALOG_GROUP_ID' => 1]
                    ]);
                    $priceType = $priceIterator->fetch();
					prent($priceType);
                $priceFilter = [
                    '@PRODUCT_ID' => $product_id,
                    [
                        'LOGIC' => 'OR',
                        '<=QUANTITY_FROM' => 1,
                        '=QUANTITY_FROM' => null
                    ],
                    [
                        'LOGIC' => 'OR',
                        '>=QUANTITY_TO' => 1,
                        '=QUANTITY_TO' => null
                    ]
                ];
                if ($selectedPriceType > 0)
                    $priceFilter['=CATALOG_GROUP_ID'] = $selectedPriceType;

                $iterator = Bitrix\Catalog\PriceTable::getList([
                    'select' => ['ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY'],
                    'filter' => $priceFilter
                ]);
                $offerLinks = array();
                while ($price = $iterator->fetch()) {
                    $id = (int)$price['PRODUCT_ID'];
                    $priceTypeId = (int)$price['CATALOG_GROUP_ID'];
                    $offerLinks[$id]['PRICES'][$priceTypeId] = $price;
                    unset($priceTypeId, $id);
                }
				
                foreach ($offerLinks as $key => $row) {
                    $arPrice = CCatalogProduct::GetOptimalPrice(
                        $key,
                        1,
                        array(2),
                        'N',
                        $row['PRICES'],
                        $lid,
                        array()
                    );
					prent($arPrice);	
                }
					
				
die;
$arAgent = array(
	"ID" => 1,
	"MODULE_ID" => "main",
	"NAME" => "CEvent::CleanUpAgent();",
);

$arEventFields = array(
	"EMAIL_TO"	=> "pravlutski@gmail.com",
	"MESSAGE"	=> "Слетел крон. <a href='https://tempusshop.ru/bitrix/admin/agent_edit.php?ID={$arAgent["ID"]}&lang=ru'>Модуль - " . $arAgent["MODULE_ID"] . " NAME - " . $arAgent["NAME"] . "</a>",
	"SUBJECT"	=> "Ошибка. Слетел крон " . $arAgent["NAME"],
);

CEvent::Send("IM_NEW_MESSAGE", "s1", $arEventFields, "Y");

die;
$rs = CIBlockElement::GetList(array(), array("PROPERTY_CML2_ARTICLE" => "FS5297"), false, false, array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN"));

while($ar = $rs->GetNext()){
	prent($ar);
}

CMaxyssOzonAgent::OzonUploadProduct("s1", 1);
die;


	
$arFilter = array (
  '=PROPERTY_126' => 
  array (
    0 => '39',
  ),
  '=PROPERTY_87' => 
  array (
    0 => '7971',
  ),
  '=PROPERTY_144' => 
  array (
    0 => '109',
  ),
  '=PROPERTY_134' => 
  array (
    0 => '88',
  ),
  '=PROPERTY_280' => 
  array (
    0 => '1793',
  ),
  '=PROPERTY_2748' => 
  array (
    0 => '1873',
  ),
  '=PROPERTY_130' => 
  array (
    0 => '73',
  ),
  '=PROPERTY_131' => 
  array (
    0 => '78',
  ),
  'IBLOCK_ID' => 16,
  'ACTIVE' => 'Y',
  'INCLUDE_SUBSECTIONS' => 'Y',
  'SECTION_ID' => '226',
);

					$filter = array("CNT_ACTIVE"=>"Y");
					foreach($arFilter as $code => $v){
						if(strripos($code, "PROPERTY") !== false){
							$prop = explode("_", $code)[1];
							$tmp = explode("PROPERTY", $code);
							//prent($tmp);
							if(count($tmp) == 2)
								$sym = $tmp[0];
							if($prop > 0){
								$filter["PROPERTY"][$sym.$prop] = $v;
							}
							
						//	$filter[$code] = $v;
						}
					}
					//prent($filter);
					//$filter["PROPERTY"][87] = array(37280);
					//prent($filter);
					$cnt = CIBlockSection::GetSectionElementsCount($arFilter["SECTION_ID"], $filter);
					prent($cnt);

$arNavStartParams = array (
  'nPageSize' => 24,
  'bDescPageNumbering' => false,
  'bShowAll' => false,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, $arNavStartParams, array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN"));

while($ar = $rs->GetNext()){
	prent($ar);
}


die;
	$ar = array();
	$objBrand = new CPanelBrand;
	//список брендов. чтобы по удалять из названия бренды и оставить только артикулы
	$arBrand = $objBrand->getList();
	foreach($arBrand as $brand)
		$ar[] = $brand["name"];
	$ar[] = "Emporio";
	prent($ar);
	
$name = trim(str_ireplace($ar, "", "Casio GA-2200GC-7A"));


prent($name);
	die;
$arSettings = array(
	"PRICE_TYPE" => 1,
	"PRICE_PROP" => "N",
	"PRICE_TYPE_PROP" => "",
	"PRICE_TYPE_NO_DISCOUNT" => "N",
	"PRICE_TYPE_FORMULA" => "",
	"PRICE_TYPE_FORMULA_ACTION" => "NOT",
);

$arFieldsOff['ID'] = 122177;
$lid = "s1";
$price = CMaxyssOzonAgent::get_price($arSettings['PRICE_TYPE'], $arSettings['PRICE_PROP'], $arSettings['PRICE_TYPE_PROP'], $arSettings['PRICE_TYPE_NO_DISCOUNT'], $arFieldsOff['ID'], $lid, $arSettings["PRICE_TYPE_FORMULA"], $arSettings["PRICE_TYPE_FORMULA_ACTION"]);
                                
prent($price);
die;
								
$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE 
	FROM 
		b_iblock_element el 
	LEFT JOIN 
		b_iblock_element_prop_s16 pr 
	ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE 
		el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
	
$arArticle = array();	
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if(strlen($row["ARTICLE"]) > 0)
		$arArticle[$row["ID"]] = $row["ARTICLE"];
}


$objUtils = new CPanelUtils;
		$arr = array();
		$strSql = "SELECT * FROM ci_barcode_update";// WHERE ACTIVE = 'Y'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(strripos($row["BARCODE"], "2008") === false){
				$arr[] = $row;
			}
			
		}
foreach($arr as $key => $arItem){
	if($arArticle[$arItem["PRODUCT_ID"]]){
		
		$article = $arArticle[$arItem["PRODUCT_ID"]];
		$barcode = $arItem["BARCODE_UPDATE"];
		//prent($article);
		//prent($barcode);
		$objUtils->addAltBarcode($article, $barcode);
	}
}
die;
$rs = CIBlockElement::GetList(array(), array("IBLOCK_ID" => 16), false, false, array("ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN"));

$arBX = array();
while($ar = $rs->GetNext()){
	if(strlen($ar["PROPERTY_CML2_ARTICLE_VALUE"]) > 0 && strlen($ar["PROPERTY_AEN_VALUE"]) > 0){
		$arBX[] = array(
			"ARTICLE" => $ar["PROPERTY_CML2_ARTICLE_VALUE"],
			"BARCODE" => $ar["PROPERTY_AEN_VALUE"],
		);
	}
}

foreach($arBX as $key => $arItem){
	//$objUtils->addAltBarcode($arItem["ARTICLE"], $arItem["BARCODE"]);
}
//prent($arBX);
die;
		$obj = new CPriceUpdate("wb");
		$obj->setAllPrice();

die;
	$coupon = Bitrix\Sale\Internals\DiscountCouponTable::generateCoupon(true);
	$fields['COUNT'] = 1;                           //кол-во необходимых нам купоном

	$fields['COUPON'] = array(                        // массив $data
		'DISCOUNT_ID' => 30,                        // id правила корзины
		'ACTIVE_FROM' => null,                        // выставляем без ограничения к началу даты активности купона
		'ACTIVE_TO' => null,                        // выставляем без ограничения к окончанию даты активности купона                  
		'TYPE' => \Bitrix\Sale\Internals\DiscountCouponTable::TYPE_ONE_ORDER, // выставляем тип купона TYPE_ONE_ORDER - использовать на один заказ, TYPE_MULTI_ORDER - использовать на несколько заказов 
		'MAX_USE' => 1,                           // выставляем максимальное кол-во применений купона
		'DESCRIPTION' => "sdfdsfdf",
		'COUPON' => $coupon,
	);
	//$couponsResult = \Bitrix\Sale\Internals\DiscountCouponTable::addPacket(
	//	$fields['COUPON'],
	//	$fields['COUNT']
	//);
	
	$fields = array(                        // массив $data
		'DISCOUNT_ID' => 30,                        // id правила корзины
		'ACTIVE_FROM' => null,                        // выставляем без ограничения к началу даты активности купона
		'ACTIVE_TO' => null,                        // выставляем без ограничения к окончанию даты активности купона                  
		'TYPE' => \Bitrix\Sale\Internals\DiscountCouponTable::TYPE_ONE_ORDER, // выставляем тип купона TYPE_ONE_ORDER - использовать на один заказ, TYPE_MULTI_ORDER - использовать на несколько заказов 
		'MAX_USE' => 1,                           // выставляем максимальное кол-во применений купона
		'DESCRIPTION' => "sdfdsfdf",
		'COUPON' => $coupon,
	);
   prent($fields);
   prent($coupon);
		$result = \Bitrix\Sale\Internals\DiscountCouponTable::add($fields);

		if (!$result->isSuccess())
		{
			$errors = $result->getErrorMessages();prent($errors);
		}
		else
		{
			$couponID = $result->getId();
		}
prent($couponID);
   /*
		$checkResult = \Bitrix\Sale\Internals\DiscountCouponTable::checkPacket($fields['COUPON'], false);
		if (!$checkResult->isSuccess(true)){
			
			$errors = $checkResult->getErrorMessages();
			
		}else{
			
			$couponsResult = \Bitrix\Sale\Internals\DiscountCouponTable::addPacket(
				$fields['COUPON'],
				$fields['COUNT']
			);
			if (!$couponsResult->isSuccess())
				$errors = $couponsResult->getErrorMessages();
			unset($couponsResult);
		}
		*/
	//if (!$couponsResult->isSuccess()){
	//	$errors = $couponsResult->getErrorMessages();
	//}else{
	//	prent($couponsResult);
	//}


/*
$order = \Bitrix\Sale\Order::load($ID);

$order_props = $order->getPropertyCollection();

$asd = $order_props->getPayerName()->getValue();

$user_id = $order->getUserId();
prent($user_id);
//если пользователь WB
if($user_id == 124407){
	$externalID = $order->getField('ACCOUNT_NUMBER');
	$tradeBindingCollection = $order->getTradeBindingCollection();


	foreach ($tradeBindingCollection as $item) {
		$tpId = $item->getField('TRADING_PLATFORM_ID');
	}
	if(!$tpId){
		$res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
			"ORDER_ID" => $ID,
			"TRADING_PLATFORM_ID" => 6,
			"EXTERNAL_ORDER_ID" => $externalID
		));
	}
}
*/




die;
   

	prent($res);	
			
			die;
$ssl = 'ssl://';
$host = "gelo.by";
$port = 443;
$res = fsockopen($ssl.$host, $port, $errno, $errstr, 5);
prent($res);
prent($errno);prent($host);


die;
	function isPageSpeedTest2(){
		return (strpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot') !== false && $_SERVER['HTTP_FROM'] == "googlebot(at)googlebot.com") || (strpos($_SERVER['HTTP_USER_AGENT'], 'YandexBot') !== false && $_SERVER['HTTP_FROM'] == "support@search.yandex.ru") || (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Lighthouse'));
	}
	
$asd = isPageSpeedTest2();
prent($asd);
prent($_SERVER);
die;
$elementFilter = array(
    "IBLOCK_ID" => 16,
    //"CHECK_PERMISSIONS" => "Y",
    //"MIN_PERMISSION" => "R",
    "INCLUDE_SUBSECTIONS" => "Y",
    "ACTIVE" => "Y",
    //"ACTIVE_DATE" => "Y",
    "SECTION_ID" => 223
);
$start = debug_microtime_float();
$arSection["~ELEMENT_CNT"] = CIBlockElement::GetList(array(), $elementFilter, array());
$end = debug_microtime_float() - $start;
prent($end);


prent($arSection["~ELEMENT_CNT"]);



$arFilter = Array(
"IBLOCK_ID"=>16,
"SECTION_ID" => 223,
"ACTIVE"=>"Y",
"INCLUDE_SUBSECTIONS" => 'Y'
);

$start = debug_microtime_float();
$activeElements = CIBlockSection::GetSectionElementsCount(223, Array("CNT_ACTIVE"=>"Y"));
$end = debug_microtime_float() - $start;
prent($end);
prent($activeElements);
die;
$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE 
	FROM 
		b_iblock_element el 
	LEFT JOIN 
		b_iblock_element_prop_s16 pr 
	ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE 
		el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
	
$arArticle = array();	
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if(strlen($row["ARTICLE"]) > 0)
		$arArticle[$row["ARTICLE"]] = $row["ID"];
}
prent($arArticle);
die;
$res = new CWbParserURI;
$res->parseAnalysis();

		$strSql = "SELECT * FROM ci_wb_top";
			
		$arIDs = array();	
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$arIDs[$row["bitrix_id"]] = $row["bitrix_id"];
		}
		
		$arItems = array();
		
		if(CModule::IncludeModule("panel.manager")){
			$objPricelist = new CPanelPricelist;
			$filter = array(
				"website" => array("wb"),
			);
			$arPrice = $objPricelist->getPriceByFilter($filter, "model");
		}
		if(CModule::IncludeModule("iblock")){
			$arFilter = Array(
				"IBLOCK_ID"	=> 16,
				"ID" => $arIDs,
				"PROPERTY_AVAILABILITY_RU" => 512,
				"!PROPERTY_AEN" => false,
				"!PROPERTY_CML2_ARTICLE" => false,
			);
			$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_AEN","PROPERTY_WBPRICE","PROPERTY_CML2_ARTICLE"));
			while($ar = $rs->GetNext()){
				if($arPrice[$ar["PROPERTY_CML2_ARTICLE_VALUE"]])
					$arItems[$ar["PROPERTY_AEN_VALUE"]] = $ar;
			}
		}
		prent(count($arItems));die;
		
$from = date("d.m.Y H:i:s", strtotime("-6 month"));
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	">DATE_CREATE" => $from,//"2021-12-10 20:00:00",
	"!PROPERTY_CML2_ARTICLE" => false,
	"PROPERTY_AVAILABILITY_RU" => array(512),
);
$cntAll = 0;
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
$arNew = array();
while($ar_fields = $rs->GetNext()){
	$arNew[] = $ar_fields;
	if(!$arPosition[$ar_fields["XML_ID"]]){
		$arPosition[$ar_fields["XML_ID"]] = array(
			"BITRIX_ID" => $ar_fields["ID"],
			"XML_ID" => $ar_fields["XML_ID"],
			"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
		);
	}
	
}
prent(count($arNew));die;
$strSql = "SELECT el.ID as ID, el.XML_ID as XML_ID, pr.PROPERTY_123 as ARTICLE 
	FROM 
		b_iblock_element el 
	LEFT JOIN 
		b_iblock_element_prop_s16 pr 
	ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE 
		el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
	
$arArticle = array();	
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if(strlen($row["ARTICLE"]) > 0)
		$arArticle[$row["ARTICLE"]] = $row;
}
		
$objPricelist = new CPanelPricelist;
$arFilter = array(
	"supplier_id" => array(88, 41),
	"active" => "Y"
);
$price = $objPricelist->getPriceByFilter($arFilter);

foreach($price as $key => $arItem){
	if($arArticle[$arItem["model"]] && !$arPosition[$arArticle[$arItem["model"]]["XML_ID"]]){
		$arPosition[$arArticle[$arItem["model"]]["XML_ID"]] = array(
			"BITRIX_ID" => $arArticle[$arItem["model"]]["ID"],
			"XML_ID" => $arArticle[$arItem["model"]]["XML_ID"],
			"ARTICLE" => $arArticle[$arItem["model"]]["ARTICLE"],
		);
	}else{
		
	}
}
prent($arPosition);
die;
$from = date("Y-m-d", strtotime("-6 month"));
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	">DATE_CREATE" => $from,
	"!PROPERTY_CML2_ARTICLE" => false,
	"PROPERTY_AVAILABILITY_RU" => array(512),
);
$cntAll = 0;
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
$arNew = array();
while($ar_fields = $rs->GetNext()){
	$arNew[] = array(
		
	);
	prent($ar_fields);die;
}


die;
if (!class_exists('MoyskladAPI')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_moysklad.php');
}


$strSql = "SELECT ID, XML_ID FROM b_iblock_element WHERE IBLOCK_ID = '16'";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arXML[$row["XML_ID"]] = $row["ID"];
}
		
$obj = new MoyskladAPI("s1");
$obj->MSPosition = array();

$obj->getListProfit(0, true);

foreach($obj->MSPosition as &$arItem){
	if($arXML[$arItem["XML_ID"]]){
		$arItem["BITRIX_ID"] = $arXML[$arItem["XML_ID"]];
	}else{
		
	}
}
unset($arItem);
prent($obj->MSPosition);
die;
$asd = new CWbParserURI;
$asd->parse();
//$asd->parseAnalysis();

//prent($ar);

die;
	function createfile2Parse($urlParse, $keyUrl, $cnt){
		if($cnt == 5) sleep(60);

		if($cnt == 16) sleep(120);

		usleep(1000000);
		$ch = curl_init($urlParse);
		$fp = fopen($_SERVER['DOCUMENT_ROOT'] . "/upload/wb_parse" . "/{$keyUrl}.txt", "w");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_COOKIE, "catalog_region_select=minsk");
		curl_setopt($ch, CURLOPT_TIMEOUT, 240);
		
		curl_setopt($ch, CURLOPT_HEADER, true);
		 
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);

		curl_close($ch);
		fclose($fp);


	}
$urlParse = "https://www.wildberries.ru/catalog/26861053/detail.aspx?targetUrl=EX";
//createfile2Parse($urlParse, "sdfsdf", 1);
$result_file = $_SERVER['DOCUMENT_ROOT'] . "/upload/wb_parse" . "/sdfsdf.txt";
					$html = gzdecodeWB($result_file);
					$saw = new CNokogiri($html);
					
					//$ar = $saw->get('.product-offers-group table.product-offers tr.product-offer')->toArray();
					//$ar = $saw->get('.product-offer-2020__container')->toArray();
					$arPrice = $saw->get('.price-block__final-price')->toArray();
					
					prent($arPrice);
					//$arTitle = $saw->get('.product-offers-group .title-limit strong')->toArray();
					$arSeller = $saw->get('.seller-details__title--link')->toArray();//prent($arTitle);
					prent($arSeller);
	function gzdecodeWB($file){
		ob_start();
		readgzfile($file);
		$d = ob_get_clean();
		return $d;
	}
	
	
	prent();
	die;
/*
$strSql = "SELECT CREATED_BY,USER_ID,EMP_STATUS_ID,EMP_PAYED_ID FROM b_sale_order WHERE ID = '160824'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arUser = array();
if ($row = $results->Fetch()){
	if($row["CREATED_BY"]) $arUser[] = $row["CREATED_BY"];
	if($row["USER_ID"]) $arUser[] = $row["USER_ID"];
	if($row["EMP_STATUS_ID"]) $arUser[] = $row["EMP_STATUS_ID"];
	if($row["EMP_PAYED_ID"]) $arUser[] = $row["EMP_PAYED_ID"];
}
$arUser = array_unique($arUser);
prent($arUser);
	
$userID = 587;
$sum = 12159;
$currency = "BYN";
$notes = "Изменено из CRM";
prent($asd);

$loyalty = getUserLoyaltyCRM($userID, "tempus-by");
if($loyalty["active"]){
	$res = updateInternalAccount($userID, $loyalty["amount"], $currency, $notes);
}

prent($asd);
	*/
die;
global $DB;
$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;
$objService = new OrderService;
$objProduct = new CPanelProduct;


$start = debug_microtime_float();
		$dbVariants = CSaleLocation::GetList(
			["SORT" => "ASC", "COUNTRY_NAME_LANG" => "ASC", "CITY_NAME_LANG" => "ASC"],
			["COUNTRY_ID" => 1, "LID" => LANGUAGE_ID],
			false,
			false,
			["ID", "COUNTRY_NAME", "CITY_NAME", "SORT", "COUNTRY_NAME_LANG", "CITY_NAME_LANG", "CITY_ID", "CODE"]
		);
		while ($arVariants = $dbVariants->GetNext())
		{
			$city = !empty($arVariants['CITY_NAME']) ? ' - '.$arVariants['CITY_NAME'] : '';

			if ($arVariants['ID'] === $curVal)
			{
				// set formatted value
				$locationFound = $arVariants;
				$arVariants['SELECTED'] = 'Y';
				$arProperty['VALUE_FORMATED'] = $arVariants['COUNTRY_NAME'].$city;
			}

			$arVariants['NAME'] = $arVariants['COUNTRY_NAME'].$city;
			// save to variants
			$arProperty['VARIANTS'][] = $arVariants;
		}
$item = \Bitrix\Sale\Location\LocationTable::getById(11395)->fetch();
prent($item);
$curVal = CSaleLocation::getLocationIDbyCODE("000000014333");
prent($curVal);

prent(count($arProperty['VARIANTS']));
prent($arProperty['VARIANTS']);
		$end = debug_microtime_float() - $start;
		prent($end);
		
		
$res = \Bitrix\Sale\Location\TypeTable::getList(array(
    'select' => array('*', 'NAME_RU' => 'NAME.NAME'),
    'filter' => array('=NAME.LANGUAGE_ID' => LANGUAGE_ID)
));
while($item = $res->fetch())
{
    prent($item);die;
}

		die;
/*
$strSql = "SELECT * FROM ci_retail_loyalty WHERE SEND_SMS = 'N' LIMIT 0,500";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
					
	$arResult['ITEMS'][] = $row;
					
}

if (\Bitrix\Main\Loader::includeModule('mlife.smsservices')){
	
	$transport = new \Mlife\Smsservices\Sender();
	
	foreach($arResult['ITEMS'] as $key => $arItem){
		$phone = $arItem["PHONE"];
		$message = "Баланс бонусного счета - {$arItem["AMOUNT"]} рублей. \r\nTEMPUS - Наручные часы\r\nwww.tempus.by";
		
		$arSend = $transport->sendSms($phone, $message);
		if(!is_object($arSend) || $arSend->error) {
			
		}else{
			$DB->Update("ci_retail_loyalty", array("SEND_SMS" => "'Y'"), "WHERE ID = '".$arItem["ID"]."'", $err_mess.__LINE__);
		}
	
	}

}

die;
*/

$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$send = true;
$page = 1;

$start = debug_microtime_float();

$arSend = array();
do {
	
	$ar = array(
		"limit" => 100,
		"page" => $page,
		"filter" => array(
			"status" => "activated",
			"minAmount" => "10"
		)
	);
	$response = $api->loyaltyList($ar);
	$response = objectToArray($response);

//prent($response);die;
	if($response && $response["response"]["success"]){
		
		foreach($response["response"]["loyaltyAccounts"] as $key => $arItem){
			if($arItem["amount"] > 0){
				$name = $arItem["customer"]["firstName"] . " " . $arItem["customer"]["lastName"];
				$name = trim($name);
				$arSend[$arItem["id"]] = array(
					"RETAIL_ID" => $arItem["id"],
					"USER_ID" => $arItem["customer"]["externalId"],
					"USER_NAME" => $name,
					"PHONE" => $arItem["phoneNumber"],
					"AMOUNT" => $arItem["amount"],
				);
			}
		}
	
		$totalPageCount = intval($response["response"]["pagination"]["totalPageCount"]);
		if($page >= $totalPageCount){
			$send = false;
		}
	}else{
		prent($page);
	}
	
	$page++;
	

	//prent($response);
	//	$send = false;
		
} while ($send == true);

$end = debug_microtime_float() - $start;
prent($end);

/*
Баланс бонусного счета - 10 рублей. 
TEMPUS - Наручные часы
tempus.by
*/
//$response = $api->loyaltyGetBonuses(21534);

//$response = objectToArray($response);

prent($arSend);

die;
$DB->Query("TRUNCATE TABLE ci_retail_loyalty", false, $err_mess.__LINE__);
foreach($arSend as $key => $arItem){
	
	$in = array(
		"RETAIL_ID" => "'".addslashes($arItem["RETAIL_ID"])."'",
		"USER_ID" => "'".addslashes($arItem["USER_ID"])."'",
		"USER_NAME" => "'".addslashes($arItem["USER_NAME"])."'",
		"PHONE" => "'".addslashes($arItem["PHONE"])."'",
		"AMOUNT" => "'".addslashes($arItem["AMOUNT"])."'",
	);

	$DB->Insert("ci_retail_loyalty", $in, $err_mess.__LINE__);
	
}
prent($arSend);

die;

	
$obj = new MoyskladAPI("s1");
$obj->MSPosition = array();

$obj->getListSupply(0, true);
//prent($obj->MSPosition);
foreach($obj->MSPosition as $arSupply){
	$applicable = ($arSupply["applicable"] ? "Y" : "N");
	
	//prent($applicable);
}
die;
				$strSql = "SELECT * FROM ci_ms_history WHERE TYPE = 'SUPPLY' ORDER BY TIMESTAMP DESC LIMIT 0,100";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					
					for($i = 0; $i < $row["QUANTITY"]; $i++){
						$arItem["LAST_PURCHASE"][$row["PRODUCT_XML_ID"]][] = $row;
					}
					
				} 
prent($arItem["LAST_PURCHASE"]);
die;
CExchange::updateProduct(163446);
CExchange::updateProduct(163454);
CExchange::updateProduct(163455);
CExchange::updateProduct(163456);
CExchange::updateProduct(163458);



die;
        $optionsSitesList = RetailcrmConfigProvider::getSitesList();
        $optionsOrderTypes = RetailcrmConfigProvider::getOrderTypes();
        $optionsDelivTypes = RetailcrmConfigProvider::getDeliveryTypes();
        $optionsPayTypes = RetailcrmConfigProvider::getPaymentTypes();
        $optionsPayStatuses = RetailcrmConfigProvider::getPaymentStatuses(); // --statuses
        $optionsPayment = RetailcrmConfigProvider::getPayment();
        $optionsOrderProps = RetailcrmConfigProvider::getOrderProps();
        $optionsLegalDetails = RetailcrmConfigProvider::getLegalDetails();
        $optionsContragentType = RetailcrmConfigProvider::getContragentTypes();
        $optionsCustomFields = RetailcrmConfigProvider::getCustomFields();

        $api = new RetailCrm\ApiClient(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

        $arParams = array(
            'optionsOrderTypes'     => $optionsOrderTypes,
            'optionsDelivTypes'     => $optionsDelivTypes,
            'optionsPayTypes'       => $optionsPayTypes,
            'optionsPayStatuses'    => $optionsPayStatuses,
            'optionsPayment'        => $optionsPayment,
            'optionsOrderProps'     => $optionsOrderProps,
            'optionsLegalDetails'   => $optionsLegalDetails,
            'optionsContragentType' => $optionsContragentType,
            'optionsSitesList'      => $optionsSitesList,
            'optionsCustomFields'   => $optionsCustomFields,
        );

$orderCrm = RCrmActions::apiMethod($api, 'ordersGet', __METHOD__, 153868, "tempus-by");

		prent($orderCrm);
die;
$strSql = "SELECT * FROM ci_price WHERE brand_id = '27'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$article = $row["model"];
	
	$w = substr($article, -2, 1);
	if(!ctype_digit($w) && substr($article, -3, 1) != "."){
		$article = substr($article, 0, -2) . "." . substr($article, -2);
		prent($article);
		$DB->Update("ci_price", array("model" => "'$article'"), "WHERE id = '".$row["id"]."'", $err_mess.__LINE__);
	}
}



die;

$el = new CIBlockElement;

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	"ID" => 2251,
	//"SECTION_ID" => 228,
	">CATALOG_QUANTITY" => 0,
	"!PROPERTY_AEN" => false,
	//"!PROPERTY_WBPRICE" => false,
	"!PROPERTY_WBARTICLE" => false,
	"!PROPERTY_BRAND" => false,
);
$cntAll = 0;
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "IBLOCK_ID", "NAME", "PREVIEW_TEXT", "DATE_ACTIVE_FROM","PROPERTY_*"));
while($ob = $rs->GetNextElement()){
	$arFields = $ob->GetFields();
	$arProps = $ob->GetProperties();
	
	$arSection = getSectionsElement($arFields["ID"]);
	$txt = "Оригинальные " . mb_strtolower($arProps["TYPE"]["VALUE"][0]) . " " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arProps["CML2_ARTICLE"]["VALUE"]}.";
	
	if($arFields["PREVIEW_TEXT"] != $txt){
		//prent($txt);
		//$el->Update($arFields["ID"], array("PREVIEW_TEXT" => $txt));
	}
	prent($arFields);
}

die;

	
	
$obj = new MoyskladAPI("s1");
$obj->MSPosition = array();

$obj->getListSupply(0, true);


//запрашиваем и пишем все Приемки
$strSql = "SELECT * FROM ci_ms_assortment";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	$arResult["ASSORTMENT"][$row["UNIQUE_ID"]] = $row["NAME"];
}

$strSql = "SELECT * FROM ci_ms_agent";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	$arResult["AGENT"][$row["UNIQUE_ID"]] = $row["NAME"];
}

$strSql = "SELECT * FROM ci_ms_history";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	$arResult["HISTORY"][$row["DOCUMENT_ID"]] = $row["CHECK_ID"];
}

$arRes = $arDocuments = array();

foreach($obj->MSPosition as $arSupply){

	$document_id = $arSupply["id"];
	$check_id = md5(serialize($arSupply));
	
	if($arResult["HISTORY"][$document_id] && $arResult["HISTORY"][$document_id] == $check_id){
	//	continue;
	}
	
	$moment = $arSupply["moment"];
	
	//$sum = $arSupply["sum"];
	/*
	$url = $arSupply["agent"]["meta"]["href"];
	$md5 = md5($url);
	if(!$arResult["AGENT"][$md5]){
		if($rs = $obj->customRequest($url)){

			if($rs["id"] && $rs["name"]){
				$in = array(
					"MS_ID" => "'".addslashes($rs["id"])."'",
					"NAME" => "'".addslashes($rs["name"])."'",
					"UNIQUE_ID" => "'".addslashes($md5)."'",
				);

				$DB->Insert("ci_ms_agent", $in, $err_mess.__LINE__);
				
				$arResult["AGENT"][$md5] = $rs["name"];
			}

		}
		
	}

	if(!$arResult["AGENT"][$md5]){
		//лог ошибки
		continue;
		//die("123");
	}
	*/
	$agent = $arResult["AGENT"][$md5];
	
	$url = $arSupply["positions"]["meta"]["href"];
	$arPosition = $obj->customRequest($url);
	
	foreach($arPosition["rows"] as $position){
		
		$ar = array();
		
		$quantity = $position["quantity"];
        $price = $position["price"];
					
		$url = $position["assortment"]["meta"]["href"];
		$md5 = md5($url);
		//if(!$arResult["ASSORTMENT"][$md5]){
			if($rs = $obj->customRequest($url)){
				prent($rs);die;
				$name = (strlen($rs["article"]) > 0 ? $rs["article"] : $rs["name"]);
				if($rs["id"] && $name){
					$in = array(
						"MS_ID" => "'".addslashes($rs["id"])."'",
						"NAME" => "'".addslashes($name)."'",
						"UNIQUE_ID" => "'".addslashes($md5)."'",
					);

					$DB->Insert("ci_ms_assortment", $in, $err_mess.__LINE__);
					
					$arResult["ASSORTMENT"][$md5] = $name;
				}

			}
			
		//}
		
		if(!$arResult["ASSORTMENT"][$md5]){
			//лог ошибки
			//continue;AddMessage2Log($config); 
			die("456");
		}
		
		$ar["DOCUMENT_ID"] = $document_id;
		
		$ar["AGENT"] = $agent;
		
		$ar["TIMESTAMP"] = $moment;
		$ar["ARTICLE"] = $arResult["ASSORTMENT"][$md5];
		
		$ar["QUANTITY"] = $quantity;
		$ar["PRICE"] = $price;
		
		$ar["CHECK_ID"] = $check_id;
		
		$arRes[] = $ar;
		//
	}
	
	$arDocuments[] = $document_id;
}

die;
prent($arRes);
/*
$DB->Query("TRUNCATE TABLE ci_ms_history", false, $err_mess.__LINE__);
foreach($arRes as $key => $arItem){
		$in = array(
			"DOCUMENT_ID" => "'".addslashes($arItem["DOCUMENT_ID"])."'",
			"AGENT" => "'".addslashes($arItem["AGENT"])."'",
			"ARTICLE" => "'".addslashes($arItem["ARTICLE"])."'",
			"QUANTITY" => $arItem["QUANTITY"],
			"PRICE" => $arItem["PRICE"] / 100,
			"CHECK_ID" => "'".addslashes($arItem["CHECK_ID"])."'",
			"TYPE" => "'".addslashes("Приемка")."'",
			"TIMESTAMP" => "'".addslashes(str_replace(".000", "", $arItem["TIMESTAMP"]))."'"
		);
prent($in);
		$DB->Insert("ci_ms_history", $in, $err_mess.__LINE__);
}*/
die;


///		$obj = new CPriceUpdate("s2");
///		$obj->setAllPrice();
		
		
		
		
$result = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS);

$rsEvents = GetModuleEvents("catalog", "OnGetOptimalPrice");
while ($arEvent = $rsEvents->Fetch())
{
    prent($arEvent);
}

die;		
CExchange::updateReserved(); 


//         https://tempusshop.ru/upload/c560e6f6cd9e5f294fd68f6d5052a979.jpg

$file = $_SERVER["DOCUMENT_ROOT"] . "/upload/temp.png";

$file = "https://www.casio.com/content/dam/casio/product-info/locales/sg/en/timepiece/product/watch/G/GM/GMA/gma-s2100wt-7a1/assets/GMA-S2100WT-7A1.png";
$img_info = $objProduct->getImgInfo($file);

                if( $img_info ){
					
					if($img_info["link"]) $file = $img_info["link"];
					
                    $types = array("", "gif", "jpeg", "png");
                    $ext = $types[$img_info[2]];
							
					if($img_info[2] == 18) $ext = "webp";
							
                    $func = 'imagecreatefrom'.$ext;
                    $img = $objProduct->CutImage( $func($file), $img_info[0], $img_info[1] );
                    $path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
                    imagejpeg( $img, $path );
					if( $btrx_img = CFile::MakeFileArray($path) )
                        $images[] = $btrx_img;
					
					prent($path);prent($images);
                }
				
prent($img_info);


die;


$strSql = "SELECT * FROM ci_catalog_artnumbers";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arAlternative = array();
while ($row = $results->Fetch()){
	$arAlternative[$row["artnumber"]][] = $row["alternative"];
	$arLink[$row["alternative"]] = $row["artnumber"];
}

$arFilter = array(
	"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR", "CL"),
	"!CANCELED" => "Y",
);
$ar = array();
$arOrder = $objService->getOrder(array(), $arFilter);

$arReserveAll = array();
foreach($arOrder as $key => $arItem){
	foreach($arItem["BASKET"] as $k => $arBasket){
		$arReserveAll[$arBasket["PRODUCT_ID"]] += $arBasket["QUANTITY"];
		$arIDs[$arBasket["PRODUCT_ID"]] = $arBasket["PRODUCT_ID"];
	}
}

if(count($arIDs) > 0){
	/*$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE 
		FROM 
			b_iblock_element el 
		LEFT JOIN 
			b_iblock_element_prop_s16 pr 
		ON el.ID=pr.IBLOCK_ELEMENT_ID 
		WHERE 
			el.ID IN ('" . implode("','", $arIDs) . "') AND el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arArticle[$row["ID"]] = $row["ARTICLE"];
		}*/
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
		);

}

$DB->Query("TRUNCATE TABLE ci_reserved", false, $err_mess.__LINE__);

foreach($arAdd as $key => $arItem){
	if($arItem["RESERVED"] >= $arItem["AVAILABLE_RU"] || $arItem["RESERVED"] >= $arItem["AVAILABLE_BY"] || $arItem["RESERVED"] >= $arItem["AVAILABLE_PL"]){
		prent($arItem);
		
		$in = array(
			"PRODUCT_ID" => "'".addslashes($arItem["PRODUCT_ID"])."'",
			"ARTICLE" => "'".addslashes($arItem["ARTICLE"])."'",
			"AVAILABLE_RU" => "'".addslashes($arItem["AVAILABLE_RU"])."'",
			"AVAILABLE_BY" => "'".addslashes($arItem["AVAILABLE_BY"])."'",
			"AVAILABLE_PL" => "'".addslashes($arItem["AVAILABLE_PL"])."'",
			"RESERVED" => "'".addslashes($arItem["RESERVED"])."'"
		);
		
		//пишем всё во временную таблицу сразу
		$DB->Insert("ci_reserved", $in, $err_mess.__LINE__);
		
	}
}

	

		
//собираем массив для вставки

//prent($arArticle);
//prent($arReserve);
die;
if (!class_exists('OnlinerCart_API')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_moysklad.php');
}
	
	
	$obj = new MoyskladAPI("s1");

	$arDB = array();

	$res = $obj->getListOrder(0);
	
prent($res);
		
die;
		
		
echo \Bitrix\Main\EventResult::SUCCESS;
$result = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS);
prent($arEvent);
$rsEvents = GetModuleEvents("sale", "OnSaleOrderBeforeSaved");
while ($arEvent = $rsEvents->Fetch())
{
    prent($arEvent);
}
prent("------------");
$rsEvents = GetModuleEvents("sale", "OnSaleOrderSaved");
while ($arEvent = $rsEvents->Fetch())
{
    prent($arEvent);
}
		die;



$lines = file('/home/bitrix/stek.txt');
$i = $sum = 0;
// Осуществим проход массива и выведем содержимое в виде HTML-кода вместе с номерами строк.
foreach ($lines as $line_num => $line) {
	if(strlen($line) > 0){
		$sum += $line;
		$i++;
	}
	
}
echo $i;
prent($sum / $i);

die;

	function gzdecode2222($file){
		ob_start();
		readgzfile($file);
		$d = ob_get_clean();
		return $d;
	}
$ceneo_id = 98515136;
			$result_file = "/var/www/bitrix/data/www/tempusshop.ru/upload/tmp_ceneo/{$ceneo_id}.txt";
			prent($result_file);
			if($ceneo_id > 0 && file_exists($result_file)){

				//if ($result_file && file_exists($result_file)){
					$html = gzdecode2222($result_file);
					$saw = new CNokogiri($html);
//					product-offer__container
//     clickable-offer js_offer-container-click
//     js_product-offer
					$ar = $saw->get('.product-offer__container')->toArray();
					//prent($ar);
					$arTitle = $saw->get('.product-offers .title-limit strong')->toArray();
					//$arTitle = $saw->get('.title-limit strong')->toArray();//prent($arTitle);
					//if($arTitle[0]["#text"][0] == "Podobne oferty") continue;
					prent($ar);prent($arTitle);
					
				}
		die;
$var_2 = "LTP-1129A-7B";
$var_1 = "LTP-1229A-7АС";
similar_text($var_2, $var_1, $percent);
prent($percent);   
die;
$strSql = "SELECT tp.EXTERNAL_ORDER_ID as EXTERNAL_ORDER_ID, ord.ACCOUNT_NUMBER as ACCOUNT_NUMBER
	FROM 
		b_sale_tp_order tp 
	LEFT JOIN 
		b_sale_order ord 
	ON tp.ORDER_ID=ord.ID 
	WHERE 
		tp.EXTERNAL_ORDER_ID = '74868411'";
prent($strSql);
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	prent($row);
}



die;
//require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/components/yandex.market/admin.grid.list/class.php');

//0\home\bitrix\ext_www\tempusshop.ru\bitrix\modules\yandex.market\lib\component\tradingorder\gridlist.php

class GridList222 extends Yandex\Market\Component\TradingOrder\GridList{
	function asd(){
		
	}
}
$controller = new GridList222;


die;
if (!class_exists('OnlinerCart_API')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_moysklad.php');
}

$obj = new MoyskladAPI("s1");
$res = $obj->getListOrder(0);
prent($res); 

die;
$arDB = array();
for($i = 0; $i < 10; $i++){
	
	$res = $obj->getListOrder($i);
	prent($res); 
	foreach($res["rows"] as $key => $arItem){
		$arDB[$arItem["id"]] = array(
			"ORDER_NUMBER" => $arItem["name"],
			"MS_ID" => $arItem["id"],
			"META" => $arItem["meta"],
		);
	}

	
} 

foreach($arDB as $key => $arItem){
	if(!in_array($arItem["MS_ID"], $arOrderMS)){
		$in = array(
			"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
			"MS_ID" => "'".addslashes($arItem["MS_ID"])."'",
			"META" => "'" . json_encode($arItem["META"]) . "'"
		);
		
		//пишем всё во временную таблицу сразу
		$DB->Insert("ci_ms_order", $in, $err_mess.__LINE__);
	}
}


die;
$obj1 = new CExchange("s1_order");
$res1 = $obj1->updateFromMoySkladOrder();
	die;

	$strSql = "SELECT * FROM ci_analysis WHERE site_id = 's1'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arr[] = $row;
	}
	if(count($arr) > 0){
		
		foreach($arr as $key => $arItem){
			$in = array(
				"brand_id" => intval($arItem["brand_id"]),
				"settings" => "'".addslashes($arItem["settings"])."'",
				"site_id" => "'wb'",
				
			);
			prent($in); 
			//пишем всё во временную таблицу сразу
			//$DB->Insert("ci_analysis", $in, $err_mess.__LINE__);
		}

	}
	
	die;
$arStickerWB = getStickerWB(array(107521799)); 
prent($arStickerWB);
die;
$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;


/*
$arOrder = array(90855843,90855842,90855840,90855839);

$data_string = array(
	"orderIds" => $arOrder,
);
		
$data_string = \Bitrix\Main\Web\Json::encode($data_string);
				
$result = CRestQueryWB::rest_query_na("https://suppliers-api.wildberries.ru", $data_string, "/api/v2/orders/stickers");
$res = \Bitrix\Main\Web\Json::decode($result);
$arResult["ITEMS"] = array();
foreach($res["data"] as $key => $arItem){
	$arResult["ITEMS"][$arItem["orderId"]] = $arItem["sticker"]["wbStickerIdParts"];
}
prent($arResult["ITEMS"]); 



die;*/
$data_string = array(
	//"id" => md5("BITRIX".$arInfo['key'].time()."LICENCE"),
	//"jsonrpc" => "2.0",
	"params" => array(
		"query" => array("limit" => 1000,"offset" => 0),
		"filter" => array(
			"order" => array("column" => "createdAt","order" => "desc"),
		//	"find" => array(),
		//	"filter" => array(),
		),
		"supplierId" => "1b8230a0-56f8-4be6-9640-e48cf1365dfc",
		"jsonrpc" => "2.0",
		"id" => "json-rpc_51"
	),
);

$data_string = \Bitrix\Main\Web\Json::encode($data_string);

		$urlrest = "https://seller.wildberries.ru/ns/card/suppliers-portal-card/card/tableList";
//prent($urlrest);
		$ch = curl_init($urlrest);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, 1);  
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36",
			"Accept: */*",
			"Accept-Language: en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7",
			"Connection: keep-alive",
                  //  'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string),
					'X-Fingerprint-Id: 0846db8b54d63f5c170ed72e0e5e1247',
                    'Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhY2Nlc3NJRCI6ImUzNmRjMzQ3LTIyYzAtNGIxZS05ZThjLWIwYWY3YWM3ZGMwNyJ9.Z6OODK2x0Xghs2ObN4lJgJHfDYMotBanv9SZ9vb-URI',
                    'X-Supplier-ID: 1b8230a0-56f8-4be6-9640-e48cf1365dfc'

                ));
		$res = curl_exec($ch);
		
/*


X-Fingerprint-Id: 463f80113cfd856bcb36f275d0059d95
Connection: keep-alive
*/
prent($res);  
$res = curl_getinfo($ch);
prent($res); 
die;
$result = CRestQueryWB::rest_query_na("https://seller.wildberries.ru", $data_string, "/ns/card/suppliers-portal-card/card/tableList");
prent($result);  

die;
if (!class_exists('OnlinerCart_API')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_onliner_cart.php');
}
$obj = new OnlinerCart_API;
$order = $obj->getOrder("25m4473", "status_change_log,positions");
$order = json_decode($order, true);
prent($order);
die;


$arIDs = array();

$strSql = "SELECT PRODUCT_ID, TIMESTAMP_X FROM b_catalog_price ORDER BY TIMESTAMP_X asc";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
while($row = $results->Fetch()){
	$price_time = strtotime($row["TIMESTAMP_X"]);
	$diff_day = intval((time() - $price_time) / (60*60*24));
	//если больше полугода, то в массив для деактивации
	if($diff_day > 180){
		$arIDs[$row["PRODUCT_ID"]] = $diff_day;
	}else{
		unset($arIDs[$row["PRODUCT_ID"]]);
	}
}
prent($arIDs);die;
foreach($arIDs as $product_id => $diff_day){

	$arLog = array(
		"event" => "R",
		"text" => "Деактивировали товар - {$product_id}",
		"detail" => "Нет в наличии - {$diff_day} дней",
	);
	prent($arLog);	
}

die;
if(count($arIDs) > 0){
	$strSql = "SELECT ID, TIMESTAMP_X FROM b_catalog_product WHERE TIMESTAMP_X < DATE_SUB(NOW(), INTERVAL 180 DAY) AND QUANTITY <= '0' AND ID IN ('".implode("','", $arIDs)."')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		prent($strSql);
	while($row = $results->Fetch()){

		$price_time = strtotime($row["TIMESTAMP_X"]);
		$diff_day = intval((time() - $price_time) / (60*60*24));

		//если больше полугода, то в массив для деактивации
		if($diff_day > 180){
			$arDeactIDs[$row["PRODUCT_ID"]] = $diff_day;
		}
		
	}
}



die;

require $_SERVER["DOCUMENT_ROOT"] . '/local/vendor/autoload.php';

// This will output the barcode as HTML output to display in the browser
$generator = new Picqer\Barcode\BarcodeGeneratorHTML();
echo $generator->getBarcode('081231723897', $generator::TYPE_CODE_128);

die;

$arResult = array();

$arSettings = array(
	"round" => -1,
	"rate" => 1,
	"currency" => "RUB",
);

$tmp = $objSupplier->getList();
$arCurrency = $objCurrency->getDetail("RUB");

if($arCurrency){
	$arSettings["rate"] = $arCurrency["rate"];
}
	
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"ID" => 39717
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_CML2_ARTICLE"));
while($ar = $rs->GetNext()){
	$article = $ar["PROPERTY_CML2_ARTICLE_VALUE"];
	$arResult["ITEMS"][$article] = $ar;
}

if(count($arResult["ITEMS"]) > 0){
	$arPrice = array();
	$filter = array(
		"website" => array("s1"),
		"article" => array_keys($arResult["ITEMS"])
	);
	$price = $objPricelist->getPriceByFilter($filter);
	//prent($price);
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			
			$arItem["price"] = ($arItem["price"] / $arSettings["rate"]);
			
			$margin = ($arItem["price"] < 800 ? 5 : 3.5);
			
			$arItem["price"] = $arItem["price"] * $margin;
			
			$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);

			if(isset($arPrice[$arItem["model"]])){
				if($arItem["price"] < $arPrice[$arItem["model"]]["price"])
					$arPrice[$arItem["model"]] = $arItem;
			}else
				$arPrice[$arItem["model"]] = $arItem;
		}
	}
	unset($arItem);
}


prent($arPrice);
die;
/*
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	//"PROPERTY_AVAILABILITY_RU" => 512,
	"ID" => 79149,
	//"!PROPERTY_AEN" => false,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","PROPERTY_MORE_PHOTO"));

if($ob = $rs->GetNextElement()){
	$arFields = $ob->GetFields();
	
	$arImagesPath = array();
	
	foreach($arFields["PROPERTY_MORE_PHOTO_VALUE"] as $img){
		$arImagesPath[] = $_SERVER["DOCUMENT_ROOT"] . CFile::GetPath($img);
	}
	prent($arImagesPath);
}

die;*/

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"!PROPERTY_CML2_ARTICLEE" => false,
	"!CODE" => false,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_CML2_ARTICLE"));
$all = $rs->SelectedRowsCount();
$i = 0;
while($ar = $rs->GetNext()){
	if($i % 50 == 0){
		prent($i);
	}
	$i++;
}
prent($all);
die;
	/*
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"!PROPERTY_AEN" => false,
	"!PROPERTY_WBARTICLE" => false,
	"PROPERTY_PROP_MAXYSS_CARDID_WB" => false,
);
//

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME"));
while($ar = $rs->GetNext()){
	$url = "https://tempusshop.ru/bitrix/tools/maxyss.wb/ajax.php?action=data_card&product_id={$ar["ID"]}";
	$res = json_decode(file_get_contents($url), true);
	
	$mess = "";
	$flg = false;
	if($res["success"] && $res["success"] == "MAXYSS_WB_DATA_SUCCESS"){
		$mess = $ar["NAME"] . " - " . $ar["NAME"] . " успешно установлен";
		$flg = true;
	}else{
		$mess = "Ошибка " . $ar["NAME"] . " - " . $ar["NAME"];
	}
	
	$arLog = array(
		"event" => "WB",
		"text" => "Получение артикула с WB" . ($flg == false ? " error" : ""),
		"detail" => array("mess" => $mess, "res" => $res),
	);
	CLog::add2log($arLog);
		
	//prent($ar);prent($res);die;
}

die;
*/
/*
$arSettings = array(
	"round" => -1,
	"rate" => 1,
	"currency" => "RUB",
);

$tmp = $objSupplier->getList();
$arCurrency = $objCurrency->getDetail("RUB");

if($arCurrency){
	$arSettings["rate"] = $arCurrency["rate"];
}
	
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"ID" => 133644,
);
//

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_CML2_ARTICLE"));
while($ar = $rs->GetNext()){
	$article = $ar["PROPERTY_CML2_ARTICLE_VALUE"];
	$arResult["ITEMS"][$article] = $ar;
}

if(count($arResult["ITEMS"]) > 0){
	$arPrice = array();
	$filter = array(
		"website" => array("s1"),
		"article" => array_keys($arResult["ITEMS"])
	);
	$price = $objPricelist->getPriceByFilter($filter);
	
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			$arItem["price"] = ($arItem["price"] / $arSettings["rate"]) * 3.5;
			$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);

			if(isset($arPrice[$arItem["model"]])){
				if($arItem["price"] < $arPrice[$arItem["model"]]["price"])
					$arPrice[$arItem["model"]] = $arItem;
			}else
				$arPrice[$arItem["model"]] = $arItem;
		}
	}
	unset($arItem);
}
foreach($arResult["ITEMS"] as $key => $arItem){
	if($arPrice[$key]){
		CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("WBPRICE" => $arPrice[$key]["price"]));
	}else{
		CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("WBPRICE" => false));
	}
}


die;

die;
*/
/*
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	"ID" => 2251,
	//"SECTION_ID" => 228,
	">CATALOG_QUANTITY" => 5
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "DETAIL_PICTURE", "PROPERTY_aen"));
while($ar = $rs->GetNext()){
    $id_element = $ar["ID"];
    $item_info = CMaxyssWb::PrepareItem($id_element);
    if ($item_info !== false){

		if($ar["PROPERTY_AEN_VALUE"]){
			$item_info["item"]["card"]["nomenclatures"][0]["variations"][0]["barcode"] = $ar["PROPERTY_AEN_VALUE"];
		}
//prent($item_info);die;	
		$item_info["item"]["card"]["nomenclatures"][0]["vendorCode"] = $ar["CODE"];

        if($item_info["item"]['card']['imtId'])
            $res = CMaxyssWb::UpdateCadr($item_info, $id_element);
        else
            $res = CMaxyssWb::UploadCadr($item_info, $id_element);
		prent($item_info);prent($res);die;	
		$arLog = array(
			"event" => "WB",
			"text" => "Обмен с WB",
			"detail" => serialize(array("item_info" => $item_info, "res" => $res)),
		);
		CLog::add2log($arLog);
    }
}


die;*/
/*
$orderId = 122811;

$order = \Bitrix\Sale\Order::load($orderId);
$deliveryIds = $order->getDeliverySystemId();
prent($deliveryIds);
die;
*/
$arLink = array(
	"TYPE" => array(
		"Мужские" => "Мужской",
		"Женские" => "Женский",
		"Унисекс" => "Мужской",
		"Детские" => "Детский",
	),
	"GLASS" => array(
		"Органическое" => "органическое",
		"Минеральное" => "Минеральное стекло",
		"Сапфировое" => "сапфировое",
	),
	"MECHANISM" => array(
		"Кварцевые" => "кварцевые",
		"Автоматические" => "Автоматический",
		"Автоматические с ручным подзаводом" => "Автоматический",
		"Механические" => "механические",
	),
	"WR" => array(
		"WR30" => "WR30 (3 atm)",
		"WR50" => "WR50 (5 atm)",
		"WR100" => "WR100 (10 atm)",
		"WR200" => "WR200 (20 atm)",
		"WR300" => "WR300 (30 atm)",
		"WR500" => "WR500 (50 atm)",
		"WR600" => "WR600 (60 atm)",
		"WR1000" => "WR1000 (100 atm)",
	),
	
	"FACE" => array(
		"Аналоговый" => "аналоговый",
		"Цифровой" => "цифровой",
	),

	"WARRANTY" => array(
		"1 год, от производителя" => "1 год от производителя",
		"1 года, от производителя" => "1 год от производителя",
		"2 года, от производителя" => "2 года",
		"3 года, от производителя" => "3 года",
	),
	"MATERIAL" => array(
		"Нержавеющая сталь" => "Нержавеющая сталь 316L",
		"Полимер" => "Полимер",
		"Кожа" => "Кожаный",
		"Текстиль" => "текстиль",
		"Латунь" => "латунь",
		"Титан" => "титан",
		"Алюминий" => "алюминий",
		"Каучук" => "каучук",
		"Силикон" => "Силикон гипоаллергенный",
		"Дерево" => "дерево",
		"Керамика" => "керамика",
		"Карбон" => "карбон",
	),
	"POPULAR_TAG" => array(
		"Военные" => "военные",
		"Пилотские" => "пилотские",
		"На каждый день" => "на каждый день; повседневные",
		"Под костюм" => "офисный стиль",
		"Дизайнерские" => "Дизайнерские",
		"Спортивные часы" => "спортивные",
		"Скелетоны" => "скелетон",
	),
	"dial_color" => array(
		"Черный" => "черный",
		"Белый" => "белый",
		"Серый" => "Серый",
		"Бежевый" => "бежевый",
		"Золотистый" => "золотистый",
		"Серебристый" => "серебристый",
		"Красный" => "красный",
		"Коричневый" => "коричневый",
		"Оранжевый" => "оранжевый",
		"Желтый" => "желтый",
		"Зеленый" => "зеленый",
		"Голубой" => "голубой",
		"Синий" => "синий",
		"Фиолетовый" => "фиолетовый",
		"Розовый" => "розовый",
		"Перламутровый" => "перламутровый",
		"Разноцветный" => "разноцветный",
	),
	"FINALCOUNTRY" => array(
		"Австрия" => "Австрия",
		"Беларусь" => "Беларусь",
		"Великобритания" => "Великобритания",
		"Китай" => "Китай",
		"Россия" => "Россия",
		"Тайланд" => "Тайланд",
		"Филиппины" => "Филиппины",
		"Швейцария" => "Швейцария",
		"Швеция" => "Швеция",
		"Япония" => "Япония",
	),
	"FEATURES" => array(
		"Ударопрочность" => "Ударопрочные",
		"Солнечная батарея" => "питание от солнечных батарей",
		"Компас" => "с компасом",
		"Bluetooth" => "Bluetooth",
		"Высотомер (альтиметр)" => "высотомер",
		"Барометр" => "барометр",
		"Шагомер" => "Шагомер",
		"Фазы луны" => "с индикатором фаз луны",
		"Хронограф" => "хронограф",
		"Таймер" => "таймер",
		"Ежечасный сигнал" => "ежечасный сигнал",
		"Будильник" => "с будильником",
		"Батарейка на 5 лет" => "батарейка на 5 лет",
		"Индикатор запаса хода" => "с индикатором запаса хода",
		"Мировое время" => "мировое время",
		"12/24-часовое отображение времени" => "12/24-часовое отображение времени",
		"SMART-часы" => "smart watch",
		"Автоматический календарь" => "автоматический календарь",
		"Калькулятор" => "калькулятор",
	),
);
/*
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	//"ID" => 2027,
	"SECTION_ID" => 228,
	">CATALOG_QUANTITY" => 5
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "DETAIL_PICTURE", "PROPERTY_aen"));
while($ar = $rs->GetNext()){
    $id_element = $ar["ID"];
    $item_info = CMaxyssWb::PrepareItem($id_element);
    if ($item_info !== false){

		if($ar["PROPERTY_AEN_VALUE"]){
			$item_info["item"]["card"]["nomenclatures"][0]["variations"][0]["barcode"] = $ar["PROPERTY_AEN_VALUE"];
		}

		$item_info["item"]["card"]["nomenclatures"][0]["vendorCode"] = $ar["CODE"];
			
        if($item_info["item"]['card']['imtId'])
            $res = CMaxyssWb::UpdateCadr($item_info, $id_element);
        else
            $res = CMaxyssWb::UploadCadr($item_info, $id_element);
			
		prent($res);
    }
}



die;*/

$arBrandReplace = array(
	"Anne Klein" => "ANNE KLEIN",
	"Aviator" => "AVIATOR",
	"Calvin Klein" => "CALVIN KLEIN",
	"Casio" => "CASIO",
	"Citizen" => "CITIZEN",
	"Daniel Klein" => "DANIEL KLEIN",
	"Essence" => "essence",
	"Guess_n" => "GUESS",
	"Jacques Lemans" => "Jacques Lemans",
	"Le Temps" => "Le temps",
	"Moschino" => "MOSCHINO",
	"Orient" => "ORIENT Watch",
	"RADO" => "Rado",
	"Raymond Weil" => "RAYMOND WEIL",
	"Sergio Tacchini" => "SERGIO TACCHINI",
	"Timex" => "TIMEX",
	"Wenger" => "WENGER",
	"Zeppelin" => "ZEPPELIN",
);

$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
);
$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
while($arFields = $result->GetNext()){
	$arBrand[$arFields["ID"]] = ($arBrandReplace[$arFields["NAME"]] ? $arBrandReplace[$arFields["NAME"]] : $arFields["NAME"]);
}

//die;
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	"ID" => 12172,
	//"SECTION_ID" => 228,
	">CATALOG_QUANTITY" => 0,
	"!PROPERTY_AEN" => false,
	"!PROPERTY_WBPRICE" => false,
	"!PROPERTY_WBARTICLE" => false,
	"!PROPERTY_BRAND" => false,
);
$cntAll = 0;
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_*"));
while($ob = $rs->GetNextElement()){
	$arFields = $ob->GetFields();
	$arProps = $ob->GetProperties();
	
	
	$arWB = array();
	if($arBrand[$arProps["BRAND"]["VALUE"]]){
		$arWB["0"] = array(
			"type" => "Бренд",
			"params" => array(array(
				"value" => $arBrand[$arProps["BRAND"]["VALUE"]]
			)),
		);
	}
//prent($arWB);die;
	
	$arWB["2"] = array(
		"type" => "Комплектация", 
		"params" => array(array(
			"value" => "Коробка, инструкция, часы, гарантия"
		)),
	);
	$arWB["3"] = array(
		"type" => "Тнвэд",
		"params" => array(array(
			"value" => "9102290000"
		)),
	);

	if($arLink["TYPE"][$arProps["TYPE"]["VALUE"][0]]){
		$arWB["4"] = array(
			"type" => "Пол",
			"params" => array(array(
				"value" => $arLink["TYPE"][$arProps["TYPE"]["VALUE"][0]]
			)),
		);
		
		$params = array();
		foreach($arProps["TYPE"]["VALUE"] as $k => $value){
			if($value == "Мужские"){
				$params[] = array("value" => "подарок мужу");
				$params[] = array("value" => "подарок парню");
				$params[] = array("value" => "подарок сыну");
			}
			if($value == "Женские"){
				$params[] = array("value" => "подарок для нее подарок маме");
				$params[] = array("value" => "подарок девушке");
				$params[] = array("value" => "подарок дочке");
			}
			if($value == "Детские"){
				$params[] = array("value" => "ребенку");
				$params[] = array("value" => "мальчику");
				$params[] = array("value" => "девочке");
			}
		}
		if(count($params) > 0){
			$params = array_slice($params, 0, 3);
			$arWB["30"] = array(
				"type" => "Назначение подарка",
				"params" => $params,
			);
		}

	}
	
	$arWB["8"] = array(
		"type" => "Глубина упаковки",
		"params" => array(array(
			"count" => (float) 15,
			"units" => "см",
		)),
	);
	
	
	if($arProps["THICKNESS"]["VALUE"]){
		$arWB["9"] = array(
			"type" => "Толщина корпуса",
			"params" => array(array(
				"count" => (float)$arProps["THICKNESS"]["VALUE"],
				"units" => "мм",
			)),
		);
	}


	if($arLink["WARRANTY"][$arProps["WARRANTY"]["VALUE"]]){
		$arWB["12"] = array(
			"type" => "Гарантийный срок",
			"params" => array(array(
				"value" => $arLink["WARRANTY"][$arProps["WARRANTY"]["VALUE"]]
			)),
		);
	}
	

	if($arProps["DIAMETER"]["VALUE"]){
		$arWB["13"] = array(
			"type" => "Диаметр корпуса",
			"params" => array(array(
				"count" => (float) ($arProps["DIAMETER"]["VALUE"] / 10),
				"units" => "см",
			)),
		);
	}
	if($arLink["GLASS"][$arProps["GLASS"]["VALUE"]]){
		$arWB["16"] = array(
			"type" => "Вид стекла",
			"params" => array(array(
				"value" => $arLink["GLASS"][$arProps["GLASS"]["VALUE"]]
			)),
		);
	}
	if($arLink["FACE"][$arProps["FACE"]["VALUE"]]){
		
		if($arProps["FACE"]["VALUE"] == "Аналогово-цифровой"){
			$arWB["17"] = array(
				"type" => "Циферблат часов",
				"params" => array(array("value" => "аналоговый"), array("value" => "цифровой")),
			);
		}else{
			$arWB["17"] = array(
				"type" => "Циферблат часов",
				"params" => array(array(
					"value" => $arLink["FACE"][$arProps["FACE"]["VALUE"]]
				)),
			);
		}

	}
	
	//мн
	
	if(count($arProps["FEATURES"]["VALUE"]) > 0){
		$params = array();
		foreach($arProps["FEATURES"]["VALUE"] as $k => $value){
			if($arLink["FEATURES"][$value]){
				$params[] = array("value" => $arLink["FEATURES"][$value]);
			}
		}
		if(count($params) > 0){
			$params = array_slice($params, 0, 3);
			$arWB["19"] = array(
				"type" => "Особенности часов",
				"params" => $params,
			);
		}
	}
	if($arLink["MECHANISM"][$arProps["MECHANISM"]["VALUE"]]){
		$arWB["20"] = array(
			"type" => "Механизм часов",
			"params" => array(array(
				"value" => $arLink["MECHANISM"][$arProps["MECHANISM"]["VALUE"]]
			)),
		);
	}
	


	//мн
	
	if(count($arProps["POPULAR_TAG"]["VALUE"]) > 0){
		$params = array();
		foreach($arProps["POPULAR_TAG"]["VALUE"] as $value){
			if($arLink["POPULAR_TAG"][$value]){
				$params[] = array("value" => $arLink["POPULAR_TAG"][$value]);
			}
		}
		if(count($params) > 0){
			$params = array_slice($params, 0, 3);
			$arWB["22"] = array(
				"type" => "Стиль часов",
				"params" => $params,
			);
		}
	}
	if($arLink["WR"][$arProps["WR"]["VALUE"]]){
		$arWB["23"] = array(
			"type" => "Класс водонепроницаемости",
			"params" => array(array(
				"value" => $arLink["WR"][$arProps["WR"]["VALUE"]]
			)),
		);
	}
	
	
	


	//мн
	if(count($arProps["dial_color"]["VALUE"]) > 0){
		$params = array();
		foreach($arProps["dial_color"]["VALUE"] as $value){
			if($arLink["dial_color"][$value]){
				$params[] = array("value" => $arLink["dial_color"][$value]);
			}
		}
		if(count($params) > 0){
			$arWB["26"] = array(
				"type" => "Цвет циферблата",
				"params" => $params,
			);
		}
	}
	//мн
	
	if(count($arProps["MATERIAL"]["VALUE"]) > 0){
		$params = array();
		foreach($arProps["MATERIAL"]["VALUE"] as $value){
			if($arLink["MATERIAL"][$value]){
				$params[] = array("value" => $arLink["MATERIAL"][$value]);
			}
		}
		
		if(count($params) > 0){
			$arWB["27"] = array(
				"type" => "Материал браслета",
				"params" => $params,
			);
		}
	}



	/*
	if($arLink["FINALCOUNTRY"][$arProps["FINALCOUNTRY"]["VALUE"]]){
		$arWB["26"] = array(
			"type" => "Страна производства",
			"params" => array(
				"value" => $arLink["FINALCOUNTRY"][$arProps["FINALCOUNTRY"]["VALUE"]]
			),
		);
	}
	Финальная сборка	Страна производства
	*/

	


	$arWB["29"] = array(
		"type" => "Повод",
		"params" => array(
			array("value" => "новый год"),
			array("value" => "просто так"),
			array("value" => "для себя"),
			//array("value" => array("день рождения")), 
		),
	);

	
	$arWB["object"] = "Часы наручные";
//{"0":{"type":"Бренд","params":[{"value":"CASIO"}]}
//prent(json_encode($arWB, JSON_UNESCAPED_UNICODE));
	CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("PROP_MAXYSS_WB" => json_encode($arWB, JSON_UNESCAPED_UNICODE)));
	$cntAll++;
}
echo $cntAll;
die;
//CAgent::CheckAgents();die;
$orderId = 122811;

$order = \Bitrix\Sale\Order::load($orderId);
$deliveryIds = $order->getDeliverySystemId();
prent($deliveryIds);
die;
$number = $order->getField('ACCOUNT_NUMBER');
$basket = $order->getBasket();
$price = $basket->getPrice();
$flgSend = true;

$price = 2;
$number = "M2C123213";
if(strripos($number, "MC") !== false || $price <= 0){
	//найдена строка
	$flgSend = false;prent($price);
}

die;
$products = array(
    array('PRODUCT_ID' => 999, 'NAME' => 'Товар 1', 'PRICE' => 500, 'CURRENCY' => 'RUB', 'QUANTITY' => 1)
            );
$basket = Bitrix\Sale\Basket::create(SITE_ID);

foreach ($products as $product)
    {
        $item = $basket->createItem("catalog", $product["PRODUCT_ID"]);
        unset($product["PRODUCT_ID"]);
        $item->setFields($product);
    }
	
$order = Bitrix\Sale\Order::create("s2", 1);
$order->setPersonTypeId(1);
$order->setBasket($basket);

$order->setField('ACCOUNT_NUMBER', "9999877");
$order->setField('STATUS_ID', "FB");

$result = $order->save();

die;




$lines = file('/home/bitrix/stek_without_goods.txt');
$i = $sum = 0;
// Осуществим проход массива и выведем содержимое в виде HTML-кода вместе с номерами строк.
foreach ($lines as $line_num => $line) {
	if(strlen($line) > 0){
		$sum += $line;
		$i++;
	}
	
}
echo $i;
prent($sum / $i);
die;
$rsEvents = GetModuleEvents("sale", "onSaleDeliveryHandlersBuildList");
while ($arEvent = $rsEvents->Fetch())
{
    prent($arEvent);
}
		
		die;
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$optionsPayStatuses = array_flip(RetailcrmConfigProvider::getPaymentStatuses());


prent($optionsPayStatuses);
die; 

$strSql = "SELECT * FROM ci_sync_ms WHERE ORDER_ID_CRM IS NOT NULL AND CANCELED = 'N'";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["SYNC_MS"][$row["ORDER_NUMBER"]] = $row;
}
prent($arResult["SYNC_MS"]);die;
$obj = new CExchange("s2");
$resRetail = $obj->getRetailSalesReturn();
//prent($res);
foreach($resRetail as $key => &$arItem){
	$demand = $arItem["demand"]["meta"]["href"];
	if($demand){
		$resDemand = $obj->getDataByUrlMS($demand);
		//prent($resDemand);
		$arItem["DEMAND_ID"] = "MS" . $resDemand["name"];
		if(!$arResult["SYNC_MS"]["MS" . $resDemand["name"]]) unset($resRetail[$key]);
	}else{
		unset($resRetail[$key]);
	}
	//
}
unset($arItem);

foreach($resRetail as $key => $arItem){
	$arDemand = $arResult["SYNC_MS"][$arItem["DEMAND_ID"]];
	prent($arDemand);
	$ar = array(
		"status" => "noorder",
		"privilegeType" => "none",
		"id" => $arDemand["ORDER_ID_CRM"],
	);
	$resCrm = $api->ordersEdit($ar, "id", "tempus-by");
	$resCrm = objectToArray($resCrm);
	
	if($resCrm["response"]["success"] && $resCrm["response"]["id"] > 0){prent($resCrm); 
		//$DB->Update("ci_sync_ms", array("CANCELED" => "Y"), "WHERE ID = '".$arDemand["ID"]."'", $err_mess.__LINE__);
	}else{
		$arLog = array(
			"event" => "ER",
			"text" => "Ошибка при отмене заказа из МС в CRM",
			"detail" => serialize(array("ORDER" => $arItem, "RESPONSE_CRM" => $resCrm)),
		);
		CLog::add2log($arLog);
	}
}


die;


/*
$arResult = array();
$arFilter = Array(
	"IBLOCK_ID"	=> CProSet::IB_SUBSCRIBE_PRODUCT,
	"ACTIVE"	=> "Y",
	"!PROPERTY_EMAIL" => false,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_EMAIL", "PROPERTY_SITE_ID", "PROPERTY_PRODUCT_ID", "PROPERTY_CNT_ATTEMPTS"));
while($ar = $rs->GetNext()){
	if(in_array($ar['PROPERTY_SITE_ID_VALUE'], array("s1", "s2"))){
		
		$type_price = ($ar['PROPERTY_SITE_ID_VALUE'] == "s1" ? 1 : 2);
		$arFilterEl = Array(
			"IBLOCK_ID"	=> CProSet::IB_CATALOG,
			"ACTIVE"	=> "Y",
			"ID"		=> $ar["PROPERTY_PRODUCT_ID_VALUE"],
			">CATALOG_QUANTITY" => 0,
			">CATALOG_PRICE_" . $type_price => 0,
		);

		$rsEl = CIBlockElement::GetList(array(), $arFilterEl, false, false, array("ID", "NAME", "IBLOCK_ID", "DETAIL_PAGE_URL", "PROPERTY_AVAILABILITY_BY", "PROPERTY_AVAILABILITY_RU"));
		if($arFields = $rsEl->GetNext()){
			$arResult["ITEMS"][] = array(
				"ID" => $ar["ID"],
				"PRODUCT_ID" => $arFields["ID"],
				"DETAIL_PAGE_URL" => $arFields["DETAIL_PAGE_URL"],
				"NAME" => $arFields["NAME"],
				"SITE_ID" => $ar["PROPERTY_SITE_ID_VALUE"],
				"EMAIL" => $ar["PROPERTY_EMAIL_VALUE"],
				"CNT_ATTEMPTS" => $ar["PROPERTY_CNT_ATTEMPTS_VALUE"],
				"AVAILABILITY_BY" => $arFields["PROPERTY_AVAILABILITY_BY_ENUM_ID"],
				"AVAILABILITY_RU" => $arFields["PROPERTY_AVAILABILITY_RU_ENUM_ID"],
			);

		}
	}
}

prent($arResult["ITEMS"]);
die;*/



 
 /*
 			$ar = array(
				"phoneNumber" => 295422025,
				"customer" => array(
					"id" => 88974,
				),
			);
			$resCustomer = $api->loyaltyCreate($ar);
			$resCustomer = objectToArray($resCustomer);
			
 
prent($resCustomer);
			die;*/
$ar = array(
	"privilegeType" => "loyalty_level",
	"id" => 121686,
);
$res = $api->ordersEdit($ar, "id", "tempus-by");
	$res = objectToArray($res);
prent($res);	
	
die;
/*
						
						$dateFrom = getNextDay(date("d.m.Y"), 1);
						$objDateFrom = new Bitrix\Main\Type\DateTime($dateFrom, "d-m-Y");
						prent($objDateFrom);
						die;*/
						
/*
						
$strSql = "SELECT * FROM ci_sync_ms";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["SYNC_MS"][$row["ORDER_NUMBER"]] = $row;
}

$obj = new CExchange("s2");
$res = $obj->getRetailSalesReturn();

foreach($res as $key => $arItem){
	if(!$arResult["SYNC_MS"]["MS" . $arItem["name"]]) unset($res[$key]);
}
prent($res);

die;*/
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$strSql = "SELECT ID,XML_ID FROM b_iblock_element WHERE IBLOCK_ID = '16'";
$arElementIDs = array();
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arElementIDs[$row["XML_ID"]] = $row["ID"];
}

prent($arElementIDs);die;

$strSql = "SELECT val.VALUE as PHONE, pr.USER_ID as USER_ID 
	FROM 
		b_sale_order_props_value val 
	LEFT JOIN 
		b_sale_order pr 
	ON val.ORDER_ID=pr.ID 
	WHERE 
		val.ORDER_PROPS_ID = '3'";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if(strlen($row["PHONE"]) >= 9){
		$phone = preg_replace("/[^0-9]/", '', $row["PHONE"]);
		$phone = substr($phone, -9); 
		$arResult["CIENTS"][$phone] = $row["USER_ID"];
	}
}


$strSql = "SELECT * FROM ci_sync_ms";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["SYNC_MS"][$row["ORDER_NUMBER"]] = $row["ORDER_NUMBER"];
}
//
//die;

$obj = new CExchange("s2");
$res = $obj->getRetailDemandAll();


//проходим по всем продажам
foreach($res as $key => &$arItem){
	if($arItem["customerOrder"]) unset($res[$key]);
}

unset($arItem);
//prent($res);die;
//проходим по всем продажам
foreach($res as $key => &$arItem){
	//получаем позиции продаж и товары
	if($arItem["id"] != "3f500e08-f69d-11eb-0a80-0499000dfda9"){
	//	continue;
	}
	
	$id = array_pop(explode("/", $arItem["agent"]["meta"]["href"]));
	
	//if($id == "6f7995ba-180c-11ea-0a80-00b30004eb01") continue;//если розничный покупатель
	
	$arItem["CLIENT"] = $obj->getAgentMS($id);
	
	$number = "MS" . $arItem["name"];
	if($arResult["SYNC_MS"][$number]) continue;
	
	$rsPos = $obj->getRetailPositions($arItem["id"]);
	
	$rsItems = array();
	foreach($rsPos as $k => $arPos){
		$id = array_pop(explode("/", $arPos["assortment"]["meta"]["href"]));
		//prent($id);
		
		$arItem["BASKET"][$k] = $arPos;
		$arItem["BASKET"][$k]["PRODUCT"] = $obj->getProductMS($id);
	}
	
}
unset($arItem);
prent($res);die;
//prent($res);   
$arOrder = array();
foreach($res as $key => $arItem){
	if($arItem["BASKET"] && $arItem["CLIENT"]){
		$phone = preg_replace("/[^0-9]/", '', $arItem["CLIENT"]["phone"]);
		$phone = substr($phone, -9);
		$arName = explode(" ", $arItem["CLIENT"]["name"]);
        $order = array(
            'number'          => "MS" . $arItem["name"],
            'orderType'       => "eshop-individual",
            'status'          => 'new',
            'customerComment' => '',
            'managerComment'  => '',
            "lastName" => $arName[0],
            "firstName" => $arName[1],
            "patronymic" => $arName[2],
            "email" => $arItem["CLIENT"]["email"],
            "phone" => $arItem["CLIENT"]["phone"],
			//'privilegeType'  => 'loyalty_level',
			"payments" => array(
				Array(
					"type" => "cash",
					"status" => "not-paid",
					"amount" => $arItem["cashSum"] / 100,
					//'externalId' => RCrmActions::generatePaymentExternalId(1),
				),

			),
        );
		if($arResult["CIENTS"][$phone]){
			$order["customer"] = array('externalId' => $arResult["CIENTS"][$phone]);
			$order["privilegeType"] = "loyalty_level";
		}	
		$basket = array();
		foreach($arItem["BASKET"] as $k => $arBasket){
			$basket[] = array(
				"quantity" => $arBasket["quantity"],
				"offer" => array(
					"externalId" => $arBasket["PRODUCT"]["externalCode"],
				),
				"productName" => $arBasket["PRODUCT"]["name"],
				//"discountManualPercent" =>  $arBasket["name"],
				"discountManualAmount" => ($arBasket["discount"] > 0 ? $arBasket["discount"] : 0),
				"initialPrice" => $arBasket["price"] / 100,
				"vatRate" => ($arBasket["vat"] > 0 ? $arBasket["vat"] : "none"),
			);
		}
		$order["items"] = $basket;
		//["cashSum"] / 100.
		$arOrder[] = $order;
		
	}
}

$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());
foreach($arOrder as $key => $arItem){
	//if($key > 0) continue;
	//prent($arItem); 
	$res = $api->ordersCreate($arItem, "tempus-by");
	$res = objectToArray($res);
	//prent($res);
	
	if($res["response"]["success"] && $res["response"]["id"] > 0){
		$in = array(
			"ORDER_NUMBER"	=> "'".addslashes($arItem["number"])."'",
			"ORDER_ID_CRM"	=> "'".addslashes($res["response"]["id"])."'",
		);
		$DB->Insert("ci_sync_ms", $in, $err_mess.__LINE__);
	}else{
		$arError = array(
			"event" => "ER",
			"text" => "Ошибка создания заказа из МС в CRM",
			"detail" => serialize(array("ORDER" => $arItem,"ERROR_CRM" => $res)),
		);
		CLog::add2log($arError);
	}
}
//die;
//prent($arResult["CIENTS"]); 
prent($arOrder); 

//prent($res);
die;

RCrmActions::orderAgent();

die;
$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objBrand = new CPanelBrand;




die;

$debugStart = debug_microtime_float();


$deliveryService = getDeliveryObjectById(1);

$end = debug_microtime_float() - $debugStart;
prent($deliveryService);

//prent($deliveryService);
die;

$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());



$response = $api->customersList(array("sites" => array("tempus-by")), 1, 100);
$res = objectToArray($response);
prent($res);die;



$arNoGroup = array("295422025","293449966","333549966");


$filename = $_SERVER['DOCUMENT_ROOT'] . "/dev/clients.csv";
$handle = fopen($filename, "r");
	
$array_line_full = array();
while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
	$arCsv[] = $line;
}
//prent($arCsv);
fclose($handle); //Закрываем файл
$arClient = array();
foreach($arCsv as $key => $arItem){
	if(count($arItem) == 2){
		if(!$arClient[$arItem[1]]){
			$arClient[$arItem[1]] = array(
				"ID" => $arItem[0],
				"PHONE" => $arItem[1],
			);
		}else{
			$arClient[$arItem[1]]["COMBINE"][] = $arItem[0];
		}
	}
}

foreach($arClient as $key => $arItem){
	if(count($arItem["COMBINE"]) > 0 && !in_array($key, $arNoGroup)){
		$resultCustomer = array("id" => $arItem["ID"]);
		$customers = array();
		foreach($arItem["COMBINE"] as $k => $v){
			if($arItem["ID"] != $v)
				$customers[] = array("id" => $v);
		}
		
		if(count($customers) > 0){
			//$response = $api->customersCombine($customers, $resultCustomer);
			//$res = objectToArray($response);
			//prent($resultCustomer);prent($customers);
		}




		//
		
		//prent($customers);
	}
}
//prent($arClient);
die;


/*
$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$ar = array(

		"phoneNumber" => "111000333",
		"customer" => array(
			"id" => 211,
			"externalId" => "211",
		),

);
//$response = $api->loyaltyCreate($ar);
*/

$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$arResult["ITEMS"] = array();

$response = $api->customersList(array("sites" => array("tempus-by")), 1, 100);
$res = objectToArray($response);
		
		foreach($res["response"]["customers"] as $key => $arItem){
			foreach($arItem["phones"] as $k => $v){
				$phone = preg_replace("/[^0-9]/", '', $v["number"]);
				$phone = substr($phone, -9);
				if(strlen($phone) > 0){
					$arResult["ITEMS"][] = array(
						"id" => $arItem["id"],
						"phone" => $phone,
					);	
				}
				
			}
		}
		
		$totalPage = $res["response"]["pagination"]["totalPageCount"];
		
	$send = true;

//do {
	for($i = 2; $i <= $totalPage; $i++){
		if($i > 3) {$send = false;break;}
			//prent($i);
		$response = $api->customersList(array("sites" => array("tempus-by")), $totalPage, 100);
		$res = objectToArray($response);
			
		if($res["statusCode"] == 200){
			foreach($res["response"]["customers"] as $key => $arItem){
				foreach($arItem["phones"] as $k => $v){
					//prent($v);
					$phone = preg_replace("/[^0-9]/", '', $v["number"]);
					$phone = substr($phone, -9);
					if(strlen($phone) > 0){
						$arResult["ITEMS"][] = array(
							"id" => $arItem["id"],
							"phone" => $phone,
						);	
					}
				}

			}
		}else{
			$send = false;
		}
	}
		
//} while ($send == true);
$filepath = $_SERVER["DOCUMENT_ROOT"] . "/dev/clients.csv";
foreach($arResult["ITEMS"] as $key => $arItem){
	$ar[0] = '"'.$arItem["id"].'"';
	$ar[1] = '"'.$arItem["phone"].'"';
	$str_csv = implode(";", $ar) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}
prent($arResult["ITEMS"]);
///loyalty/accounts


die;



$ar = array(
	"resultCustomer" => array("id" => 20939),
	"customers" => array(
		"id" => 9830,
	),

);
$customers = array(array("id" => 9830));
$resultCustomer = array("id" => 20939);
$response = $api->customersCombine($customers, $resultCustomer);
$res = objectToArray($response);
prent($res);
die;



$arNoGroup = array("295422025","293449966","333549966");


$filename = $_SERVER['DOCUMENT_ROOT'] . "/dev/customer-19-07-21.09-44_aa9dbf.csv";
$handle = fopen($filename, "r");
	
$array_line_full = array();
while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
	$arCsv[] = $line;
}
fclose($handle); //Закрываем файл
$arClient = array();
foreach($arCsv as $key => $arItem){
	if(count($arItem) == 3){
		if(!$arClient[$arItem[2]]){
			$arClient[$arItem[2]] = array(
				"ID" => $arItem[1],
				"NAME" => iconv("WINDOWS-1251","UTF-8",$arItem[0]),
				"PHONE" => $arItem[2],
			);
		}else{
			prent($arItem);
		}
	}
}
//RCrmActions::orderAgent();
prent($arClient);
die;

$ar["PRICES"] = Array(
	"2" => Array(
		"ID" => 19041,
		"PRODUCT_ID" => 1000,
		"CATALOG_GROUP_ID" => 2,
		"PRICE" => 743.00,
		"CURRENCY" => "BYN",
	)
);
$calculatePrice = CCatalogProduct::GetOptimalPrice(
						85736,
						1,
						array(2),
						'N',
						$ar["PRICES"],
						"s2",
						array()
					);
//deleteOldBaskets();
prent($calculatePrice);
die;
$arFilter = array('IBLOCK_ID' => 16, 'ACTIVE' => "Y");
$rsSect = CIBlockSection::GetList(array('LEFT_MARGIN' => 'ASC'),$arFilter);
while ($arSect = $rsSect->GetNext()){
	if($arSect["XML_ID"] > 0)
		$arXML_IDs[] = $arSect["XML_ID"];
	else{
		$xml_id = rand(10000,100000);
		
		if(!in_array($xml_id, $arXML_IDs)){
			$arFields = Array("XML_ID" => $xml_id);
			//$res = $bs->Update($arSect["ID"], $arFields);
		}
	}
}
$ar = array();
foreach($arXML_IDs as $k => $xml){
	if(!$ar[$xml]) $ar[$xml] = $xml; else prent($xml);
}
prent($arXML_IDs);
die;
$arCode = array("WR","FACE","CASE","COLOR","MATERIAL");
$properties = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID" => 16));
$arResult = array();
while ($prop_fields = $properties->GetNext()){
	if(in_array($prop_fields["CODE"], $arCode)){
		//если список смотрим возможные значения
		$arResult["PROP"][$prop_fields["CODE"]] = $prop_fields;
		if($prop_fields["PROPERTY_TYPE"] == "L"){
			$db_enum_list = CIBlockProperty::GetPropertyEnum($prop_fields["CODE"], Array());
			while($ar_enum_list = $db_enum_list->GetNext()){
			//	prent($ar_enum_list);
				$arResult["PROP"][$prop_fields["CODE"]]["VARIANTS"][] = $ar_enum_list;
				//$db_important_news = CIBlockElement::GetList(Array(), Array("IBLOCK_ID"=>$BID, "PROPERTY"=>array("IMPORTANT_NEWS"=>$ar_enum_list["ID"])));
			}
		}
		
	}
	
}
prent($arResult["PROP"]);

die;
$orderID = 109627;
	$objCourier = new CCourier();
	
	$arFilter = array(
		"ids" => array($orderID),
//		"extendedStatus" => array("delivering"),
//		"couriers" => array($objCourier->courierID),
	);

	$res = $objCourier->getOrderCrm($arFilter);
	prent($res);
	die;
//GROUP BY PRODUCT_ID 


$obj = new CExchange("s1");
$objService = new OrderService;
	$arFilter = array(
		//"BASKET_PRODUCT_ID" => $arIDs,
		"@STATUS_ID" => array("N","TA","CO","SE","SB"),
		"!CANCELED" => "Y",
	);

	$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	prent($arOrder);
	
die;



$strSql = "SELECT PRODUCT_ID, TIMESTAMP_X FROM b_catalog_price WHERE PRODUCT_ID='6346' ORDER BY TIMESTAMP_X asc";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
while($row = $results->Fetch()){
	
	$price_time = strtotime($row["TIMESTAMP_X"]);
	$diff_day = intval((time() - $price_time) / (60*60*24));
	//$diff = (time() - $price_time);
	//prent($diff_day);
	//если больше полугода, то в массив для деактивации
	if($diff_day > 180){
		$arIDs[$row["PRODUCT_ID"]] = $row["PRODUCT_ID"];
	}else{
		unset($arIDs[$row["PRODUCT_ID"]]);
	}
	
	//prent($diff);
	//prent(time());
}
	
prent($arIDs);
	
	
	die;
	$arFilter = Array(
	   "PROPERTY_VAL_BY_CODE_ONLINER_ORDER_KEY" => "6m5k363",
	);

	$db_sales = CSaleOrder::GetList(array("DATE_INSERT" => "ASC"), $arFilter);
	if($ar_sales = $db_sales->Fetch()){
		prent($ar_sales);
	}
	
	
	die;
//Если в артикуле 14 символов, то нужно поставить точки после 4, 7, 9,12 символов. Если 9 символов, то после 3,4, 7 символов
$article = "IN2705vsvB";
prent($article);
$pattern = '/^(.{6})B$/i';

$replacement = '$1';
$asd = preg_replace($pattern, $replacement, $article);
prent($asd);

die;
$article = "T03112580";
prent($article);
$pattern = '/(.{3})(.{1})(.{3})/i';

$replacement = '$1.$2.$3.';
$asd = preg_replace($pattern, $replacement, $article);
prent($asd);
die;
$obj1 = new PWexchange();
$res1 = $obj1->getOrder();
prent($res1);

$obj1 = new CExchange("s1_order");
$res1 = $obj1->updateFromMoySkladOrder();


die;
/*

$article = "2416asd-1869991";

$pattern = '/(\w+)-(\w+)/i';
$pattern = '/(\\w+)-(\\w+)/i';

$replacement = '${1}/$2';
$asd = preg_replace($pattern, $replacement, $article);

prent($asd);
								
								
die;*/

die;
$url = "http://www.bestwatch.ru/inetmag/export_csv.phtml?mag=tempusshop&pass=kOMzTn&code=3040422770";
$file_name = "supplier86_" . rand(0, 9999999999) . ".csv";
$local_file = $_SERVER["DOCUMENT_ROOT"] . "/upload/pricelist_tmp/{$file_name}";

$supplier_id = 86;
$arSupp = $objSupplier->getDetail($supplier_id);
$arSupp["settings"] = json_decode( $arSupp["settings"], true );
$arSupp["settings_pricelist"] = json_decode( $arSupp["settings_pricelist"], true );
$arSupp["settings_pricelist_detail"] = json_decode( $arSupp["settings_pricelist_detail"], true );
	
$arBrandID = array_keys($arSupp["settings"]["brand"]);
file_put_contents($local_file, file_get_contents($url));
//prent($asd);

if(file_exists($local_file)){
		
	$arPost = array(
		"supplier" => $supplier_id,
		"brand" => $arBrandID
	);
	$result = $objPricelist->upload($local_file, $arPost, $arSupp);
}



die;
$n = date('Y-m-d', strtotime("+1 days"));
$n = date('Y-m-d');
prent($n);



die;
$asd = "";
if(!empty($asd)) prent("jr");
//

die;
//$obj3 = new CExchange("s3");
//$res3 = $obj3->updateFromMoySklad();/[0-9]{6}/i	
$str = "Часы Claude Bernard 83014 357R AIR, шт";
//$article = preg_replace('/[^-a-zA-Z0-9_\s]+/i', '', $str);

								preg_match('/[^-a-zA-Z0-9_\s]+/i', $str, $matches);
								//$matches = array_diff($matches, array(''));
								//$matches = array_unique($matches);
								
								
prent($article);prent($matches);
die;



$objBrand = new CPanelBrand;

		
		$format = "d-m-Y";
		$date = '10-05-2021';
		$day_delivery = 2;
	$holidays = array('12-05-2021');
	$i = $day_delivery;
	$nextBusinessDay = date($format, strtotime($date . ' +' . $i . ' Weekday'));
prent($nextBusinessDay);
	while (in_array($nextBusinessDay, $holidays)) {
		$i++;
		$nextBusinessDay = date($format, strtotime($date . ' +' . $i . ' Weekday'));
	}
	prent($nextBusinessDay);
	die;
/*		
$article = "AE-1200WHD-1A";
if(preg_match("/^(A\-|AE\-|AEQ\-|AMW\-|AQ\-|AW\-|B\-|CA\-|CPA\-|DB\-|DBC\-|DQ\-|F\-|HDA\-|HDC\-|HS\-|ID\-|LA\-|LQ\-|LRW\-|LTP\-|LTR\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MTD\-|MTP\-|MTS\-|MW\-|MWA\-|MWC\-|MWD\-|PQ\-|SDB\-|W\-|WS\-|WSC\-|WV)\w+\-\w+$/", $article))
	prent("asdasd");
	
if(preg_match("/^(A\-|AE\-|AEQ\-|AMW\-|AQ\-|AW\-|B\-|CA\-|CPA\-|DB\-|DBC\-|DQ\-|F\-|HDA\-|HDC\-|HS\-|ID\-|LA\-|LQ\-|LRW\-|LTP\-|LTR\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MTD\-|MTP\-|MTS\-|MW\-|MWA\-|MWC\-|MWD\-|PQ\-|SDB\-|W\-|WS\-|WSC\-|WV)\w+$/", $article))
	prent("ok");
				die;	*/
				//Casio
$str = "Ремешок для  GA-100BW-1 / GA-110BW-1 (белый хлястик) GA-110, GA-120,GA-300, GDF-100, GAC-100, G-8900 , GR-8900 (10508122)";
$article = preg_replace('~[^-a-zA-Z0-9_\s,\/()]+~', '', $str);
$article = trim(str_replace(array(" / ", " , ", ", ", "()", "( )", "  "), array("/", ",",",",""," "), $article));

preg_match("/\([A-Za-z0-9]+\)/i", $article, $matches);
$matches = array_diff($matches, array(''));
$matches = array_unique($matches);
$arArt = array();
if(count($matches) == 1 && strlen($matches[0]) > 0){
	$tmp = str_replace($matches[0], "", $article);
	$article = str_replace(array("(",")"), "", $matches[0]);
	if($ar = explode(",", $tmp)){
		foreach($ar as $k => $v){
			if($ar2 = explode("/", $v)){
				foreach($ar2 as $k2 => $v2){
					if($ar3 = explode(" ", $v2)){
						foreach($ar3 as $k3 => $v4){
							$arArt[] = $v4;
						}
					}else{
						$arArt[] = $v2;
					}
				}
			}else{
				$arArt[] = $v;
			}
		}
	}elseif($ar = explode("/", $tmp)){
		foreach($ar as $k => $v){
			$arArt[] = $v;
		}
	}
	$arArt = array_diff($arArt, array(''));
	$arArt = array_unique($arArt);
	//$ar
	prent($article);
	prent($arArt);
}

						
						
	
prent($article);

	
die;
		
$str = " для Casio HDD-600C-2 (10162540)";
if(preg_match("/РЕМЕШОК/", $str))
	prent("ok");
prent("s");
//if(preg_match("/^(A\-|AE\-|AEQ\-|AMW\-|AQ\-|AW\-|B\-|CA\-|CPA\-|DB\-|DBC\-|DQ\-|F\-|HDA\-|HDC\-|HS\-|ID\-|LA\-|LQ\-|LRW\-|LTP\-|LTR\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MTD\-|MTP\-|MTS\-|MW\-|MWA\-|MWC\-|MWD\-|PQ\-|SDB\-|W\-|WS\-|WSC\-|WV)\w+$/", $str))
//	prent("ok");

/*
A\-|AE|AEQ|AMW|AQ|AW|B|CA|CPA|DB|DBC|DQ|F|HDA|HDC|HS|ID|LA|LQ|LRW|LTP|LTR|LW|LWA|LWS|LX|MCW|MQ|MRW|MTD|MTP|MTS|MW|MWA|MWC|MWD|PQ|SDB|W|WS|WSC|WV|
*/
		die;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;

global $USER;

Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");

if (!class_exists('OnlinerCart_API')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_onliner_cart.php');
}

	$arFilter = Array(
	   "!PROPERTY_VAL_BY_CODE_ONLINER_ORDER_KEY" => false,
	);

	$db_sales = CSaleOrder::GetList(array("DATE_INSERT" => "DESC"), $arFilter);
	while($ar_sales = $db_sales->Fetch()){
		prent($ar_sales);
	}

?>

</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>