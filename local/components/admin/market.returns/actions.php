<?php
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!defined('BX_SECURITY_SESSION_READONLY')) {
    define('BX_SECURITY_SESSION_READONLY', true);
}
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
global $USER;
global $DB;
use Bitrix\Main\Loader;

Loader::includeModule('sale');
Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

if (!$USER) {
    die('Access denied');
}

$userID = $USER->getID();

//if (!check_bitrix_sessid()) {
//    die('Invalid session');
//}

$action = $_REQUEST['action'] ?? '';
$cabinet = $_REQUEST['cabinet'] ?? 's1';

if (!in_array($cabinet, ['s1', 's2']))
	$cabinet = 's1';

if (empty($action)) {
    die('Invalid parameters');
}

// Разрешенные действия
$allowedActions = [
	'get_article', 
	'check_article', 
	'find_order', 
	'create_return', 
	'get_settings', 
	'set_settings', 
	'get_history', 
	'save_barcode', 
	'get_sales_channels',
	'get_warehouses'
];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}

$logger = new TsLogger("/utils/returns/");
$logger->log("LOG", "Запрос", ['userID' => $userID, '_REQUEST' => $_REQUEST]); 

//file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($_POST, true), 8);
$IBLOCK_ID = 16;
$fileHistory = "/var/www/bitrix_logs/utils/market.returns/" . date("Y-m-d") . ".txt";
$ms = new MoyskladAPI($cabinet);

function addHistory($data = []) {
	global $USER;
	$fileHistory = "/var/www/bitrix_logs/utils/market.returns/" . date("Y-m-d") . ".txt";
	
	$data["date"] = date("Y-m-d H:i:s");
	$data["user_id"] = $USER->getID();
	
	file_put_contents($fileHistory, serialize($data) . "\r\n", 8);
}

function formatDateMoysklad($dateString) {
    $date = DateTime::createFromFormat('Y-m-d H:i:s.u', $dateString);
    if ($date === false) {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        if ($date === false) {
            return null;
        }
    }
    return $date->format('d.m.Y H:i');
}

