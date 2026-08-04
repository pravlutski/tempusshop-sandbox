<?php
namespace Panel\Manager\Service;

use Panel\Manager\Order\OrderReservedTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;


class OrderReservedService
{
    private $logger;
    private $connection;
    private $arAlternative = [];
    private $executors = []; // список товаров которые в исполнении
    private $objPricelist;

    public function __construct()
    {
        if (!Loader::includeModule('panel.manager')) {
            throw new \Exception('Module panel.manager not installed');
        }
		$this->logger = new \TsLogger("/OrderReservedService/");
        
        $this->connection = Application::getConnection();
        $this->objPricelist = new \CPanelPricelist();
        $this->loadAlternatives();
		$this->priceService = \PanelManager::getPriceManager();
		$this->settingsTp = $this->priceService->getAllTradingSettings();
    }

    private function loadAlternatives()
    {
        $sql = "SELECT artnumber, alternative FROM ci_catalog_artnumbers";
        $results = $this->connection->query($sql);
        
        while ($row = $results->fetch()) {
            $this->arAlternative[$row['artnumber']][] = $row['alternative'];
        }
    }

    private function getReserveStatuses()
    {
        return ["CO", "WT", "PO", "SE", "TA", "CR", "CL"];
    }

    private function collectReservedFromOrders()
    {
        $statuses = implode("','", $this->getReserveStatuses());
        
        $sql = "
            SELECT 
                b.ID as BASKET_ID,
				b.PRODUCT_ID,
                o.ID as ORDER_ID,
                o.LID as SITE_ID,
                COALESCE(tp.TRADING_PLATFORM_ID, 0) as TRADING_PLATFORM_ID,
                b.QUANTITY as QUANTITY
            FROM b_sale_order o
            INNER JOIN b_sale_basket b ON o.ID = b.ORDER_ID
            LEFT JOIN b_sale_tp_order tp ON o.ID = tp.ORDER_ID
            WHERE o.STATUS_ID IN ('{$statuses}')
                AND o.CANCELED = 'N'
                AND b.PRODUCT_ID > 0
        ";
        
        $result = $this->connection->query($sql);
        
        $arOrder = [];
		
		//$settingsTp = $this->priceService->getAllTradingSettings();
//prent($this->settingsTp);
		$this->settingsTp[$order['TRADING_PLATFORM_ID']]['warehouse_sites'][$order['SITE_ID']] ?? false;
        while ($row = $result->fetch()) {
            $productId = $row['PRODUCT_ID'];
            $siteId = $row['SITE_ID'];
            $platformId = (int)$row['TRADING_PLATFORM_ID'];
            $orderId = (int)$row['ORDER_ID'];
            $basketId = (int)$row['BASKET_ID'];
            $quantity = (int)$row['QUANTITY'];
            $priceType = $this->settingsTp[$platformId]['type_prices'][$siteId] ?? 'RU';

			for ($i = 1; $i <= $quantity; $i++) {
				$arOrder[] = [
					'PRODUCT_ID' => $productId,
					'SITE_ID' => $siteId,
					'TRADING_PLATFORM_ID' => $platformId,
					'PRICE_TYPE' => $priceType,
					'ORDER_ID' => $orderId,
					'BASKET_ID' => $basketId,
					'NUMBER_ID' => $i,
					'RESERVED' => 1,
				];
			}
        }

        return $arOrder;
    }

    private function getArticlesByProductIds($productIds)
    {
        if (empty($productIds)) {
            return [[], []];
        }
        
        $ids = implode(',', array_map('intval', $productIds));
        
        $sql = "
            SELECT 
                be.ID as PRODUCT_ID,
                bep.PROPERTY_123 as ARTICLE
            FROM b_iblock_element be
            INNER JOIN b_iblock_element_prop_s16 bep ON be.ID = bep.IBLOCK_ELEMENT_ID
            WHERE be.IBLOCK_ID = 16 
                AND be.ID IN ({$ids})
                AND be.IBLOCK_ID = 16
        ";

        $result = $this->connection->query($sql);
        
        $arArticleMain = [];
        $arArticles = [];
        
        while ($row = $result->fetch()) {
            $productId = $row['PRODUCT_ID'];
            $article = $row['ARTICLE'];
            
            $arArticleMain[$productId] = $article;
            $arArticles[] = $article;
            
            if (!empty($this->arAlternative[$article])) {
                foreach ($this->arAlternative[$article] as $alt) {
                    $arArticles[] = $alt;
                }
            }
        }
        
        return [$arArticleMain, array_unique($arArticles)];
    }

