<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(3600);
//error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

set_time_limit(3600);

global $DB, $USER;
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class UpdateSaleItems{

	private $arStockIDs;
	private $ms;
	private $brands;
	private $sections;
	private $items;
	private $connection;

	function __construct(){
		global $DB;

		$this->connection = Application::getConnection();
		$this->db = $DB;

		$this->triggers = new TsTriggers();
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);

		$this->loadModules();
		$this->objService = new OrderService;

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
	}

	public function run(){
		$this->logger->log("LOG", "Запуск обработчика");
		$this->logger->StartDebugTime(__FUNCTION__);

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Обработчик занят");
			exit();
		}

		$this->workers->updateStatus("Y");

		// получаем XML_ID от 2 складов

		$cntTop1 = intval(CProSet::getOption("UPDATE_TOP_s1"));
		if($cntTop1 > 0){
			$this->getStock("s1");
			$this->getStock("s1_2");
			if(count_($this->arStockIDs) > 0){
				$this->getExludeItems('s1');
				$this->prepareItems('s1');
				$this->updateBindingSection('s1');
				//$this->getItemsAdditional();
				//$this->setItems();
	//			$this->triggers->SetError(["Суперцена обновлена. " . count($this->items) . " товаров."]);
	//			$this->triggers->SendTriggerErrors();
			}else{
				$this->logger->log("LOG", "Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}");
				$this->triggers->SetError(["Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}"]);
				$this->triggers->SendTriggerErrors();
			}
			$this->arStockIDs = [];
		}

		$cntTop2 = intval(CProSet::getOption("UPDATE_TOP_s2"));
		if($cntTop2 > 0){
			$this->getStock("s2");
			if(count_($this->arStockIDs) > 0){
				$this->getExludeItems('s2');
				$this->prepareItems('s2');
				$this->updateBindingSection('s2');
				//$this->getItemsAdditional();
				//$this->setItems();
	//			$this->triggers->SetError(["Суперцена обновлена. " . count($this->items) . " товаров."]);
	//			$this->triggers->SendTriggerErrors();
			}else{
				$this->logger->log("LOG", "Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}");
				$this->triggers->SetError(["Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}"]);
				$this->triggers->SendTriggerErrors();
			}
		}

