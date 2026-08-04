#!/usr/bin/php81
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
if (function_exists('ini_set')) ini_set('memory_limit','1512M');

use Bitrix\Main\Loader;

class TopItemsWB{
	private $xmlID;
	private $article;
	private $items;
	public function __construct(){
		global $DB;
		$this->db = $DB;
		$this->loadModules();

		$this->ms = new MoyskladAPI("s1");
		$this->pricelist = new CPanelPricelist;

		$this->triggers = new TsTriggers();
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);

		$option = CProSet::getOption("WB_TOP_MIN_QUANTITY");
		//$this->minWbQuantity = ($option && !empty($option["value"]) ? intval($option["value"]) : 0);
        $option = CProSet::getOption("WB_TOP_MIN_QUANTITY");
        $this->minWbQuantity = (int)(is_array($option) ? $option["value"] : $option);

	}
	public function loadModules(){
		Loader::includeModule('main');
		Loader::includeModule('iblock');
		Loader::includeModule('panel.manager');
	}
	public function run(){
		$this->logger->log("LOG", "Запуск обработчика");
		$this->logger->StartDebugTime(__FUNCTION__);

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Не нужно обрабатывать");
			exit();
		}

		CProSet::setOption("UPDATE_TOP_wb", "0");
		$this->workers->updateStatus("Y");

		// список всех XML_ID
		$this->getXmlID();
		// список всех артикулов сайта
		$this->getArticles();
		// получаем отчет пиз MS
		$this->getReport();

		if(count_($this->items) > 0){
			//$this->clear();
			//$this->getItemsAdditional();
			$this->prepareItems();
			//добавляем в массив новинки. не старше 6 месяцев
			$this->addItemsNew();
			//добавляем в массив товары из свойства Наши предложения со значением Распродажа
			$this->addItemsSale();
			// добавляем в массив всё со склад Москва, если больше 2 остаток
			$this->addItemsSupplier();

			$this->setItems();

			//$this->triggers->SetError(["WB. ТОП лист обновлен. " . count($this->items) . " товаров."]);
			//$this->triggers->SendTriggerErrors();

			CProSet::setOption("UPDATE_TOP_wb", count($this->items));
		}else{
			$this->logger->log("LOG", "WB. ТОП не обновлен.");
			$this->triggers->SetError(["WB. ТОП не обновлен."]);
			$this->triggers->SendTriggerErrors();
		}
		$this->workers->updateStatus("N");
		$triggers = new TsTriggers();
		$triggers->SetError(["Обновление ТОП WB [". date('d.m.Y H:i:s')."]\n" . $this->messageDeb]);
		$triggers->SendTriggerErrors();

		$this->logger->EndDebugTime(__FUNCTION__);

        $this->logger->log("LOG", "Затраченное время: \r\n".print_r($this->logger->debugData, true));
		$this->logger->log("LOG", "Завершение работы");
	}

	private function getXmlID(){

		$this->logger->log("LOG", "Получаем все XML_ID");
		$this->logger->StartDebugTime(__FUNCTION__);

		$strSql = "SELECT ID, XML_ID FROM b_iblock_element WHERE IBLOCK_ID = '16'";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->xmlID[$row["XML_ID"]] = $row["ID"];
		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function getArticles(){
		$this->logger->log("LOG", "Получаем все артикулы сайта");
		$this->logger->StartDebugTime(__FUNCTION__);

		$strSql = "SELECT el.ID as ID, el.XML_ID as XML_ID, pr.PROPERTY_123 as ARTICLE
			FROM
				b_iblock_element el
			LEFT JOIN
				b_iblock_element_prop_s16 pr
			ON el.ID=pr.IBLOCK_ELEMENT_ID
			WHERE
				el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			if(strlen($row["ARTICLE"]) > 0)
				$this->article[$row["ARTICLE"]] = $row;
		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function getReport(){

		$this->logger->log("LOG", "Получаем Отчет из MS");
		$this->logger->StartDebugTime(__FUNCTION__);

		$arFilter = array(
			"momentFrom" => date("Y-m-d", strtotime("-6 month")),
			//"momentFrom" => date("Y-m-d", strtotime("-1 day")),
			"momentTo" => date("Y-m-d"),
		);

		$arPosition = $this->ms->getListProfitNew($arFilter);
		//print_r($this->minWbQuantity);
		foreach($arPosition as $key => $arItem){
			if($arItem["sellQuantity"] >= $this->minWbQuantity && $arItem["assortment"]["code"]){
				$this->items[$arItem["assortment"]["code"]] = array(
					"XML_ID" => $arItem["assortment"]["code"],
					"ARTICLE" => $arItem["assortment"]["article"],
					'SOURCE' => 'Отчет мой склад'
				);
			}
		}
		$this->messageDeb = 'Из мс получено - ' . count($arPosition) . " \n";
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/testprof.txt", print_r('###start###' ,true));
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/testprof.txt", print_r(date("d.m.Y H:i:s") ,true));
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/testprof.txt", print_r($arPosition ,true));


		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function prepareItems(){
		$this->logger->StartDebugTime(__FUNCTION__);
		$this->logger->log("LOG", "До поиска в BX - " . count($this->items));

		foreach($this->items as $key => &$arItem){
			if($this->xmlID[$arItem["XML_ID"]]){
				$arItem["BITRIX_ID"] = $this->xmlID[$arItem["XML_ID"]];
				$arItem["IS_MOYSKLAD"] = true;
			}elseif($this->article[$arItem["ARTICLE"]]){
				$arItem["BITRIX_ID"] = $this->article[$arItem["ARTICLE"]]["ID"];
				$arItem["IS_MOYSKLAD"] = true;
			}else{
				$this->logger->log("LOG", "Не найдет ID в битриксе" . print_r($arItem, true));
				unset($this->items[$key]);
			}
		}
		unset($arItem);

		$this->logger->log("LOG", "После поиска в BX - " . count($this->items));
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function addItemsNew(){
		$this->logger->StartDebugTime(__FUNCTION__);
		//добавляем в массив новинки. не старше 6 месяцев
		$from = date("d.m.Y H:i:s", strtotime("-6 month"));
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"ACTIVE"	=> "Y",
			">DATE_CREATE" => $from,
			"!PROPERTY_CML2_ARTICLE" => false,
			//"PROPERTY_AVAILABILITY_RU" => array(512),
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
		while($ar_fields = $rs->GetNext()){
			if(!$this->items[$ar_fields["XML_ID"]]){
				$this->items[$ar_fields["XML_ID"]] = array(
					"BITRIX_ID" => $ar_fields["ID"],
					"XML_ID" => $ar_fields["XML_ID"],
					"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
					'SOURCE' => 'Новика БТ'
				);
			}

		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function addItemsSale(){
		$this->logger->StartDebugTime(__FUNCTION__);
		//добавляем в массив товары из свойства Наши предложения со значением Распродажа
		$from = date("d.m.Y H:i:s", strtotime("-6 month"));
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"ACTIVE"	=> "Y",
			"PROPERTY_HIT" => array(164),//Распродажа
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
		while($ar_fields = $rs->GetNext()){
			if(!$this->items[$ar_fields["XML_ID"]]){
				$this->items[$ar_fields["XML_ID"]] = array(
					"BITRIX_ID" => $ar_fields["ID"],
					"XML_ID" => $ar_fields["XML_ID"],
					"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
					'SOURCE' => 'Расспродажа БТ'
				);
			}

		}
		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function addItemsSupplier(){
		$this->logger->StartDebugTime(__FUNCTION__);
		/*
		$arFilter = array(
			"supplier_id" => array(88, 41),
			"active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]]){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
				);
			}else{

			}
		}
		*/
		// добавляем в массив всё со склад Москва, если больше 2 остаток
		$arFilter = array(
			"supplier_id" => array(47),
			"active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]] && $this->checkCount($this->article[$arItem["model"]]) === true){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
					'SOURCE' => 'Остаток на наших складах (Москва)'
				);
			}
		}
		unset($price);
		unset($arFilter);
		unset($arItem);

		$arFilter = array(
			"supplier_id" => array(103),
			"active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]] && $this->checkCount($this->article[$arItem["model"]]) === true){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
					'SOURCE' => 'Остаток на наших складах (Хронос 2)'
				);
			}
		}
		unset($price);
		unset($arFilter);
		unset($arItem);

		$arFilter = array(
			"supplier_id" => array(116),
						  "active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]] && $this->checkCount($this->article[$arItem["model"]]) === true){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
					'SOURCE' => 'Остаток на наших складах (Хронос 1)'
				);
			}
		}
		unset($price);
		unset($arFilter);
		unset($arItem);

		$arFilter = array(
			"supplier_id" => array(129),
						  "active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]] && $this->checkCount($this->article[$arItem["model"]]) === true){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
					'SOURCE' => 'Остаток на наших складах (Москва 2)'
				);
			}
		}
		unset($price);
		unset($arFilter);
		unset($arItem);

		$arFilter = array(
			"supplier_id" => array(44),
						  "active" => "Y"
		);
		$price = $this->pricelist->getPriceByFilter($arFilter);

		foreach($price as $key => $arItem){
			if($this->article[$arItem["model"]] && !$this->items[$this->article[$arItem["model"]]["XML_ID"]] && $this->checkCount($this->article[$arItem["model"]]) === true){
				$this->items[$this->article[$arItem["model"]]["XML_ID"]] = array(
					"BITRIX_ID" => $this->article[$arItem["model"]]["ID"],
					"XML_ID" => $this->article[$arItem["model"]]["XML_ID"],
					"ARTICLE" => $this->article[$arItem["model"]]["ARTICLE"],
					'SOURCE' => 'Остаток на наших складах (Минск)'
				);
			}
		}
		unset($price);
		unset($arFilter);
		unset($arItem);

		$this->logger->EndDebugTime(__FUNCTION__);
	}

	private function checkCount($ID){

		if(!$ID) return false;

		$strSql = "SELECT * FROM ci_reserved WHERE PRODUCT_ID = '{$ID}'";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			if(($row["AVAILABLE_RU"] - $row["RESERVED"]) > 2) return true;
			if(($row["AVAILABLE_BY"] - $row["RESERVED"]) > 2) return true;
			if(($row["AVAILABLE_PL"] - $row["RESERVED"]) > 2) return true;
		}else{
			return true;
		}

		return false;
	}

	private function setItems(){
		$this->logger->StartDebugTime(__FUNCTION__);

		$this->db->Query("TRUNCATE TABLE ci_wb_top", false, $err_mess.__LINE__);
		$arFavorit = [];
		$this->messageDeb .= 'Всего измненено - ' . count($this->items) . " \n";
		foreach($this->items as $key => $arItem){
			if(!$arItem["BITRIX_ID"]) continue;
			$in = array(
				"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
				"article" => "'".addslashes($arItem["ARTICLE"])."'",
				"source" => "'".addslashes($arItem["SOURCE"])."'",
			);

			$this->db->Insert("ci_wb_top", $in, $err_mess.__LINE__);

			//if($arItem["IS_MOYSKLAD"] === true){
			//	$arFavorit[] = $arItem["BITRIX_ID"];
			//}
		}
		/*
		if(count_($arFavorit) > 0){

			//снимаем свойство у всех товаров и проставляем новые

			$rs = CIBlockElement::GetList(array(), array("PROPERTY_FAVORIT_ITEM" => 1125), false, false, array("ID", "NAME"));
			$arDeact = array();
			while($ar = $rs->GetNext()){
				$arDeact[] = $ar["ID"];
			}

			foreach($arDeact as $key => $product_id){
				CIBlockElement::SetPropertyValuesEx($product_id, false, array("FAVORIT_ITEM" => false));
			}

			$arFavorit = array_unique($arFavorit);

			foreach($arFavorit as $key => $product_id){
				CIBlockElement::SetPropertyValuesEx($product_id, false, array("FAVORIT_ITEM" => 1125));
			}
		}*/

		$this->logger->EndDebugTime(__FUNCTION__);
	}

}

(new TopItemsWB())->run();
