<?php
namespace Panel\Manager\Service;

class CatalogPriceService
{
    private $logger;
    
    public function __construct()
    {
		global $DB;
		$this->db = $DB;
		
        //$this->logger = new \TsLogger("/updatePrice/" . $this->marketCode . "/");
    }
    
	public static function updatePriceProps($arIDs = []) {
		global $DB;

		$transitDaysRU = json_decode(\CProSet::getOption("TRANSIT_DAYS_RU"), true);
		$transitDaysBY = json_decode(\CProSet::getOption("TRANSIT_DAYS_BY"), true);

		$arUpdate = [];

		$arBx = [];
		$modelsForExpress = \CPanelPricelist::setWarehouseIfPriceAllows();

		$arFilter = [
			"IBLOCK_ID" => 16,
			"!PROPERTY_123" => false,
		];

		if (is_array($arIDs) && count($arIDs) > 0) {
			$arFilter["ID"] = $arIDs;
		}

		$arSelect = [
			"ID", "CODE", "PROPERTY_123",
			"PROPERTY_3047", "PROPERTY_2813",
			"PROPERTY_2843", "PROPERTY_3110",
			"PROPERTY_267", "PROPERTY_282",

		];
		$rs = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

		while($ar = $rs->GetNext()){
			$arBx[$ar["ID"]] = [
				"ID" => $ar["ID"],
				"CODE" => $ar["CODE"],
				"ARTICLE" => $ar["PROPERTY_123_VALUE"],
				"EX_YA" => $ar["PROPERTY_3047_ENUM_ID"],
				"DELIVERY_DAY_RU" => $ar["PROPERTY_2813_VALUE"],
				"AVAILABILITY_BY" => $ar["PROPERTY_267_ENUM_ID"],
				"AVAILABILITY_RU" => $ar["PROPERTY_282_ENUM_ID"],
				"DELIVERY_JSON" => false,
			];
			//TYPEOFSKLAD приводим к виду как сохранять собираемся
			$tmp = [];
			foreach($ar["PROPERTY_2843_VALUE"] as $k => $v){
				$tmp[$k] = ["VALUE" => $v];
			}
			usort($tmp, function($a, $b) {
				return strcmp($a['VALUE'], $b['VALUE']);
			});

			$arBx[$ar["ID"]]["TYPEOFSKLAD"] = $tmp;

			if($ar["PROPERTY_3110_VALUE"]){
				$jsonData = stripslashes(html_entity_decode($ar["PROPERTY_3110_VALUE"]));
				$arBx[$ar["ID"]]["DELIVERY_JSON"] = json_decode($jsonData, true);
			}

		}

		if (!$arBx) return;

		$suppliers = [];

		$strSql = "SELECT * FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$working_week = ($tmp["working_week"] ? $tmp["working_week"] : []);

			$suppliers[$row["id"]] = [
				"day_delivery" => $tmp["day_delivery"],
				"type_sklad" => json_decode($row["settings_type_sklad"], true),
				"working_week" => $working_week,
				"working_time" => $tmp["working_time"],
				"location" => $tmp["location"],
			];
		}

		$arArticle = array_column($arBx, "ARTICLE");

		$arPrice = [];