// 		if(count_($this->arStockIDs) > 0){
// 			$this->getExludeItems();
// 			$this->prepareItems();
//
// 			$this->updateBindingSection();
//
//
//
// 			//$this->getItemsAdditional();
//
// 			//$this->setItems();
//
// //			$this->triggers->SetError(["Суперцена обновлена. " . count($this->items) . " товаров."]);
// //			$this->triggers->SendTriggerErrors();
//
// 		}else{
// 			$this->logger->log("LOG", "Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}");
// 			$this->triggers->SetError(["Обновление Суперцена. от ни одного MS не получены товары. ТОП s1 - {$cntTop1}, ТОП s2 - {$cntTop2}"]);
// 			$this->triggers->SendTriggerErrors();
// 		}

		$this->workers->updateStatus("N");
		$this->logger->log("LOG", "Затраченное время: \r\n".print_r($this->logger->debugData, true));
		$this->logger->log("LOG", "Завершение работы");
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	public function getStock($site_id = "s1"){
		$this->logger->log("LOG", "Получаем сток {$site_id}");
		$this->logger->StartDebugTime(__FUNCTION__);
		$log_site_id = $site_id;
		$exch = new CExchange($site_id);
		if ( $site_id == "s1_2" ){
			$site_id = "s1";
		}
		if ($site_id == 's2'){
			$stockDays = 14;
		}else{
			$stockDays = 30;
		}
		$this->ms = new MoyskladAPI($site_id);
		$ar = $exch->msStore;
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/log.txt", print_r($ar, true).PHP_EOL, FILE_APPEND);
		foreach($ar as $stock){
			$this->ms->getStock(0, "stockMode=positiveOnly&filter=stockDaysFrom={$stockDays};store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$stock}");

			$this->logger->log("LOG", "{$site_id} stock - {$stock} . Получили - " . count($this->ms->MSPosition));
			if(count_($this->ms->MSPosition) > 0){
				foreach($this->ms->MSPosition as $key => $arItem){
					$this->arStockIDs[$arItem["XML_ID"]] = $arItem["XML_ID"];
					$this->arLog[$arItem["externalCode"]] .= " Москва stockDays - {$arItem["stockDays"]};";
				}
			}else{
				$this->logger->log("LOG", "Обновление Суперцена. {$log_site_id} от MS не получены товары");
				$this->triggers->SetError(["Обновление Суперцена. {$log_site_id} от MS не получены товары"]);
				$this->triggers->SendTriggerErrors();
			}

		}

		$this->logger->EndDebugTime(__FUNCTION__);

	}


	private function getExludeItems($site_id = 's1'){
		$obj = new CPanelPricelist;
		$price = $obj->getPriceByFilter(array("supplier_id" => "103"), "bitrix_id", array("model","bitrix_id",));

		if(is_array($price) && count($price) > 0){
			$arIDs = array_keys($price);

			$arFilter = Array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"=XML_ID" => $arIDs,
			);

			$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "XML_ID"));
			while($arFields = $result->GetNext()){
				$this->arExcludeItem[$arFields["XML_ID"]] = $arFields["XML_ID"];
			}
		}

		$price = $obj->getPriceByFilter(array("supplier_id" => "116"), "bitrix_id", array("model","bitrix_id",));

		if(is_array($price) && count($price) > 0){
			$arIDs = array_keys($price);

			$arFilter = Array(
				"IBLOCK_ID" => CProSet::IB_CATALOG,
				"=XML_ID" => $arIDs,
			);

			$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "XML_ID"));
			while($arFields = $result->GetNext()){
				$this->arExcludeItem[$arFields["XML_ID"]] = $arFields["XML_ID"];
			}
		}

		$this->logger->log("LOG", "Исключаем из массива склад 103 - 116 ", $this->arExcludeItem);
	}

	private function prepareItems($site_id = 's1'){
		$this->logger->StartDebugTime(__FUNCTION__);
		$this->logger->log("LOG", "До очистки массива - " . count($this->arStockIDs));

		$arProductBasket = array(); //массив кодов товаров на которые есть заказы. потом надо его исключить из массива от МС

		$arFilter = array(
			"BASKET_PRODUCT_XML_ID" => $this->arStockIDs,
			"@STATUS_ID" => array("N","TA","CO","SE","SB","CL"),
			"!CANCELED" => "Y",
		);

		$arOrder = $this->objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
		foreach($arOrder as $key => $arItem){
			foreach($arItem["BASKET"] as $k => $arBasket){
				if(in_array($arBasket["PRODUCT_XML_ID"], $this->arStockIDs)){
					$arProductBasket[$arBasket["PRODUCT_XML_ID"]] = $arBasket["PRODUCT_XML_ID"];
					$this->arLog[$arBasket["PRODUCT_XML_ID"]] .= " Есть в заказе - {$arItem["ORDER_ID"]};";
				}
			}
		}

		if(count_($arProductBasket) > 0){
			foreach($this->arStockIDs as $key => $ID){
				if(in_array($ID, $arProductBasket)){
					unset($this->arStockIDs[$ID]);
				}
			}
		}
		// Не добавлять модель в суперцену, если она есть на складе Склад 2


		foreach($this->arStockIDs as $key => $ID){
			if($this->arExcludeItem[$ID]){
				unset($this->arStockIDs[$ID]);
			}
		}

		$this->logger->log("LOG", "После очистки массива - " . count($this->arStockIDs));
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function updateBindingSection( $site_id = 's1'){
		if( $site_id == 's1' ){
			$section_id = 4541;
		}else{
			$section_id = 370;
		}
		$this->logger->log("LOG", "Обновляем привязки к разделам");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arXmlID = array();
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"=XML_ID" => $this->arStockIDs,

		);

		$topList = $this->getTopList();

		$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL"));
		while($arFields = $result->GetNext()){

			if(!$topList[$arFields['ID']]){
				$arXmlID[$arFields['XML_ID']] = $arFields['ID'];

			}else{
				$this->arLog[$arFields["XML_ID"]] .= " есть в ТОП листе;";
			}

			$arModels[$arFields['ID']] = array(
				"XML_ID" => $arFields['XML_ID'],
				"ARTICLE" => $arFields['PROPERTY_CML2_ARTICLE_VALUE'],
				"DETAIL_PAGE_URL" => $arFields['DETAIL_PAGE_URL'],
			);
		}

		//получаем новый список
		$arNew = array();
		$db_old_groups = CIBlockElement::GetElementGroups($arXmlID, true);
		while($ar_group = $db_old_groups->Fetch()){
			$arSection = getSectionsElement($ar_group["IBLOCK_ELEMENT_ID"]);
			if($arSection[0]["ID"] == 932){

				$arNew[$ar_group["IBLOCK_ELEMENT_ID"]]["ID"] = $ar_group["IBLOCK_ELEMENT_ID"];
				$arNew[$ar_group["IBLOCK_ELEMENT_ID"]]["SECTION_ID"][] = $ar_group["ID"];

			}
		}

		if(count_($arNew) <= 0){
			$this->logger->log("LOG", "Нет товаров для добавления");
			return false;
		}

		//получаем все товары из категории СУПЕРЦЕНА и деактивируем
		$arDeact = array();
		$arSelect = array("ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "DETAIL_PAGE_URL");
		$arFilter = array("IBLOCK_ID" => CProSet::IB_CATALOG, "SECTION_ID" => $section_id);
		$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while($ob = $res->GetNextElement()){
			$arFields = $ob->GetFields();
			$arDeact[] = $arFields["ID"];

			$arModels[$arFields['ID']] = array(
				"XML_ID" => $arFields['XML_ID'],
				"ARTICLE" => $arFields['PROPERTY_CML2_ARTICLE_VALUE'],
				"DETAIL_PAGE_URL" => $arFields['DETAIL_PAGE_URL'],
			);
		}

		$db_old_groups = CIBlockElement::GetElementGroups($arDeact, true);
		$arOld = array();
		while($ar_group = $db_old_groups->Fetch()){
			$arOld[$ar_group["IBLOCK_ELEMENT_ID"]]["ID"] = $ar_group["IBLOCK_ELEMENT_ID"];
			if($ar_group["ID"] != $section_id)
				$arOld[$ar_group["IBLOCK_ELEMENT_ID"]]["SECTION_ID"][] = $ar_group["ID"];
		}

		$cntAdd = $cntDelete = 0;

		$logSearch = "";
		//деактивируем
		foreach($arOld as $key => $arItem){
			if(count_($arItem["SECTION_ID"]) > 0){
				CIBlockElement::SetElementSection($arItem["ID"], $arItem["SECTION_ID"]);

				if(!$arNew[$arItem["ID"]]){
					$log = "<p>Удалена модель - <a href='https://tempus.by" . $arModels[$arItem["ID"]]["DETAIL_PAGE_URL"] . "' target='_blank'>" . $arModels[$arItem["ID"]]["ARTICLE"] . "</a>" . ($this->arLog[$arModels[$arItem["ID"]]["XML_ID"]] ? $this->arLog[$arModels[$arItem["ID"]]["XML_ID"]] : "") . "</p>";
					$html .= $log;
					$this->logger->log("LOG", $log);
					$cntDelete++;
					$logSearch .= $arModels[$arItem["ID"]]["ARTICLE"] . " ";
				}
			}

		}

		$arFilter = Array(
			"IBLOCK_ID"  => 16,
			"PROPERTY_BRAND"=> [182598,37278,125923,7974,7972,7975,37235,37280,119484,179977,43508]
		);
		$excludeCiga = [];
		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID"));
		while($ar = $rs->GetNext()){
			$excludeCiga[] = $ar['ID'];
		}

//		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/arlogtet.txt', print_r($excludeCiga, true). PHP_EOL, FILE_APPEND);


		$ar = array();
		//привязываем новый список к разделу
		foreach($arNew as $key => $arItem){
			$arSec = $arItem["SECTION_ID"];
			if (in_array($arItem["ID"], $excludeCiga)){
//				file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/arlogtet.txt', print_r($arItem, true). PHP_EOL,FILE_APPEND);
//				file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/arlogtet.txt', print_r($arModels[$arItem["ID"]], true). PHP_EOL, FILE_APPEND);
				continue;
			}
			if(!in_array($section_id, $arSec)) $arSec[] = $section_id;
			if(count_($arSec) > 0){
				CIBlockElement::SetElementSection($arItem["ID"], $arSec);

				if(!$arOld[$arItem["ID"]]){
					$log = "<p>Добавлена модель - <a href='https://tempus.by" . $arModels[$arItem["ID"]]["DETAIL_PAGE_URL"] . "' target='_blank'>" . $arModels[$arItem["ID"]]["ARTICLE"] . "</a>" . ($this->arLog[$arModels[$arItem["ID"]]["XML_ID"]] ? $this->arLog[$arModels[$arItem["ID"]]["XML_ID"]] : "") . "</p>";
					$html .= $log;
					$this->logger->log("LOG", $log);
					$cntAdd++;
					$logSearch .= $arModels[$arItem["ID"]]["ARTICLE"] . " ";
				}

				$ar[] = $arModels[$arItem["ID"]]["ARTICLE"];
			}
		}

		if($cntAdd > 0 || $cntDelete){
			clearSectionCache($section_id);
		}

		$this->logger->log("LOG", "Добавлено - " . $cntAdd);
		$this->logger->log("LOG", "Удалено - " . $cntDelete);

		if(count_($ar) > 0){
		/*
			$obj = new CPriceUpdate("RU");
			$obj->sendMail = false;
			$obj->setOptimalPrice($ar);

			$obj = new CPriceUpdate("BY");
			$obj->sendMail = false;
			$obj->setOptimalPrice($ar);*/

		}

		if(strlen($html) > 0){
			//if(date("H") == 20){
				$message = "<p>Обновление товаров на странице СУПЕРЦЕНА тест</p>";
				$message .= $html;
				$arFields = array(
					"EMAIL_TO" => "pravlutski@gmail.com",
					"SUBJECT" => "Обновление товаров на странице СУПЕРЦЕНА тест",
					"MESSAGE" => $message,
				);
//prent($message);
				//CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);
			//}

			CLog::add2log(array("event" => "S", "detail" => $html, "search" => $logSearch));

		}

		$this->logger->EndDebugTime(__FUNCTION__);

	}

	private function getTopList(){

		$strSql = "SELECT * FROM ci_top_models";
		$results = $this->connection->query($strSql);

		$ar = [];
        while ($row = $results->Fetch()){
            $ar[$row["bitrix_id"]] = $row;
        }
		return $ar;
	}

	public function getItems(){
		if(!$this->sections) return false;
		$this->logger->log("LOG", "Получаем отчет из MS");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arFilter = array(
			"momentFrom" => date("Y-m-d", strtotime("-1 year")),
			"momentTo" => date("Y-m-d"),
		);

		$res = $this->ms->getListProfitNew($arFilter);

		foreach($res as $key => $arItem){
			if(!$arItem["assortment"]["code"]) continue;
			$this->items[$arItem["assortment"]["code"]] = array(
				"NAME" => $arItem["assortment"]["name"],
				"XML_ID" => $arItem["assortment"]["code"],
				"ARTICLE" => $arItem["assortment"]["article"],
				"SELL_QUANTITY" => $arItem["sellQuantity"],
			);
		}

		$arXml = array_keys($this->items);

		$strSql = "SELECT el.ID as ELEMENT_ID, el.XML_ID as ELEMENT_XML_ID, psc.IBLOCK_SECTION_ID as SECTION_ID
			FROM
				b_iblock_element el
			LEFT JOIN
				b_iblock_section_element sc
			ON el.ID=sc.IBLOCK_ELEMENT_ID
			LEFT JOIN
				b_iblock_section psc
			ON sc.IBLOCK_SECTION_ID=psc.ID
			WHERE
				el.XML_ID IN ('".implode("','", $arXml)."') AND psc.IBLOCK_SECTION_ID IN ('".implode("','", array_keys($this->sections))."') AND el.IBLOCK_ID = '16'";

		$results = $this->connection->query($strSql);

        while ($row = $results->Fetch()){
			$this->items[$row["ELEMENT_XML_ID"]]["BX_ID"] = $row["ELEMENT_ID"];
			$this->items[$row["ELEMENT_XML_ID"]]["SECTION"][] = $row["SECTION_ID"];
			if($this->sectionMatchBrand[$row["SECTION_ID"]]){
				$this->items[$row["ELEMENT_XML_ID"]]["MIN_SELL_QUANTITY"] = $this->settings["brand"][$this->sectionMatchBrand[$row["SECTION_ID"]]];
			}
        }

		$this->logger->EndDebugTime(__FUNCTION__);

	}

	public function getItemsAdditional(){
		$this->logger->log("LOG", "Добавляем принудительно товары, если есть");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arArticle = explode("\r\n", $this->settings["additional"]);
		$arArticle = array_diff($arArticle, array(''));

		if(!$arArticle){
			$this->logger->log("LOG", "Нет доп артикулов");
			return;
		}

		$this->logger->log("LOG", "Дополнительные артикулы" . print_r($arArticle, true));

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"=PROPERTY_CML2_ARTICLE" => $arArticle,
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","XML_ID","NAME","PROPERTY_CML2_ARTICLE"));
		while($ar = $rs->GetNext()){
            $this->items[$ar["XML_ID"]] = array(
				"NAME" => $ar["NAME"],
				"XML_ID" => $ar["XML_ID"],
				"ARTICLE" => $ar["PROPERTY_CML2_ARTICLE_VALUE"],
				"SELL_QUANTITY" => 1,
				"BX_ID" => $ar["ID"],
				"MIN_SELL_QUANTITY" => 1,
			);

		}

		$this->settings["additional"] = $arSettings["additional"];
		$this->logger->EndDebugTime(__FUNCTION__);

	}


	private function clear(){
		$this->logger->StartDebugTime(__FUNCTION__);

		$this->db->Query("DELETE FROM ci_top_models WHERE site_id = '".$this->site_id."'", false, $err_mess.__LINE__);

		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function setItems(){
		$this->logger->StartDebugTime(__FUNCTION__);
		foreach($this->items as $key => $arItem){
			$in = array(
				"model" => "'".addslashes($arItem["ARTICLE"])."'",
				"site_id" => "'".$this->site_id."'",
				"bitrix_id" => "'".$arItem["BX_ID"]."'",
			);
			$this->db->Insert("ci_top_models", $in, $err_mess.__LINE__);
		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

}
(new UpdateSaleItems())->run();
//(new UpdateTopList("s2"))->run();
//(new UpdateTopList("s3"))->run();

?>
