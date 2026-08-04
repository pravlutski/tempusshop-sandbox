<?php
namespace Panel\Manager\Market;

use Panel\Manager\Config\MarketConfig;

\CBitrixComponent::includeComponentClass('admin:price.monitoring');

abstract class AbstractMarket
{
    protected string $marketCode;
    protected array $config;
    protected array $options = [];
    protected array $competitorPrices = [];
    protected array $priceFilter = [];
    protected $dbPanel;
	
    protected ?array $saleItems = null;
    protected ?array $arMargin = null;
    protected ?array $indivMarkups = null;
    protected ?array $arSuppName = null;
    public array $suppliers = [];
    public array $warehouseSupplier = [];
    public array $minSupplierPrices = [];
    protected ?array $arBrandMargin = null;
    public string $lastError = '';
	
	
    public function __construct(string $marketCode)
    {
        $this->marketCode = $marketCode;
        $this->lowerMarketCode = strtolower($marketCode);
        $this->config = MarketConfig::getConfig($marketCode);
        $this->loadOptions();
		
		$this->monitoring = new \PriceMonitoringComponent;
		$this->monitoring->priceType = $this->lowerMarketCode;
		
		$this->dbPanel = new \DBPanel;
    }
	
    protected function loadOptions(): void
    {	
		$brandDiscountsJson = \COption::GetOptionString("panel.manager", "PRICEUPDATE_BRAND_DISCOUNT_{$this->lowerMarketCode}");
		$brandDiscounts = json_decode($brandDiscountsJson, true) ?: [];
		
        $this->options = [
            'margin' => (float)\COption::GetOptionString('panel.manager', "PRICELIST_MARGIN_{$this->marketCode}", 0),
            'min_margin_per' => (float)\COption::GetOptionString('panel.manager', "PRICEUPDATE_MIN_PER_{$this->marketCode}", 0),
            'max_margin_per' => (float)\COption::GetOptionString('panel.manager', "PRICEUPDATE_MAX_PER_{$this->marketCode}", 100),
            'rev_min' => (float)\COption::GetOptionString('panel.manager', "PRICEUPDATE_REV_MIN_{$this->marketCode}", 0),
            'mp_commission' => (float)\COption::GetOptionString('panel.manager', "PRICEUPDATE_MP_COMMISSION_{$this->marketCode}", 0),
            'rrc_required' => \COption::GetOptionString('panel.manager', "PRICELIST_REQUIRED_RRC_{$this->marketCode}") == "Y",
            'take_market_prices' => \COption::GetOptionString('panel.manager', "PRICEUPDATE_TAKE_MARKET_PRICES_{$this->marketCode}") == "Y",
            'market_required' => \COption::GetOptionString('panel.manager', "PRICELIST_REQUIRED_MARKET_{$this->marketCode}") == "Y",
            'apply_rrp' => \COption::GetOptionString('panel.manager', "PRICEUPDATE_APPLY_RRP_{$this->marketCode}") == "Y",
            'apply_min_margin' => \COption::GetOptionString('panel.manager', "PRICEUPDATE_APPLY_MIN_MARGIN_{$this->marketCode}") == "Y",
            'min_margin_fail_per' => (float)\COption::GetOptionString('panel.manager', "PRICEUPDATE_MIN_MARGIN_FAIL_PER_{$this->marketCode}", 0),
            'brand_discounts' => $brandDiscounts,
        ];

		if ($this->config['option_status_parser']) {
			$this->options['status_parser'] = \CProSet::getOption($this->config['option_status_parser']);
		} else {
			$this->options['status_parser'] = 'end';
		}
		
		$this->options['price_deviation'] = \CProSet::getOption("PRICE_DEVIATION_ORDER");
		
        $controlRrc = json_decode(\CProSet::getOption("CONTROL_RRC"), true)[$this->marketCode] ?? [];
        if ($controlRrc) {
            $this->priceFilter["brand_id"] = $controlRrc;
        }
        $this->priceFilter["price_id"] = $this->lowerMarketCode;
    }
    
