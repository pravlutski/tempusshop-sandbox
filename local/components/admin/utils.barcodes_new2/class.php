<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Context;

class BarcodeManagerComponent extends CBitrixComponent
{
    protected $db;
    protected $objUtils;
    protected $objService;
    protected $objProduct;
    protected $request;
    protected $arIDs = [];

    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->db = Application::getConnection();
        $this->objUtils = new CPanelUtils();
        $this->objService = new OrderService();
        $this->objProduct = new CPanelProduct();
        $this->request = Context::getCurrent()->getRequest();
    }

    public function executeComponent()
    {
        try {
            if (!$this->checkAccess()) {
                return;
            }

            $this->loadRequiredModules();
            
            // Обработка AJAX запросов
            $ajaxProcessed = $this->processRequests();
            
            if ($ajaxProcessed !== false) {
                return;
            }
            
            $this->prepareData();
            $this->includeComponentTemplate();

        } catch (Exception $e) {
            // Если это AJAX запрос, возвращаем JSON ошибку
            if ($this->request->isAjaxRequest() || $this->request->get('ajax')) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Type: application/json');
                echo Json::encode(['status' => 'error', 'message' => $e->getMessage()]);
                die();
            }
            ShowError($e->getMessage());
        }
    }
    
    private function checkAccess()
    {
        global $USER;
        return true;
    }
    
    private function prepareData()
    {
        $this->initResultArrays();

        // Получение артикулов
        $this->loadArticles();
    }

    private function initResultArrays()
    {
        $this->arResult = [
            "SORT_ENABLE" => true,
            "SORT_BACKEND" => false,
            "MARKET_STICKER_ERROR" => [],
            "ARTICLE_BX" => [],
            "ID_BX" => [],
            "COUNT_ITEMS" => [],
            "ORDER_W_STICKER" => [],
            "GROUP_ORDER_W_STICKER" => [],
            "ORDER_SOURCE" => [],
            "ORDER_MARKET_NUMBERS" => [],
            "PROP_KEY_MARKET" => false,
            "ERRORS" => [],
            "ITEMS" => [],
            "ORDER_ITEMS" => [],
            "ORDER_ITEMS_MARKET" => [],
            "PURCHASE_ITEMS" => [],
            "EXCLUSIVE_LIST" => [],
            "SUPPLIER_LIST" => [],
            "SUPPLIER_NAME" => [],
            "PURCHASE" => [],
            "SHIPMENT" => [],
            "MS_HISTORY" => [],
            "PURCHASE_LIST" => [],
            "ORDER_PRINT_HISTORY" => [],
            "GROUP_RESULT" => $this->request->getPost("group-result") == "Y",
            "IS_YANDEX" => $this->request->getPost("is-yandex") == "Y",
            "USE_ID" => $this->request->getPost("use-id") == "Y"
        ];
    }
    
    private function loadRequiredModules()
    {
        if (!Loader::includeModule('panel.manager')) {
            throw new Exception('Модуль panel.manager не установлен');
        }
        Loader::includeModule('iblock');
        
        if (!class_exists('OrderPrintManager')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
        }
    }

    private function loadArticles()
    {
        $strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
            FROM b_iblock_element el
            LEFT JOIN b_iblock_element_prop_s16 pr ON el.ID=pr.IBLOCK_ELEMENT_ID
            WHERE el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

        $results = $this->db->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            if (strlen($row["ARTICLE"]) > 0) {
                $this->arResult["ARTICLE_BX"][$row["ID"]] = $row["ARTICLE"];
                $this->arResult["ID_BX"][$row["ARTICLE"]] = $row["ID"];
            }
        }
    }

    public function processRequests()
    {
        $action = $this->request->get('action');
        
        if (!$action) {
            return false;
        }

        // Проверяем, что это AJAX запрос
        if (!$this->request->isAjaxRequest() && !$this->request->get('ajax')) {
            return false;
        }

        // ОЧИСТКА БУФЕРА ВЫВОДА - это ключевое исправление
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        try {
            switch ($action) {
                case 'load_marketplace_orders':
                    $result = $this->processMarketplaceOrders();
                    break;
                case 'load_purchase_orders':
                    $result = $this->loadPurchaseOrders();
                    break;
                case 'load_manual_orders':
                    $result = $this->loadManualOrders();
                    break;
                case 'get_barcode':
                    $result = $this->getBarcode();
                    break;
                case 'set_barcode':
                    $result = $this->setBarcode();
                    break;
                case 'scan_barcode':
                    $result = $this->processBarcodeScan();
                    break;
                default:
                    $result = ['status' => 'error', 'message' => 'Unknown action'];
            }
            
            echo Json::encode($result);
            
        } catch (Exception $e) {
            echo Json::encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        
        die();
    }

    private function processMarketplaceOrders()
    {
        // Ваша логика обработки заказов с маркетплейсов
        $dateTo = $this->request->getPost("date_to");
        $cabinets = $this->request->getPost("cabinet");
        
        if (!empty($dateTo) && is_array($cabinets) && !empty($cabinets)) {
            foreach ($cabinets as $cabinet) {
                if (!in_array($cabinet, ["WB_WR", "WB_IP", "OZON_IP", "YANDEX"])) continue;
                $this->processCabinetOrders($cabinet, $dateTo);
            }
        }
        
        return [
			'status' => 'success', 
			'data' => [
				'orders' => $this->arResult["ORDER_ITEMS_MARKET"],
				'items' => $this->arResult["ITEMS"],
			]
		];
    }
    
    private function processCabinetOrders($cabinet, $dateTo)
    {
        global $DB;

        $arFilter = [
            "LID" => "s1",
            "<=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL")), strtotime($dateTo)),
        ];

        $source = "";
        
        switch ($cabinet) {
            case "WB_WR":
                $arFilter["USER.EMAIL"] = '796079104009249351@emailwb.ru';
                $arFilter["USER_ID"] = 135989;
                $arFilter["STATUS_ID"] = ["CL"];
                $arFilter["!PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER"] = false;
                
                $this->arResult["PROP_KEY_MARKET"] = "MAXYSS_WB_NUMBER";
                $this->arResult["ORDER_SOURCE"][] = "wb";
                $source = "wb";
                break;
                
            case "WB_IP":
                $arFilter["USER.EMAIL"] = '111796079104009249351@emailwb.ru';
                $arFilter["USER_ID"] = 161898;
                $arFilter["STATUS_ID"] = ["CL"];
                $arFilter["!PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER"] = false;
                
                $this->arResult["PROP_KEY_MARKET"] = "MAXYSS_WB_NUMBER";
                $this->arResult["ORDER_SOURCE"][] = "wb";
                $source = "wb";
                break;
                
            case "OZON_IP":
                $arFilter["USER_ID"] = 182118;
                $arFilter["!PROPERTY_VAL_BY_CODE_OZON_NUMBER"] = false;
                $arFilter["STATUS_ID"] = ["SE", "CL"];
                
                $this->arResult["PROP_KEY_MARKET"] = "OZON_NUMBER";
                $this->arResult["ORDER_SOURCE"][] = "ozon";
                $source = "ozon";
                break;
                
            case "YANDEX":
                $arFilter["USER_ID"] = 81140;
                $arFilter["!PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA"] = false;
                $arFilter["STATUS_ID"] = ["SE", "CL"];
                
                $this->arResult["PROP_KEY_MARKET"] = "ORDER_NUMBER_YA";
                $this->arResult["ORDER_SOURCE"][] = "yandex";
                $source = "yandex";
                break;
        }

        $this->arResult["ORDER_SOURCE"] = array_unique($this->arResult["ORDER_SOURCE"]);

        // Получаем заказы
        $arOrder = $this->objService->getOrder([], $arFilter);
        $arOrder = array_reverse($arOrder);

        $currentDateTime = new DateTime();
        $this->processOrders($arOrder, $source, $currentDateTime, $cabinet);

        // Загружаем информацию о товарах
        $this->loadItems();

        // Обрабатываем стикеры в зависимости от кабинета
        $this->processStickers($cabinet);
    }

    private function processOrders($arOrder, $source, $currentDateTime, $cabinet)
    {
        foreach ($arOrder as $key => $order) {
            $insertDateTime = new DateTime($order["DATE_INSERT"]);
            $insertDateTime->setTime(23, 59, 59);
            $interval = $insertDateTime->diff($currentDateTime);
            $insertHour = $interval->h + ($interval->days * 24);
            $insertDate = $insertDateTime->format('d.m');

            $is_group = false;
            if (is_array($order["BASKET"]) && count($order["BASKET"]) > 1) {
                $is_group = true;
            }

            foreach ($order["BASKET"] as $k => $arItem) {
                $this->arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
                if ($arItem["QUANTITY"] > 1) {
                    $is_group = true;
                }

                for ($i = 0; $i < $arItem["QUANTITY"]; $i++) {
                    $this->arResult["ORDER_ITEMS_MARKET"][] = [
                        "ID" => $order["ID"],
                        "ORDER_NUMBER_ID" => $order["ORDER_ID"],
                        "ORDER_MARKET_NUMBER" => $order[$this->arResult["PROP_KEY_MARKET"]],
                        "ORDER_COMMENTS" => $order["COMMENTS"],
                        "PRODUCT_ID" => $arItem["PRODUCT_ID"],
                        "MAXYSS_OP_STICKER" => $order["MAXYSS_OP_STICKER"],
                        "OZON_NUMBER" => $order["OZON_NUMBER"],
                        "ARTICLE" => $this->arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]] ?? '',
                        "SORT_ARTICLE" => $this->arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]] ?? '',
                        "INSERT_DATE" => $insertDate,
                        "INSERT_HOUR" => $insertHour,
                        "SOURCE" => $source,
                        "STICKER" => false,
                        "STICKER_PRINT" => $order["STICKER_PRINT"] ?? 'N',
                        "IS_GROUP" => $is_group,
                    ];
                }
            }

            // Сохраняем номера заказов
            if (in_array($order['MAXYSS_WB_CABINET'] ?? '', ['WR', 'TL'])) {
                $k = ($order['MAXYSS_WB_CABINET'] == 'TL') ? 'WB_IP' : 'WB_WR';
                $this->arResult["ORDER_MARKET_NUMBERS"][$k][] = $order[$this->arResult["PROP_KEY_MARKET"]];
            } else {
                $this->arResult["ORDER_MARKET_NUMBERS"][$source][] = $order[$this->arResult["PROP_KEY_MARKET"]];
            }
        }
    }

    private function loadItems()
    {
        if (empty($this->arIDs)) {
            return;
        }

        $arArticle = [];
        $rs = CIBlockElement::GetList([], ["IBLOCK_ID" => 16, "ID" => $this->arIDs], false, false, 
            ["ID", "PROPERTY_WBARTICLE"]);

        while ($ar = $rs->GetNext()) {
            $arArticle[] = $ar["PROPERTY_WBARTICLE_VALUE"];
        }

        if (empty($arArticle)) {
            return;
        }

        $arFilter = [
            "IBLOCK_ID" => 16,
            "PROPERTY_WBARTICLE" => $arArticle,
        ];

        $arSelect = [
            "ID", "XML_ID", "NAME", "PREVIEW_PICTURE", "DETAIL_PICTURE", 
            "IBLOCK_ID", "DETAIL_PAGE_URL", "PROPERTY_WBARTICLE", 
            "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN"
        ];

        $rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

        while ($ob = $rs->GetNextElement()) {
            $arFields = $ob->GetFields();

            if ($arFields["PREVIEW_PICTURE"]) {
                $arFields["PICTURE_SRC"] = CFile::GetPath($arFields["PREVIEW_PICTURE"]);
            } elseif ($arFields["DETAIL_PICTURE"]) {
                $arFields["PICTURE_SRC"] = CFile::GetPath($arFields["DETAIL_PICTURE"]);
            }

            $this->arResult["ITEMS"][$arFields["ID"]] = $arFields;
            $this->arResult["ARTICLE_BX"][] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
            $this->arResult["IDS_BX"][] = $arFields["ID"];
            $this->arResult["XML_IDS_BX"][] = $arFields["XML_ID"];
        }
    }

    private function processStickers($cabinet)
    {
        if (in_array($cabinet, ["WB_IP", "WB_WR"])) {
            $this->processWBStickers($cabinet);
        } elseif ($cabinet == "OZON_IP") {
            $this->processOzonStickers();
        } elseif ($cabinet == "YANDEX") {
            $this->processYandexStickers();
        }
    }
    
    private function processWBStickers($cabinet)
    {
        if (empty($this->arResult["ORDER_MARKET_NUMBERS"][$cabinet])) {
            return;
        }

        $arNumber = [];
        foreach ($this->arResult["ORDER_MARKET_NUMBERS"][$cabinet] as $order_market_number) {
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_market_number}.svg")) {
                $arNumber[] = $order_market_number;
            }
        }

        if (!empty($arNumber)) {
            // Логика обработки WB стикеров
            // prent($arNumber);
        }

        $cabinetType = str_replace("WB_", "", $cabinet);
        $arStickerWB = getStickerWB($this->arResult["ORDER_MARKET_NUMBERS"][$cabinet], $cabinetType);
        $arStickerOrder = [];

        foreach ($this->arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
            $order_market_number = $arItem["ORDER_MARKET_NUMBER"];
            if ($arStickerWB[$order_market_number] ?? false) {
                $arItem["STICKER"] = $arStickerWB[$order_market_number];
                $arStickerOrder[] = $arStickerWB[$order_market_number];

                // Обновляем свойство заказа если нужно
                if ((!$arItem["MAXYSS_OP_STICKER"] || 
                     ($arItem["MAXYSS_OP_STICKER"] != $arItem["STICKER"]["STICKER_ENCODING"])) && 
                    $arItem["STICKER"]["STICKER_ENCODING"]) {
                    AddOrderProperty(62, $arItem["STICKER"]["STICKER_ENCODING"], $arItem["ID"]);
                }
            }
        }
        unset($arItem);

        $arStickerOrder = sort_nested_arrays($arStickerOrder, ['STICKER_PART_B' => 'asc']);

        foreach ($arStickerOrder as $key => $arItem) {
            $this->arResult["ORDER_W_STICKER"][] = $arItem["ORDER_ID_WB"];
            $this->arResult["COUNT_ITEMS"][$arItem["STICKER_PART_B"]] += 1;
        }

        if (is_array($arStickerWB["ERRORS"] ?? []) && !empty($arStickerWB["ERRORS"])) {
            foreach ($arStickerWB["ERRORS"] as $e) {
                $this->arResult["ERRORS"][] = $e;
            }
        }
    }

    private function processOzonStickers()
    {
        if (empty($this->arResult["ORDER_MARKET_NUMBERS"]["ozon"])) {
            return;
        }

        $arNumber = [];
        foreach ($this->arResult["ORDER_MARKET_NUMBERS"]["ozon"] as $order_market_number) {
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_market_number}.pdf")) {
                $arNumber[] = $order_market_number;
            }
        }

        if (!empty($arNumber)) {
            foreach ($arNumber as $posting_number) {
                $res = setStatusOzon($posting_number, "IP");
                if ($res['ERROR'] ?? false) {
                    foreach ($res['ERROR'] as $e) {
                        $this->arResult["ERRORS"][] = $e;
                    }
                }
            }
        }

        $filename = time() . ".txt";
        file_put_contents("/var/www/bitrix_logs/ozon/{$filename}", json_encode($arNumber));
        
        exec("/usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/bitrix/components/adm/utils.barcodes/ozon_sticker.php filename={$filename}", $output);
        
        $resultSticker = json_decode($output[0] ?? '[]', true) ?: [];
        
        $this->arResult["MARKET_STICKER_ERROR"] = [
            "error" => is_array($resultSticker["error"] ?? []) ? count($resultSticker["error"]) : 'N/A',
            "no_sticker" => is_array($resultSticker["no_sticker"] ?? []) ? count($resultSticker["no_sticker"]) : 'N/A',
            "not_defined" => is_array($resultSticker["not_defined"] ?? []) ? count($resultSticker["not_defined"]) : 'N/A',
        ];

        $this->processStickerErrors($resultSticker);

        foreach ($this->arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
            $order_market_number = $arItem["ORDER_MARKET_NUMBER"];
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_market_number}.pdf")) {
                $arItem["STICKER"] = $order_market_number;
            }
        }
        unset($arItem);
    }

    private function processYandexStickers()
    {
        if (empty($this->arResult["ORDER_MARKET_NUMBERS"]["yandex"])) {
            return;
        }

        $arNumber = [];
        foreach ($this->arResult["ORDER_MARKET_NUMBERS"]["yandex"] as $order_market_number) {
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$order_market_number}.pdf")) {
                $arNumber[] = $order_market_number;
            }
        }

        $filename = time() . ".txt";
        file_put_contents("/var/www/bitrix_logs/yandex/{$filename}", json_encode($arNumber));

        // Раскомментируйте когда будет готова обработка Яндекс
        /*
        exec("/usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/bitrix/components/adm/utils.barcodes/ozon_sticker.php filename={$filename}", $output);
        $resultSticker = json_decode($output[0] ?? '[]', true) ?: [];
        $this->processStickerErrors($resultSticker);
        */

        foreach ($this->arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
            $order_market_number = $arItem["ORDER_MARKET_NUMBER"];
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$order_market_number}.pdf")) {
                $arItem["STICKER"] = $order_market_number;
            }
        }
        unset($arItem);
    }

    private function processStickerErrors($resultSticker)
    {
        if (is_array($resultSticker["error"] ?? []) && !empty($resultSticker["error"])) {
            foreach ($resultSticker["error"] as $e) {
                $this->arResult["ERRORS"][] = $e;
            }
        }

        if (is_array($resultSticker["no_sticker"] ?? []) && !empty($resultSticker["no_sticker"])) {
            foreach ($resultSticker["no_sticker"] as $e) {
                $this->arResult["ERRORS"][] = $e;
            }
        }

        if (is_array($resultSticker["not_defined"] ?? []) && !empty($resultSticker["not_defined"])) {
            foreach ($resultSticker["not_defined"] as $e) {
                $this->arResult["ERRORS"][] = $e;
            }
        }
    }

    private function loadPurchaseOrders()
    {
        // Логика обработки закупок
        return [
            'status' => 'success',
            'data' => [
                'orders' => [
                    [
                        'ID' => 2,
                        'ARTICLE' => 'PURCH-001',
                        'PRODUCT_ID' => 1002,
                        'BARCODE' => '9876543210987',
                        'ORDER_NUMBER' => 'PURCH-123',
                        'COMMENTS' => 'Закупка Минск',
                        'WAREHOUSE_INFO' => 'Минск: 3 шт.'
                    ]
                ],
                'stats' => [
                    'total' => 1,
                    'printed' => 0
                ]
            ]
        ];
    }

    private function loadManualOrders()
    {
        $articles = $this->request->get('article_all');
        $articles = explode("\r\n", $articles);
		
        if (!$articles || !is_array()) {
            return ['status' => 'error', 'message' => 'No articles provided'];
        }


	$articles = array_diff($articles, array(''));

	$arArticle = [];
	$arOrderID = [];
	$arOrderYA = [];
	$arOrderOzon = [];

	$arOrderFind = [];
	$arOrderFindOzon = [];
	$arOrderFindYA = [];

	if ( $this->arResult['IS_YANDEX'] ){
		foreach($articles as $k => $article){
			$arOrderYA[] = trim($article);
		}
	}else{
		foreach($articles as $k => $article){
			if(substr_count($article, '-') == 2){
				$arOrderOzon[] = trim($article);
			}else{
				$arOrderID[] = trim($article);
			}
			$arOrderAll[] = trim($article);
		}

	}

	$arOrderID = array_diff($arOrderID, array(''));
	$arOrderOzon = array_diff($arOrderOzon, array(''));
	$arOrderAll = array_diff($arOrderAll, array(''));
	$arOrderYA = array_diff($arOrderYA, array(''));

	$arResult["COUNT_ITEMS"] = array();
	if(count($arOrderID) > 0){
		$arFilter = array(
			"ACCOUNT_NUMBER" => $arOrderID
		);
		if ( $this->arResult['USE_ID'] ){
			$arFilter = array(
				"ID" => $arOrderID
			);
		}
		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		
		$currentDateTime = new DateTime();
		
		foreach($arOrder as $key => $order){
			
			$insertDateTime = new DateTime($order["DATE_INSERT"]);
			$insertDateTime->setTime(23, 59, 59);
			$interval = $insertDateTime->diff($currentDateTime);
			$insertHour = $interval->h + ($interval->days * 24);
			$insertDate = $insertDateTime->format('d.m');

			if ( $this->arResult['USE_ID'] ){
				$arOrderFind[] = $order["ID"];
			} else {
				$arOrderFind[] = $order["ORDER_ID"];
			}
			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"ORDER_QUANTITY" => $arItem["QUANTITY"], 
						"INSERT_DATE" => $insertDate,
						"INSERT_HOUR" => $insertHour,
					);
					$arSort[$order["ORDER_ID"]][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			if (is_array($order["BASKET"])) {
				$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
			} else {
				print_r('ЗАКАЗ '.$order["ORDER_ID"].' НЕ ОБРАБОТАН ПУСТАЯ КОРЗИНА<br>');
			}
		}

	}

	// заказы озона
	if( count($arOrderOzon) > 0) {
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"PROPERTY_VAL_BY_CODE_OZON_NUMBER" => $arOrderOzon
		);
		$objService->getPropOrderFlg = true;
		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		$currentDateTime = new DateTime();
		
		foreach($arOrder as $key => $order){
		
			$insertDateTime = new DateTime($order["DATE_INSERT"]);
			$insertDateTime->setTime(23, 59, 59);
			$interval = $insertDateTime->diff($currentDateTime);
			$insertHour = $interval->h + ($interval->days * 24);
			$insertDate = $insertDateTime->format('d.m');
		
			$arOrderFindOzon[] = $order["OZON_NUMBER"];
			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"SOURCE" => "OZ",
 						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"STICKER" => $order["OZON_NUMBER"],
						"ORDER_QUANTITY" => $arItem["QUANTITY"], 
						"INSERT_DATE" => $insertDate,
						"INSERT_HOUR" => $insertHour,
					);
					$arSort[$order["OZON_NUMBER"]][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			if (is_array($order["BASKET"])) {
				$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
			} else {
				print_r('ЗАКАЗ '.$order["ORDER_ID"].' НЕ ОБРАБОТАН ПУСТАЯ КОРЗИНА<br>');
			}
			// $arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
		}

	}

	// заказы яндекса
	if( count($arOrderYA) > 0) {
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA" => $arOrderYA
		);
		// var_dump($arFilter);
		$objService->getPropOrderFlg = true;
		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		foreach($arOrder as $key => $order){
			$arOrderFindYA[] = $order['ORDER_NUMBER_YA'];
			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"STICKER" => $order['ORDER_NUMBER_YA'],
					);
					$arSort[$order['ORDER_NUMBER_YA']][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
		}

	}

	if( count($arIDs) > 0) {
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"ID" => $arIDs,
		);
		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","XML_ID","NAME","PREVIEW_PICTURE", "DETAIL_PICTURE", "IBLOCK_ID", "DETAIL_PAGE_URL", "PROPERTY_WBARTICLE", "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN"));


		while($ob = $rs->GetNextElement()){
			$arFields = $ob->GetFields();

			if($arFields["PREVIEW_PICTURE"]) {
				$arFields["PICTURE_SRC"] = CFile::GetPath($arFields["PREVIEW_PICTURE"]);
			} elseif($arFields["DETAIL_PICTURE"]) {
				$arFields["PICTURE_SRC"] = CFile::GetPath($arFields["DETAIL_PICTURE"]);
			}
			
			$arResult["ITEMS"][$arFields["ID"]] = $arFields;

			$arArticleBX[] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];

			$arIDsBX[] = $arFields["ID"];

			$arXMLIDsBX[] = $arFields["XML_ID"];

		}
	}

	foreach($arOrderID as $order){
		if(!in_array($order, $arOrderFind)){
			$arResult["ERRORS"][] = "Заказ - " . $order . " не найден";
		}
	}

	foreach($arOrderOzon as $order){
		if(!in_array($order, $arOrderFindOzon)){
			$arResult["ERRORS"][] = "Заказ ozon - " . $order . " не найден";
		}
	}

	foreach($arOrderYA as $order){
		if(!in_array($order, $arOrderFindYA)){
			$arResult["ERRORS"][] = "Заказ Yandex - " . $order . " не найден";
		}
	}
        // Логика обработки ручного ввода
        return [
            'status' => 'success',
            'data' => [
                'orders' => [
                    [
                        'ID' => 3,
                        'ARTICLE' => 'MANUAL-001',
                        'PRODUCT_ID' => 1003,
                        'BARCODE' => '5555555555555',
                        'ORDER_NUMBER' => 'MAN-001',
                        'COMMENTS' => 'Ручной ввод',
                        'WAREHOUSE_INFO' => 'Основной склад'
                    ]
                ],
                'stats' => [
                    'total' => 1,
                    'printed' => 0
                ]
            ]
        ];
    }

    private function getBarcode()
    {
        $productId = $this->request->get('product_id');
        
        if (!$productId) {
            return ['status' => 'error', 'message' => 'Product ID required'];
        }

        // Здесь ваша логика получения штрихкода из базы
        return [
            'status' => 'success', 
            'barcode' => '200000000000' . str_pad($productId, 4, '0', STR_PAD_LEFT)
        ];
    }

    private function setBarcode()
    {
        $productId = $this->request->get('product_id');
        $barcode = $this->request->get('barcode');
        
        if (!$productId || !$barcode) {
            return ['status' => 'error', 'message' => 'Product ID and barcode required'];
        }

        // Здесь ваша логика сохранения штрихкода
        // Например: CIBlockElement::SetPropertyValuesEx($productId, false, array("AEN" => $barcode));
        
        return ['status' => 'success', 'message' => 'Barcode saved successfully'];
    }

    private function processBarcodeScan()
    {
        $barcode = $this->request->get('barcode');
        
        if (!$barcode) {
            return ['status' => 'error', 'message' => 'Barcode required'];
        }

        // Логика обработки отсканированного штрихкода
        return [
            'status' => 'success',
            'message' => 'Штрихкод ' . $barcode . ' обработан',
            'article' => 'TEST-' . substr($barcode, -3),
            'product_id' => 1000 + (int)substr($barcode, -3)
        ];
    }

}
?>