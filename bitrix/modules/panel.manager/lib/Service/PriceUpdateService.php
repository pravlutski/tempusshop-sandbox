<?php
namespace Panel\Manager\Service;

use Panel\Manager\Market\MarketFactory;
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlHelper;

class PriceUpdateService
{
    private $logger;
    private $marketCode;
    public $market;
    public $marketConfig;
    public $optionStatus;
    public $priceQuarantine = [];
    private $dynamicReserve = [];
    
    public function __construct(string $marketCode, string $mode)
    {
		global $DB;
		$this->marketCode = $marketCode;
		$this->sendMail = true;
		$this->db = $DB;
		
        $this->logger = new \TsLogger("/updatePrice/" . $this->marketCode . "/");
		$this->mode = $mode;
		$this->market = MarketFactory::create($this->marketCode);
		$this->marketConfig = $this->market->getConfig();
		$this->market->loadRequiredData();
		$this->optionStatus = $this->market->getConfig('option_update');
    }
    
    public function updatePrices(): array
    {
        try {
			
			\CProSet::setOption($this->optionStatus, 0);
			
			$this->logger->log("LOG", "Запуск для цены - " . $this->marketCode);
            
			if (!$this->market->canUpdatePrices()) {
                $this->logger->log("ERROR", "Обновление цен. Отмена. Условия не выполнены. Цена - " . $this->marketCode);
                return [
					'success' => false, 
					'message' => 'Условия не выполнены',
					'error' => $this->market->lastError,
				];
            }
            
            $data = $this->getUpdateData();

            if (empty($data['supplier_prices'])) {
                $this->logger->log("ERROR", "Нет товаров для обновления. Цена - " . $this->marketCode);
                return [
					'success' => false, 
					'message' => 'Нет товаров',
					'error' => 'Нет товаров',
				];
            }
			
            $competitorPrices = $this->market->getCompetitorPrices($data['articles']);
            
            if (!$this->market->hasRequiredCompetitorPrices($competitorPrices)) {
				\CProSet::setOption($this->optionStatus, 0);
                $this->logger->log("ERROR", "Обновление цен. Отмена. Цен конкурентов 0. Цена - " . $this->marketCode);
                return [
					'success' => false, 
					'message' => 'Нет цен конкурентов',
					'error' => 'Нет цен конкурентов',
				];
            }
			
            $result = $this->processPrices($data, $competitorPrices);

            $this->logger->log("LOG", "Обновление завершено: {$result['updated']}/{$result['total']}");
            
            return [
                'success' => true,
                'updated' => $result['updated'],
                'total' => $result['total'],
                'logs' => $result['logs']
            ];
            
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function analysPrices(): array
    {
        try {
            $data = $this->getUpdateData();

            $competitorPrices = $this->market->getCompetitorPrices($data['articles']);

            //$resultPrices = $this->processPrices($data, $competitorPrices);
			
			$calculatedPrices = $this->market->calculatePrices($data['supplier_prices'], $data['catalog_prices'], $competitorPrices);

            return [
                'data' => $data,
                'competitorPrices' => $competitorPrices,
                'minSupplierPrices' => $this->market->minSupplierPrices,
                'calculatedPrices' => $calculatedPrices,
            ];
            
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMinPurchasePrice(): array
    {
        try {
            $data = $this->getUpdateDataSupplier();

			$minSupplierPrices = $this->market->findMinSupplierPrices($data['supplier_prices']);
//prent($minSupplierPrices);
            return $minSupplierPrices;
            
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
	
    private function getUpdateData(): array
    {
        $result = $this->getUpdateDataSupplier();
		$result['catalog_prices'] = $this->market->getCatalogPrice($result['articles']);
        return $result;
    }
	
	private function getUpdateDataSupplier(): array
	{
		$price = $this->market->getAvailablePrice();

		$dynamicReserve = $this->market->getOption('is_dynamic_reserve') ?? false;
		if ($dynamicReserve === true) {
			$arReserve = $this->getDynamicReserve();
			//prent($arReserve);
		} else {
			$arReserve = $this->getReserves();
		}
		//prent(['arReserve', $arReserve]);
		//$this->options['force_priority_supplier'] === true

        $arQuarantine = $this->getQuarantine();
//
        foreach ($price as &$item) {
			$supplier = $this->market->suppliers[$item['supplier_id']];

			$item["store_id"] = $supplier["store_id"];
			if (isset($arQuarantine[$item["model"]])) {
				$item["can_buy"] = false;
			} elseif (isset($arReserve[$item["supplier_id"]][$item["model"]])) {
				if ($arReserve[$item["supplier_id"]][$item["model"]] >= $item["count"]) {
					$item["can_buy"] = false;
					$item["count"] = 0;
				} else {
					$item["can_buy"] = true;
					//$arReserve[$item["supplier_id"]][$item["model"]] -= $item["count"];
					$item["count"] -= $arReserve[$item["supplier_id"]][$item["model"]];
				}
			} else {
				$item["can_buy"] = true;
			}
        }
		unset($item);

		// оставляем только can_buy
		$priceFiltered = [];
		foreach ($price as &$item) {//  && $item['model'] == 'FS4487'
			if ($item['can_buy']) {
				$this->setPricePriority($item);
				$priceFiltered[] = $item;
			}
		}
		unset($item);

        $arArticles = array_column($priceFiltered, 'model');

        return [
            'supplier_prices' => $priceFiltered,
            'articles' => $arArticles
        ];
	}
	
	private function getUpdateDataCatalog(): array
	{
		
	}
	
    private function setPricePriority(&$item)
    {
        if (isset($this->market->suppliers[$item["supplier_id"]]['settings']['brand_priority'][$item["brand_id"]])) {
            $item["priority"] = $this->market->suppliers[$item["supplier_id"]]['settings']['brand_priority'][$item["brand_id"]];
        } elseif (in_array($item["supplier_id"], $this->market->warehouseSupplier)) {
			if (isset($this->market->suppliers[$item["supplier_id"]]['settings_pricelist']['priority_default'])) {
				$item["priority"] = $this->market->suppliers[$item["supplier_id"]]['settings_pricelist']['priority_default'];
			} else {
				$item["priority"] = 1;
			}
        } else {
			if (isset($this->market->suppliers[$item["supplier_id"]]['settings_pricelist']['priority_default'])) {
				$item["priority"] = $this->market->suppliers[$item["supplier_id"]]['settings_pricelist']['priority_default'];
			} else {
				$item["priority"] = 10;
			}
        }
		
		if ($this->marketConfig['warehouse_priorities'] && $this->marketConfig['warehouse_priorities'][$item["supplier_id"]]) {
			$item["priority2"] = $this->marketConfig['warehouse_priorities'][$item["supplier_id"]]['PRIORITY'];
		} else {
			$item["priority2"] = 10;
		}
    }
	
    private function processPrices(array $data, array $competitorPrices): array
    {

        $supplierPricesByArticle = [];
        foreach ($data['supplier_prices'] as $price) {
            $article = $price['model'];
            if (!isset($supplierPricesByArticle[$article])) {
                $supplierPricesByArticle[$article] = [];
            }
            $supplierPricesByArticle[$article][] = $price;
        }
        
        
        $calculatedPrices = $this->market->calculatePrices($data['supplier_prices'], $data['catalog_prices'], $competitorPrices);
        \CProSet::setOption($this->optionStatus, 50);
		
		$results = $this->updateProductPrices($calculatedPrices, $data['catalog_prices']);
		
		\CProSet::setOption($this->optionStatus, 100);
        return $results;
    }
	
    private function updateProductPrices(array $calculatedPrices, array $catalogPrices): array
    {
        $results = [
            'total' => 0,
            'updated' => 0,
            'logs' => []
        ];
        
        $catalogByArticle = [];
        foreach ($catalogPrices as $item) {
            $article = $item['model'];
            $catalogByArticle[$article] = $item;
        }
        
		$countPrices = count($calculatedPrices);
		$setPrices = [];
		$i = 0;
        foreach ($calculatedPrices as $article => $priceData) {
            if (!isset($catalogByArticle[$article])) {
                continue;
            }
            
            $catalogItem = $catalogByArticle[$article];
            $results['total']++;
            
            $newPrice = $priceData['price'];

            $currentPrice = $catalogItem['b_price'] ? $catalogItem['b_price'] : $catalogItem[$this->market->getConfig('column_price')] ?? 0;
//prent($newPrice);prent($currentPrice);
            if ($newPrice > 0 && abs($newPrice - $currentPrice) > 0.01) {
                if ($this->mode === 'prod' || $this->mode === 'prod_partially') {
					$this->updateSinglePrice($catalogItem['product_id'], $newPrice);
					
					$logItem = "<a href='".$catalogItem["detail_page_url"]."' target='_blank'>" . $article . "</a> " . $currentPrice . " >> " . $newPrice;

					\CLog::add2log(
						[
							"event" => "UI", 
							"text" => $logItem, 
							"price_id" => $this->marketCode, 
							"detail" => $priceData["detail_log"], 
							"search" => $article
						]
					);
					
					// смотрим надо ли добавлять товар в карантин
					if ($currentPrice > 0 && $newPrice > 0 && ($currentPrice / $newPrice) >= 2) {
						$this->priceQuarantine[] = [
							"PRODUCT_ID" => $catalogItem["product_id"],
							"ARTICLE" => $article,
							"PRICE_ID" => $this->marketCode,
							"PRICE" => $newPrice,
							"PRICE_OLD" => $currentPrice,
						];

						/*//metrica
						$someText = '<b style="color:#f00;"> ТОВАР ПОПАЛ В КАРАНТИН СТАРАЯ ЦНЕА = '.round($arItem["PRICE_OLD"], 2).' => НОВАЯ ЦЕНА = '.round($arItem["PRICE"], 2).'<br>Тип цены: '.$this->priceID.'<br>'.$arItem['METRIC_LOG'];
						$dataMetrica = [
								'model' => $arItem['ARTICLE'],
								'price_id' => $this->priceID,
								'name' => 'Карантин',
								'code' => 'Quarantine',
								'result' => $someText,
						];
						$this->metric->Price($dataMetrica);*/
					}
				}

                $results['updated']++;
                $results['logs'][] = [
                    'article' => $article,
                    'old_price' => $currentPrice,
                    'new_price' => $newPrice,
                    'detail_log' => $priceData['detail_log'],
                ];
				

				//$arLog[] = $text;
				//$this->logger->log("LOG", $text);
				
			}
			
			if ($i % 100 === 0 || $i === $countPrices - 1) {
				\CProSet::setOption($this->optionStatus, round(50 + (($i + 1) / $countPrices) / 2 * 100, 2));
			}
			$i++;
			
			$setPrices[] = [
				"PRODUCT_ID" => $priceData['product']['b_id'],
				"ARTICLE" => $priceData['product']['article'],
				"PRICE_TYPE" => $this->marketCode,
				"PRICE_SUPPLIER" => $priceData['product']['price'],
				"PRICE_COMPETITOR" => $priceData['price_competitor'],
				"PRICE_LEVEL" => $priceData['price_level'],
				"SUPPLIER_ID" => $priceData['product']['supplier_id'],
			];
        }

		$this->setQuarantine();
		//prent($setPrices);
		if ($this->mode === 'prod' || $this->mode === 'prod_partially') {
			$this->updatePriceSet($setPrices);
		}
		
		$this->logger->log("LOG", "results", $results);
		
        return $results;
    }
	
    private function updateSinglePrice(int $productId, float $newPrice): void
    {
        switch ($this->marketCode) {
            case 'WB':
            case 'WBTL':
                $propertyCode = $this->marketCode === 'WBTL' ? 'WBTL_PRICE' : 'WBPRICE';
                \CIBlockElement::SetPropertyValuesEx($productId, false, [$propertyCode => $newPrice]);
                break;
                
            case 'OS':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['OZSB_PRICE' => $newPrice]);
                break;
                
            case 'AV':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['AVITO_PRICE' => $newPrice]);
                break;
                
            case 'SB':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['SBER_PRICE' => $newPrice]);
                break;
                
            case 'OZKZ':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['PRICE_OZKZ' => $newPrice]);
                break;
                
            case 'OZTI':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['PRICE_OZTI' => $newPrice]);
                break;
				
            case 'WBBY':
                \CIBlockElement::SetPropertyValuesEx($productId, false, ['WBBY_PRICE' => $newPrice]);
                break;
                
            default:
                $priceTypeId = $this->market->getConfig('price_type_id');
                if ($priceTypeId) {
                    $this->updateCatalogPrice($productId, $priceTypeId, $newPrice, $this->market->getConfig('currency'));
                }
                break;
        }
        
        $this->updateCatalogPriceTable($productId, $newPrice);
		
		\CExchange::updateProduct($productId, \CProSet::IB_CATALOG);
    }

    private function updateCatalogPrice(int $productId, int $priceTypeId, float $price, string $currency): bool
    {
        $priceFields = [
            'PRODUCT_ID' => $productId,
            'CATALOG_GROUP_ID' => $priceTypeId,
            'PRICE' => $price,
            'CURRENCY' => $currency,
        ];
        
        $dbPrice = \CPrice::GetList(
            [],
            [
                'PRODUCT_ID' => $productId,
                'CATALOG_GROUP_ID' => $priceTypeId,
            ]
        );
        
        if ($existingPrice = $dbPrice->Fetch()) {
            return \CPrice::Update($existingPrice['ID'], $priceFields);
        } else {
            return \CPrice::Add($priceFields) > 0;
        }
    }

    private function updateCatalogPriceTable(int $productId, float $price): void
    {
        global $DB;
        
        $priceKey = strtolower($this->marketCode);
        $tableFields = [
            'RU' => 'price_ru',
            'BY' => 'price_by',
            'PL' => 'price_pl',
            'YA' => 'price_ya',
            'WB' => 'price_wb',
            'WBTL' => 'price_wbtl',
            'OS' => 'price_os',
            'AV' => 'price_av',
            'SB' => 'price_sb',
            'KZ' => 'price_kz',
            'OZKZ' => 'price_ozkz',
            'OZTI' => 'price_ozti',
        ];
        
        if (isset($tableFields[$this->marketCode])) {
            $field = $tableFields[$this->marketCode];
            $DB->Query("UPDATE ci_price_catalog SET {$field} = {$price} WHERE product_id = {$productId}");
        }
    }
	
	/**
	 * все резервы
	 */
    private function getReservesOld(string $siteR): array
    {
        global $DB;
        $reserves = [];
		
		if($siteR == "ya") $siteR = "s1"; 

		$strSql = "SELECT ARTICLE as ARTICLE, RESERVED_{$siteR} as RESERVED FROM ci_reserved WHERE RESERVED_{$siteR} > 0";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$reserves[$row["ARTICLE"]] = (int)$row["RESERVED"];
		}
		
        return $reserves;
    }

