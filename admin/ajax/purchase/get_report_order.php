<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $APPLICATION;

class PurchaseDisplayAjax
{
    private $DB;
    private $website;
    private $trading;
    private $priceService;
    private $arResult = [];
    private $arStock = [];
    private $arStockWbby = [];
    private $arPurchase = [];
    private $arPurchaseSP = [];
    private $arMinPrice = [];
    private $arMinPrice_n = [];
    private $skipSup = [];
    private $add_filter = '';
    private $arCurrency = [];
    private $arResultItems = [];
    private $settingsRrc = [];

    private $warehouseSupplier = []; // поставщики являющиеся складами
    private $arSuppSite = [
        47 => "s1", 128 => "s1", 129 => "s1", 141 => "s1", 144 => "s1",
        44 => "s2", 103 => "s1"
    ];
    private $arStockCode = [
        "UPDATE_STOCK_RU",
        "UPDATE_STOCK_BY",
        "UPDATE_STOCK_PL",
        "UPDATE_STOCK_MSK"
    ];
    private $checkSec = 600;

    public function __construct()
    {
        global $DB;
        $this->DB = $DB;
        $this->initializeRequestData();
    }

    private function initializeRequestData()
    {
        // Определение website
        if (isset($_POST["website"]) && in_array($_POST["website"], ["s1", "s2"])) {
            $this->website = [$_POST["website"]];
        } else {
            $this->website = ["s1", "s2"];
        }

        // Определение trading
        if ($_POST["trading"] && $_POST["trading"] != 'all') {
            $this->trading = (int)$_POST["trading"];
        } else {
            $this->trading = 0;
        }
    }

    private function checkAccess()
    {
        global $USER;
        $arGroups = $USER->GetUserGroupArray();
        return ($USER->isAdmin() || in_array(6, $arGroups) || in_array(19, $arGroups));
    }

    private function checkStocks()
    {
        $strSql = "SELECT * FROM ci_options WHERE code IN ('" . implode("','", $this->arStockCode) . "')";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        $arError = [];
        while ($row = $results->Fetch()) {
            $timeStock = strtotime($row["timestamp"]);
            if (time() > ($timeStock + $this->checkSec)) {
                $arError[] = "Склад {$row["code"]} загружен более {$this->checkSec} секунд назад.";
            }
        }

        return $arError;
    }