    public function updateReserved()
    {
		$lockFile = '/tmp/order_reserved_update.lock';
		
		if (file_exists($lockFile)) {
			$fileTime = filemtime($lockFile);
			$maxExecutionTime = 30;
			
			if (time() - $fileTime < $maxExecutionTime) {
				$this->logger->log("WARNING", "updateReserved already running, skipped");
				return 0;
			} else {
				$this->logger->log("WARNING", "updateReserved: stale lock file found, removing");
				@unlink($lockFile);
			}
		}
		
		file_put_contents($lockFile, date('Y-m-d H:i:s') . ' - PID: ' . getmypid());
		
        $this->connection->startTransaction();
		
		//$arBacktrace = \Bitrix\Main\Diag\Helper::getBackTrace(20, DEBUG_BACKTRACE_IGNORE_ARGS);
		//$this->logger->log("LOG", "arBacktrace", $arBacktrace);
		
        try {
			set_time_limit(60);
			
			$oldArticles = $this->getArticlesFromReservedTable();
			
			$arOrder = $this->collectReservedFromOrders();

			$productIds = array_column($arOrder, 'PRODUCT_ID');
			
            list($arArticleMain, $arArticles) = $this->getArticlesByProductIds($productIds);
            
            if (empty($arArticles)) {
                $this->connection->commitTransaction();
                return 0;
            }
			
			// добавляем SUPPLIER_ID, распределяем по поставщикам
			$this->distributeBySuppliers($arOrder, $arArticleMain);

            if (empty($arOrder)) {
                $this->connection->commitTransaction();
                return 0;
            }
			
			$arOrder = $this->prepareReserved($arOrder);
			
			$newArticles = array_unique(array_column($arOrder, 'ARTICLE'));
			
			$addedArticles = array_diff($newArticles, $oldArticles);
			
			$removedArticles = array_diff($oldArticles, $newArticles);
			
			$this->logger->log("LOG", "Очищаем таблицу");
            OrderReservedTable::truncateReserved();
			
			//$this->logger->log("LOG", "Записываем - ", $arOrder);
			$affectedRows = OrderReservedTable::massInsert($arOrder);

            $this->connection->commitTransaction();

            $diff = array_values($addedArticles) + array_values($removedArticles);
			if (is_array($diff) && count($diff) > 0) {
				$pricelist = new \CPanelPricelist;
				$pricelist->addItemsDiff($diff);
			}
			//prent($diff);
        } catch (\Exception $e) {
            $this->connection->rollbackTransaction();
            throw $e;
		} finally {
			if (file_exists($lockFile)) {
				@unlink($lockFile);
			}
		}
    }

