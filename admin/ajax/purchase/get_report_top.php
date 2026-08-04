<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

class PurchaseTopDisplay
{
    private $DB;
    private $website;
    private $arStockCode = ["UPDATE_STOCK_RU","UPDATE_STOCK_BY","UPDATE_STOCK_PL","UPDATE_STOCK_MSK"];
    private $checkSec = 600;
    private $arSuppStockID = [];
    private $arResult = [];
    private $arArt = [];
    private $arArtIDs = [];
    private $arPurchase = [];
    private $arStock = [];
    private $arPrice = [];
    private $arMinPrice = [];
    private $arMinPrice_n = [];
    private $objService;
    private $objSupplier;
    private $reserved = [];

    public function __construct()
    {
        global $DB;
        $this->DB = $DB;
    }

    public function execute()
    {
        $start = debug_microtime_float();
        
        $this->handleRequest();
        $this->checkSite();
        $this->checkStocks();
        
        if ($this->initModules()) {
            $this->loadSettings();
            $this->loadExistingPurchases();
            $this->loadTopModels();
            $this->setStockIds();
            
			$this->gerReserve();
			$this->loadStockData();
			//$this->prepareStockData();
            $this->adjustTopItemsByStock();
            $this->loadNonStockData();
            $this->adjustTopItemsByStockRemoval();
            $this->adjustTopItemsByExistingPurchases();
            $this->processOrders();
            $this->adjustPricesByOrders();
            $this->loadSuppliers();
            $this->expandTopItems();
            $this->processPriceDeviation();
            $this->determineActualPrices();
            $this->groupBySupplier();
            $this->sortAlternativePrices();
            $this->renderOutput();
        } else {
            $this->renderError();
        }
        
        $end = debug_microtime_float() - $start;
        prent("Время - " . $end, 0, 1);
    }

    private function handleRequest()
    {
        if (isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s1_nkz"))) {
            $this->website = $_POST["website"];
        }
    }

    private function checkSite()
    {
        if (!in_array($this->website, array("s1", "s2", "s1_nkz"))) {
            echo "<p>Выберите сайт</p>";
            die;
        }
    }

    private function checkStocks()
    {
        $strSql = "SELECT * FROM ci_options WHERE code IN ('" . implode("','", $this->arStockCode) . "')";
        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);
        $arError = [];
        
        while ($row = $results->Fetch()) {
            $timeStock = strtotime($row["timestamp"]);
            if (time() > ($timeStock + $this->checkSec)) {
                $arError[] = "Склад {$row["code"]} загружен более {$this->checkSec} секунд назад.";
            }
        }
        