    private function loadPurchases()
    {
        $strSql = "SELECT * FROM ci_purchase WHERE status = 'N' AND active = 'Y'";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            $this->arPurchase[$row["order_basket_id"]] = $row;
            $this->arPurchaseSP[$row["supp_id"]][$row["model"]] += 1;
        }
    }

    private function loadSuppliers()
    {
        $objSupplier = new CPanelSupplier;
        $this->arResult["SUPPLIER_LIST"] = $objSupplier->getList();

        // Оптовые поставщики
        $tmp = $objSupplier->getList(["opt_supplier" => "Y"]);
        foreach ($tmp as $arItem) {
            $this->skipSup[] = $arItem["id"];
        }

        if (is_array($this->skipSup) && count($this->skipSup) > 0) {
            $this->add_filter = " AND supplier_id NOT IN ('" . implode("','", $this->skipSup) . "')";
        }

        // Данные поставщиков
        foreach ($this->arResult["SUPPLIER_LIST"] as $arSup) {
            $this->arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
            $this->arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];

            $settings = json_decode($arSup["settings"], true);
            foreach ($settings["brand"] as $k => $v) {
                $this->arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
            }

            $settings_pricelist = json_decode($arSup["settings_pricelist"], true);
			$this->arResult["SUPPLIER_SETTINGS"][$arSup["id"]] = $settings_pricelist;

			if ($arSup["is_warehouse"] == 'Y') {
				$this->warehouseSupplier[] = $arSup["id"];
			}
        }
    }

    private function loadSettings()
    {
		$this->priceService = PanelManager::getPriceManager();

        $settingsS2 = CProSet::getOption("TOP_ITEMS_s2");
        $settingsS2 = json_decode($settingsS2, true);
        $this->arResult["purchaseDay"] = intval($settingsS2['purchase_day']);
        $this->arResult["PRICE_DEVIATION"] = CProSet::getOption("PRICE_DEVIATION_ORDER");

		$this->settingsRrc = json_decode(CProSet::getOption("SETTINGS_RRC"), true);

		$this->settingsTp = $this->priceService->getAllTradingSettings();
    }

    private function loadTopModels()
    {
        $strSql = "SELECT * FROM ci_top_models";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            $this->arResult["TOP_LIST"][$row["site_id"]][$row["model"]] = $row["model"];

            if ($this->arResult["purchaseDay"] > 0) {
                $this->arResult["TOP_LIST_COUNTER"][$row["site_id"]][$row["model"]] =
                    ceil(($row["sell_quantity"] / (365 / $this->arResult["purchaseDay"])));
            }
        }
    }

    private function loadTradePlatforms()
    {
        $strSql = "SELECT * FROM b_sale_tp";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            $this->arResult["TRADING_LIST"][$row["ID"]] = $row["NAME"];
        }
    }

    private function loadOrders()
    {
        $objService = new OrderService;
        $objService->getPropOrderFlg = true;

        $arFilter = [
            "LID" => $this->website,
            "STATUS_ID" => ["CO", "WT", "PO", "SE", "TA", "CL"],
            "!CANCELED" => "Y",
        ];

        $arOrder = $objService->getOrderCache(["DATE_INSERT" => "ASC"], $arFilter);

        // Загружаем торговые площадки для заказов
        $arIDs = array_column($arOrder, "ID");
        $arTradePlatform = [];

        if (is_array($arIDs) && count($arIDs) > 0) {
            $strSql = "SELECT ORDER_ID, TRADING_PLATFORM_ID FROM b_sale_tp_order WHERE ORDER_ID IN ('" . implode("','", $arIDs) . "')";
            $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

            while ($row = $results->Fetch()) {
                $arTradePlatform[$row["ORDER_ID"]] = $row["TRADING_PLATFORM_ID"];
            }
        }

        // Формируем список позиций
        $arCrmID = $objService->getOrderCrmID(["ORDER_ID" => $arIDs]);

        foreach ($arOrder as $arItem) {
            $tradingPlatformId = $arTradePlatform[$arItem["ID"]] ?? '';

            if ($this->trading && $tradingPlatformId && $tradingPlatformId != $this->trading) {
                continue;
            }

			$deliveryDay = false;
			if ($arItem["DELIVERY_DATE"]) {
				/*$delivery = DateTime::createFromFormat('d.m.Y', $arItem["DELIVERY_DATE"]);// . " 23:59:59"

				$interval = $current_date->diff($delivery);
				$deliveryDay = $interval->days;*/


				$current_date = date('d.m.Y');
				$delivery = DateTime::createFromFormat('d.m.Y', $arItem["DELIVERY_DATE"]);
				$current = DateTime::createFromFormat('d.m.Y', $current_date);

				$interval = $current->diff($delivery);
				$deliveryDay = $interval->days;

				/*if ($arItem["ORDER_ID"] == 809420) {
					prent($arItem["DELIVERY_DATE"]);
					prent($delivery);
					prent($interval);
					prent($deliveryDay);
				}*/
			}


            foreach ($arItem["BASKET"] as $arBasket) {
                for ($i = 1; $i <= $arBasket["QUANTITY"]; $i++) {
                    if (!$this->arPurchase[$arBasket["ID"] . "." . $i]) {
                        $this->arResult["ITEMS"][] = [
                            "ID" => $arBasket["ID"],
                            "ID_UNIQUE" => $arBasket["ID"] . "." . $i,
                            "PRODUCT_ID" => $arBasket["PRODUCT_ID"],
                            "NAME" => $arBasket["NAME"],
                            "PRICE" => $arBasket["PRICE"],
                            "CURRENCY" => $arBasket["CURRENCY"],
                            "SITE_ID" => $arItem["LID"],
                            "ORDER_ID" => $arItem["ID"],
                            "ORDER_XML_ID" => $arItem["XML_ID"],
                            "ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
                            "STATUS_ID" => $arItem["STATUS_ID"],
                            "DELIVERY_ID" => $arItem["DELIVERY_ID"],
                            "DELIVERY_DATE" => $arItem["DELIVERY_DATE"],
                            "DELIVERY_DATE_COUNT" => $deliveryDay ?? false,
                            "FROM" => $arItem['FIO'],
                            "TRADING_PLATFORM_ID" => $tradingPlatformId,
							"DATE_CREATE" => $arItem['DATE_INSERT'],
                        ];
                    }
                }
            }
        }

        $this->arResult["ITEMS"] = sort_nested_arrays($this->arResult["ITEMS"], ["ID" => "asc"]);
    }

    private function loadPrices()
    {
        $objCurrency = new CPanelCurrency;
        $this->arCurrency = $objCurrency->getList();

		$suppIds = $this->warehouseSupplier;
		//unset($suppIds[array_search(128, $suppIds)]);

        $strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('" . implode("','", $suppIds) . "')";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

		$this->reservePriceIds = [];

        while ($row = $results->Fetch()) {
            if (isset($this->arPurchaseSP[$row["supplier_id"]][$row["model"]])) {
                $row["count"] = $row["count"] - $this->arPurchaseSP[$row["supplier_id"]][$row["model"]];
            }

            if ($row["count"] > 0) {
                // Разбиваем если больше 1
                if ($row["count"] > 1) {
                    for ($i = 1; $i <= $row["count"]; $i++) {
                        $tmp_row = $row;
                        $tmp_row["count"] = 1;
                        $this->arStock[$row["supplier_id"]][$row["model"]][] = $tmp_row;
                    }
                } else {
                    $this->arStock[$row["supplier_id"]][$row["model"]][] = $row;
                }

                // Минимальные цены
                /*if (!isset($this->arMinPrice[$row["model"]]) || $this->arMinPrice[$row["model"]] > $row["price"]) {
                    $this->arMinPrice[$row["model"]] = $row["price"];
                }

                if (!isset($this->arMinPrice_n[$row["model"]]) || $this->arMinPrice_n[$row["model"]] > $row["price_n"]) {
                    $this->arMinPrice_n[$row["model"]] = $row["price_n"];
                }*/
            } else {
				$this->reservePriceIds[] = $row["id"];
			}
        }

		// очищаем от правой колонки
		/*
		foreach ($this->arPurchaseSP as $suppId => $purchaseItems) {
			if (!isset($this->arStock[$suppId])) {
				continue;
			}

			foreach ($purchaseItems as $article => $quantity) {
				if (!isset($this->arStock[$suppId][$article])) {
					continue;
				}

				array_splice($this->arStock[$suppId][$article], 0, $quantity);

				if (empty($this->arStock[$suppId][$article])) {
					prent($article);
					//unset($this->arStock[$suppId][$article]);
					//
				}
			}

			if (empty($this->arStock[$suppId])) {
				//unset($this->arStock[$suppId]);
			}
		}*/

        $this->arStockWbby = $this->arStock[44] ?? [];
    }

    private function processTopModelsForWbby()
    {
        foreach (array_keys($this->arStockWbby) as $article) {
            $cntTop = $this->arResult["TOP_LIST_COUNTER"]['s2'][$article] ?? 0;
            if ($cntTop > 0) {
                if ($cntTop >= count($this->arStockWbby[$article])) {
                    unset($this->arStockWbby[$article]);
                } else {
                    $this->arStockWbby[$article] = array_slice($this->arStockWbby[$article], 0, $cntTop);
                }
            }
        }
    }

    private function enrichItemsWithArticles()
    {
        foreach ($this->arResult["ITEMS"] as &$arItem) {
            // Конвертация валют
            if ($arItem["SITE_ID"] == "s2" && $arItem["CURRENCY"] == "RUB") {
                $arItem["CURRENCY"] = "BYN";
            }

            if (isset($arItem["CURRENCY"]) && $arItem["CURRENCY"] != "RUB") {
                $currency = (new CPanelCurrency)->getDetail($arItem["CURRENCY"]);
                $rate = $currency["rate"];
                $arItem["PRICE"] = round($arItem["PRICE"] * $rate, 2);
            }

            // Получение артикула
            $objRes = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]],
                false,
                false,
                ['PROPERTY_CML2_ARTICLE']
            );

            if ($res = $objRes->GetNext()) {
                $arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
            }

			$arItem["FAKE_TRADING_PLATFORM_NAME"] = $this->getFakeTradingName($arItem);
        }
    }

	private function getFakeTradingName($arItem)
	{
		if ($arItem['SITE_ID'] == 's1' && $arItem['STATUS_ID'] == 'TA') {
			return 'SITES_NKZ';
		}
		if ($arItem['SITE_ID'] == 's2' && ($arItem['STATUS_ID'] == 'TA' || $arItem['STATUS_ID'] == 'CO')) {
			return 'SITES_NEMIGA';
		}
		return $this->arResult["TRADING_LIST"][$arItem['TRADING_PLATFORM_ID']];
	}

    private function filterItemsByStock()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (!$arItem["ARTICLE"]) {
                unset($this->arResult["ITEMS"][$key]);
                continue;
            }

			$this->processRegularStock($arItem, $key);
        }
    }

    private function processRegularStock(&$arItem, $key)
    {
		$settingsTp = $this->settingsTp[$arItem['TRADING_PLATFORM_ID']] ?? false;

		if (!$settingsTp) return;

		if ($settingsTp['warehouse_id']) {
			$warehouseId = $settingsTp['warehouse_id'];
		} elseif ($settingsTp['warehouse_sites']) {


			if ($arItem['SITE_ID'] == 's1' && $arItem['STATUS_ID'] == 'TA' && $settingsTp['warehouse_sites']['s1_nkz']) {
				$warehouseId = $settingsTp['warehouse_sites']['s1_nkz'];
			} elseif ($arItem['SITE_ID'] == 's2' && ($arItem['STATUS_ID'] == 'TA' || $arItem['STATUS_ID'] == 'CO') && $settingsTp['warehouse_sites']['s2_nemiga']) {
				$warehouseId = $settingsTp['warehouse_sites']['s2_nemiga'];
			} else {
				$warehouseId = $settingsTp['warehouse_sites'][$arItem['SITE_ID']] ?? false;
			}

		} else {
			return;
		}

		if (!$warehouseId) return;

		$arItem["WAREHOUSE_ID"] = $warehouseId;

		if (isset($this->arStock[$warehouseId][$arItem["ARTICLE"]]) &&
			is_array($this->arStock[$warehouseId][$arItem["ARTICLE"]]) &&
			count($this->arStock[$warehouseId][$arItem["ARTICLE"]]) > 0) {

			$this->arResult["ITEMS_SHIPMENT"][$key] = $this->arResult["ITEMS"][$key];

			unset($this->arResult["ITEMS"][$key]);

			$key_stock = array_keys($this->arStock[$warehouseId][$arItem["ARTICLE"]]);

			//$this->reservePriceIds[]
			//if ($arItem["ARTICLE"] == 'NY0125-08W') {
			//	prent($this->arStock[$warehouseId][$arItem["ARTICLE"]][$key_stock[0]]);
			//}
			$this->reservePriceIds[] = $this->arStock[$warehouseId][$arItem["ARTICLE"]][$key_stock[0]]['id'];

			unset($this->arStock[$warehouseId][$arItem["ARTICLE"]][$key_stock[0]]);

			if (is_array($this->arStock[$warehouseId][$arItem["ARTICLE"]]) && count($this->arStock[$warehouseId][$arItem["ARTICLE"]]) == 0) {
				unset($this->arStock[$warehouseId][$arItem["ARTICLE"]]);
			}
			if ($arItem["ARTICLE"] == 'NY0125-08W') {
			//	prent($arItem);
			}
			// Для WBBY
			/*if ($supp_id == 44 && $this->arStockWbby[$arItem["ARTICLE"]]) {
				$key_stock = array_keys($this->arStockWbby[$arItem["ARTICLE"]]);
				unset($this->arStockWbby[$arItem["ARTICLE"]][$key_stock[0]]);

				if (is_array($this->arStockWbby[$arItem["ARTICLE"]]) && count($this->arStockWbby[$arItem["ARTICLE"]]) == 0) {
					unset($this->arStockWbby[$arItem["ARTICLE"]]);
				}
			}*/
		}
    }

    private function loadAllPrices()
    {
        $strSql = "SELECT * FROM ci_price WHERE 1=1 {$this->add_filter}";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
			if (in_array($row["id"], $this->reservePriceIds)) continue;
            $this->arResult["ALL_PRICE"][$row["model"]][] = $row;
        }

		//supp_id
		/*
        (
            [id] => 890917
            [active] => Y
            [site_id] => s1
            [model] => SRPG35K1
            [order_id] => 783196
            [tmp_order_id] => 0
            [order_basket_id] => 1732916.1
            [top_id] => 0
            [product_id] => 159412
            [user_id] => 152301
            [price] => 22520.00
            [supp_id] => 142
            [status] => N
            [ms_need_sync] => N
            [ms_supply_id] =>
            [ms_data] => a:1:{s:6:"supply";a:5:{s:2:"id";s:36:"295ecad0-2828-11f1-0a80-1072003aab91";s:4:"name";s:8:"27292765";s:7:"created";s:23:"2026-03-25 11:53:58.292";s:7:"cabinet";s:2:"s1";s:9:"timestamp";d:1774428838.490889;}}
            [timestamp] => 2026-03-25 09:20:17
            [user_modify] =>
        )

        (
            [id] => 609660014
            [active] => Y
            [active_ru] => N
            [active_by] => N
            [active_pl] => N
            [active_ya] => N
            [active_os] => N
            [active_wb] => N
            [active_wbtl] => N
            [active_wbby] => N
            [active_av] => N
            [active_opt] => N
            [active_v1] => N
            [active_v2] => N
            [active_sb] => N
            [active_kz] => N
            [active_ozkz] => N
            [active_ozti] => N
            [model] => LTP-V007D-4E
            [brand_id] => 1
            [supplier_id] => 142
            [stock_days] =>
            [store_id] => 3
            [price] => 1472.50
            [price_n] => 1767.00
            [discPrice] =>
            [count] => 500
            [multiplicity] => 1
            [priceСurrency] => 1472.50
            [currency] => RUB
            [timestamp] => 2026-03-23 17:28:49
            [bitrix_id] => 13660
        )

		*/
		//            $this->arPurchase[$row["order_basket_id"]] = $row;
        //    $this->arPurchaseSP[$row["supp_id"]][$row["model"]] += 1;
    }

    private function processPriceDeviations()
    {
        $arAllArticle = [];

        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            $arr = [];

            if (isset($this->arResult["ALL_PRICE"][$arItem["ARTICLE"]])) {
                foreach ($this->arResult["ALL_PRICE"][$arItem["ARTICLE"]] as $k => $row) {
                    // Фильтры по активности

                    if (!$this->checkPriceActivity($row, $arItem)) {
                        continue;
                    }

                    //if (in_array($row["supplier_id"], $this->warehouseSupplier)) {
                    if ($row["supplier_id"] == $arItem["WAREHOUSE_ID"]) {
                        if (!$this->arStock[$row["supplier_id"]][$row["model"]] ||
                            (is_array($this->arStock[$row["supplier_id"]][$row["model"]]) &&
                             count($this->arStock[$row["supplier_id"]][$row["model"]]) <= 0)) {
                            continue;
                        } else {
                            $key_stock = array_keys($this->arStock[$row["supplier_id"]][$row["model"]]);
                            unset($this->arStock[$row["supplier_id"]][$row["model"]][$key_stock[0]]);

                            if (is_array($this->arStock[$row["supplier_id"]][$row["model"]]) &&
                                count($this->arStock[$row["supplier_id"]][$row["model"]]) == 0) {
                                unset($this->arStock[$row["supplier_id"]][$row["model"]]);
                            }
                        }
                    }

					$row['index'] = $k;
                    $arr[$row["id"]] = $row;
                    $this->setPriority($arr[$row["id"]], $row, $arItem["SITE_ID"]);


					if(!isset($this->arMinPrice[$row["model"]]) || $this->arMinPrice[$row["model"]] > $row["price"]){
						$this->arMinPrice[$row["model"]] = $row["price"];
					}
					if(!isset($this->arMinPrice_n[$row["model"]]) || $this->arMinPrice_n[$row["model"]] > $row["price_n"]){
						$this->arMinPrice_n[$row["model"]] = $row["price_n"];
					}
					if(in_array($row["supplier_id"], $this->warehouseSupplier)){
						$arItem["IN_STOCK"] = true;
					}
                }
            }

            if (is_array($arr) && count($arr) > 0) {
                $this->processDeviations($arr, $arItem);

				if ($arItem['OPTIMAL_DEVIATION']) {
					foreach ($arItem['DEVIATION'] as $v) {
						if ($v['id'] == $arItem['OPTIMAL_DEVIATION']) {
if($arItem["ARTICLE"] == 'NY0125-08W'){
	//prent($v);
}

							$this->arResult["ALL_PRICE"][$arItem["ARTICLE"]][$v['index']]['count'] -= 1;

							if ($this->arResult["ALL_PRICE"][$arItem["ARTICLE"]][$v['index']]['count'] <= 0) {
								unset($this->arResult["ALL_PRICE"][$arItem["ARTICLE"]][$v['index']]);
							}
						}
					}
				}
                $arAllArticle[$arItem["ARTICLE"]] = $arItem["ARTICLE"];
            } else {
                $this->handleNoStockItem($arItem);
            }
        }

		if(is_array($arAllArticle) && count($arAllArticle) > 0){

			$strSql = "SELECT * FROM ci_price WHERE model IN ('" . implode("','", $arAllArticle) . "') {$this->add_filter}";

			$results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

			while ($row = $results->Fetch()){

				$this->rsALL[$row["model"]][$row["id"]] = $row;

			}

		}

        $this->arResult["ITEMS"] = array_reverse($this->arResult["ITEMS"]);
    }

    private function checkPriceActivity($row, $arItem)
    {
		$warehouseId = $arItem["WAREHOUSE_ID"];
        if ($arItem["TRADING_PLATFORM_ID"] == 6) {
            if ($row["active_wb"] == "N") return false;
        } elseif ($arItem["TRADING_PLATFORM_ID"] == 8) {
            if ($row["active_os"] == "N") return false;
        } elseif ($arItem["TRADING_PLATFORM_ID"] == 21) {
            if ($row["active_wbtl"] == "N") return false;
        } elseif ($arItem["TRADING_PLATFORM_ID"] == 18) {
            if ($row["active_opt"] == "N") return false;
        } elseif ($arItem["TRADING_PLATFORM_ID"] == 19) {
            if ($row["active_wbby"] == "N") return false;
        } elseif ($arItem["TRADING_PLATFORM_ID"] == 4) {
            if ($row["active_ya"] == "N") return false;
        } elseif ($arItem["SITE_ID"] == "s1") {
            if ($row["active_ru"] == "N") {
                return false;
            }
        } elseif ($arItem["SITE_ID"] == "s2") {
            if ($row["active_by"] == "N") {
                return false;
            }
        }/* elseif ($arItem["SITE_ID"] == "s1") {
            if ($row["active_ru"] == "N" || ($row["supplier_id"] == $warehouseId && $this->arStock[$warehouseId][$arItem["ARTICLE"]])) {
                return false;
            }
        } elseif ($arItem["SITE_ID"] == "s2") {
            if ($row["active_by"] == "N" || ($row["supplier_id"] == $warehouseId && $this->arStock[$warehouseId][$arItem["ARTICLE"]])) {
                return false;
            }
        }*/

        //if ($row["model"] == 'DW-5600UE-1E') {
		//	prent($row);
		//	prent($arItem);
		//}
        return true;
    }

    private function setPriority(&$item, $row, $siteId)
    {
		//$this->arResult["SUPPLIER_SETTINGS"]

        if (isset($this->arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]])) {
            $item["priority"] = $this->arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]];
        } elseif (in_array($row["supplier_id"], $this->warehouseSupplier)) {
            $item["priority"] = 1;

			$setFlg = true;
            if ($siteId == "s1") {
                if ($row["supplier_id"] == 103) {
                    $item["priority"] = 1;
                } elseif ($row["supplier_id"] == 141) {
                    $item["priority"] = 0;
                } elseif ($row["supplier_id"] == 149000) {
                    //$item["priority"] = 4;
                } else {
					$setFlg = false;
				}
            } elseif ($siteId == "s2") {
                if (in_array($row["supplier_id"], [103, 47])) {
                    $item["priority"] = 1;
                } elseif ($row["supplier_id"] == 141) {
                    $item["priority"] = 0;
                } else {
					$setFlg = false;
				}
            }

			if ($setFlg === true && isset($this->arResult["SUPPLIER_SETTINGS"][$row["supplier_id"]]['priority_default'])) {
				$item["priority"] = $this->arResult["SUPPLIER_SETTINGS"][$row["supplier_id"]]['priority_default'];
			}
        } else {
			if (isset($this->arResult["SUPPLIER_SETTINGS"][$row["supplier_id"]]['priority_default'])) {
				$item["priority"] = $this->arResult["SUPPLIER_SETTINGS"][$row["supplier_id"]]['priority_default'];
			} else {
				$item["priority"] = 10;
			}
        }
			//if ($row["supplier_id"] == 149) {
			//	prent($item);
			//}
    }

    private function processDeviations($arr, &$arItem)
    {
        $arItem["PRICELIST"] = $arr;

        foreach ($arr as $k => $v) {
            if ($this->arMinPrice[$v["model"]] && $this->arResult["PRICE_DEVIATION"]) {
                if ($arItem["SITE_ID"] == "s2") {
                    //$sets = json_decode(CProSet::getOption("SETTINGS_RRC"), true)['by']['price_type'];

                    //if ($sets == 'price_n') {

					if ($this->settingsRrc['by']['price_type'] == 'price_n') {
                        $v["price"] = $v["price_n"];
                        $arr[$k]["price"] = $arr[$k]["price_n"];
                        $diff = $v["price"] / $this->arMinPrice_n[$v["model"]] * 100 - 100;
                    } else {
                        $diff = $v["price"] / $this->arMinPrice[$v["model"]] * 100 - 100;
                    }
                } else {
                    $diff = $v["price"] / $this->arMinPrice[$v["model"]] * 100 - 100;
                }

                if (($diff <= $this->arResult["PRICE_DEVIATION"] &&
                     (!$this->arResult["TOP_LIST"][$this->arSuppSite[$v["supplier_id"]]][$v["model"]])) ||
                    in_array($v["supplier_id"], $this->warehouseSupplier)) {

                    $arItem["DEVIATION"][] = $v;
					//if($v["model"] == 'RA-AS0010S'){
					//	prent([$diff, $v]);
					//}
                }

            }
        }

        if (is_array($arItem["DEVIATION"]) && count($arItem["DEVIATION"]) > 0) {
            $arItem["DEVIATION"] = sort_nested_arrays($arItem["DEVIATION"], ["priority" => "asc", "price" => "asc"]);
            $arItem["OPTIMAL_DEVIATION"] = $arItem["DEVIATION"][0]["id"];
        }
		if($arItem["ORDER_NUMBER_ID"] == 773277){
					//prent([$arItem]);
		}
        $arItem["IN_STOCK"] = true;
    }

    private function handleNoStockItem(&$arItem)
    {
        $arr = [];
        $strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' {$this->add_filter} ORDER BY store_id asc";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            $arr[$row["id"]] = $row;
        }

        if (is_array($arr) && count($arr) > 0) {
            $arItem["PRICELIST"] = $arr;
        }

        $arItem["NOT_STOCK"] = "Y";
    }

    private function setActualPrices()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            $_ar_min = [];
            foreach ($arItem["PRICELIST"] as &$arr) {
                if (!$_ar_min || $arr["price"] < $_ar_min["price"]) {
                    $_ar_min = $arr;
                }

				unset($this->rsALL[$arr["model"]][$arr["id"]]);
            }

            if ($arItem["OPTIMAL_DEVIATION"] &&
                $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]] &&
                $_ar_min["id"] != $arItem["OPTIMAL_DEVIATION"]) {
                $_ar_min = $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]];
            }
            if ($arItem["ARTICLE"] == 'MTP-1302DD-9A') {
				//prent($_ar_min);
				//prent($arItem);
			}
            if ($_ar_min["id"]) {
                $arItem["PRICE_ACTUAL"] = $_ar_min;
                $arItem["PRICE_ACTUAL"]["site_id"] = $arItem["SITE_ID"];
                $arItem["PRICE_ACTUAL"]["status_id"] = $arItem["STATUS_ID"];
                $arItem["PRICE_ACTUAL"]["order_id"] = $arItem["ORDER_ID"];
                $arItem["PRICE_ACTUAL"]["order_xml_id"] = $arItem["ORDER_XML_ID"];
                $arItem["PRICE_ACTUAL"]["order_number_id"] = $arItem["ORDER_NUMBER_ID"];
                $arItem["PRICE_ACTUAL"]["order_basket_id"] = $arItem["ID_UNIQUE"];
                $arItem["PRICE_ACTUAL"]["product_id"] = $arItem["PRODUCT_ID"];
                $arItem["PRICE_ACTUAL"]["delivery_id"] = $arItem["DELIVERY_ID"];
                $arItem["PRICE_ACTUAL"]["delivery_date"] = $arItem["DELIVERY_DATE"];
                $arItem["PRICE_ACTUAL"]["delivery_date_count"] = $arItem["DELIVERY_DATE_COUNT"];
                $arItem["PRICE_ACTUAL"]["from"] = $arItem["FROM"];
                $arItem["PRICE_ACTUAL"]["source"] = $arItem["FAKE_TRADING_PLATFORM_NAME"];
                $arItem["PRICE_ACTUAL"]["price_order"] = round($arItem["PRICE"], 2);

                unset($arItem["PRICELIST"][$_ar_min["id"]]);
            }
        }
    }

    private function groupBySupplier()
    {
        $arSort = [];

        foreach ($this->arResult["ITEMS"] as $key => $arItem) {
            if ($arItem["NOT_STOCK"] !== "Y") {
                if ($arItem["PRICE_ACTUAL"]) {
                    $tmp = $arItem["PRICE_ACTUAL"];
                    $arSort[$tmp["supplier_id"]] = $this->arResult["SUPPLIER_SORT"][$tmp["supplier_id"]];
                    $this->arResult["PRICE_GROUP"][$tmp["supplier_id"]][] = $arItem["PRICE_ACTUAL"];
                }

                foreach ($arItem["PRICELIST"] as $alt) {
                    $this->arResult["PRICE_ALTERNATIVE"][$alt["model"]][$alt["id"]] = $alt;
                }
            } else {
                $this->arResult["PRICE_NO_SUPP"][] = $arItem;
            }
        }

        // Сортировка групп
        asort($arSort);
        $tmp = [];
        foreach ($arSort as $key => $val) {
            $tmp[$key] = $this->arResult["PRICE_GROUP"][$key];
        }
        $this->arResult["PRICE_GROUP"] = $tmp;
    }

    private function debugMicrotimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    public function execute()
    {
        $start = $this->debugMicrotimeFloat();

        // Проверка доступа
        if (!$this->checkAccess()) {
            return;
        }

        // Проверка складов
        $stockErrors = $this->checkStocks();
        if (count($stockErrors) > 0) {
            foreach ($stockErrors as $error) {
                echo "<p>{$error}</p>";
            }
            return;
        }



		$this->arResult['ITEMS'] = $this->arResult['ITEMS_SHIPMENT'] = [];

        $this->loadPurchases();
        $this->loadSuppliers();
        $this->loadSettings();
        $this->loadTopModels();
        $this->loadTradePlatforms();

        $this->loadOrders();

        $this->loadPrices();
        //$this->processTopModelsForWbby();
        $this->enrichItemsWithArticles();
        $this->filterItemsByStock();
        $this->loadAllPrices();
        $this->processPriceDeviations();
        $this->setActualPrices();
        $this->groupBySupplier();

        // Вывод результатов
        $this->renderSummaryResults();
        $this->renderResults();
        $this->renderShipmentResults();
    }

	public function getShipmentList() {
		$this->arResult['ITEMS'] = $this->arResult['ITEMS_SHIPMENT'] = [];

        $this->loadPurchases();
        $this->loadSuppliers();
        $this->loadSettings();
        $this->loadTopModels();
        $this->loadTradePlatforms();

        $this->loadOrders();

        $this->loadPrices();
        $this->enrichItemsWithArticles();
        $this->filterItemsByStock();
        $this->loadAllPrices();
        $this->processPriceDeviations();

		return $this->arResult['ITEMS_SHIPMENT'];
	}

    private function renderResults()
    {//prent($this->arResult["PRICE_GROUP"]);
        ?>
        <div class="row">
        <?php foreach ($this->arResult["PRICE_GROUP"] as $key => $arItem): ?>
            <?php $txt = ""; ?>
            <div class="col-sm-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40%"><span class="btn-clipboard-list" style="cursor:pointer; color: #337ab7;" data-id="textarea-purchase-<?=$key?>" data-clipboard-target="#textarea-purchase-<?=$key?>"><?=$this->arResult["SUPPLIER_NAME"][$key]?></span>
							<?php if (is_array($arItem)) {?>
							<span class="badge" style="margin: 0 0 0 5px;"><?=count($arItem)?></span>
							<?php }?></th>
                            <th style="width: 25%">Цена</th>
                            <th style="width: 5%"></th>
                            <th style="width: 10%"></th>
                            <th style="width: 5%"></th>
                            <th style=""><button type="button" class="btn btn-sm btn-default add-all-purchase" style="width: 90px;padding: 1px;">OK</button></th>
                        </tr>
                        <tr>
                            <?php
                            $summ = 0;
                            foreach ($arItem as $art => $val) {
                                $summ = $summ + $val["price"];
                            } ?>
                            <th style="width: 100%">Цена (<?=round($summ, -2)?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($arItem as $article => $arPrice): ?>
                        <?php
                        if ($arPrice["currency"] == 'RUB') {
                            $newPriceCheck = $arPrice["priceСurrency"];
                        } else {
                            $newPriceCheck = $arPrice["priceСurrency"] * $this->arCurrency[$arPrice["currency"]]["rate"];
                        }
                        $arPrice["price"] = $newPriceCheck;
                        $txt .= $arPrice["model"] . "\r\n";
                        ?>
                        <tr class="<?php if ($arPrice["price_order"] && $arPrice["price_order"] < $arPrice["price"]):?>black<?php elseif ($arPrice["status_id"] == "WT"):?>warning<?php endif ?> <?php if ($this->arResult["TOP_LIST"][$this->arSuppSite[$arPrice["supplier_id"]]][$arPrice["model"]]):?>danger<?php endif ?>" data-orderid="<?=$arPrice["order_id"]?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>" data-productid="<?=$arPrice["product_id"]?>">
                            <td><?=$arPrice["model"]?></td>
                            <td><?=$arPrice["price"]?></td>
                            <td style="position: relative;">
                                <?php if ($arPrice["order_id"] > 0): ?>
                                    <a href="https://tempusshop.ru/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?=$arPrice["order_id"]?>" target="_blank" style="position: relative;">
                                        <span><?=$arPrice["order_number_id"]?></span>
                                    </a>
                                <?php else: ?>
                                    <?=$arPrice["order_number_id"]?>
                                <?php endif ?>
								<?if($arPrice['delivery_date_count'] !== false):?>
								<span class="badge" style="font-size: 9px;position: absolute;padding: 3px 4px 3px 3px;top: -3px;right: -2px;"><?=$arPrice['delivery_date_count']?>Д</span>
								<?endif?>
                            </td>
                            <td style="font-size: 14px;"><?=$arPrice["source"]?></td>
                            <td><?=$arPrice["site_id"]?></td>
                            <td class="right">
                                <?php $tmpID = []; ?>
                                <div class="btn-group main-item">
                                    <button type="button" class="btn btn-sm btn-default add-purchase" data-id="<?=$arPrice["id"]?>">OK</button>

                                    <?php if (isset($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) &&
                                              is_array($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) &&
                                              count($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) > 0): ?>
                                    <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="caret"></span>
                                        <span class="sr-only">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php
                                        $arPriceAlt = array_values($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]);
                                        $min = $this->arMinPrice[$arPrice["model"]] ?? 1;
                                        ?>
                                        <?php foreach ($arPriceAlt as $key_alt => $arAlt): ?>
                                            <?php if ($arAlt["id"] == $arPrice["id"]) continue; ?>
                                            <?php $tmpID[] = $arAlt["id"]; ?>
                                            <?php
                                            $class = "";
                                            if ($key_alt >= 0) {
                                                $diff = $arAlt["price"] / $min * 100 - 100;
                                                if ($diff <= 3) {
                                                    $class = "btn-success";
                                                } elseif ($diff <= 5) {
                                                    $class = "btn-warning";
                                                } elseif ($diff <= 10) {
                                                    $class = "btn-danger";
                                                } else {
                                                    $class = "btn-dark";
                                                }
                                            }
                                            ?>
                                            <li class="<?=$class?>"><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$this->arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
                                        <?php endforeach ?>
                                    </ul>
                                    <?php endif ?>
                                </div>
								<div class="btn-group" style="float:right;">
									<?if((isset($this->rsALL[$arPrice["model"]]) && is_array($this->rsALL[$arPrice["model"]]) && count($this->rsALL[$arPrice["model"]]) > 0)):?>
									<button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">D</button>
									<ul class="dropdown-menu">
										<?
										$list = sort_nested_arrays($this->rsALL[$arPrice["model"]], array("price" => "asc"), true);
										foreach($list as $key_alt => $arAlt):?>
											<?if($arAlt["id"] == $arPrice["id"] || in_array($arAlt["id"], $tmpID)) continue;?>
											<li class="<?//=$class?>"><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$this->arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
										<?endforeach?>
									</ul>
									<?endif?>
								</div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <textarea id="textarea-purchase-<?=$key?>" style="position: fixed;left: -9999px;display:none;"><?=$txt?></textarea>
        <?php endforeach ?>

        <?php if (is_array($this->arResult["PRICE_NO_SUPP"]) && count($this->arResult["PRICE_NO_SUPP"]) > 0): ?>
            <div class="col-sm-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 45%">Нет у поставщиков</th>
                            <th style="width: 30%">Цена</th>
                            <th style="width: 5%"></th>
                            <th style="width: 10%"></th>
                            <th style="width: 10%"></th>
                        </tr>
                    </thead>
                    <?php foreach ($this->arResult["PRICE_NO_SUPP"] as $key => $arPrice): ?>
                        <tbody>
                            <tr class="<?php if ($arPrice["STATUS_ID"] == "N"):?>danger<?php endif ?>" data-orderid="<?=$arPrice["ORDER_ID"]?>" data-orderbasketid="<?=$arPrice["ID_UNIQUE"]?>" data-productid="<?=$arPrice["PRODUCT_ID"]?>">
                                <td><a href="/bitrix/admin/sale_order_view.php?ID=<?=$arPrice["ORDER_ID"]?>&lang=ru&filter=Y&set_filter=Y" target="_blank"><?php if ($arPrice["ARTICLE"]): ?><?=$arPrice["ARTICLE"]?><?php else: ?><?=$arPrice["NAME"]?><?php endif ?></a></td>
                                <td><?=$arPrice["PRICE"]?></td>
                                <td class="right">
                                    <?php if ($arPrice["ORDER_ID"] > 0): ?>
                                        <a href="https://tempusshop.ru/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?=$arPrice["ORDER_ID"]?>" target="_blank" style="position: relative;"><span><?=$arPrice["ORDER_NUMBER_ID"]?></span></a>
                                    <?php else: ?>
                                        <?=$arPrice["ORDER_NUMBER_ID"]?>
                                    <?php endif ?>
                                </td>
                                <td class="right">
                                    <?php if ($arPrice["PRICE_ACTUAL"]): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">D</button>
                                        <ul class="dropdown-menu">
                                            <li><a href="#" class="add-purchase" data-id="<?=$arPrice["PRICE_ACTUAL"]["id"]?>"><?=$this->arResult["SUPPLIER_NAME"][$arPrice["PRICE_ACTUAL"]["supplier_id"]]?> - <?=$arPrice["PRICE_ACTUAL"]["price"]?></a></li>
                                            <?php
                                            $list = sort_nested_arrays($arPrice["PRICELIST"], ["price" => "asc"]);
                                            ?>
                                            <?php foreach ($list as $key_alt => $arAlt): ?>
                                                <?php if ($arAlt["id"] == $arPrice["PRICE_ACTUAL"]["id"]) continue; ?>
                                                <li><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$this->arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
                                            <?php endforeach ?>
                                        </ul>
                                    </div>
                                    <?php endif ?>
                                </td>
                                <td><?=$arPrice['FAKE_TRADING_PLATFORM_NAME']?></td>
                                <td><?=$arPrice['SITE_ID']?></td>
                            </tr>
                        </tbody>
                    <?php endforeach ?>
                </table>
            </div>
        <?php endif ?>

        <a href="/admin/ajax/purchase/get_list_order_stock_excel.php?site_id=<?=addslashes($_POST["website"])?>">Список отгрузкок</a>
        </div>

        <?php
    }

    private function renderShipmentResults()
    {
        global $USER;

        if ($_REQUEST["xls"] == "Y" || $USER->getID() == 11587) {
            $this->generateExcel();
        }

        $displayItems = $this->arResult['ITEMS_SHIPMENT'];

        if (is_array($displayItems) && count($displayItems) > 0):

        usort($displayItems, function($a, $b) {
            return strtotime($a['DATE_CREATE']) - strtotime($b['DATE_CREATE']);
        });

        ?>
        <div class="row">
            <div class="col-sm-12">
                <h4>Готовы к отгрузке</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 20%">Модель</th>
                            <th style="width: 20%">Источник</th>
                            <th style="width: 20%">Дата</th>
                            <th style=""></th>
                            <th style="width: 10%">Сайт</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayItems as $key => $item): ?>
                            <tr>
                                <td><?= $item["ARTICLE"] ?></td>
                                <td><?= $item["FAKE_TRADING_PLATFORM_NAME"] ?></td>
                                <td><?= $item["DATE_CREATE"] ?></td>
                                <td><a href="/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?= $item["ORDER_ID"] ?>" target="_blank"><?= $item["ORDER_NUMBER_ID"] ?></a></td>
                                <td><?= $item['SITE_ID'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        endif;
    }

    private function renderSummaryResults():void
    {
      $sums = [];
      $suppGroups = [ // группировка + порядок отображения
        'def' => [],
        'nds' => [],
        'wh' => [],
      ];
      $groupsSum = [ 'def' => 0, 'wh' => 0, 'nds' => 0 ];

      foreach ( $this->arResult['PRICE_GROUP'] as $key => $arItem ){
        $sums[ $key ] = [
          'name' => $this->arResult["SUPPLIER_NAME"][$key],
          'sum' => array_sum( array_column($arItem, 'price') ),
        ];
      }
      foreach ( $this->arResult["SUPPLIER_LIST"] as $supp ){
        if ( $supp['is_warehouse'] == 'Y' ){
          $suppGroups['wh'][] = $supp['id'];
          $groupsSum['wh'] += $sums[ $supp['id'] ]['sum'];
          continue;
        }
        if ( $supp['nds'] == 'Y' ){
          $suppGroups['nds'][] = $supp['id'];
          $groupsSum['nds'] += $sums[ $supp['id'] ]['sum'];
          continue;
        }
        $suppGroups['def'][] = $supp['id'];
        $groupsSum['def'] += $sums[ $supp['id'] ]['sum'];
      }


      $dict = [
        'def' => "Поставщики",
        'nds' => "Поставщики с НДС",
        'wh' => 'Склады'
      ];
      ?>
      <h3>Сводка по объемам закупки</h3>
      <table style="width: 100%;" class="table table-stripped">
      <?
        foreach ( $suppGroups as $group => $supps ){
          ?>
          <tr style="height: 40px;">
            <td><b><?=$dict[$group]?> (<? echo number_format( round($groupsSum[$group]), 0, ' ', ' ' ); ?>)</b></td>
          </tr>
          <?
          foreach ( $supps as $id ):
            if ( empty($sums[$id]) ) continue;
            ?>
            <tr>
              <td><?=$sums[$id]['name']?></td>
              <td><? echo number_format( round($sums[$id]['sum']), 0, ' ', ' ' ); ?></td>
            </tr>
            <?
          endforeach;
        }
      ?>
      </table>
      <hr>
      <?
    }
}
global $USER;

if (!CModule::IncludeModule("panel.manager") ||
	!CModule::IncludeModule("iblock") ||
	!CModule::IncludeModule("catalog")) {
	echo "<h2 class='color'><span>Не удалось получить список моделей(</span></h2>
		  <p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>";
	return;
}

if ($_REQUEST['get_shipment_list'] == 'Y' && $_REQUEST['token'] == '2a5363b9f1454a040d2309e8f8b2b629') {
	$purchaseDisplay = new PurchaseDisplayAjax();
	$shipmentList = $purchaseDisplay->getShipmentList();

	$reserve = [];
	foreach ($shipmentList as $item) {
		$reserve[$item['WAREHOUSE_ID']][$item['ARTICLE']] += 1;
	}

	$APPLICATION->RestartBuffer();
	echo json_encode($reserve, JSON_UNESCAPED_UNICODE);
	die;
} else {
	$purchaseDisplay = new PurchaseDisplayAjax();
	$purchaseDisplay->execute();
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
