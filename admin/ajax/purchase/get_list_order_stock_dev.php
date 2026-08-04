<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
class PurchaseDisplayManager
{
    private $website;
    private $arResult = [];
    private $warehouseSupplier = [];
    
    private $siteSuppliersMap = [
        's1' => [47, 128],
        's2' => [44],
    ];

    public function __construct()
    {
        global $DB;
        $this->DB = $DB;
        if (!CModule::IncludeModule("panel.manager") || 
            !CModule::IncludeModule("iblock") || 
            !CModule::IncludeModule("catalog")) {
            echo "<h2 class='color'><span>Не удалось получить список моделей(</span></h2>
                  <p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>";
            return;
        }
        $this->initWebsite();
        $this->loadTradePlatforms();
        $this->loadSettings();
    }

    private function initWebsite()
    {
        if (isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3"))) {
            $this->website = array($_POST["website"]);
        } else {
            $this->website = array("s1", "s2", "s3");
        }
    }

    private function loadSettings()
    {
		$this->priceService = PanelManager::getPriceManager();
		$this->settingsTp = $this->priceService->getAllTradingSettings();
    }
	
    private function loadTradePlatforms()
    {
        $strSql = "SELECT * FROM b_sale_tp";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);
        
        while ($row = $results->Fetch()) {
            $this->arResult["TRADING_LIST"][$row["ID"]] = $row["NAME"];
        }
    }
	
    public function execute()
    {
        global $USER;
        
        if (!$this->checkAccess($USER)) {
            return;
        }

        $start = debug_microtime_float();

        if ($this->initModules()) {
            $this->processOrders();
			$this->loadSuppliers();
            $this->processArticles();
            $this->processStock();
            $this->processShipment();
            
            $this->displayResults();
        } else {
            $this->displayError();
        }

        $end = debug_microtime_float();
        prent($end - $start, 0, 1);
    }

    private function checkAccess($USER)
    {
        $arGroups = $USER->GetUserGroupArray();
        return ($USER->isAdmin() || in_array(6, $arGroups));
    }

    private function initModules()
    {
        return CModule::IncludeModule("panel.manager") 
            && CModule::IncludeModule("iblock") 
            && CModule::IncludeModule("catalog");
    }

    private function processOrders()
    {
        $objService = new OrderService;
        $objService->getPropOrderFlg = false;

        $arFilter = array(
            "LID" => $this->website,
            "STATUS_ID" => array("SE", "TA", "CO", "CL"),
            "!CANCELED" => "Y",
        );

        CModule::IncludeModule('sale');
        $arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
        
		$arIDs = array_column($arOrder, "ID");
        $arTradePlatform = [];
        
        if (is_array($arIDs) && count($arIDs) > 0) {
            $strSql = "SELECT ORDER_ID, TRADING_PLATFORM_ID FROM b_sale_tp_order WHERE ORDER_ID IN ('" . implode("','", $arIDs) . "')";
            $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);
            
            while ($row = $results->Fetch()) {
                $arTradePlatform[$row["ORDER_ID"]] = $row["TRADING_PLATFORM_ID"];
            }
        }
		
        foreach ($arOrder as $key => $arItem) {
            $tradingPlatformId = $arTradePlatform[$arItem["ID"]] ?? '';
                    
			foreach ($arItem["BASKET"] as $k => $basket) {
				for ($i = 1; $i <= $basket["QUANTITY"]; $i++) {
					$this->arResult["ITEMS"][] = array(
						"ID" => $arItem["ID"],
						"PRODUCT_ID" => $basket["PRODUCT_ID"],
						"SITE_ID" => $arItem["LID"],
						"COMMENTS" => $arItem["COMMENTS"],
						"DELIVERY_ID" => $arItem["DELIVERY_ID"],
						"STATUS_ID" => $arItem["STATUS_ID"],
						"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
						"DATE_CREATE" => $arItem['DATE_INSERT'],
						"TRADING_PLATFORM_ID" => $tradingPlatformId,
					);
				}
			}
		}

        $this->arResult["ITEMS"] = sort_nested_arrays($this->arResult["ITEMS"], array("ID" => "asc"));
    }

    private function loadSuppliers()
    {
        $objSupplier = new CPanelSupplier;
        $this->arResult["SUPPLIER_LIST"] = $objSupplier->getList();
        
        // Данные поставщиков
        foreach ($this->arResult["SUPPLIER_LIST"] as $arSup) {
			if ($arSup["is_warehouse"] == 'Y') {
				$this->warehouseSupplier[] = $arSup["id"];
			}
        }
    }
	
    private function processArticles()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            $objRes = CIBlockElement::GetList(
                array(), 
                array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]), 
                false, 
                false, 
                array('PROPERTY_CML2_ARTICLE')
            );
            
            if ($res = $objRes->GetNext()) {
                $arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
            }
			
			$arItem["FAKE_TRADING_PLATFORM_NAME"] = $this->getFakeTradingName($arItem);
        }
        unset($arItem);
    }
	
	private function getFakeTradingName($arItem)
	{
		if ($arItem['SITE_ID'] == 's1' && $arItem['STATUS_ID'] == 'TA') {
			return 'SITES_NKZ';
		}
		if ($arItem['SITE_ID'] == 's2' && $arItem['STATUS_ID'] == 'TA') {
			return 'SITES_NEMIGA';
		}
		return $this->arResult["TRADING_LIST"][$arItem['TRADING_PLATFORM_ID']];
	}
	
    private function processStock()
    {
        global $DB;
        
        $strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('47', '44', '71')";
		$strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('" . implode("','", $this->warehouseSupplier) . "')";
        $results = $DB->Query($strSql, false, $err_mess__LINE__);
        
        $this->arStock = [];
        while ($row = $results->Fetch()) {
            for ($i = 1; $i <= $row["count"]; $i++) {
                $this->arStock[$row["supplier_id"]][$row["model"]][] = $row;
            }
        }

        /*$activeSiteSuppliersMap = [];
        foreach ($this->website as $siteId) {
            if (isset($this->siteSuppliersMap[$siteId])) {
                $activeSiteSuppliersMap[$siteId] = $this->siteSuppliersMap[$siteId];
            }
        }*/

        $this->filterItemsByStock($arStock, $activeSiteSuppliersMap);
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
			} elseif ($arItem['SITE_ID'] == 's2' && $arItem['STATUS_ID'] == 'TA' && $settingsTp['warehouse_sites']['s2_nemiga']) {
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

			//unset($this->arResult["ITEMS"][$key]);
			
			$key_stock = array_keys($this->arStock[$warehouseId][$arItem["ARTICLE"]]);
			unset($this->arStock[$warehouseId][$arItem["ARTICLE"]][$key_stock[0]]);
			
			if (is_array($this->arStock[$warehouseId][$arItem["ARTICLE"]]) && count($this->arStock[$warehouseId][$arItem["ARTICLE"]]) == 0) {
				unset($this->arStock[$warehouseId][$arItem["ARTICLE"]]);
			}

		} else {
			unset($this->arResult["ITEMS"][$key]);
		}
    }
	
	
    private function filterItemsByStock_________($arStock, $activeSiteSuppliersMap)
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if (empty($arItem["ARTICLE"])) {
                unset($this->arResult["ITEMS"][$key]);
                continue;
            }

            $siteId = $arItem["SITE_ID"];
            $availableSuppliers = $activeSiteSuppliersMap[$siteId] ?? [];

            if (empty($availableSuppliers)) {
                unset($this->arResult["ITEMS"][$key]);
                continue;
            }

            $foundSupplier = null;
            foreach ($availableSuppliers as $supplierId) {
                if (isset($arStock[$supplierId][$arItem["ARTICLE"]]) &&
                    !empty($arStock[$supplierId][$arItem["ARTICLE"]])) {
                    $foundSupplier = $supplierId;
                    break;
                }
            }

            if (!$foundSupplier) {
                unset($this->arResult["ITEMS"][$key]);
                continue;
            }

            if (is_array($arStock[$foundSupplier][$arItem["ARTICLE"]])) {
                $keys = array_keys($arStock[$foundSupplier][$arItem["ARTICLE"]]);
                if (!empty($keys)) {
                    unset($arStock[$foundSupplier][$arItem["ARTICLE"]][$keys[0]]);

                    if (empty($arStock[$foundSupplier][$arItem["ARTICLE"]])) {
                        unset($arStock[$foundSupplier][$arItem["ARTICLE"]]);
                    }
                }
            }
        }
        unset($arItem);prent($this->arResult["ITEMS"]);
    }

    private function processShipment()
    {
        $this->arResult["SHIPMENT"] = array();
        
        foreach ($this->arResult["ITEMS"] as $key => $arItem) {
            $this->arResult["SHIPMENT"][$arItem['DATE_CREATE']][$arItem["SITE_ID"]][$arItem["ARTICLE"]][] = $arItem;
        }
        
        krsort($this->arResult["SHIPMENT"]);
    }

    private function displayResults()
    {
        global $USER;
        
        if ($_REQUEST["xls"] == "Y" || $USER->getID() == 11587) {
            $this->generateExcel();
        }

        $displayItems = $this->arResult['ITEMS'];
        usort($displayItems, function($a, $b) {
            return strtotime($a['DATE_CREATE']) - strtotime($b['DATE_CREATE']);
        });

        if (is_array($this->arResult["SHIPMENT"]) && count($this->arResult["SHIPMENT"]) > 0):
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
                                <td><a href="/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?= $item["ID"] ?>"><?= $item["ORDER_NUMBER_ID"] ?></a></td>
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

    private function generateExcel()
    {
        require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

        $arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D", 4 => "E");
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();

        $sheet->setTitle("tempus");
        $sheet->getStyle("A:D")->getFont()->setName("Arial");
        $sheet->getStyle("A:D")->getFont()->setSize(10);

        $i = 1;
        foreach ($this->arResult["SHIPMENT"] as $date => $arSite) {
            foreach ($arSite as $site_id => $arPrice) {
                foreach ($arPrice as $key => $arItem) {
                    $col_num = 0;
                    $sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ARTICLE"]);
                    $col_num++;
                    $sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["ID"]);
                    $col_num++;
                    $sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["DELIVERY_ID"]);
                    $col_num++;
                    $sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["COMMENTS"]);
                    $col_num++;
                    $i++;
                }
            }
        }

        $writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $writer->save("/var/www/bitrix/data/www/tempusshop.ru/upload/purchase.xlsx", __FILE__);
    }

    private function displayError()
    {
        ?>
        <h2 class="color"><span>Не удалось получить список моделей(</span></h2>
        <p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
        <?php
    }
}

$purchaseManager = new PurchaseDisplayManager();
$purchaseManager->execute();
?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