		if (is_array($arArticle) && count($arArticle) > 1000) {
			$arArticle = [];
		}
		//$arArticle = ['A-130WE-7A'];
		// получаем мин цены
		$arPrice['s1'] = self::getMinPrices('RU', $arArticle);
		$arPrice['s2'] = self::getMinPrices('BY', $arArticle);
prent($arBx);prent($arPrice);
		/*
		пока хардкод
		store_id 1 - немига, 2 - новокуз
		*/
		/* Товарам, у которых есть конкурент Generalwatches установить срок доставки 50. */
		$arDelivery50 = array();
		$strSql = "SELECT bitrix_id FROM ci_yandex_price WHERE info='Generalwatches'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arDelivery50[$row["bitrix_id"]] = true;
		}
		$ar = [];
		$arItems = array();
		foreach($arBx as $id => $arItem){
			$arDelivery = [
				"s1" => false,
				"s2" => false,
			];
			$availability = [
				"s1" => [
					"in_store" => 0,
					"in_supplier" => 0,
				],
				"s2" => [
					"in_store" => 0,
					"in_supplier" => 0,
				],
			];
			if ($arPrice["s1"][$arItem["ARTICLE"]]) {
				$price = $arPrice["s1"][$arItem["ARTICLE"]][0];
				$supplier = $suppliers[$price["supplier_id"]] ?? false;

				$type_sklad = ($supplier["type_sklad"] ? $supplier["type_sklad"] : false);
				$day_delivery = $supplier["day_delivery"];
				$working_week = $supplier["working_week"];
				$working_time = $supplier["working_time"];

				$arDelivery["s1"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $supplier["location"],
					"transit_days" => ($supplier["location"] == "minsk" ? $transitDaysBY : []),
				];

				if($arDelivery50[$arItem["ID"]]){
					$day_delivery = 50;
				}

				if($arItem["DELIVERY_DAY_RU"] != $day_delivery){
					$arUpdate[$arItem["ID"]]["DELIVERY_DAY_RU"] = $day_delivery;
				}

				if (is_array($type_sklad)) {

					if ( in_array( $arItem['ARTICLE'], $modelsForExpress ) && !in_array('Express 7D', $type_sklad) ){
						$type_sklad[] = "Express 7D";
					}
					$arValues = [];
					foreach ($type_sklad as $key => $value) {
						$arValues[] = ['VALUE' => $value];
					}

					usort($arValues, function($a, $b) {
						return strcmp($a['VALUE'], $b['VALUE']);
					});

					if(md5(serialize($arValues)) != md5(serialize($arItem["TYPEOFSKLAD"])))
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = $arValues;
				}else{
					if($arItem["TYPEOFSKLAD"]){
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
					}
				}

				//ограничение по цене для экспресса яндекс
				if (\CPanelPricelist::checkIfExpressYA($supplier["type_sklad"])){
					if($arItem["EX_YA"] != 2084){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2084;
					}
				}else{
					if($arItem["EX_YA"] != 2085){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2085;
					}
				}

				foreach ($arPrice["s1"][$arItem["ARTICLE"]] as $item) {
					if ($item['store_id'] == 2) {
						$availability['s1']['in_store'] += $item['count'];
					} else {
						$availability['s1']['in_supplier'] += $item['count'];
					}
				}

				if ($availability['s1']['in_store']) {
					if ($arItem["AVAILABILITY_RU"] != 2126) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 2126;
					}
				} else if ($availability['s1']['in_supplier']) {
					if ($arItem["AVAILABILITY_RU"] != 512) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 512;
					}
				} else {
					if ($arItem["AVAILABILITY_RU"] != 514) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 514;
					}
				}

			} else {
				if($arItem["TYPEOFSKLAD"]){
					$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
				}
				if ($arItem["AVAILABILITY_RU"] != 514) {
					$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 514;
				}
			}

			if($arPrice["s2"][$arItem["ARTICLE"]]){
				$price = $arPrice["s2"][$arItem["ARTICLE"]][0];
				$supplier = $suppliers[$price["supplier_id"]] ?? false;

				$day_delivery = $supplier["day_delivery"];
				$working_week = $supplier["working_week"];
				$working_time = $supplier["working_time"];

				$arDelivery["s2"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $supplier["location"],
					"transit_days" => ($supplier["location"] == "moscow" ? $transitDaysRU : []),
				];

				foreach ($arPrice["s2"][$arItem["ARTICLE"]] as $item) {
					if ($item['store_id'] == 1) {
						$availability['s2']['in_store'] += $item['count'];
					} else {
						$availability['s2']['in_supplier'] += $item['count'];
					}
				}

				if ($availability['s2']['in_store']) {
					if ($arItem["AVAILABILITY_BY"] != 492) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 492;
					}
				} else if ($availability['s2']['in_supplier']) {
					if ($arItem["AVAILABILITY_BY"] != 493) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 493;
					}
				} else {
					if ($arItem["AVAILABILITY_BY"] != 494) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 494;
					}
				}
			} else {
				if ($arItem["AVAILABILITY_BY"] != 494) {
					$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 494;
				}
			}

			if(!$arItem["DELIVERY_JSON"] || md5(json_encode($arItem["DELIVERY_JSON"])) != md5(json_encode($arDelivery))) {
				$arUpdate[$arItem["ID"]]["DELIVERY_JSON"] = json_encode($arDelivery, JSON_UNESCAPED_SLASHES);

				//prent($arItem["DELIVERY_JSON"]);prent($arDelivery);prent($arItem);
				//prent('-----------------');
				$ar['DELIVERY_JSON'][] = [
					'ARTICLE' => $arItem['ARTICLE'],
					'OLD' => $arItem["DELIVERY_JSON"],
					'NEW' => $arDelivery,
				];
			}

			if ($arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] || $arUpdate[$arItem["ID"]]["AVAILABILITY_RU"]) {
				$ar['AVAILABILITY'][] = [
					'ARTICLE' => $arItem['ARTICLE'],
					'AVAILABILITY_BY' => $arUpdate[$arItem["ID"]]["AVAILABILITY_BY"],
					'AVAILABILITY_BY_OLD' => $arItem["AVAILABILITY_BY"],
					'AVAILABILITY_RU' => $arUpdate[$arItem["ID"]]["AVAILABILITY_RU"],
					'AVAILABILITY_RU_OLD' => $arItem["AVAILABILITY_RU"],
				];
			}

		}
		//return $ar;
		
		//$arBx[$ar["ID"]]["ARTICLE"]
		//prent($arUpdate);die;
		foreach($arUpdate as $elID => $arProp){
			\CIBlockElement::SetPropertyValuesEx($elID, 16, $arProp);
		}

		require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
		$syncHelper = new \SyncHelper();
		$syncHelper->sendPrices($arIDs ?? []);

		\CLog::add2log(array("event" => "DD", "text" => "Проанализировано - " . count($arBx) . ', изменено - ' . count($arUpdate)));
		
		return $ar;
	}

	public static function getMinPrices($priceType = 'RU', $arArticle = []){ 
		$service = \PanelManager::getPriceManager();
		$servicePrice = $service->updatePriceService($priceType, 'debug');
		if ($arArticle) {
			$filter = [
				'article' => $arArticle
			];
			$servicePrice->market->setPriceFilter($filter);
		}
		$result = $servicePrice->getMinPurchasePrice();

		return $result;
	}
	
	public static function updatePrices($priceTypes = [], $articles = []) {
		$service = \PanelManager::getPriceManager();
		if ($priceTypes == 'all') {
			$arPrices = $service->getTypePrices(); 
			$priceTypes = [];
			foreach ($service->getTypePrices() as $price) {
				$priceTypes[] = $price['id'];
			}
		}
		if (!is_array($priceTypes)) return;

		foreach ($priceTypes as $type) {
			$servicePrice = $service->updatePriceService($type, 'prod_partially');
			
			if ($articles) {
				$filter = [
					'article' => $articles
				];
				$servicePrice->market->setPriceFilter($filter);
			}
			
			$servicePrice->market->setOption('market_required', false);
			
			$result = $servicePrice->updatePrices();

			file_put_contents("/var/www/bitrix_logs/rabbitmq/price_update/dyn_updatePrices.txt", print_r([date('Y-m-d H:i:s'), $type, $articles, $result], true) . "\r\n", 8);

		}

	}
}