        if (count($arError) > 0) {
            foreach ($arError as $error) {
                echo "<p>{$error}</p>";
            }
            die;
        }
    }

    private function initModules()
    {
        return CModule::IncludeModule("panel.manager") && 
               CModule::IncludeModule("iblock") && 
               CModule::IncludeModule("catalog");
    }

    private function loadSettings()
    {
        $this->objService = new OrderService;
        $this->objSupplier = new CPanelSupplier;
        
        $arSettings = CProSet::getOption("TOP_ITEMS_" . $this->website);
        $arSettings = json_decode($arSettings, true);
        $this->arResult["purchaseDay"] = $arSettings["purchase_day"];
    }

    private function loadExistingPurchases()
    {
		$strSql = "SELECT * FROM ci_purchase WHERE status = 'T' AND active = 'Y' AND site_id = '" . 
				  $this->DB->ForSql($this->website) . "'";
		$results = $this->DB->Query($strSql, false, $err_mess__LINE__);
		
		while ($row = $results->Fetch()) {
			$this->arPurchase[$row["product_id"]] = isset($this->arPurchase[$row["product_id"]]) 
				? $this->arPurchase[$row["product_id"]] + 1 
				: 1;
		}
    }

    private function loadTopModels()
    {
        $strSql = "SELECT * FROM ci_top_models WHERE site_id = '" . $this->DB->ForSql($this->website) . "'";
        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);
        
        while ($row = $results->Fetch()) {
            $this->arResult["ITEMS"][] = array(
                "ID" => $row["id"],
                "ARTICLE" => $row["model"],
                "BITRIX_ID" => $row["bitrix_id"],
                "QUANTITY" => ceil(($row["sell_quantity"] / (365 / $this->arResult["purchaseDay"]))),
            );
            $this->arArt[$row["model"]] = $row["model"];
            $this->arArtIDs[$row["bitrix_id"]] = $row["model"];
        }
    }

    private function setStockIds()
    {
        switch ($this->website) {
            case "s1":
                $this->arSuppStockID = [47];
                break;
            case "s2":
                $this->arSuppStockID = [149];
                break;
            case "s1_nkz":
                $this->arSuppStockID = [128];
                break;
        }
    }

	private function gerReserve()
    {
		
        if (!is_array($this->arArt) || count($this->arArt) == 0) {
            return;
        }
		
		$reserveService = PanelManager::getOrderReservedManager();
		$arReserve = $reserveService->getReservedByArticles($this->arArt, false, false);
		
		$priceService = PanelManager::getPriceManager();
		$tpSettings = $priceService->getAllTradingSettings();

		foreach ($arReserve as $reserve) {
			if(!$tpSettings[$reserve['TRADING_PLATFORM_ID']]) continue;
			$settings = $tpSettings[$reserve['TRADING_PLATFORM_ID']];
			
			if ($settings['warehouse_id']) {
				$this->reserved[$settings['warehouse_id']][$reserve['ARTICLE']] += $reserve['RESERVED'];
			} elseif ($settings['warehouse_sites']) {
				$warehouseId = false;
				
				if ($reserve['TRADING_PLATFORM_ID'] == 23 && $settings['warehouse_sites']['s1_nkz']) {
					$warehouseId = $settings['warehouse_sites']['s1_nkz'];
				} elseif ($reserve['TRADING_PLATFORM_ID'] == 24 && $settings['warehouse_sites']['s2_nemiga']) {
					$warehouseId = $settings['warehouse_sites']['s2_nemiga'];
				} else {
					$warehouseId = $settings['warehouse_sites'][$reserve['SITE_ID']] ?? false;
				}
				
				if ($warehouseId) {
					$this->reserved[$warehouseId][$reserve['ARTICLE']] += $reserve['RESERVED'];
				}
			}
		}
		//prent($this->reserved);
	}
	
    private function loadStockData()
    {
        if (!is_array($this->arArt) || count($this->arArt) == 0) {
            return;
        }

        $strSql = "SELECT * FROM ci_price WHERE model IN ('" . implode("','", $this->arArt) . "') 
                   AND supplier_id IN ('" . implode("','", $this->arSuppStockID) . "')";
        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);
        
        while ($row = $results->Fetch()) {
			if ($this->reserved[$row["supplier_id"]][$row["model"]]) {
				$countItems = $row["count"] - $this->reserved[$row["supplier_id"]][$row["model"]];
				
				if ($countItems <= 0) {
					//prent($row["model"]);
					$row["count"] = 0;
					//continue;
				}
			}
            $this->arResult["checkSkld"][$row["model"]] = $row["count"];
            
            if ($row["count"] > 1) {
                for ($i = 1; $i <= $row["count"]; $i++) {
                    $tmp_row = $row;
                    $tmp_row["count"] = 1;
                    $this->arStock[$row["supplier_id"]][$row["model"]][] = $tmp_row;
                }
            } else {
                $this->arStock[$row["supplier_id"]][$row["model"]][] = $row;
            }
        }
    }

    private function adjustTopItemsByStock()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (isset($this->arResult["checkSkld"][$arItem["ARTICLE"]]) && 
                $this->arResult["checkSkld"][$arItem["ARTICLE"]] > 0) {
                if ($arItem["QUANTITY"] <= $this->arResult["checkSkld"][$arItem["ARTICLE"]]) {
                    unset($this->arResult["ITEMS"][$key]);
                } else if ($arItem["QUANTITY"] > $this->arResult["checkSkld"][$arItem["ARTICLE"]]) {
                    $arItem["QUANTITY"] = $arItem["QUANTITY"] - $this->arResult["checkSkld"][$arItem["ARTICLE"]];
                }
            }
        }
    }

    private function loadNonStockData()
    {
        if (!is_array($this->arArt) || count($this->arArt) == 0) {
            return;
        }

        $skipSup = $this->arSuppStockID;
        $tmp = $this->objSupplier->getList(array("opt_supplier" => "Y"));
        
        foreach ($tmp as $arItem) {
            $skipSup[] = $arItem["id"];
        }

        $add_filter = "";
        if (is_array($skipSup) && count($skipSup) > 0) {
            $add_filter = " AND supplier_id NOT IN ('" . implode("','", $skipSup) . "')";
        }

        $strSql = "SELECT * FROM ci_price WHERE model IN ('" . implode("','", $this->arArt) . "') {$add_filter}";

        if ($this->website == "s1") {
            $strSql .= " AND active_ru = 'Y'";
        } elseif ($this->website == "s2") {
            $strSql .= " AND active_by = 'Y'";
        } elseif ($this->website == "s1_nkz") {
            $strSql .= " AND active_ru = 'Y'";
        }

        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);

        while ($row = $results->Fetch()) {
			if ($this->reserved[$row["supplier_id"]][$row["model"]]) {
				$countItems = $row["count"] - $this->reserved[$row["supplier_id"]][$row["model"]];
				
				if ($countItems <= 0) {
					//prent($row["model"]); 
					//$row["count"] = 0;
					continue;
				}
			}
            $this->arPrice[$row["model"]][$row["id"]] = $row;

            if (!isset($this->arMinPrice[$row["model"]]) || $this->arMinPrice[$row["model"]] > $row["price"]) {
                $this->arMinPrice[$row["model"]] = $row["price"];
            }
            if (!isset($this->arMinPrice_n[$row["model"]]) || $this->arMinPrice_n[$row["model"]] > $row["price_n"]) {
                $this->arMinPrice_n[$row["model"]] = $row["price_n"];
            }
        }
    }

    private function adjustTopItemsByStockRemoval()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (!$arItem["ARTICLE"]) {
                unset($this->arResult["ITEMS"][$key]);
                continue;
            }

            foreach ($this->arSuppStockID as $k => $supp_id) {
                if (isset($this->arStock[$supp_id][$arItem["ARTICLE"]]) && 
                    count($this->arStock[$supp_id][$arItem["ARTICLE"]]) > 0) {
                    
                    $arItem["QUANTITY"] -= 1;
                    
                    if ($arItem["QUANTITY"] <= 0) {
                        unset($this->arResult["ITEMS"][$key]);
                    }
                    
                    if (is_array($this->arStock[$supp_id][$arItem["ARTICLE"]])) {
                        $key_stock = array_keys($this->arStock[$supp_id][$arItem["ARTICLE"]]);
                        unset($this->arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
                    }

                    if (is_array($this->arStock[$supp_id][$arItem["ARTICLE"]]) && 
                        count($this->arStock[$supp_id][$arItem["ARTICLE"]]) == 0) {
                        unset($this->arStock[$supp_id][$arItem["ARTICLE"]]);
                    }
                }
            }
        }
        unset($arItem);
    }

    private function adjustTopItemsByExistingPurchases()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (isset($this->arPurchase[$arItem["BITRIX_ID"]])) {
                $arItem["QUANTITY"] -= $this->arPurchase[$arItem["BITRIX_ID"]];

                if ($arItem["QUANTITY"] <= 0) {
                    unset($this->arResult["ITEMS"][$key]);
                }
            }
        }
        unset($arItem);
    }

    private function processOrders()
    {
        $arFilter = array(
            "STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CL"),
            "!CANCELED" => "Y",
        );
        
        $arOrder = $this->objService->getOrderCache(array("DATE_INSERT" => "ASC"), $arFilter);

        foreach ($arOrder as $key => $arItem) {
            foreach ($arItem["BASKET"] as $arBasket) {
                if (isset($this->arArtIDs[$arBasket["PRODUCT_ID"]])) {
                    $model = $this->arArtIDs[$arBasket["PRODUCT_ID"]];
					
					if ($arItem["LID"] == 's1' && $arItem["STATUS_ID"] == 'TA') {
						$lid = 's1_nkz';
					} else {
						$lid = $arItem["LID"];
					}
					//$lid = $arItem["LID"];
                    if (!isset($this->arResult["ORDER_ITEMS"][$lid][$model])) {
                        $this->arResult["ORDER_ITEMS"][$lid][$model] = 0;
                    }
                    $this->arResult["ORDER_ITEMS"][$lid][$model] += $arBasket["QUANTITY"];
                }
            }
        }
    }

    private function adjustPricesByOrders()
    {
        $arSuppSite = array(149 => "s2", 47 => "s1", 74 => "s2", 128 => "s1_nkz");

        foreach ($this->arPrice as $key => &$arItem) {
            foreach ($arItem as $k => $item) {
                if (isset($arSuppSite[$item["supplier_id"]]) && 
                    isset($this->arResult["ORDER_ITEMS"][$arSuppSite[$item["supplier_id"]]][$item["model"]]) &&
                    $this->arResult["ORDER_ITEMS"][$arSuppSite[$item["supplier_id"]]][$item["model"]] > 0) {
                    
                    $cntOrder = $this->arResult["ORDER_ITEMS"][$arSuppSite[$item["supplier_id"]]][$item["model"]];
                    $this->arPrice[$key][$k]["count"] = $item["count"] - $cntOrder;

                    if ($this->arPrice[$key][$k]["count"] <= 0) {
                        unset($this->arPrice[$key][$k]);
                    }
                }
            }
        }
        unset($arItem);
    }

    private function loadSuppliers()
    {
        $this->arResult["SUPPLIER_LIST"] = $this->objSupplier->getList();
        $this->arWarehouseStock = [];
        foreach ($this->arResult["SUPPLIER_LIST"] as $arSup) {
            $this->arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
            $this->arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
            $settings = json_decode($arSup["settings"], true);
            
            foreach ($settings["brand"] as $k => $v) {
                $this->arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
            }
			
			if ($arSup["is_warehouse"] == 'Y') {
				$this->arWarehouseStock[] = $arSup["id"];
			}
        }

        $this->arResult["PRICE_DEVIATION"] = CProSet::getOption("PRICE_DEVIATION_TOP");
    }

    private function expandTopItems()
    {
        $tmp = [];
        foreach ($this->arResult["ITEMS"] as $key => $arItem) {
            for ($i = 1; $i <= $arItem["QUANTITY"]; $i++) {
                $tmp[] = $arItem;
            }
        }
        $this->arResult["ITEMS"] = $tmp;
    }

    private function processPriceDeviation()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            $arr = array();

            if (!isset($this->arPrice[$arItem["ARTICLE"]])) {
                continue;
            }

            foreach ($this->arPrice[$arItem["ARTICLE"]] as $k => $row) {
                $arr[$row["id"]] = $row;
                $arr[$row["id"]]["top_id"] = $arItem["ID"];
                $arr[$row["id"]]["product_id"] = $arItem["BITRIX_ID"];
				
                if (in_array($row["supplier_id"], $this->arWarehouseStock)) {
					//prent($row);
					$arr[$row["id"]]["priority"] = 1;
					$arItem["IN_STOCK"] = true;
				} elseif (isset($this->arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]])) {
                    $arr[$row["id"]]["priority"] = $this->arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]];
                } elseif (in_array($row["supplier_id"], array(149, 47, 71, 82, 103))) {
                    $arr[$row["id"]]["priority"] = 1;
                    if ($this->website == "s2" && $row["supplier_id"] == 103) {
                        $arr[$row["id"]]["priority"] = 0;
                    }
                } else {
                    $arr[$row["id"]]["priority"] = 10;
                }

                //if (in_array($row["supplier_id"], array(149, 47, 71, 82, 103))) {
                //    $arItem["IN_STOCK"] = true;
                //}
            }

            if (is_array($arr) && count($arr) > 0) {
                foreach ($arr as $k => $v) {
                    if (isset($this->arMinPrice[$v["model"]]) && $this->arResult["PRICE_DEVIATION"]) {
                        if ($this->website == "s2") {
                            $sets = json_decode(CProSet::getOption("SETTINGS_RRC"), true)['by']['price_type'];
                            if ($sets == 'price_n') {
                                $v["price"] = $v["price_n"];
                                $arr[$k]["price"] = $arr[$k]["price_n"];
                                $diff = $v["price"] / $this->arMinPrice_n[$v["model"]] * 100 - 100;
                            } else {
                                $diff = $v["price"] / $this->arMinPrice[$v["model"]] * 100 - 100;
                            }
                        } else {
                            $diff = $v["price"] / $this->arMinPrice[$v["model"]] * 100 - 100;
                        }

                        if ($diff <= $this->arResult["PRICE_DEVIATION"] || 
                            in_array($row["supplier_id"], $this->arWarehouseStock)) {
                            $arItem["DEVIATION"][] = $v;
                            $last = $k;
                        }
                    }
                }
if ($arItem["ARTICLE"] == 'RA-AA0C04B') {
	//prent($arr);
	//in_array($row["supplier_id"], $this->arWarehouseStock)
}
                if (isset($arItem["DEVIATION"]) && is_array($arItem["DEVIATION"]) && count($arItem["DEVIATION"]) > 0) {
                    $arItem["DEVIATION"] = $this->sortNestedArrays($arItem["DEVIATION"], array("priority" => "asc", "price" => "asc"));
                    $arItem["OPTIMAL_DEVIATION"] = $arItem["DEVIATION"][0]["id"];
                }

                if (isset($this->arPrice[$arItem["ARTICLE"]])) {
                    foreach ($this->arPrice[$arItem["ARTICLE"]] as $k => &$v) {
                        $v["top_id"] = $arItem["ID"];
                        $v["product_id"] = $arItem["BITRIX_ID"];
                    }
                    unset($v);
                    $arItem["PRICELIST"] = $this->arPrice[$arItem["ARTICLE"]];
                }
            } else {
                if (isset($this->arPrice[$arItem["ARTICLE"]])) {
                    $arItem["PRICELIST"] = $this->arPrice[$arItem["ARTICLE"]];
                }
                $arItem["NOT_STOCK"] = "Y";
            }
        }
        unset($arItem);
		
		//prent($this->arResult["ITEMS"]);
    }

    private function determineActualPrices()
    {
        $this->arResult["PRICE"] = array();

        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (!isset($arItem["PRICELIST"])) {
                continue;
            }

            $_ar_min = null;
            foreach ($arItem["PRICELIST"] as $arr) {
                if ($_ar_min === null || $arr["price"] < $_ar_min["price"]) {
                    $_ar_min = $arr;
                }
            }

            if (isset($arItem["OPTIMAL_DEVIATION"]) && 
                isset($arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]]) && 
                $_ar_min["id"] != $arItem["OPTIMAL_DEVIATION"]) {
                $_ar_min = $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]];
            }

            $min = $_ar_min["id"];

            if (isset($arItem["PRICELIST"][$min])) {
                $arItem["PRICE_ACTUAL"] = $arItem["PRICELIST"][$min];
                unset($arItem["PRICELIST"][$min]);
            }
        }
        unset($arItem);
    }

    private function groupBySupplier()
    {
        $arSort = array();
        
        foreach ($this->arResult["ITEMS"] as $key => $arItem) {
            if (isset($arItem["PRICE_ACTUAL"])) {
                $tmp = $arItem["PRICE_ACTUAL"];
                $arSort[$tmp["supplier_id"]] = $this->arResult["SUPPLIER_SORT"][$tmp["supplier_id"]];
                $this->arResult["PRICE_GROUP"][$tmp["supplier_id"]][] = $arItem["PRICE_ACTUAL"];
            } elseif (!isset($arItem["IN_STOCK"]) || $arItem["IN_STOCK"] != "Y") {
                $this->arResult["PRICE_NO_SUPP"][] = $arItem;
            }
            
            foreach ($arItem["PRICELIST"] as $alt) {
                $this->arResult["PRICE_ALTERNATIVE"][$alt["model"]][$alt["id"]] = $alt;
            }
        }
        unset($arItem);
        
        asort($arSort);
        $tmp = array();

        foreach ($arSort as $key => $val) {
            $tmp[$key] = $this->arResult["PRICE_GROUP"][$key];
        }
        $this->arResult["PRICE_GROUP"] = $tmp;
    }

    private function sortAlternativePrices()
    {
        $tmp = array();
        
        foreach ($this->arResult["PRICE_ALTERNATIVE"] as $model => $ar) {
            if (is_array($ar) && count($ar) > 1) {
                $arSortAlt = array();
                foreach ($ar as $code => $arItem) {
                    $arSortAlt[$code] = $arItem["price"];
                }
                asort($arSortAlt);
                foreach ($arSortAlt as $key => $val) {
                    $tmp[$model][$key] = $this->arResult["PRICE_ALTERNATIVE"][$model][$key];
                }
            } else {
                $tmp[$model] = $ar;
            }
        }
        $this->arResult["PRICE_ALTERNATIVE"] = $tmp;
    }

    private function renderOutput()
    {
        foreach ($this->arResult["PRICE_GROUP"] as $key => $arItem):
            $txt = "";
?>
            <div class="col-sm-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50%"><span class="btn-clipboard" style="cursor:pointer; color: #337ab7;" data-clipboard-target="#textarea-purchase-<?= $key ?>"><?= $this->arResult["SUPPLIER_NAME"][$key] ?></span></th>
                            <th style="width: 25%">Цена</th>
                            <th style="width: 25%"><button type="button" class="btn btn-sm btn-default add-all-purchase-top" style="width: 100%;padding: 1px;">OK</button></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arItem as $article => $arPrice): ?>
                            <?php $txt .= $arPrice["model"] . "\r\n"; ?>
                            <tr>
                                <td><?= $arPrice["model"] ?></td>
                                <?php if ($this->website == "s2"): ?>
                                    <?php $sets = json_decode(CProSet::getOption("SETTINGS_RRC"), true)['by']['price_type']; ?>
                                    <?php if ($sets == 'price_n'): ?>
                                        <td><?= $arPrice["price_n"] ?></td>
                                    <?php else: ?>
                                        <td><?= $arPrice["price"] ?></td>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <td><?= $arPrice["price"] ?></td>
                                <?php endif; ?>
                                <td class="right">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-default add-purchase-top" data-id="<?= $arPrice["id"] ?>" data-topid="<?= $arPrice["top_id"] ?>" data-productid="<?= $arPrice["product_id"] ?>">OK</button>
                                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" <?php if (!isset($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]])): ?>disabled<?php endif; ?>>
                                            <span class="caret"></span>
                                            <span class="sr-only">Toggle Dropdown</span>
                                        </button>
                                        <?php if (isset($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) && count($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) > 0): ?>
                                            <ul class="dropdown-menu">
                                                <?php
                                                $arPriceAlt = array_values($this->arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]);
                                                $min = $this->arMinPrice[$arPrice["model"]];
                                                ?>
                                                <?php foreach ($arPriceAlt as $key_alt => $arAlt): ?>
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
                                                    <li class="<?= $class ?>"><a href="#" class="add-purchase-top" data-id="<?= $arAlt["id"] ?>" data-topid="<?= $arAlt["top_id"] ?>" data-productid="<?= $arAlt["product_id"] ?>"><?= $this->arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]] ?> - <?= $arAlt["price"] ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <textarea id="textarea-purchase-<?= $key ?>" style="position: fixed;left: -9999px;"><?= $txt ?></textarea>
        <?php endforeach; ?>
        
        <script>
            new Clipboard('.btn-clipboard');
        </script>
        
        <?php if (isset($this->arResult["PRICE_NO_SUPP"]) && is_array($this->arResult["PRICE_NO_SUPP"]) && count($this->arResult["PRICE_NO_SUPP"]) > 0): ?>
            <div class="col-sm-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50%">Нет у поставщиков</th>
                            <th style="width: 30%">Цена</th>
                            <th style="width: 20%"></th>
                        </tr>
                    </thead>
                    <?php foreach ($this->arResult["PRICE_NO_SUPP"] as $key => $arPrice): ?>
                        <tbody>
                            <tr data-id="<?= $arPrice["id"] ?>" id="tbl-<?= $arPrice["id"] ?>">
                                <td><?php if ($arPrice["ARTICLE"]): ?><?= $arPrice["ARTICLE"] ?><?php else: ?><?= $arPrice["NAME"] ?><?php endif; ?></td>
                                <td><?= $arPrice["PRICE"] ?></td>
                                <td class="right"></td>
                            </tr>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif;
    }

    private function renderError()
    {
        ?>
        <h2 class="color"><span>Не удалось получить список моделей(</span></h2>
        <p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
        <?php
    }

    private function sortNestedArrays($array, $args)
    {
        if (empty($array) || empty($args)) {
            return $array;
        }
        
        usort($array, function($a, $b) use ($args) {
            foreach ($args as $key => $sort) {
                $res = 0;
                if ($sort == 'asc') {
                    $res = strnatcmp($a[$key], $b[$key]);
                } elseif ($sort == 'desc') {
                    $res = strnatcmp($b[$key], $a[$key]);
                }
                
                if ($res != 0) {
                    return $res;
                }
            }
            return 0;
        });
        
        return $array;
    }
}

$purchaseTopDisplay = new PurchaseTopDisplay();
$purchaseTopDisplay->execute();
?>
</div>
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
?>