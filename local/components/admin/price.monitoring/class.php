<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class PriceMonitoringComponent extends CBitrixComponent
{
    private $pageSize = 100; // Количество строк на странице
    private $currentPage = 1;
    private $sortField = 'SORT';
    private $sortOrder = 'desc';

    public function executeComponent()
    {
		global $DB;
		$this->db = $DB;
		$this->dbPanel = new DBPanel();
		
		$this->pricelist = new CPanelPricelist; 
		/*$result = $this->dbPanel->query("SELECT * FROM ms_profit_ru_6 LIMIT 0,10");
		$rows = $this->dbPanel->fetchAll($result);
		foreach ($rows as $row) {
			prent($row);
		}*/
        try {
            $this->checkModules();
            $this->processRequest();
            $this->arResult = $this->prepareData();
            $this->includeComponentTemplate();
        } catch (Exception $e) {
            ShowError($e->getMessage());
        }
    }

    private function checkModules()
    {
        if (!CModule::IncludeModule('iblock')) {
            throw new Exception('Модуль iblock не установлен');
        }
    }

    private function processRequest()
    {

        $this->currentPage = max(1, intval($_REQUEST['page'] ?? 1));
        $this->sortField = $_REQUEST['sort'] ?? 'SORT';
        $this->sortOrder = $_REQUEST['order'] ?? 'desc';

		$priceType = $this->getPriceTypes();
		if ($_REQUEST['price_type'] && $priceType[$_REQUEST['price_type']]) {
			$this->priceType = $_REQUEST['price_type'];
		} else {
			$this->priceType = 'ru';
		}
		
		// костыль пока
		$this->config = Panel\Manager\Config\MarketConfig::getConfig(strtoupper($this->priceType));

        $this->arParams['FILTER_ARTICLE'] = $_REQUEST['filter_article'] ?? '';
		$this->arParams['FILTER_UNCOMPETITIVE_PRICE'] = $_REQUEST['filter_uncompetitive_price'] ?? '';
		
        $this->arParams['CURRENT_PAGE'] = $this->currentPage;
        $this->arParams['SORT_FIELD'] = $this->sortField;
        $this->arParams['SORT_ORDER'] = $this->sortOrder;
		
        // Обработка AJAX запросов
        if ($_REQUEST['ajax'] == 'Y' && check_bitrix_sessid()) {
            $this->processAjaxRequest();
        }
    }

    private function processAjaxRequest()
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();

        header('Content-Type: application/json');

        $action = $_REQUEST['action'] ?? '';
        $response = [];

        try {
            switch ($action) {
                case 'update_table':
                    $response = $this->getTableData();
                    break;
				case 'get_monitoring_settings':
					$priceType = $_POST['price_type'] ?? $this->priceType;
					$response = [
						'success' => true, 
						'settings' => $this->getMonitoringSettingsByPriceType($priceType)
					];
					break;
				case 'save_monitoring_settings':
					$response = $this->updateMonitoringSettings($_POST);
					break;
				case 'get_competitors_list':
					$priceType = $_POST['price_type'] ?? $this->priceType;
					$response = ['success' => true, 'competitors' => $this->getCompetitorsFromDB(priceType: $priceType, detail: false)];
					break;
				case 'get_competitor_data':
					$competitorName = $_POST['competitor_name'] ?? '';
					$response = $this->getCompetitorData($competitorName);
					break;
				case 'save_competitor_data':
					$competitorData = [
						'ID' => $_POST['id'] ?? 0,
						'AUTO_PARSE' => $_POST['autoparse'] ?? '',
						'NAME' => $_POST['name'] ?? '',
						'PRICE_TYPE' => $_POST['price_type'] ?? 'ru',
						'PARSING_FILENAME' => $_POST['parsing_filename'] ?? '',
						'MAPPING' => $_POST['mappings'] ?? '[]',
						'SETTINGS' => $_POST['settings'] ?? '[]'
					];
					$response = $this->saveCompetitorData($competitorData);
					break;
				case 'delete_competitor':
					$competitorId = $_POST['competitor_id'] ?? 0;
					$response = $this->deleteCompetitor($competitorId);
					break;
				default:
					$response = ['success' => false, 'error' => 'Unknown action'];
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
        }

        echo json_encode($response);
        die();
    }

    private function prepareData()
    {
        return [
            'PRICE_TYPES' => $this->getPriceTypes(),
            'COMPETITORS' => $this->getCompetitors(),
            'BRANDS' => $this->getBrands(),
            'SETTINGS' => $this->getMonitoringSettingsByPriceType($this->priceType),
            'NAV_STRING' => $this->getNavigationString(),
            'FILTER_VALUES' => [
                'article' => $this->arParams['FILTER_ARTICLE'],
				'uncompetitive_price' => $this->arParams['FILTER_UNCOMPETITIVE_PRICE'],
            ],
            'PRICE_TYPE_ACTIVE' => $this->priceType,
        ];
    }

    private function getPriceTypes()
    {
        return [
            'ru' => 'RU Сайт',
            'by' => 'BY Сайт', 
            'os' => 'OZON',
            'yandex' => 'Яндекс Маркет',
            'wb' => 'WB',
            'wb_by' => 'WB (BY)',
        ];
    }

    private function getBrands()
    {
		$brands = [];
		$rs = CIBlockElement::GetList(['NAME' => 'ASC'], ["IBLOCK_ID" => CProSet::IB_BRANDS, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME']);

		while($ar = $rs->GetNext()){
			$brands[] = [
				'ID' => $ar['ID'],
				'NAME' => $ar['NAME'],
			];
		}
		return $brands;
    }
	
    public function getTableData()
    {
        $products = $this->getProductsData();
		// дополняем ценами и наличием
        $products = $this->prepareProductsData($products);
		
		if (!empty($this->arParams['FILTER_UNCOMPETITIVE_PRICE']) && $this->arParams['FILTER_UNCOMPETITIVE_PRICE'] == 'Y') {
			$totalCount = $totalUncompetiveCount = $this->arResult['COUNT_UNCOMPETITIVE_PRICE'];
		} else {
			$totalCount = $this->getTotalCount();
			$totalUncompetiveCount = $this->arResult['COUNT_UNCOMPETITIVE_PRICE'];
		}

        return [
            'success' => true,
            'html' => $this->renderTableHtml($products),
            'pagination' => $this->getPaginationData($totalCount),
            'total_count' => $totalCount,
            'total_uncompetive_count' => $totalUncompetiveCount ?? 0,
        ];
    }

	private function getProductsData()
	{
		global $DB;

		$whereConditions = [
			"PRICE_TYPE = '" . $DB->ForSql($this->priceType) . "'",
			"PRICE > 0" // Только товары с ценами
		];
		
		$coInvest = 0;
		if ($this->priceType == 'wb') {
			$coInvest = (float) COption::GetOptionString("panel.manager", "PRICEUPDATE_CO_INVEST_WB");
		}
		
		// Фильтр по артикулу
		if (!empty($this->arParams['FILTER_ARTICLE'])) {
			$whereConditions[] = "ARTICLE LIKE '%" . $DB->ForSql($this->arParams['FILTER_ARTICLE']) . "%'";
		}

		$whereClause = implode(' AND ', $whereConditions);
		$sql = "SELECT *
				FROM ci_price_competitor 
				WHERE {$whereClause}";

		$result = $DB->Query($sql);
		$products = [];
		
		while ($row = $result->Fetch()) {
			$article = $row['ARTICLE'];
			$price = $price_original = $row['PRICE'];

			if ($coInvest > 0) {
				//$price_original = $price / (1 + $coInvest / 100);
				$price_original = $price * (1 - $coInvest / 100);
			}
			//
			$change = $this->calculatePriceChange($price, $row['PREVIOUS_PRICE']);
			if ($products[$article]) {
				$products[$article]['COMPETITOR_PRICES'][$row['COMPETITOR_NAME']] = [
					'COMPETITOR' => $row['COMPETITOR_NAME'],
					'PRICE' => $price,
					'PRICE_ORIGINAL' => $price_original,
					'PRODUCT_URL' => $row['PRODUCT_URL'],
					'CHANGE_PERCENT' => $change['PERCENT'],
					'CHANGE_AMOUNT' => $change['AMOUNT']
				];
			} else {
				$products[$article] = [
					'ARTICLE' => $row['ARTICLE'],
					'BRAND_ID' => $row['BRAND_ID'],
					'OUR_PRICE' => 0,
					'PRICE_SUPPLIER' => 0,
					'COMPETITOR_MIN_PRICE' => 0,
					'SORT' => 0,
					'COMPETITOR_PRICES' => [
						$row['COMPETITOR_NAME'] => [
							'COMPETITOR' => $row['COMPETITOR_NAME'],
							'PRICE' => $price,
							'PRICE_ORIGINAL' => $price_original,
							'PRODUCT_URL' => $row['PRODUCT_URL'],
							'CHANGE_PERCENT' => $change['PERCENT'],
							'CHANGE_AMOUNT' => $change['AMOUNT'],
						]
					]
				];
			}

		}
		
		foreach ($products as &$item) {
			if (!empty($item['COMPETITOR_PRICES']) && is_array($item['COMPETITOR_PRICES'])) {
				//usort($item['COMPETITOR_PRICES'], function($a, $b) {
				//	return $a['PRICE'] <=> $b['PRICE'];
				//});
				
				$competitorPrice = sort_nested_arrays_v2($item['COMPETITOR_PRICES'], ["PRICE" => "asc"], true);

				$item['COMPETITOR_PRICES'] = $competitorPrice;
				
				//$item['COMPETITOR_MIN_PRICE'] = $item['COMPETITOR_PRICES'][0]['PRICE'] ?? 0;
				$item['COMPETITOR_MIN_PRICE'] = current($item['COMPETITOR_PRICES'])['PRICE'] ?? 0;
			} else {
				$item['COMPETITOR_MIN_PRICE'] = 0;
			}
		}
		unset($item);
		//$products = array_slice($products, 0, 2, true);
		//prent($products);
		
		$products = $this->setSort($products);
		
		// сортировку и пагинацию делаю тут
		$orderBy = $this->getOrderByClause();
		$products = sort_nested_arrays_v2($products, [$orderBy['SORT'] => $orderBy['ORDER']], true, true);
		//prent($products);

		$products = $this->getPrices($products);
		
		$offset = ($this->currentPage - 1) * $this->pageSize;
		$products = array_slice($products, $offset, $this->pageSize, true);

		
		return $products;
	}
	
	private function getPrices($products) {
		if (!$products) return [];
		$arArticle = array_column($products, 'ARTICLE');
		
		// добавляем цену конкурента
		$filter = [
			'price_id' => $this->priceType,
			'article' => $arArticle,
		];
		$priceSupplier = $this->pricelist->getPriceByFilterNew($filter, 'model', ['model', 'MIN(price) as price'], false, 'price');
		
		$rate = $this->config['rate'];
		
		foreach ($priceSupplier as $price) {
			if ($products[$price['model']]) {
				if ($rate != 1.0) {
					$products[$price['model']]['PRICE_SUPPLIER'] = (float)($price['price'] / $rate);
				} else {
					$products[$price['model']]['PRICE_SUPPLIER'] = $price['price'];
				}
				
			}
		}
		
		$promo_per = $sale_per = 0;
		if ($this->priceType == 'wb') {
			$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
			$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");
		}
		
		
		// наша цена
		$colPrice = $this->getColPrice();
		if ($colPrice) {
			$sql = "SELECT model, {$colPrice} FROM ci_price_catalog WHERE model IN ('".implode("','", $arArticle)."')";
			$result = $this->db->Query($sql);
			
			$competitors = [];
			while ($row = $result->Fetch()) {
				$price = $row[$colPrice];
				
				if ($promo_per > 0 || $sale_per > 0) {
					$price = $price / 100 * (100 - $sale_per) / (100 / (100 - $promo_per));
				}
				
				$products[$row['model']]['OUR_PRICE'] = $price;
			}
		}
		
		$this->arResult['COUNT_ALL_PRICE'] = count($products);
		
		$uncompetivePrice = array_filter($products, function($product) {
			return $product['OUR_PRICE'] > $product['COMPETITOR_MIN_PRICE'];
		});
		$this->arResult['COUNT_UNCOMPETITIVE_PRICE'] = count($uncompetivePrice);
		if (!empty($this->arParams['FILTER_UNCOMPETITIVE_PRICE']) && $this->arParams['FILTER_UNCOMPETITIVE_PRICE'] == 'Y') {
			$products = $uncompetivePrice;
		}
		
		$this->applyBrandDiscounts($products);
		//prent($products);
		return $products;
	}
	
	private function prepareProductsData($products) {
		global $DB;
		if (!$products) return [];
		
		$propAvail = $this->getPropAvail();
		
		$arArticle = array_column($products, 'ARTICLE');
		if ($propAvail) {
			
			$arSelect = [
				"ID",
				"NAME",
				"PROPERTY_{$propAvail}",
				"PROPERTY_CML2_ARTICLE",
				"PROPERTY_MODEL_ONLINER",
				'CODE',
				'IBLOCK_ID',
				'DETAIL_PAGE_URL',
			];
			$arFilter = [
				'IBLOCK_ID' => 16, 
				//'ID' => array_keys($products)
				//'PROPERTY_CML2_ARTICLE' => $arArticle
			];
			if ($this->priceType == 'by') {
				$arFilter['=PROPERTY_MODEL_ONLINER'] = array_keys($products);
			} else {
				
				$arFilter['=PROPERTY_CML2_ARTICLE'] = $arArticle;
			}
			
			$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

			while($ar = $rs->GetNext()){
				if ($this->priceType == 'by') {
					if($products[$ar['PROPERTY_MODEL_ONLINER_VALUE']]) {
						$products[$ar['PROPERTY_MODEL_ONLINER_VALUE']]['STATUS'] = $ar["PROPERTY_{$propAvail}_VALUE"];
					} elseif($products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]) {
						$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['STATUS'] = $ar["PROPERTY_{$propAvail}_VALUE"];
					}
				} else {
					if($products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]) {
						$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['STATUS'] = $ar["PROPERTY_{$propAvail}_VALUE"];
					}
				}
				
				if ($products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['STATUS'] == 'Нет в наличии') {
					$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['STATUS_CLASS'] = 'out-of-stock';
				} else {
					$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['STATUS_CLASS'] = 'in-stock';
				}
				$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['NAME'] = $ar["NAME"];
				$products[$ar['PROPERTY_CML2_ARTICLE_VALUE']]['DETAIL_PAGE_URL'] = $ar["DETAIL_PAGE_URL"];
			}
		}

		return $products;
	}
	
			
	private function setSort($products) {
		if (!$products) return [];

		$arArticle = array_column($products, 'ARTICLE');
		
		$result = $this->dbPanel->query("SELECT * FROM ms_profit_ru_6 WHERE model IN ('".implode("','", $arArticle)."')");
		$rows = $this->dbPanel->fetchAll($result);
		foreach ($rows as $row) {
			$products[$row['model']]['SORT'] = $row['sellQuantity'];
		}
		//prent($products);
		return $products;
	}
	
	private function getOrderByClause()
	{
		$fieldMap = [
			'ARTICLE' => 'ARTICLE',
			'OUR_PRICE' => 'OUR_PRICE',
			'COMPETITOR_MIN_PRICE' => 'COMPETITOR_MIN_PRICE',
			'SORT' => 'SORT',
		];

		$field = $fieldMap[$this->sortField] ?? 'ARTICLE';
		$order = $this->sortOrder === 'desc' ? 'desc' : 'asc';

		return [
			'SORT' => $field,
			'ORDER' => $order,
		];
	}

	private function getTotalCount()
	{
		global $DB;
				
		$whereConditions = [
			"PRICE_TYPE = '" . $DB->ForSql($this->priceType) . "'",
			"PRICE > 0"
		];

		if (!empty($this->arParams['FILTER_ARTICLE'])) {
			$whereConditions[] = "ARTICLE LIKE '%" . $DB->ForSql($this->arParams['FILTER_ARTICLE']) . "%'";
		}

		$whereClause = implode(' AND ', $whereConditions);

		$sql = "SELECT COUNT(DISTINCT ARTICLE) as total
				FROM ci_price_competitor 
				WHERE {$whereClause}";

		$result = $DB->Query($sql);
		$row = $result->Fetch();
		
		return intval($row['total']);
	}

    private function getSiteIdByPrice()
    {
		$ar = [
			'ru' => 's1',
			'by' => 's2',
			'os' => 's1',
			'yandex' => 's1',
			'wb' => 's1',
			'wb_by' => 's2',
		];
		return $ar[$this->priceType] ?? false;
    }
	
    private function getColPrice()
    {
		$ar = [
			'ru' => 'price_discount_ru',
			'by' => 'price_discount_by',
			'os' => 'price_os',
			'yandex' => 'price_discount_ya',
			'wb' => 'price_wb',
			'wb_by' => 'price_wbby',
		];
		return $ar[$this->priceType] ?? false;
    }
	
    private function getPropAvail()
    {
		$ar = [
			'ru' => 'AVAILABILITY_RU',
			'by' => 'AVAILABILITY_BY',
		];
		return $ar[$this->priceType] ?? false;
    }

    private function calculatePriceChange($currentPrice, $previousPrice)
    {
        if (!$previousPrice || !$currentPrice || $previousPrice == 0) {
            return ['PERCENT' => 0, 'AMOUNT' => 0];
        }

        $changeAmount = $currentPrice - $previousPrice;
        $changePercent = $previousPrice ? (($changeAmount / $previousPrice) * 100) : 0;

        return [
            'PERCENT' => round($changePercent, 2),
            'AMOUNT' => $changeAmount
        ];
    }

    private function renderTableHtml($products)
    {
        ob_start();
		$competitors = $this->priceType == 'ru' ? $this->getCompetitors() : false;
		//prent($products);
        ?>
        <table class="monitoring-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="ARTICLE">Артикул</th>
                    <th>Статус</th>
                    <th>Наша цена</th>
                    <th class="sortable" data-sort="COMPETITOR_MIN_PRICE">Мин. цена конкурента</th>
                    <th>Цены конкурентов</th>
                    <th class="sortable" data-sort="SORT">Изменение</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            Нет данных для отображения
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
						<?
						$name = $product['NAME'] ?? $product['ARTICLE'];
						$class = ($product['OUR_PRICE'] > $product['COMPETITOR_MIN_PRICE'] ? 'uncompetive-price' : '');
						
						$margin_rub = $margin_percent = 0;
						$margin_rub = $product['OUR_PRICE'] - $product['COMPETITOR_MIN_PRICE'];
						if ($product['OUR_PRICE'] != 0)
							$margin_percent = ($margin_rub / $product['OUR_PRICE']) * 100;

						$margin_rub = round($margin_rub, 2);
						$margin_percent = round($margin_percent, 2);
						
						$revenue_p = 0;
						$revenue = $product['OUR_PRICE'] - $product['PRICE_SUPPLIER'];
						if($product['PRICE_SUPPLIER'] > 0){
							$revenue_p = ($revenue / $product['PRICE_SUPPLIER']) * 100;
							$revenue_p = round($revenue_p, 2);
						}
						if (in_array($this->priceType, ['ru', 'by']) && $product['DETAIL_PAGE_URL']) {
							//DETAIL_PAGE_URL
							$link = $this->config['url'] . $product['DETAIL_PAGE_URL'];
						}
						?>
                        <tr data-product-id="<?= $product['ID'] ?>" class="">
                            <td class="product-name">
								<?if($link):?>
                                <a href="<?= $link ?>" target="_blank"><span class="product-article"><?= htmlspecialcharsbx($name) ?></span></a>
								<?else:?>
								<span class="product-article"><?= htmlspecialcharsbx($name) ?></span>
								<?endif?>
                            </td>
                            <td class="status <?=$product['STATUS_CLASS']?>"><?=$product['STATUS']?></td>
                            <td class="our-price">
                                <span class="<?=$class?>"><?= number_format($product['OUR_PRICE'], 2, '.', ' ') ?></span><br>
								Себес <?= number_format($product['PRICE_SUPPLIER'], 2, '.', ' ') ?><br>
								ВП: <?= number_format($revenue, 2, '.', ' ') ?><br>
								ВП %: <?= number_format($revenue_p, 2, '.', ' ') ?><br>
								Дельта с конкурентом<br>
								<?= number_format($margin_rub, 2, '.', ' ') ?><br>
								<?= number_format($margin_percent, 2, '.', ' ') ?> %
                            </td>
                            <td class="min-competitor-price">
                                <?= number_format($product['COMPETITOR_MIN_PRICE'], 2, '.', ' ') ?><br>
                                <?= number_format($product['PRICE_WITH_BRAND_DISCOUNT'], 2, '.', ' ') ?>
                            </td>
                            <td class="competitor-prices">
								<?if($competitors):?>
									<?php foreach ($competitors as $competitor): ?>
										<?if ($product['COMPETITOR_PRICES'][$competitor]) :?>
										<?
										$competitorPrice = $product['COMPETITOR_PRICES'][$competitor];
										?>
										<div class="competitor-row">
											<span class="competitor-name"><?if($competitorPrice['PRODUCT_URL']):?><a href="<?=$competitorPrice['PRODUCT_URL']?>" target="_blank"><?endif?><?= htmlspecialcharsbx($competitorPrice['COMPETITOR']) ?><?if($competitorPrice['PRODUCT_URL']):?></a><?endif?></span>
											<span class="competitor-price">
												<?= number_format($competitorPrice['PRICE'], 2, '.', ' ') ?>
												<?if($competitorPrice['PRICE']):?>
													<?= number_format($competitorPrice['PRICE_ORIGINAL'], 2, '.', ' ') ?>
												<?endif?>
											</span>

										</div>
										<?else:?>
										<div class="competitor-row">
											<span class="competitor-name"><?=$competitor?></span>
										</div>
										<?endif?>
									<?php endforeach; ?>
								<?else:?>
									<?//prent($product['COMPETITOR_PRICES']);?>
									<?php foreach ($product['COMPETITOR_PRICES'] as $competitorPrice): ?>
										<div class="competitor-row">
											<span class="competitor-name"><?if($competitorPrice['PRODUCT_URL']):?><a href="<?=$competitorPrice['PRODUCT_URL']?>" target="_blank"><?endif?><?= htmlspecialcharsbx($competitorPrice['COMPETITOR']) ?><?if($competitorPrice['PRODUCT_URL']):?></a><?endif?></span>
											<span class="competitor-price">
												<?= number_format($competitorPrice['PRICE'], 2, '.', ' ') ?>
												<?if($competitorPrice['PRICE']):?>
													(<?= number_format($competitorPrice['PRICE_ORIGINAL'], 2, '.', ' ') ?>)
												<?endif?>
											</span>
										</div>
									<?php endforeach; ?>
								<?endif?>
                            </td>
                            <td class="price-changes">
								<?if($competitors):?>
									<?php foreach ($competitors as $competitor): ?>
										<?if ($product['COMPETITOR_PRICES'][$competitor]) :?>
										<?
										$competitorPrice = $product['COMPETITOR_PRICES'][$competitor];
										?>
										<div class="change-row">
											<?= $this->formatPriceChange($competitorPrice['CHANGE_PERCENT'], $competitorPrice['CHANGE_AMOUNT']) ?>
										</div>
										<?else:?>
										<div class="change-row">нет в наличии</div>
										<?endif?>
									<?php endforeach; ?>
								<?else:?>
									<?php foreach ($product['COMPETITOR_PRICES'] as $competitorPrice): ?>
										<div class="change-row">
											<?= $this->formatPriceChange($competitorPrice['CHANGE_PERCENT'], $competitorPrice['CHANGE_AMOUNT']) ?>
										</div>
									<?php endforeach; ?>
								<?endif?>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private function getMinCompetitorPrice($competitorPrices)
    {
        if (empty($competitorPrices)) {
            return '-';
        }
        
        $minPrice = min(array_column($competitorPrices, 'price'));
        return number_format($minPrice, 2, '.', ' ');
    }

    private function formatPriceChange($percent, $amount)
    {
		$amount = number_format($amount, 2, '.', ' ');
        if ($percent > 0) {
            return '<span class="change-positive">+'.round($percent,1).'%/+'.$amount.'</span>';
        } elseif ($percent < 0) {
            return '<span class="change-negative">'.round($percent,1).'%/'.$amount.'</span>';
        } else {
            return '<span class="change-neutral">0</span>';
        }
    }

    private function getPaginationData($totalCount)
    {
        $totalPages = ceil($totalCount / $this->pageSize);
        
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'page_size' => $this->pageSize
        ];
    }

    private function getNavigationString()
    {
        return '';
    }

	private function getCompetitorsFromDB($priceType = '', $detail = true)
	{
		global $DB;
		
		if ($priceType) {
			$sql = "SELECT * FROM ci_competitors WHERE PRICE_TYPE = '{$priceType}' ORDER BY NAME";
		} else {
			$sql = "SELECT * FROM ci_competitors ORDER BY NAME";
		}
		
		$result = $DB->Query($sql);
		
		$competitors = [];
		while ($row = $result->Fetch()) {
			if (str_starts_with($row['PARSING_FILENAME'], '/upload/competitor_prices/')) {
				$filename = basename($row['PARSING_FILENAME']);
			} else {
				$filename = $row['PARSING_FILENAME'];
			}
			
			$lastParse = '';
			$file = "/var/www/bitrix_logs/debug/competitor/.last_parse_{$row['ID']}.log";
			if (file_exists($file)) {
				$time = filemtime($file);
				$lastParse = date("d.m.Y H:i:s", $time);
			}
			
			$competitors[] = [
				'ID' => $row['ID'],
				'AUTO_PARSE' => ($row['AUTO_PARSE'] == 'Y' ? 'Y' : 'N'),
				'NAME' => $row['NAME'],
				'PRICE_TYPE' => $row['PRICE_TYPE'],
				'MAPPING' => $detail && $row['MAPPING'] ? json_decode($row['MAPPING'], true) : [],
				'SETTINGS' => $detail && $row['SETTINGS'] ? json_decode($row['SETTINGS'], true) : [],
				'PARSING_FILENAME' => $filename ?: '',
				'LAST_PARSE' => $lastParse,
			];
		}
		
		return $competitors;
	}

	private function getCompetitorByName($name)
	{
		global $DB;
		
		$sql = "SELECT * FROM ci_competitors WHERE NAME = '" . $DB->ForSql($name) . "'";
		$result = $DB->Query($sql);
		
		if ($row = $result->Fetch()) {
			return [
				'ID' => $row['ID'],
				'AUTO_PARSE' => ($row['AUTO_PARSE'] == 'Y' ? 'Y' : 'N'),
				'NAME' => $row['NAME'],
				'PRICE_TYPE' => $row['PRICE_TYPE'],
				'MAPPING' => $row['MAPPING'] ? json_decode($row['MAPPING'], true) : [],
				'SETTINGS' => $row['SETTINGS'] ? json_decode($row['SETTINGS'], true) : [],
				'PARSING_FILENAME' => $row['PARSING_FILENAME'] ?: ''
			];
		}
		
		return null;
	}

	private function saveCompetitorToDB($competitorData)
	{
		global $DB;
		
		$fields = [
			'AUTO_PARSE' => "'" . ($competitorData['AUTO_PARSE'] == 'Y' ? 'Y' : 'N') . "'",
			'NAME' => "'" . $DB->ForSql($competitorData['NAME']) . "'",
			'PRICE_TYPE' => "'" . $DB->ForSql($competitorData['PRICE_TYPE']) . "'",
			'MAPPING' => "'" . $DB->ForSql(json_encode($competitorData['MAPPING'] ?? [])) . "'",
			'SETTINGS' => "'" . $DB->ForSql(json_encode($competitorData['SETTINGS'] ?? [])) . "'",
			'PARSING_FILENAME' => "'" . $DB->ForSql($competitorData['PARSING_FILENAME'] ?? '') . "'"
		];
		
		if (isset($competitorData['ID']) && $competitorData['ID'] > 0) {
			// Update existing
			$sql = "UPDATE ci_competitors SET " . 
				   implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($fields), $fields)) .
				   " WHERE ID = " . intval($competitorData['ID']);
		} else {
			// Insert new
			$sql = "INSERT INTO ci_competitors (" . implode(', ', array_keys($fields)) . 
				   ") VALUES (" . implode(', ', $fields) . ")";
		}
		
		$result = $DB->Query($sql);
		return $result !== false;
	}

	private function deleteCompetitorFromDB($competitorId)
	{
		global $DB;
		
		$sql = "DELETE FROM ci_competitors WHERE ID = " . intval($competitorId);
		$result = $DB->Query($sql);
		
		return $result !== false;
	}

	private function getCompetitors()
	{
		$dbCompetitors = $this->getCompetitorsFromDB();

		return array_map(fn($comp) => $comp['NAME'], $dbCompetitors);
	}

	public function getCompetitorData($competitorName)
	{
		$competitor = $this->getCompetitorByName($competitorName);
		
		if (!$competitor) {
			return ['success' => false, 'error' => 'Competitor not found'];
		}
		
		return [
			'success' => true,
			'competitor' => $competitor,
			'brands' => $this->getBrands()
		];
	}

	public function saveCompetitorData($competitorData)
	{
		try {
			if (isset($competitorData['MAPPING']) && is_string($competitorData['MAPPING'])) {
				$competitorData['MAPPING'] = json_decode($competitorData['MAPPING'], true) ?: [];
			}
			
			if (isset($competitorData['SETTINGS']) && is_string($competitorData['SETTINGS'])) {
				$competitorData['SETTINGS'] = json_decode($competitorData['SETTINGS'], true) ?: [];
			}
			
			$result = $this->saveCompetitorToDB($competitorData);
			
			if ($result) {
				return ['success' => true, 'message' => 'Competitor saved successfully'];
			} else {
				return ['success' => false, 'error' => 'Failed to save competitor'];
			}
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	public function deleteCompetitor($competitorId)
	{
		try {
			$result = $this->deleteCompetitorFromDB($competitorId);
			
			if ($result) {
				return ['success' => true, 'message' => 'Competitor deleted successfully'];
			} else {
				return ['success' => false, 'error' => 'Failed to delete competitor'];
			}
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}
	
	private function getPriceIdModule($priceType) {
		$ar = [
			'ru' => 'RU',
			'by' => 'BY',
			'wb' => 'WB',
			'os' => 'OS',
		];
		return $ar[$priceType] ?? false;
	}
	
	public function getMonitoringSettingsByPriceType($priceType)
	{
		$priceIdModule = $this->getPriceIdModule($priceType);
		
		if ($priceIdModule) {
			$brandDiscountsJson = COption::GetOptionString("panel.manager", "PRICEUPDATE_BRAND_DISCOUNT_{$priceIdModule}");
			
			$brandDiscounts = json_decode($brandDiscountsJson, true) ?: [];
			
			$val = COption::GetOptionString("panel.manager", "PRICEUPDATE_APPLY_RRP_{$priceIdModule}");
			if ($val && $val == 'Y') {
				$apply_rrp = true;
			}

			$val = COption::GetOptionString("panel.manager", "PRICEUPDATE_TAKE_MARKET_PRICES_{$priceIdModule}");
			if ($val && $val == 'Y') {
				$take_market_prices = true;
			}
			
			$val = COption::GetOptionString("panel.manager", "PRICEUPDATE_APPLY_MIN_MARGIN_{$priceIdModule}");
			if ($val && $val == 'Y') {
				$apply_min_margin = true;
			}
			
			return [
				'margin' => COption::GetOptionString("panel.manager", "PRICELIST_MARGIN_{$priceIdModule}"),
				'min_margin_rub' => COption::GetOptionString("panel.manager", "PRICEUPDATE_REV_MIN_{$priceIdModule}"),
				'min_margin_percent' => COption::GetOptionString("panel.manager", "PRICEUPDATE_MIN_PER_{$priceIdModule}"),
				'max_margin_percent' => COption::GetOptionString("panel.manager", "PRICEUPDATE_MAX_PER_{$priceIdModule}"),
				'co_invest' => COption::GetOptionString("panel.manager", "PRICEUPDATE_CO_INVEST_{$priceIdModule}"),
				'mp_commission' => COption::GetOptionString("panel.manager", "PRICEUPDATE_MP_COMMISSION_{$priceIdModule}"),
				'apply_rrp' => $apply_rrp ?? false,
				'apply_min_margin' => $apply_min_margin ?? false,
				'min_margin_fail_percent' => COption::GetOptionString("panel.manager", "PRICEUPDATE_MIN_MARGIN_FAIL_PER_{$priceIdModule}"),
				'brand_discounts' => $brandDiscounts,
				'take_market_prices' => $take_market_prices ?? false,
			];
		}

		return [];
	}

	public function updateMonitoringSettings($settingsData)
	{
		$priceType = $settingsData['price_type'] ?? $this->priceType;
		$priceIdModule = $this->getPriceIdModule($priceType);
		
		$brandDiscounts = $settingsData['brand_discounts'] ?? '[]';
		
		if ($settingsData['apply_rrp'] && $settingsData['apply_rrp'] == 'Y') {
			$apply_rrp = 'Y';
		} else {
			$apply_rrp = 'N';
		}
		if ($settingsData['take_market_prices'] && $settingsData['take_market_prices'] == 'Y') {
			$take_market_prices = 'Y';
		} else {
			$take_market_prices = 'N';
		}

		if ($settingsData['apply_min_margin'] && $settingsData['apply_min_margin'] == 'Y') {
			$apply_min_margin = 'Y';
		} else {
			$apply_min_margin = 'N';
		}
		$settings = [
			"PRICELIST_MARGIN_{$priceIdModule}" => floatval($settingsData['margin'] ?? -1),
			"PRICEUPDATE_REV_MIN_{$priceIdModule}" => floatval($settingsData['min_margin_rub'] ?? 0),
			"PRICEUPDATE_MIN_PER_{$priceIdModule}" => floatval($settingsData['min_margin_percent'] ?? 0),
			"PRICEUPDATE_MAX_PER_{$priceIdModule}" => floatval($settingsData['max_margin_percent'] ?? 0),
			"PRICEUPDATE_CO_INVEST_{$priceIdModule}" => floatval($settingsData['co_invest'] ?? 0),
			"PRICEUPDATE_MP_COMMISSION_{$priceIdModule}" => floatval($settingsData['mp_commission'] ?? 0),
			"PRICEUPDATE_APPLY_RRP_{$priceIdModule}" => $apply_rrp,
			"PRICEUPDATE_TAKE_MARKET_PRICES_{$priceIdModule}" => $take_market_prices,
			"PRICEUPDATE_APPLY_MIN_MARGIN_{$priceIdModule}" => $apply_min_margin,
			"PRICEUPDATE_MIN_MARGIN_FAIL_PER_{$priceIdModule}" => floatval($settingsData['min_margin_fail_percent'] ?? 0),
			//"PRICEUPDATE_MIN_PER_ALT_{$priceIdModule}" => floatval($settingsData['min_margin_alt_percent'] ?? 0),
			"PRICEUPDATE_BRAND_DISCOUNT_{$priceIdModule}" => $brandDiscounts
		];

		foreach ($settings as $key => $value) {
			COption::SetOptionString("panel.manager", $key, $value);
		}
		
		return ['success' => true, 'asdsad' => $settings, 'settingsData' => $settingsData];
	}
	
	public function applyBrandDiscounts(&$products)
	{
		$monitoringSettings = $this->getMonitoringSettingsByPriceType($this->priceType);
		$brandDiscounts = $monitoringSettings['brand_discounts'] ?? [];

		foreach ($products as &$product) {
			$productBrandId = $product['BRAND_ID'] ?? 0;
			
			if ($productBrandId > 0 && !empty($brandDiscounts)) {
				$minPrice = $product['COMPETITOR_MIN_PRICE'] ?? $product['PRICE'];
				//prent($minPrice);
				if ($minPrice > 0) {
					$discount = $this->calculateBrandDiscount($brandDiscounts, $productBrandId, $minPrice);
					if ($discount != 0) {
						$product['BRAND_DISCOUNT'] = $discount;
						$product['PRICE_WITH_BRAND_DISCOUNT'] = $minPrice * (1 - $discount / 100);
						//$product['PRICE_WITH_BRAND_DISCOUNT'] = $minPrice - $minPrice * ($discount / 100);
					}
				}
				
				/*$minPrice = $product['PRICE'] ?? 0; 
				
				if ($minPrice > 0) {
					$discount = $this->calculateBrandDiscount($brandDiscounts, $productBrandId, $minPrice);
					if ($discount != 0) {
						$product['PRICE_DISCOUNT'] = $minPrice * (1 - $discount * -1 / 100);
						//$product['PRICE_WITH_BRAND_DISCOUNT'] = $minPrice - $minPrice * ($discount / 100);
					}
				}*/
			}
		}
		unset($product);
	}

	public function calculateBrandDiscount($brandDiscounts, $brandId, $minCompetitorPrice)
	{
		foreach ($brandDiscounts as $discountRule) {
			if ($discountRule['brand_id'] == $brandId && 
				$minCompetitorPrice >= $discountRule['min_price'] && 
				$minCompetitorPrice <= $discountRule['max_price']) {
				return $discountRule['discount'];
			}
		}
		
		return 0;
	}
}
?>