    private function getReserves(): array
    {
		$siteId = $this->marketConfig['site_id'] ?? false;
		$tradingId = $this->marketConfig['tradingId'] ?? false;
		
		if (!$siteId || !$tradingId) return [];
		
        global $DB;
        $reserves = [];

		$strSql = "SELECT ARTICLE, SUPPLIER_ID, SUM(RESERVED) as TOTAL_RESERVED 
		FROM ci_order_reserved 
		WHERE 
			SUPPLIER_ID > 0 
		GROUP BY 
			ARTICLE, SUPPLIER_ID";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$reserves[$row["SUPPLIER_ID"]][$row["ARTICLE"]] = (int)$row["TOTAL_RESERVED"];
		}

        return $reserves;
    }
	
    public function setDynamicReserve($ar = [])
    {
        foreach ($ar as $k => $v) {
			$this->dynamicReserve[$k] = $v;
		}
    }
	
    private function getDynamicReserve(): array
    {
        return $this->dynamicReserve;
    }
	
	/**
	 * весь карантин
	 */
    private function getQuarantine(): array
    {
        global $DB;
        $quarantine = [];

		$strSql = "SELECT ARTICLE FROM ci_price_quarantine WHERE PRICE_ID = '{$this->marketCode}'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$quarantine[$row["ARTICLE"]] = true;
		}
        return $quarantine;
    }
	
	// пишим товары, если есть в карантин
	private function setQuarantine(){
		if(!$this->priceQuarantine || count_($this->priceQuarantine) <= 0) return false;
		$this->logger->log("LOG", "Есть товары в карантине", $this->priceQuarantine);
		$productIds = [];
		foreach($this->priceQuarantine as $key => $arItem){

			$strSql = "SELECT ID FROM ci_price_quarantine WHERE PRODUCT_ID  = '{$arItem["PRODUCT_ID"]}' AND PRICE_ID = '{$arItem["PRICE_ID"]}'";

			$in = array(
				"PRODUCT_ID" => "'".$arItem["PRODUCT_ID"]."'",
				"ARTICLE" => "'".$arItem["ARTICLE"]."'",
				"PRICE_ID" => "'".$arItem["PRICE_ID"]."'",
				"PRICE" => "'".$arItem["PRICE"]."'",
				"PRICE_OLD" => "'".$arItem["PRICE_OLD"]."'",
			);

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$this->logger->log("LOG", "update");
				$this->db->Update("ci_price_quarantine", $in, "WHERE ID='".$row["ID"]."'", $err_mess.__LINE__);
			}else{
				$this->logger->log("LOG", "add");
				$this->db->Insert("ci_price_quarantine", $in, $err_mess.__LINE__);
			}
			$this->logger->log("LOG", "Карантин", $in);

			$productId = intval($arItem["PRODUCT_ID"]);
			
			$productIds[$productId] = $productId;
			//\CExchange::updateProduct($productId);
		}
		
		\CPanelPricelist::addItemsProductIdDiff($productIds);
	}

    private function updatePriceSet($setPrices)
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        
        try {
            $connection->startTransaction();
            
            $deleteSql = "DELETE FROM ci_price_set WHERE PRICE_TYPE = '" . $helper->forSql($this->marketCode) . "'";
			if ($this->mode === 'prod_partially') {
				$productIds = array_column($setPrices, 'PRODUCT_ID');
				$deleteSql .= " AND PRODUCT_ID IN ('".implode("','", $productIds)."')";
			}
			
            $connection->query($deleteSql);
            
            if (empty($setPrices)) {
                $connection->commitTransaction();
                return true;
            }
            
            $values = [];
            foreach ($setPrices as $item) {
                $productId = (int)$item['PRODUCT_ID'];
                $article = $helper->forSql($item['ARTICLE']);
                $priceTypeSql = $helper->forSql($this->marketCode);
                $priceSupplier = (float)$item['PRICE_SUPPLIER'];
                $priceCompetitor = (float)$item['PRICE_COMPETITOR'];
                $priceLevel = (int)$item['PRICE_LEVEL'];
                $supplierId = (int)$item['SUPPLIER_ID'];
                
                $values[] = "($productId, '$article', '$priceTypeSql', $priceSupplier, $priceCompetitor, $priceLevel, $supplierId)";
            }
            
            $insertSql = "
                INSERT INTO ci_price_set 
                (PRODUCT_ID, ARTICLE, PRICE_TYPE, PRICE_SUPPLIER, PRICE_COMPETITOR, PRICE_LEVEL, SUPPLIER_ID)
                VALUES 
                " . implode(", ", $values);
            
            $connection->query($insertSql);
            
            $connection->commitTransaction();
            
            return true;
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $this->logger->log("ERROR", "Ошибка записи updatePriceSet", $e->getMessage());
        }
    }
	
	private function roundPrice(float $price, int $round): float
	{
		if ($round >= 0) {
			return round($price, $round);
		}
		
		$multiplier = pow(10, abs($round));
		return floor($price / $multiplier) * $multiplier;
	}
}