    public function getMarketCode(): string
    {
        return $this->marketCode;
    }
    
    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? null;
    }
	
    public function setConfig(string $key, $value)
    {
        $this->config[$key] = $value;
    }
    
    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }
    
    public function setOption(string $key, $value)
    {
        $this->options[$key] = $value;
    }
	
    public function canUpdatePrices(): bool
    {
        if ($this->getOption('rrc_required')) {
            $controlRrc = json_decode(\CProSet::getOption("CONTROL_RRC"), true)[$this->marketCode] ?? [];
            if (empty($controlRrc)) {
				$this->lastError = 'CONTROL_RRC не заполнены';
                return false;
            }
        }

		if ($this->getOption('status_parser') != 'end') {
			$this->lastError = 'Отмена. Запущен парсер.';
			return false;
		}
		
        return true;
    }

	public function hasRequiredCompetitorPrices(array $competitorPrices): bool
	{
		if (!$this->getOption('market_required')) {
			return true;
		}
		
		return count($competitorPrices) > 0;
	}

	public function getAvailablePrice(): array
	{
		$arPrice = [];

		$prices = $this->getPriceSupplier();

		$rate = $this->config['rate'];
		$round = $this->config['round'];
		$usePriceN = $this->shouldUsePriceN();

		foreach ($prices as &$item) {
			$item['id'] = $item['id'];
			$item['price_raw'] = $item['price'];
			if ($rate != 1.0) {
				$item['price'] = (float)($item['price'] / $rate);
			}
			
            if ($usePriceN && !empty($item['price_n'])) {
                $item['price'] = (float)($item['price_n'] / $rate);
            }
		}
		unset($item);

		$sebesFbo = $this->config['tbl_sebes_fbo'] ? $this->getSebesFbo() ?? [] : []; // себесы которые на FBO
		
		$priceMerged = [];
		foreach ($sebesFbo as $model => $data) {
			$priceMerged[] = $data;
		}
		
		foreach ($prices as $price) {
			if (!array_key_exists($price['model'], $sebesFbo)) {
				$priceMerged[] = $price;
			}
		}
		
		return $priceMerged;
	}
	
	
	public function getCatalogPrice($arArticles): array
	{
		$objPricelist = new \CPanelPricelist();
		
		$tmpCatalogPrice = $objPricelist->getCatalogPriceByFilter(["model" => $arArticles]);
		
		$arCatalogPrice = [];
		foreach ($tmpCatalogPrice as $item) {
			$arCatalogPrice[$item['model']] = $item;
		}
		
		$priceFbo = $this->config['tbl_price_fbo'] ? $this->getPriceFbo() ?? [] : []; // цены которые на FBO

		foreach ($priceFbo as $article => $item) {
			if ($arCatalogPrice[$article]) {
				$arCatalogPrice[$article]['b_price'] = $arCatalogPrice[$article][$this->config['column_price']]; // запоминаем цену из битры
				$arCatalogPrice[$article][$this->config['column_price']] = $item['price'];
			} else {
				$arCatalogPrice[$article] = [
					'model' => $item['model'],
					'price' => $item['price'],
				];
			}
		}
		$arCatalogPrice = array_values($arCatalogPrice);
		
		return $arCatalogPrice;
		/*$prices = $this->getPriceSupplier();

    
		$rate = $this->config['rate'];
		$round = $this->config['round'];
		$usePriceN = $this->shouldUsePriceN();
		
		foreach ($prices as &$item) {
			if ($rate != 1.0) {
				$item['price'] = (float)($item['price'] / $rate);
			}
			
            if ($usePriceN && !empty($item['price_n'])) {
                $item['price'] = (float)($item['price_n'] / $rate);
            }
		}
		unset($item);
	
		$sebesFbo = $this->getSebesFbo() ?? []; // цены которые на FBO

		$priceMerged = [];
		foreach ($sebesFbo as $model => $data) {
			$priceMerged[] = $data;
		}
		
		foreach ($prices as $price) {
			if (!array_key_exists($price['model'], $sebesFbo)) {
				$priceMerged[] = $price;
			}
		}
		

		//prent($priceMerged);
		
		//die;*/
		return $arCatalogPrice;
	}
	
	protected function getPriceSupplier (): array
	{
        $objPricelist = new \CPanelPricelist();
        
		$filter = $this->getPriceFilter();
        $price = $objPricelist->getPriceByFilter($filter, false, false, "price asc");

		return $price;
	}
	
	protected function getPriceFilter (): array
	{
		return $this->priceFilter ?? [];
	}
		
	public function setPriceFilter (array $filter)
	{
		foreach ($filter as $k => $v) {
			$this->priceFilter[$k] = $v;
		}
	}
	
	protected function getPriceFbo () {}
	protected function getSebesFbo () {}
	
    public function calculatePrices(array $supplierPrices, array $catalogPrices, array $competitorPrices): array
    {
		//$this->loadRequiredData();
		if ($this->options['only_rrc'] === true) {
			$competitorPrices = [];
		}

        $articles = array_column($catalogPrices, 'model');
        $this->findMinSupplierPrices($supplierPrices);

        $results = [];
        foreach ($catalogPrices as $catalogItem) {
            $article = $catalogItem['model'];
            
            if (!isset($this->minSupplierPrices[$article])) {
                //continue;
            }
			if (!$this->minSupplierPrices[$article]) continue;
				
            $supplierItem = $this->minSupplierPrices[$article];
            $competitorData = $competitorPrices[$article] ?? [];
            
			//global $USER;
			
			//if ($USER->getId() != 12677 && $supplierItem) {
			//	$supplierItem = $this->correctSupplierPrice($supplierItem);
			//}
			
            $productData = [
                'b_id' => $catalogItem['product_id'],
                'brand_id' => $supplierItem['brand_id'],
                'price' => $supplierItem['price'],
                'supplier_id' => $supplierItem['supplier_id'],
                'article' => $article,
                'model' => $article,
                'price_timestamp' => $supplierItem['timestamp'] ?? '',
                'correct_price' => $supplierItem['correct_price'] ?? 0,
                'take_priority_log' => $supplierItem['take_priority_log'] ?? '',
            ];
            
            $results[$article] = $this->calculateSinglePrice($productData, $competitorData, $catalogItem);
        }

        return $results;
    }
	
    public function loadRequiredData(): void
    {
        $this->loadIndividualMarkups();
        $this->loadSuppliers();
        //$this->loadBrandMargins();
        $this->matchBrands();
        $this->loadMargins();
        
        $defaultRrc = $this->config['default_rrc'] ?? [];
        if (isset($defaultRrc['supersale']) && $defaultRrc['supersale'] > 0) {
            $this->loadSaleItems();
        }
    }
	
    public function shouldUsePriceN(): bool
    {
        $defaultRrc = $this->config['default_rrc'] ?? [];
        return isset($defaultRrc['price_type']) && $defaultRrc['price_type'] == 'price_n';
    }
	
    protected function calculateSinglePrice(array $product, array $competitorData, array $catalogItem): array
    {
        $detailLog = "";
        $metricLog = "";
        $priceLevel = null;
		
		$this->setCompetitorPrice = $this->setPriceRRC = 0;
		
        if (isset($this->saleItems[$product['b_id']])) {
            $optimalPrice = $this->calculateOptimalPrice(
                $product['brand_id'], 
                $product['price'], 
                $product['b_id']
            );
            $detailLog = "Установлена РРЦ со скидкой. ({$this->getSupplierName($product['supplier_id'])}) - {$product['price']}\r\n";
            
            $finalPrice = $this->applyFinishPrice($optimalPrice, $product);
            
            return [
                'price' => $finalPrice,
                'detail_log' => $detailLog,
                'price_level' => -1,
				'product' => $product,
            ];
        }
        
        $calculatedPrice = $this->calculatePriceByLevels($product, $competitorData, $detailLog, $priceLevel);
//prent(['calculatedPrice' => $calculatedPrice]);
        if ($this->marketCode === 'BY') {
            $optimalPrice = $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
            if ($optimalPrice < $calculatedPrice) {
                $calculatedPrice = $optimalPrice;
                $detailLog = "Для BY взят минимум РРЦ: {$optimalPrice}\r\n" . $detailLog;
            }
        }
//prent(['calculatedPrice2' => $calculatedPrice]);
//prent(['competitorData' => $competitorData]);
		$priceCompetitor = $priceRRC = 0;
		// Если КЦ выше РРЦ, то ОПЦИОНАЛЬНО применять РРЦ. Эту настройку вывести в настройки мониторинга. Назвать “применять РРЦ, если КЦ выше”
		if ($competitorData) {
			//prent(['priceLevel' => $priceLevel]);
			if ($priceLevel > 0 && $this->options['apply_rrp']) {
				$optimalPrice = $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
				if ($calculatedPrice > $optimalPrice) {
					//$originalMinPer = $this->options['min_margin_per'];
					//$this->options['min_margin_per'] = $this->options['min_per_alt'];
					//$calculatedPrice = $this->calculatePriceByLevels($product, $competitorData, $detailLog, $priceLevel);
					//$this->options['min_margin_per'] = $originalMinPer;
					$detailLog = "Взяли РРЦ: {$optimalPrice} calculatedPrice > optimalPrice\r\n опционально - " . $detailLog;
					$calculatedPrice = $optimalPrice;
				} elseif ($this->options['apply_min_margin']) {
					/*$detailLog = "Взяли минимум cебес ({$product['price']}) + {$this->options['min_margin_per']} %\r\n" . $detailLog;
					
					$calculatedPrice = $product['price'] + $product['price'] * $this->options['min_margin_per'] / 100;
					//$calculatedPrice = 123;//(($tmpPrice - ($tmpPrice * $this->options['mp_commission'] / 100) - $product['price']) / $product['price']) * 100;
					prent(['Взяли минимум cебесыыы', $calculatedPrice]);*/
					$priceCompetitor = $this->setCompetitorPrice;
				}
			} elseif ($priceLevel == 0 && $this->options['apply_min_margin']) {
				//$detailLog = "Взяли минимум cебес ({$product['price']}) + {$this->options['min_margin_fail_per']} %\r\n опционально - " . $detailLog;
				//prent(['product' => $product]);
				//$calculatedPrice = $product['price'] + $product['price'] * $this->options['min_margin_fail_per'] / 100;
				//prent(['calculatedPrice3' => $calculatedPrice]);
			} elseif ($priceLevel == 0) {
				//$calculatedPrice = $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
			} else {
				$priceCompetitor = $this->setCompetitorPrice;
			}
		} else {
			$calculatedPrice = $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
		}

		$calculatedPrice = $this->applySpecificCoefficients($calculatedPrice);
//prent(['calculatedPrice4' => $calculatedPrice]);
        $finalPrice = $this->applyFinishPrice($calculatedPrice, $product);
//prent(['finalPrice' => $finalPrice]);
		/*
		и давай на время попробуем так:
		не применять наценку, если установлена КЦ
		*/
		if ($priceLevel == 0) {
			$tmpPrice = $finalPrice;
			$finalPrice = $this->correctSupplierPrice2($finalPrice, $product);
			
			if ($tmpPrice != $finalPrice) {
				
			}
		}
		//prent(['finalPrice2' => $finalPrice]);
		//if ($finalPrice != $asdasd)
		//prent($priceLevel);
		//prent($finalPrice);
		//prent($calculatedPrice);
		if ($product['correct_price'] != 0) {
			$detailLog .= "\r\nКоррекция цены = {$product['correct_price']}";
		}
		if ($product['take_priority_log']) {
			$detailLog .= "\r\n{$product['take_priority_log']}";
		}
		
		if ($this->getOption('need_price_rrc') && !$this->setPriceRRC) {
			$this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
		}
//prent(['finalPrice3' => $finalPrice]); 
        return [
            'price' => round($finalPrice, $this->config['round']),
            'price_competitor' => $priceCompetitor,
            'price_rrc' => $this->setPriceRRC,
            'detail_log' => $detailLog,
            'price_level' => $priceLevel,
            'product' => $product,
        ];
    }

    protected function calculatePriceByLevels(array $product, array $competitorData, string &$detailLog, ?int &$priceLevel, int $level = 1): float
    {
        $priceLevel = $level;
        $competitorPrice = $competitorData['minPrice' . ($level === 1 ? '' : $level)] ?? 0;
		$this->setCompetitorPrice = $competitorPrice;
		
        if ($competitorPrice <= 0 || $product['price'] <= 0) {
            $detailLog = "Установлена ({$level},3) ({$this->getSupplierName($product['supplier_id'])}) - {$product['price']}";
            $priceLevel = 0;
			return $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
        }
        
		
        $margin = $this->getItemMargin($product['brand_id'], $competitorPrice);
        $tmpPrice = $competitorPrice + $competitorPrice * $margin / 100;
        //prent($margin);  
        //prent($product);  
        $revenuePercent = (($tmpPrice - $product['price']) / $product['price']) * 100;
        $revenue = $tmpPrice - $product['price'];
        $goodMarginality = true;

        if ($this->options['mp_commission'] > 0) {
            $marginality = (($tmpPrice - ($tmpPrice * $this->options['mp_commission'] / 100) - $product['price']) / $product['price']) * 100;
            
			if ($marginality < $this->options['min_margin_per']) {
                $goodMarginality = false;
            }
        }
		//&& $revenuePercent < $this->options['max_margin_per']
		/*prent([
			'goodMarginality' => $goodMarginality,
			'revenue' => $revenue,
			'rev_min' => $this->options['rev_min'],
			'revenuePercent' => $revenuePercent,
			'min_margin_per' => $this->options['min_margin_per'],
		]);*/
        if ($goodMarginality && 
            $revenue > $this->options['rev_min'] && 
            $revenuePercent > $this->options['min_margin_per']) {

            $newPrice = $tmpPrice;
			//prent($newPrice);
            //if (!in_array($this->marketCode, ['WB', 'WBTL', 'OS', 'AV', 'SB', 'OZKZ', 'OZTI'])) {
            //if (!in_array($this->marketCode, ['WB', 'OS'])) {
                //$newPrice = $this->modifyPriceWithoutSale($product['b_id'], $newPrice);
            //}

            $detailLog = $this->buildDetailLog($product, $competitorPrice, $tmpPrice, $margin, $revenuePercent, $revenue);

            //if (in_array($this->marketCode, ['YA', 'BY', 'RU'])) { 
			//if ($this->options['apply_rrp']) {
                $revenuePercent2 = (($newPrice - $product['price']) / $product['price']) * 100;
				//prent($revenuePercent2);prent($this->options['max_margin_per']);
                if ($revenuePercent2 > $this->options['max_margin_per']) {
                    $nextCompetitorPrice = $competitorData['minPrice' . ($level + 1)] ?? 0;
					
                    if ($nextCompetitorPrice > 0) {
                        return $this->calculatePriceByLevels($product, $competitorData, $detailLog, $priceLevel, $level + 1);
                    } else {
                        $detailLog = "Установлена ({$level},1) ({$this->getSupplierName($product['supplier_id'])}) - {$product['price']}";
                        $priceLevel = 0;
						//$asd = $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
						//prent(['asd' => $asd]);
						return $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
                    }
                }
           // }
			
            return $newPrice;
        } else {
            $nextCompetitorPrice = $competitorData['minPrice' . ($level + 1)] ?? 0;
            if ($nextCompetitorPrice > 0) {
                return $this->calculatePriceByLevels($product, $competitorData, $detailLog, $priceLevel, $level + 1);
            } else {
                $detailLog = "Установлена ({$level},2) ({$this->getSupplierName($product['supplier_id'])}) - {$product['price']} goodMarginality - {$goodMarginality}\r\n";
                $priceLevel = 0;
				return $this->calculateOptimalPrice($product['brand_id'], $product['price'], $product['b_id']);
            }
        }
    }
	
	// переопределить если надо в конекретном маркете
    public function getCompetitorPrices(array $articles): array
    {
		if (!$this->getOption('take_market_prices')) {
			return [];
		}
		
        $objPricelist = new \CPanelPricelist();
		$price = $objPricelist->getCompetitorPriceByFilter($this->lowerMarketCode, ["article" => $articles]);
//prent($price);
		$this->monitoring->applyBrandDiscounts($price);
//prent($price);
		$price = $this->prepareMinCompetitorPrices($price);
//prent($price);
		return $price;
    }
	
	public function prepareMinCompetitorPrices($price = []) {
		$groupedPrices = [];
		foreach ($price as $item) {
			$article = $item['ARTICLE'];
			if ($item['PRICE_WITH_BRAND_DISCOUNT222'] && $item['PRICE_WITH_BRAND_DISCOUNT2222'] < $item['PRICE']) {
				$groupedPrices[$article][] = $item['PRICE_WITH_BRAND_DISCOUNT'];
			} else {
				$groupedPrices[$article][] = $item['PRICE'];
			}
		}

		$result = [];
		foreach ($groupedPrices as $article => $prices) {
			sort($prices);

			$minPrices = array_slice($prices, 0, 3);

			$result[$article] = [
				'name' => $article,
				'minPrice' => $minPrices[0] ?? null,
				'minPrice2' => $minPrices[1] ?? null,
				'minPrice3' => $minPrices[2] ?? null
			];
		}

		return $result;
	}
	
    public function calculateOptimalPrice(int $brandId, float $price, int $productId): float
    {
        $price = round($price);
        $markup = 1;

        if (isset($this->arMargin[$brandId])) {
            $profileSettings = json_decode($this->arMargin[$brandId], true);
            foreach ($profileSettings as $item) {
                if ($price >= $item['price_from'] && $price <= $item['price_to'] && $item['markup'] > 0) {
                    $markup = (float)$item['markup'];
                }
            }
        } 
		
        elseif (is_array($this->config['default_rrc']['rules'] ?? [])) {
            foreach ($this->config['default_rrc']['rules'] as $item) {
                if ($price >= $item['price_from'] && $price <= $item['price_to'] && $item['markup'] > 0) {
                    $markup = (float)$item['markup'];
                }
            }
        }

        if (!empty($this->indivMarkups[$this->marketCode][$productId])) {
            $markupInd = $this->indivMarkups[$this->marketCode][$productId]['m'];
            $price = $price * floatval($markupInd);
        } else {
            $price = $price * $markup;
        }
		$this->setPriceRRC = $price;
        return $price;
    }
	
	public function findMinSupplierPrices(array $supplierPrices): array
	{
        $supplierPricesByArticle = [];
        foreach ($supplierPrices as $supplierItem) {
			$article = $supplierItem['model'] ?? $supplierItem['article'] ?? '';
			
			if (empty($article)) {
				continue;
			}
			
            if (!isset($supplierPricesByArticle[$article])) {
                $supplierPricesByArticle[$article] = [];
            }
            $supplierPricesByArticle[$article][] = $supplierItem;
        }
		
		$this->minSupplierPrices = [];
		$allPrices = [];
		
		global $USER;

		if ($USER->getId() == 12677 || $USER->getId() == 1) {
			//$this->config['default_rrc']['take_priority_supplier'] = 'Y';
		}

		// если стоит галка Учитывать приоритет поставщика сортируем по приоритету и цене
		if ($this->config['default_rrc']['take_priority_supplier'] == 'Y' || $this->options['force_priority_supplier'] === true) {
			foreach ($supplierPricesByArticle as $article => $arPrice) {
				$sortPrice = sort_nested_arrays($arPrice, ['priority' => 'asc', 'priority2' => 'asc', 'price' => 'asc'], false);
				$bestPrice = $sortPrice[0];
				
				$this->minSupplierPrices[$article] = [
					'id' => $bestPrice['id'],
					'price' => floatval($bestPrice['price'] ?? 0),
					'price_raw' => floatval($bestPrice['price_raw'] ?? 0),
					'supplier_id' => $bestPrice['supplier_id'],
					'supplier_name' => $this->getSupplierName($bestPrice['supplier_id']),
					'brand_id' => intval($bestPrice['brand_id'] ?? 0),
					'model' => $article,
					'article' => $article,
					'priority' => $bestPrice['priority'],
					'timestamp' => $bestPrice['price_timestamp'] ?? $bestPrice['timestamp'] ?? '',
				];
				
				$allPrices[$article] = $sortPrice;
			}

			if ($this->options['price_deviation'] > 0) {

				foreach ($supplierPricesByArticle as $article => $arPrice) {

					if (
						in_array($this->minSupplierPrices[$article]["supplier_id"], $this->warehouseSupplier) || 
						count($arPrice) == 1
					) {
						continue;
					}

					$sortPrice = sort_nested_arrays($arPrice, ['price' => 'asc'], false);
					
					$minPrice = $sortPrice[0];
					$replaceMin = false;
					foreach ($sortPrice as $price) {
						$diff = $price["price"] / $minPrice['price'] * 100 - 100;
						//prent($diff);prent("{$price["price"]} / {$minPrice['price']} * 100 - 100 = {$diff}");

						if (
							$diff > 0 && $diff <= $this->options['price_deviation'] &&
							$price['priority'] < $minPrice['priority']
						) {
							$this->minSupplierPrices[$article] = [
								'id' => $price['id'],
								'price' => floatval($price['price'] ?? 0),
								'price_raw' => floatval($price['price_raw'] ?? 0),
								'supplier_id' => $price['supplier_id'],
								'supplier_name' => $this->getSupplierName($price['supplier_id']),
								'brand_id' => intval($price['brand_id'] ?? 0),
								'model' => $article,
								'article' => $article,
								'take_priority_log' => "Мин была у " . $this->getSupplierName($minPrice['supplier_id']) . " - " . $minPrice['price'],
								'timestamp' => $price['price_timestamp'] ?? $price['timestamp'] ?? ''
							];
							$replaceMin = true;
							
							/* меняем местами */
							$index = null;
							foreach ($allPrices[$article] as $key => $item) {
								if ($item['id'] == $minPrice['id']) {
									$index = $key;
									break;
								}
							}

							if ($index !== null && $index !== 0) {
								$temp = $allPrices[$article][0];
								$allPrices[$article][0] = $allPrices[$article][$index];
								$allPrices[$article][$index] = $temp;
							}
						}
					}
					
					if (!$replaceMin) {
						$this->minSupplierPrices[$article] = [
							'id' => $minPrice['id'],
							'price' => floatval($minPrice['price'] ?? 0),
							'price_raw' => floatval($minPrice['price_raw'] ?? 0),
							'supplier_id' => $minPrice['supplier_id'],
							'supplier_name' => $this->getSupplierName($minPrice['supplier_id']),
							'brand_id' => intval($minPrice['brand_id'] ?? 0),
							'model' => $article,
							'article' => $article,
							'timestamp' => $minPrice['price_timestamp'] ?? $minPrice['timestamp'] ?? ''
						];

						/* меняем местами */
						$index = null;
						foreach ($allPrices[$article] as $key => $item) {
							if ($item['id'] == $minPrice['id']) {
								$index = $key;
								break;
							}
						}

						if ($index !== null && $index !== 0) {
							$temp = $allPrices[$article][0];
							$allPrices[$article][0] = $allPrices[$article][$index];
							$allPrices[$article][$index] = $temp;
						}
					}
				}
			}
		} else {
			foreach ($supplierPricesByArticle as $article => $arPrice) {
				$sortPrice = sort_nested_arrays($arPrice, ['price' => 'asc'], false);
				$bestPrice = $sortPrice[0];
				
				$this->minSupplierPrices[$article] = [
					'id' => $bestPrice['id'],
					'price' => floatval($bestPrice['price'] ?? 0),
					'price_raw' => floatval($bestPrice['price_raw'] ?? 0),
					'supplier_id' => $bestPrice['supplier_id'],
					'supplier_name' => $this->getSupplierName($bestPrice['supplier_id']),
					'brand_id' => intval($bestPrice['brand_id'] ?? 0),
					'model' => $article,
					'article' => $article,
					'timestamp' => $bestPrice['price_timestamp'] ?? $bestPrice['timestamp'] ?? ''
				];
				
				$allPrices[$article] = $sortPrice;
			}
		}

		return $allPrices;
	}
	
	protected function correctSupplierPrice(array $supplierItem): array
	{
		$correctPrice = $this->suppliers[$supplierItem['supplier_id']]['settings']['correct_price'][$this->marketCode] ?? false;
		
		if ($correctPrice) {
			$supplierItem['price'] = $supplierItem['price'] + ($supplierItem['price'] * $correctPrice / 100);
			$supplierItem['correct_price'] = $correctPrice;
		}
		return $supplierItem;
	}
	
	protected function correctSupplierPrice2($finalPrice, array &$item)
	{
		$correctPrice = $this->suppliers[$item['supplier_id']]['settings']['correct_price'][$this->marketCode] ?? false;

		if ($correctPrice) {
			$finalPrice = $finalPrice + ($finalPrice * $correctPrice / 100);
			$item['correct_price'] = $correctPrice;
		}
		return $finalPrice;
	}

    protected function applyFinishPrice(float $price, array $product): float
    {
        /*if (isset($this->saleItems[$product['b_id']])) {
            $priceOld = $price;
            $supersalePercent = $this->config['default_rrc']['supersale'] ?? 0;
            $price = $price - ($price * $supersalePercent / 100);
        }*/

        return round($price, $this->config['round']);
    }

    protected function modifyPriceWithoutSale(int $productId, float $price): float
    {
        $ar = \AHCatalog::OnGetOptimalPrice($productId, 1, [], "N", [], $this->config['site_id']);
        
        if (isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P") {
            $discountPercent = $ar["DISCOUNT"]["VALUE"] / 100;
            $price = $price / (1 - $discountPercent);
            $price = round($price, $this->config['round']);
        }
        
        return $price;
    }

    protected function applySpecificCoefficients(float $price): float
    {
        return $price;
    }
    
    protected function buildDetailLog(array $product, float $competitorPrice, float $tmpPrice, float $margin, float $revenuePercent, float $revenue): string
    {
        return sprintf(
            "Установлена цена маркетплейса = %s\n" .
            "tmp_price = %s + %s * %s / 100 = %s\n" .
            "revenue_p = ((%s - %s) / %s) * 100 = %s\n" .
            "revenue = %s - %s = %s\n" .
            "Мин. по прайсу - %s (%s - %s)",
            $competitorPrice,
            $competitorPrice, $competitorPrice, $margin, $tmpPrice,
            $tmpPrice, $product['price'], $product['price'], $revenuePercent,
            $tmpPrice, $product['price'], $revenue,
            $product['price'], $this->getSupplierName($product['supplier_id']), $product['price_timestamp']
        );
    }

    protected function getSupplierName($supplierId): string
    {
        return is_numeric($supplierId) ? $this->arSuppName[$supplierId] : (string)$supplierId;
    }
    
    protected function getItemMargin(int $brandId, $price = 0): float
    {
		//if (isset($this->options['global_margin'])) {
		//	return $this->options['global_margin'];
		//}
		
		if ($this->options['brand_discounts'] && $price) {
			$brandBX = $this->matchBrand[$brandId] ?? 0;
			$discount = $this->monitoring->calculateBrandDiscount($this->options['brand_discounts'], $brandBX, $price);
			
			if ($discount) {
				return $discount;
			}
		}
		
        if (isset($this->arBrandMargin[$brandId][$this->marketCode]) && 
            is_numeric($this->arBrandMargin[$brandId][$this->marketCode])) {
            return $this->arBrandMargin[$brandId][$this->marketCode];
        }
        
        return $this->options['margin'] ?? 0;
    }
    protected function loadSaleItems(): void
    {
        $arFilter = ["IBLOCK_ID" => \CProSet::IB_CATALOG, "SECTION_ID" => 370];
        $res = \CIBlockElement::GetList([], $arFilter, false, false, ["ID"]);
        while ($arFields = $res->GetNext()) {
            $this->saleItems[$arFields["ID"]] = $arFields["ID"];
        }
    }
    
    protected function loadMargins(): void
    {
        $objAnalysis = new \CPanelAnalysis;
        $arProfile = $objAnalysis->getListByFilter(["price_id" => $this->lowerMarketCode]);
        
        foreach ($arProfile as $arItem) {
            $this->arMargin[$arItem["brand_id"]] = $arItem["settings"];
        }
    }
    
    protected function loadIndividualMarkups(): void
    {
        global $DB;
        $strSql = "SELECT * FROM individual_markups";
        $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
        
        while ($row = $resultDB->Fetch()) {
            $this->indivMarkups[mb_strtoupper($row['source'])][$row['bitrix_id']] = [
                'm' => floatval($row['markup']),
                'model' => $row['model']
            ];
        }
    }
    
    protected function loadSuppliers(): void
    {
        $objSupplier = new \CPanelSupplier;
        foreach ($objSupplier->getList() as $arItem) {
            $this->arSuppName[$arItem["id"]] = $arItem["name"];
			
			
            $settings = json_decode($arItem["settings"], true);
            foreach ($settings["brand"] as $k => $v) {
                $settings["brand_priority"][$v["id"]] = $v["priority"];
            }
			
			$this->suppliers[$arItem['id']] = [
				'id' => $arItem['id'],
				'name' => $arItem['name'],
				'store_id' => (int)$arItem['store_id'],
				'settings' => $settings,
				'settings_pricelist' => json_decode($arItem['settings_pricelist'], true),
			];
			
			if ($arItem["is_warehouse"] == 'Y') {
				$this->warehouseSupplier[] = $arItem["id"];
			}
        }
    }
    
    protected function loadBrandMargins(): void
    {
        $objBrand = new \CPanelBrand;
        foreach ($objBrand->getList() as $arItem) {
			if ($arItem["margin_" . $this->lowerMarketCode] > 0)
				$this->arBrandMargin[$arItem["id"]][$this->marketCode] = $arItem["margin_" . $this->lowerMarketCode];
        }
    }

    protected function matchBrands(): void
    {
        $objBrand = new \CPanelBrand;
        foreach ($objBrand->getList() as $arItem) {
			if ($arItem['bitrix_id']) {
				$this->matchBrand[$arItem['id']] = $arItem['bitrix_id'];
			}
        }
    }






	
    //abstract public function getCompetitorPrices(array $articles): array;
}