    private function distributeBySuppliers(&$arOrder, $arArticleMain)
	{
		$typePrices = $this->priceService->getTypePrices();
		$typePricesIds = array_column($typePrices, 'id');
		//prent($priceTypes);
		$orderByType = [];
		
		foreach ($arOrder as $order) {
			$typePrice = $order['PRICE_TYPE'];
			$article = $arArticleMain[$order['PRODUCT_ID']];
			$warehouseExecutor = $this->settingsTp[$order['TRADING_PLATFORM_ID']]['warehouse_sites'][$order['SITE_ID']] ?? false;
			
			$orderByType[$typePrice][] = [
				'PRODUCT_ID' => $order['PRODUCT_ID'],
				'SITE_ID' => $order['SITE_ID'],
				'TRADING_PLATFORM_ID' => $order['TRADING_PLATFORM_ID'],
				'ORDER_ID' => $order['ORDER_ID'],
				'BASKET_ID' => $order['BASKET_ID'],
				'NUMBER_ID' => $order['NUMBER_ID'],
				'PRICE_TYPE' => $typePrice,
				'ARTICLE' => $article,
				'WAREHOUSE_EXECUTOR' => $warehouseExecutor,
			];
		}

		// идем в таком порядке
		/*foreach ($typePricesIds as $typePrice) {
			if (!$orderByType[$typePrice]) continue;
			$arArticle = array_column($orderByType[$typePrice], 'ARTICLE');
			$arArticle = array_unique($arArticle);
			
			$servicePrice = $this->priceService->updatePriceService($typePrice, 'debug');
			$filter = [
				'article' => $arArticle
			];
			$servicePrice->market->setPriceFilter($filter);
			
			$result2 = $servicePrice->getMinPurchasePrice();
			prent($result2);
		}*/
		// $arReserve[$item["supplier_id"]][$item["model"]]
		$arOrder = [];
		$priceReserve = [];
		$allMarketReserve = [];
		//foreach ($orderByType as $typePrice => $orders) {
		foreach ($typePricesIds as $typePrice) {
			if (!$orderByType[$typePrice]) continue;
			$orders = $orderByType[$typePrice];
			
			$marketReserve = [];
			$arArticle = array_column($orders, 'ARTICLE');
			$arArticle = array_unique($arArticle);
			
			$servicePrice = $this->priceService->updatePriceService($typePrice, 'debug');
			$filter = [
				'article' => $arArticle
			];
			
			$servicePrice->setDynamicReserve($allMarketReserve);
			$servicePrice->market->setConfig('tbl_sebes_fbo', false);
			$servicePrice->market->setPriceFilter($filter);
			$servicePrice->market->setOption('is_dynamic_reserve', true);
			//prent($allMarketReserve[47]['EFR-539D-1A']);
			$prices = $servicePrice->getMinPurchasePrice();
			//
			if ($typePrice == 'WB') {
				//prent([$typePrice, $prices, $servicePrice->market->minSupplierPrices]);
				
			}
			if (in_array('GM-2100-1A', $arArticle)) {
				prent($typePrice);
				prent($prices['GM-2100-1A']);
			}
			$this->executors = [];
			
			foreach ($orders as &$order) {
				$article = $order['ARTICLE'];
				
				//$warehouseExecutor = $this->settingsTp[$order['TRADING_PLATFORM_ID']]['warehouse_sites'][$order['SITE_ID']] ?? false;
				//$this->executors[$priceType]
				//if ($warehouseExecutor) {
				//	prent($warehouseExecutor);
				//	prent($order);
				//}
				if ($prices[$article]) {
					// ищем склад исполнения
					$executorWarehouse = false;
					if ($order['WAREHOUSE_EXECUTOR']) {
						foreach ($prices[$article] as $k => &$price) {
							if ($price['count'] <= 0) continue;
							if ($price['supplier_id'] == $order['WAREHOUSE_EXECUTOR']) {
								$id = $price['id'];
								$marketReserve[$id] += 1;
								$allMarketReserve[$price['supplier_id']][$price['model']] += 1;
								$executorWarehouse = $price['supplier_id'];
								if ($article == 'GM-2100-1A') {
									//prent($price); 
									//prent($allMarketReserve); 
								}
								//$prices[$article][$k]['count'] -= 1;
								$price['count'] -= 1;
								break;

							}
						}
						unset($price);
					}

					if ($executorWarehouse) {
						$order['SUPPLIER_ID'] = $executorWarehouse;
					} else {
						foreach ($prices[$article] as &$price) {
							if ($article == 'GM-2100-1A') {
								prent($price);
							}
							if ($price['count'] <= 0) continue;
							$id = $price['id'];
							if ($marketReserve[$id] && $marketReserve[$id] >= $price['count']) {
								$allMarketReserve[$price['supplier_id']][$price['model']] += 1;
								$price['count'] = 0;
								continue;
							}

							$order['SUPPLIER_ID'] = $price['supplier_id'];
							//$price['count'] -= 1;
							
							$marketReserve[$id] += 1;

							if ($allMarketReserve[$price['supplier_id']][$price['model']]) {
								$allMarketReserve[$price['supplier_id']][$price['model']] += 1;
							} else {
								$allMarketReserve[$price['supplier_id']][$price['model']] = 1;
							}
							break;
						}
						unset($price);
					}
				} else {
					$order['SUPPLIER_ID'] = 0;
				}
				$arOrder[] = $order;
			}
			unset($order);
		}
		//prent($arOrder); 
	}
	