function findOrderMS($meta = [], $viewForce = false, $findAssortmentID = false, $cabinet = 's1') {
	global $DB;
	$ms = new MoyskladAPI($cabinet);
	
	$url = $meta['href'];
	$orderMS = $ms->customRequest($meta['href']);

	if (is_array($orderMS)) {
		if (is_array($orderMS['demands'])) {
			$demandMS = $ms->customRequest($orderMS['demands'][0]['meta']['href']);

			if (is_array($demandMS)) {
				if (!$demandMS['applicable']) {
					$result = [
						'success' => false,
						'message' => 'Отгрузка не проведена',
					];
				} elseif (is_array($demandMS['returns']) && count($demandMS['returns']) > 2220) {
					$result = [
						'success' => false,
						'message' => 'Возврат уже создан',
					];
				} elseif (is_array($demandMS['positions'])) {
					$positionMS = $ms->customRequest($demandMS['positions']['meta']['href']);
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/components/admin/market.returns/s.txt", print_r($positionMS, true));
file_put_contents("/var/www/bitrix_logs/utils/market.returns/s.txt", print_r([$positionMS], true));

					// смотрим что уже отгружено
					$arReturnProducts = [];
					if (is_array($demandMS['returns']) && count($demandMS['returns']) > 0) {
						foreach ($demandMS['returns'] as $returns) {
							$returnMS = $ms->customRequest($returns['meta']['href']);
							$returnPositionMS = $ms->customRequest($returnMS['positions']['meta']['href']);
file_put_contents("/var/www/bitrix_logs/utils/market.returns/s.txt", print_r([$returnPositionMS], true),8);
							foreach($returnPositionMS['rows'] as $position){
								$assortmentID = basename($position['assortment']['meta']['href']);
								if (!$arReturnProducts[$assortmentID]) {
									$arReturnProducts[$assortmentID] = [
										'assortment_id' => $assortmentID,
										'quantity' => $position['quantity'],
										'price' => $position['price'] / 100,
									];
								} else {
									$arReturnProducts[$assortmentID]['quantity'] += $position['quantity'];
								}

							}
		
						}
					}
					
					//if ((is_array($positionMS['rows']) && count($positionMS['rows']) == 1) || $viewForce === true) {
					$success = true;
					if ((is_array($positionMS['rows']) && count($positionMS['rows']) > 0) || $viewForce === true) {
						$arProducts = [];
						foreach($positionMS['rows'] as $position){
							$assortmentID = basename($position['assortment']['meta']['href']);
							$sql = "SELECT BX_ID FROM ci_ms_assortment WHERE MS_ID = '".$assortmentID."'";

							$results = $DB->Query($sql, false, $err_mess.__LINE__);
							if ($_row = $results->Fetch()){
								
								if ($_row['BX_ID'] > 0) {
									$arFilter = [
										"IBLOCK_ID" => $IBLOCK_ID, 
										"ID" => $_row['BX_ID']
									];
									$arSelect = [
										'ID', 'NAME', 'PROPERTY_CML2_ARTICLE', 'PREVIEW_PICTURE',
									];
									$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
									if ($arFields = $rs->GetNext()){
										$product = [
											'name' => $arFields['NAME'],
											'article' => $arFields['PROPERTY_CML2_ARTICLE_VALUE'],
										];
										if ($arFields['PREVIEW_PICTURE']) {
											$product['picture'] = CFile::GetPath($arFields['PREVIEW_PICTURE']);
										}
									} else {
										$product = [
											'name' => 'undefined4',
											'article' => 'undefined',
										];
									}
								} else {
									$product = [
										'name' => 'undefined3',
										'article' => 'undefined',
									];
								}
							} else {
								$assortmentMS = $ms->customRequest($position['assortment']['meta']['href']);
						
								if (is_array($assortmentMS) && $assortmentMS['name']) {
									$product = [
										'name' => $assortmentMS['name'],
										'article' => 'undefined',
									];
								} else {
									$product = [
										'name' => 'undefined2',
										'article' => 'undefined',
									];
								}
							}
							
							$returns = ($arReturnProducts[$assortmentID] ? $arReturnProducts[$assortmentID] : []);
							
							if ($returns) {
								$quantity_returns = $returns['quantity'];
							} else {
								$quantity_returns = 0;
							}

							if ($quantity_returns >= $position['quantity']) {
								$quantity_input = 0;
								$max_quantity = 0;
							} else {
								if ($viewForce) {
									$quantity_input = $max_quantity = $position['quantity'] - $quantity_returns;
								} else {
									$quantity_input = 1;
									$max_quantity = $position['quantity'] - $quantity_returns;
								}
							}
							
							$checked = false;
							if ($viewForce) {
								if ($max_quantity > 0)
									$checked = true;
							} else {
								if ($findAssortmentID && $max_quantity > 0 && $findAssortmentID == $assortmentID)
									$checked = true;
							}
							
							if (!$viewForce && $max_quantity == 0) {
								$success = false;
							}
							
							$arProducts[] = [
								'name' => $product['name'],
								'article' => $product['article'],
								'assortment_id' => $assortmentID,
								'quantity' => $position['quantity'],
								'quantity_returns' => $quantity_returns,
								'max_quantity' => $max_quantity,
								'quantity_input' => $quantity_input,
								'price' => $position['price'] / 100,
								'picture' => $product['picture'] ?? '/upload/no-photo--lg.png',
								'returns' => $returns,
								'checked' => $checked
							];
						}
					} else {
						return [
							'success' => false,
							'message' => 'Нужна 1 позиция. В текущей - ' . (is_array($positionMS['rows']) ? count($positionMS['rows']) : ''),
						];
					}

					$result = [
						'success' => $success,
						'order' => [
							'id' => $orderMS['id'],
							'number' => $orderMS['name'],
							'date' => formatDateMoysklad($orderMS['moment']),
							'sum' => $orderMS['sum'] / 100,
							'payedSum' => $orderMS['payedSum'] / 100,
							'shippedSum' => $orderMS['shippedSum'] / 100,
							'products' => $arProducts,
						],
						'shipment' => [
							'id' => $demandMS['id'],
							'number' => $demandMS['name'],
							'date' => formatDateMoysklad($demandMS['moment']),
						],
					];
					if (!$success) {
						$result['message'] = 'max_quantity = 0';
					}
				} else {
					$result = [
						'success' => false,
						'message' => 'Нет позиций в отгруке',
					];
				}
				
			} else {
				$result = [
					'success' => false,
					'message' => 'Ошибка получения отгрузки',
				];
			}
		} else {
			$result = [
				'success' => false,
				'message' => 'Нет отгрузки в заказе',
			];
		}
	} else {
		$result = [
			'success' => false,
			'message' => 'Ошибка получения заказа',
		];
	}
	
	return $result;
}