	private function prepareReserved($arOrder)
	{
		// получаем правый столбец закупок и подменяем
		$sql = "SELECT 
			site_id as SITE_ID,
			model as ARTICLE,
			order_id as ORDER_ID,
			tmp_order_id as TMP_ORDER_ID,
			order_basket_id as ORDER_BASKET_ID,
			top_id as TOP_ID,
			product_id as PRODUCT_ID,
			supp_id as SUPPLIER_ID
		FROM ci_purchase WHERE active = 'Y'";
		$result = $this->connection->query($sql);
		
		$purchaseOrder = [];
		$purchaseTop = [];
		while ($row = $result->fetch()) {
			if (strlen($row['ORDER_BASKET_ID']) > 0) {
				$purchaseOrder[$row['ORDER_BASKET_ID']] = $row;
			}
			if (intval($row['TOP_ID']) > 0) {
				$purchaseTop[$row['TOP_ID']] = $row;
			}
		}
		
		$diff = [
			['Market', 'Артикул', 'ID заказа', 'Из закупок', 'Авто']
		];
		foreach ($arOrder as &$order) {
			$order['ORDER_BASKET_ID'] = (string) $order['BASKET_ID'] . '.' . $order['NUMBER_ID'];
			
			if (
				$purchaseOrder[$order['ORDER_BASKET_ID']] && 
				$purchaseOrder[$order['ORDER_BASKET_ID']]['SUPPLIER_ID'] != $order['SUPPLIER_ID']// &&
				//is_numeric($order['SUPPLIER_ID'])
			) {
				$old = $purchaseOrder[$order['ORDER_BASKET_ID']];
				$new = $order;
				$diff[] = [
					$new['PRICE_TYPE'],
					$old['ARTICLE'],
					$old['ORDER_ID'],
					$old['SUPPLIER_ID'],
					$new['SUPPLIER_ID'],
				];
				//$order['SUPPLIER_ID'] = $purchaseOrder[$order['ORDER_BASKET_ID']]['SUPPLIER_ID'];
			}

		}
		unset($order);
		//prent(['не совпадают', $diff]);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/dev/purchaseTop.txt', print_r($purchaseTop, true));
		
		$fp = fopen('/var/www/bitrix/data/www/tempusshop.ru/dev/file.csv', 'w');

		foreach ($diff as $fields) {
			fputcsv($fp, $fields, ';', '"', '');
		}
		return $arOrder;
	}
	
	private function getArticlesFromReservedTable()
	{
		$sql = "SELECT DISTINCT ARTICLE FROM " . OrderReservedTable::getTableName();
		$result = $this->connection->query($sql);
		
		$articles = [];
		while ($row = $result->fetch()) {
			$articles[$row['ARTICLE']] = $row['ARTICLE'];
		}
		
		return $articles;
	}

	public function getReservedByArticles($articles, $siteId = null, $groupByArticle = true)
	{
		if (empty($articles)) {
			return [];
		}
		
		$helper = $this->connection->getSqlHelper();
		$escapedArticles = array_map([$helper, 'forSql'], $articles);
		$articlesList = "'" . implode("','", $escapedArticles) . "'";
		
		$siteCondition = '';
		if (!empty($siteId)) {
			if (is_array($siteId)) {
				$escapedSites = array_map([$helper, 'forSql'], $siteId);
				$siteList = "'" . implode("','", $escapedSites) . "'";
				$siteCondition = "AND r.SITE_ID IN ({$siteList})";
			} else {
				$siteCondition = "AND r.SITE_ID = '{$helper->forSql($siteId)}'";
			}
		}
		
		$sql = "
			SELECT 
				r.PRODUCT_ID,
				r.ARTICLE,
				r.SITE_ID,
				r.PRICE_TYPE,
				r.TRADING_PLATFORM_ID,
				r.AVAILABLE,
				r.RESERVED,
				r.TIMESTAMP,
				tp.NAME as TRADING_PLATFORM_NAME
			FROM ci_order_reserved r
			LEFT JOIN b_sale_tp tp ON r.TRADING_PLATFORM_ID = tp.ID
			WHERE r.ARTICLE IN ({$articlesList})
			{$siteCondition}
			ORDER BY r.ARTICLE, r.SITE_ID, r.TRADING_PLATFORM_ID
		";
		
		$result = $this->connection->query($sql);
		
		$arResult = [];
		while ($row = $result->fetch()) {
			if($groupByArticle) {
				$arResult[$row['ARTICLE']][] = [
					'PRODUCT_ID' => (int)$row['PRODUCT_ID'],
					'ARTICLE' => $row['ARTICLE'],
					'SITE_ID' => $row['SITE_ID'],
					'PRICE_TYPE' => $row['PRICE_TYPE'],
					'TRADING_PLATFORM_ID' => (int)$row['TRADING_PLATFORM_ID'],
					'TRADING_PLATFORM_NAME' => $row['TRADING_PLATFORM_NAME'] ?? 'По умолчанию',
					'AVAILABLE' => (int)$row['AVAILABLE'],
					'RESERVED' => (int)$row['RESERVED'],
					'TIMESTAMP' => $row['TIMESTAMP'],
				];
			} else {
				$arResult[] = [
					'PRODUCT_ID' => (int)$row['PRODUCT_ID'],
					'ARTICLE' => $row['ARTICLE'],
					'SITE_ID' => $row['SITE_ID'],
					'PRICE_TYPE' => $row['PRICE_TYPE'],
					'TRADING_PLATFORM_ID' => (int)$row['TRADING_PLATFORM_ID'],
					'TRADING_PLATFORM_NAME' => $row['TRADING_PLATFORM_NAME'] ?? 'По умолчанию',
					'AVAILABLE' => (int)$row['AVAILABLE'],
					'RESERVED' => (int)$row['RESERVED'],
					'TIMESTAMP' => $row['TIMESTAMP'],
				];
			}

		}
		
		return $arResult;
	}
	
	public function getReservedBySupplier($articles, $groupByArticle = true)
	{
		if (empty($articles)) {
			return [];
		}
		
		$helper = $this->connection->getSqlHelper();
		$escapedArticles = array_map([$helper, 'forSql'], $articles);
		$articlesList = "'" . implode("','", $escapedArticles) . "'";
		
		$sql = "
			SELECT 
				r.PRODUCT_ID,
				r.ARTICLE,
				r.SITE_ID,
				r.PRICE_TYPE,
				r.TRADING_PLATFORM_ID,
				r.SUPPLIER_ID,
				r.AVAILABLE,
				r.RESERVED,
				r.TIMESTAMP,
				tp.NAME as TRADING_PLATFORM_NAME
			FROM ci_order_reserved r
			LEFT JOIN b_sale_tp tp ON r.TRADING_PLATFORM_ID = tp.ID
			WHERE r.ARTICLE IN ({$articlesList})
		";
		
		$result = $this->connection->query($sql);

		$arResult = [];
		while ($row = $result->fetch()) {
			/*if (!$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']]) {
				$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']] = [
					'PRODUCT_ID' => $row['PRODUCT_ID'],
					'ARTICLE' => $row['ARTICLE'],
					'SITE_ID' => $row['SITE_ID'],
					'PRICE_TYPE' => $row['PRICE_TYPE'],
					'TRADING_PLATFORM_ID' => $row['TRADING_PLATFORM_ID'],
					'SUPPLIER_ID' => $row['SUPPLIER_ID'],
					'TRADING_PLATFORM_NAME' => $row['TRADING_PLATFORM_NAME'] ?? 'По умолчанию',
					'RESERVED' => $row['RESERVED'],
					'TIMESTAMP' => $row['TIMESTAMP'],
				];
			} else {
				$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']]['RESERVED'] += $row['RESERVED'];
			}*/
			if (!$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']][$row['TRADING_PLATFORM_ID']]) {
				$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']][$row['TRADING_PLATFORM_ID']] = [
					'PRODUCT_ID' => $row['PRODUCT_ID'],
					'ARTICLE' => $row['ARTICLE'],
					'SITE_ID' => $row['SITE_ID'],
					'PRICE_TYPE' => $row['PRICE_TYPE'],
					'TRADING_PLATFORM_ID' => $row['TRADING_PLATFORM_ID'],
					'SUPPLIER_ID' => $row['SUPPLIER_ID'],
					'TRADING_PLATFORM_NAME' => $row['TRADING_PLATFORM_NAME'] ?? 'По умолчанию',
					'RESERVED' => $row['RESERVED'],
					'TIMESTAMP' => $row['TIMESTAMP'],
				];
			} else {
				$arResult[$row['SUPPLIER_ID']][$row['ARTICLE']][$row['TRADING_PLATFORM_ID']]['RESERVED'] += $row['RESERVED'];
			}
		}

		return $arResult;
	}
}