try {
    switch ($action) {
        case 'get_article':
			$barcode = trim($_POST["barcode"]);
			$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$result = [
					'success' => true,
					'article' => $row["ARTICLE"],
					'productId' => $row["PRODUCT_ID"],
				];
			} else {
				$result = [
					'success' => false,
					'message' => 'ШК не найден',
				];
			}
			break;
        case 'check_article':
			$article = trim($_POST["article"]);
			$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_CML2_ARTICLE" => $article];
			$arSelect = [
				'ID', 
			];
			$rs = CIBlockElement::GetList([], $arFilter, false, false, );
			if ($arFields = $rs->GetNext()){
				$result = [
					'success' => true,
					'exists' => true,
					'productId' => $arFields["ID"],
				];
			} else {
				$result = [
					'success' => true,
				];
			}
			break;
        case 'find_order':
			$orderNumber = mb_strtoupper($_POST["orderNumber"]);
			$productId = intval($_POST["productId"]);
			$salesChannel = trim($_POST["salesChannel"]) ?? '';
			
			if ($orderNumber > 0) {
				$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER = '".$orderNumber."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$meta = json_decode($row['META'], true);
					$result = findOrderMS($meta, true, false, $cabinet);
				} else {
					$result = [
						'success' => false,
						'message' => 'Заказ не найден',
					];
				}
			} elseif ($productId > 0 && strlen($salesChannel) > 0) {
				$settings = unserialize(CProSet::getOption("SETTINGS_UTILS_MARKET_RETURN"));
				
				if (!$settings["salesChannels"][$salesChannel]) {
					return [
						'success' => false,
						'message' => 'Не найдена связка канала с контрагентом',
					];
				}
				
				$strSql = "SELECT * FROM ci_ms_saleschannel WHERE MS_ID = '{$salesChannel}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				$siteID = false;
				while ($row = $results->Fetch()){
					$siteID = $row['SITE_ID'];
				}
				
				if (!$siteID) {
					return [
						'success' => false,
						'message' => 'Не определен сайт канала',
					];
				}
				
				$strSql = "SELECT * FROM ci_ms_assortment WHERE BX_ID = '".$productId."' AND SITE_ID = '{$siteID}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				$assortmentID = false;
				if ($row = $results->Fetch()){
					$assortmentID = $row['MS_ID'];
				}
				
				if ($assortmentID) {
					$agentID = $settings["salesChannels"][$salesChannel];
					
					$strSql = "SELECT * 
						FROM ci_ms_demand_assortments 
						WHERE ASSORTMENT_ID = '".$assortmentID."' AND AGENT_ID = '{$agentID}'
						ORDER BY DATE_DOCUMENT DESC
					";

					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					//$assortmentID = false;
					$arDemands = [];
					while ($row = $results->Fetch()){
						//$assortmentID = $row['MS_ID'];
						$arDemands[] = $row;
					}

					if (count($arDemands) > 0) {
						foreach ($arDemands as $demand) {
							$strSql = "SELECT * FROM ci_ms_order WHERE MS_ID = '".$demand["CUSTOMER_ORDER_ID"]."'";
							$results = $DB->Query($strSql, false, $err_mess.__LINE__);
							if ($row = $results->Fetch()){
								$meta = json_decode($row['META'], true);
								$result = findOrderMS($meta, false, $assortmentID, $cabinet);
							} else {
								$result = [
									'success' => false,
									'message' => 'Заказ не найден',
									//'message' => $strSql,
								];
							}
							
							if ($result['success']) {
								//file_put_contents("/var/www/bitrix_logs/utils/market.returns/sss.txt", print_r([$demand], true),8);

								break;
							}
						}
					} else {
						$result = [
							'success' => false,
							'message' => 'Отгрузки не найдены',
						];
					}

					/*$result = [
						'success' => false,
						'message' => serialize($arDemands),
						//'message' => $strSql,
					];*/
				} else {
					$result = [
						'success' => false,
						'message' => 'Товар не найден в таблице',
					];
				}
			} else {
				$result = [
					'success' => false,
					'message' => 'Ошибка в запросе. Нет productId или salesChannel',
				];
			}

			/*$result = [
				'success' => false, 
				'message' => 'Заказ не найден',
			];*/
			break;
        case 'create_return':
			$orderId = trim($_POST["orderId"]) ?? '';
			$shipmentId = trim($_POST["shipmentId"]) ?? '';
			$warehouseId = trim($_POST["warehouseId"]) ?? '';
			$comment = trim($_POST["comment"]) ?? '';
			$orderNumber = trim($_POST["orderNumber"]) ?? '';
			$orderStatus = trim($_POST["orderStatus"]) ?? 'NZ';
			//$productIds = $_POST["productIds"] ? explode(',', $_POST["productIds"]) : [];
			$arProduct = [];
			foreach (json_decode($_POST["products"], true) as $item) {
				$arProduct[$item['id']] = intval($item['quantity']);
			}
			
			$logger->log("LOG", "create_return _POST", $_POST); 
			
			$productIds = array_keys($arProduct);

			if (!$orderId) {
				$result = [
					'success' => false,
					'message' => 'Заказ не определен',
				];
				break;
			}

			if (!$shipmentId) {
				$result = [
					'success' => false,
					'message' => 'Отгрузка не определена',
				];
				break;
			}
			
			if (!$warehouseId) {
				$result = [
					'success' => false,
					'message' => 'Склад не определен',
				];
				break;
			}
			
			if (!$productIds) {
				$result = [
					'success' => false,
					'message' => 'Не выбран ни один товар',
				];
				break;
			}
			//$orderId = 'cbeb398e-56d0-11f0-0a80-147d0006c1ae';
			// получаем заказ из мс
			$orderMS = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$orderId}");
			
			$logger->log("LOG", "create_return orderMS", $orderMS); 
			
			if(!$orderMS || $orderMS["errors"]){
				$result = [
					'success' => false,
					'message' => 'Данные по заказу не получены',
				];
				$logger->log("ERROR", "Данные по заказу не получены", ['orderMS' => $orderMS]); 
				break;
			}
			/*if($orderMS["demands"] && count($orderMS["demands"]) > 0){
				$result = [
					'success' => false,
					'message' => 'По заказу уже есть отгрузка',
				];
				break;
				$demandMS = $ms->customRequest($orderMS['demands'][0]['meta']['href']);
			}*/

			// запрашиваем шаблон для создания возврата
			$template = array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$shipmentId}",
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata",
				"type" => "demand",
				"mediaType" => "application/json",
			);

			$arTemplate = $ms->getSalesReturnTemplate($template);

			$arTemplate['store'] = array('meta' => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/{$warehouseId}",
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
				"type" => "store",
				"mediaType" => "application/json",
				"uuidHref" => "https://online.moysklad.ru/app/#warehouse/edit?id={$warehouseId}"
			));
			
			$logger->log("LOG", "create_return arTemplate", $arTemplate); 
			
			if ($comment) {
				$arTemplate['description'] = $comment;
			}
			
			$rows = [];
			foreach ($arTemplate['positions']['rows'] as $arItem) {
				$pos = basename($arItem['assortment']['meta']['href']);
				if (in_array($pos, $productIds) && $arProduct[$pos]) {
					$arItem['quantity'] = $arProduct[$pos];
					$rows[] = $arItem;
				}
			}

			$arTemplate['positions']['rows'] = $rows;


			$returnMS = $ms->setSalesReturn(array($arTemplate));

			$logger->log("LOG", "create_return returnMS", $returnMS); 
			file_put_contents("/var/www/bitrix_logs/utils/market.returns/cccccc.txt", print_r([$returnMS], true));

			if (is_array($returnMS) && count($returnMS) == 1) {
				$return = $returnMS[0];
				if(!$return["id"]){
					$error = [];
					if(is_array($return["errors"])){
						foreach($return["errors"] as $k => $v){
							$error[] = $v["error"];
						}
					}elseif($return["error"]){
						$error[] = $return["error"];
					}else{
						$error[] = 'Ошибка не определена';
					}
					$data = [
						"type" => "error",
						"message" => "Возврат не создан. " . serialize($error),
					];
					addHistory($data);
					
					$result = [
						'success' => false,
						'message' => 'Возврат не создан',
					];
					
					$logger->log("ERROR", "Возврат не создан", ['error' => $error, 'return' => $return]); 
				}else{
					$data = [
						"type" => "info",
						"message" => "Возврат {$return["name"]} успешно создан. " . $return["sum"] / 100 . " руб.",
					];
					addHistory($data);
					
					$result = [
						'success' => true,
						'returnNumber' => $return["name"],
					];
					file_put_contents("/var/www/bitrix_logs/utils/market.returns/s.txt", print_r(['orderNumber', $orderNumber], true), 8);
					// меняем статус если заполнен номер заказа   
					if ($orderNumber) {
						$order = Bitrix\Sale\Order::loadByAccountNumber($orderNumber);
						if ($order) {
							$orderId = $order->getId();
							$logger->log("LOG", "Перевод в статус {$orderStatus}", ['orderNumber' => $orderNumber]); 
							file_put_contents("/var/www/bitrix_logs/utils/market.returns/s.txt", print_r(["устанавливаем {$orderStatus}", $orderNumber, $orderId], true), 8);
							OrderService::setStatusOrderD7($orderId, $orderStatus);
							
							$logger->log("LOG", "Перевод в статус {$orderStatus} конец", ['orderNumber' => $orderNumber]); 
						}
					}
				}
			} else {
				$result = [
					'success' => false,
					'message' => 'Ошибка создания возврата',
				];
			}
			break;
        case 'get_settings':
			$settings = unserialize(CProSet::getOption("SETTINGS_UTILS_MARKET_RETURN"));
			
			$arSalesChannel = [];
			$strSql = "SELECT * FROM ci_ms_saleschannel WHERE TYPE = 'MARKETPLACE' ORDER BY NAME";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arSalesChannel[] = [
					'MS_ID' => $row['MS_ID'],
					'NAME' => $row['NAME'],
					'SITE_ID' => $row['SITE_ID'],
					'AGENT_ID' => $settings["salesChannels"][$row['MS_ID']] ?? '',
				];
			}
			$result = [
				'success' => true,
				'settings' => [
					'salesChannels' => $arSalesChannel,
				],
			];
			break;
        case 'set_settings':
			$arSettings = [
				'salesChannels' => [],
			];
			foreach ($_POST['sales_channel'] as $sales_chanel => $agentID) {
				$arSettings['salesChannels'][$sales_chanel] = $agentID;
			}
			CProSet::setOption("SETTINGS_UTILS_MARKET_RETURN", serialize($arSettings));
			$result = [
				'success' => true,
			];
			break;

		case 'get_history':
			$filter = $_POST["filter"];
			$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
			$user_id = $USER->getID();
			
			$history = [];
			
			if (file_exists($fileHistory)) {
				$lines = [];
				$fp = fopen($fileHistory, 'r');
				$pos = -2;
				$currentLine = '';
				
				while ($limit > 0) {
					// Ищем начало строки
					while (fseek($fp, $pos, SEEK_END) !== -1) {
						$char = fgetc($fp);
						if ($char === "\n") {
							// Нашли конец строки
							break;
						}
						$currentLine = $char . $currentLine;
						$pos--;
					}
					
					if (!empty($currentLine)) {
						try {
							$data = unserialize(trim($currentLine));
							if ($data) {// && $data["user_id"] == $user_id
								array_unshift($lines, $data);
								$limit--;
							}
						} catch (Exception $e) {
						}
						$currentLine = '';
					}
					
					if (ftell($fp) <= 1) {
						break;
					}
					
					$pos--;
				}
				
				fclose($fp);
				$history = $lines;
			}
			
			$result = [
				'success' => true,
				'history' => array_reverse($history)
			];
            break;
			
        case 'save_barcode':
			$productId = intval($_POST["productId"]);
			$barcode = trim($_POST["barcode"]) ?? '';
			$article = trim($_POST["article"]) ?? '';
			
			if (!$productId) {
				$result = [
					'success' => false,
					'message' => 'ID товара не определен',
				];
			}
			
			if (!$barcode) {
				$result = [
					'success' => false,
					'message' => 'ШК не определен',
				];
			}
			
			if (!$article) {
				$result = [
					'success' => false,
					'message' => 'Артикул не определен',
				];
			}
			
			$utils = new CPanelUtils();
			
			$result = [
				'success' => $utils->addAltBarcode($article, $barcode, $productId) ?? false,
			];
			break;
			
        case 'get_sales_channels':
            //MS_ID;
            //NAME
			//SITE_ID
			$settings = unserialize(CProSet::getOption("SETTINGS_UTILS_MARKET_RETURN"));
			
			$arSalesChannel = [];
			$strSql = "SELECT * FROM ci_ms_saleschannel WHERE TYPE = 'MARKETPLACE' AND SITE_ID = '{$cabinet}' ORDER BY NAME";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if($settings["salesChannels"][$row['MS_ID']])
					$arSalesChannel[] = $row;
			}
			$result = [
				'success' => true,
				'channels' => $arSalesChannel
			];
			break;
			
        case 'get_warehouses':
			$arWarehouse = [
				's1' => [
					'51538bd5-6cf3-11ef-0a80-10ba001db77c' => 'Дубровка 2',
					'093c792f-0ae4-11ea-0a80-0256000b6f8f' => 'Дубровка Авито',
					'92f817c0-303e-11ed-0a80-09cb0025b1e7' => 'Дубровка Списать',
					'270883fd-0ae4-11ea-0a80-01f4000b8d66' => 'Склад Ремонт',
				],
				's2' => [
					'6f6d2169-180c-11ea-0a80-00b30004eaef' => 'Минск',
					'c4823547-40dd-11ea-0a80-05ac000ee149' => 'Немига Ремонт',
					'5f8d0c89-71c0-11ef-0a80-0c290010494e' => 'Склад транзит',
					'8e7eac5e-38b6-11ef-0a80-064d00014b9c' => 'Офис',
				],
			];
			
			$result = [
				'success' => true,
				'warehouses' => $arWarehouse[$cabinet] ?? []
			];
			break;
		default:
            $result = ['status' => 'error', 'message' => 'Unknown action'];
    }
    //file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($result, true), 8);
    header('Content-Type: application/json');
	
	$logger->log("LOG", "result", $result); 
	
    echo json_encode($result);
    
} catch (Exception $e) {
	//file_put_contents("/var/www/bitrix_logs/barcode/req_error.txt", print_r($e->getMessage(), true), 8);
	$logger->log("ERROR", "Exception", [$_REQUEST, $e->getMessage()]); 
    die('Error: '.$e->getMessage());
}
