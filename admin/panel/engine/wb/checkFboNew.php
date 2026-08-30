<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_wb_checkFboNew_php_WR");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require("classes/fbo/bootstrap.php");
set_time_limit(0);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBONEW
{
  private $dbPanel;
  private $db;
  private $bot;

  private string $cabinet;
  private string $module;

  private array $control = [];
  private array $fromMS = []; // Себестоимости МоегоСклада
  private array $sppAnalyticsData = []; // Данные для почасовой аналитики
	private array $costSubstituted = []; // Подмененные себестоимости
	private array $competitorsPrices = []; // Цены конкурентов
	private array $corrections = []; // корректировки

  private int $bot_threshold; // Процент отклонения для уведомления
  private int $threshold; // Процент отклонения себестоимости

	private ?FboStockValidator $validator = null;

	private array $stockNFCost;

  private array $excludedWHNames = [
    'Всего находится на складах',
    'В пути до получателей',
    'В пути возвраты на склад WB',
		'Электросталь',
		'Невинномысск',
		'Краснодар',
		'СПБ Шушары',
  ];

	private array $whWhitelist = [
		'Рязань (Тюшевское)' => true,
		'Воронеж' => true,
		'Тула' => true,
		'Коледино' => true,
		'Екатеринбург - Перспективная 14' => true,
		'Казань' => true,
		'Тверь' => true,
		'Красный Бор' => true,
		// '' => true,
		// '' => true,
	];

  private array $config = [
    'WR' => [
      'article' => 'WBARTICLE2',
      'price' => 'WBPRICE'
    ],
    'IP' => [
      'article' => 'WBARTICLE3',
      'price' => 'WBTL_PRICE'
    ],
		'TL' => [
      'article' => 'WBARTICLE3',
      'price' => 'WBTL_PRICE'
    ]
  ];

	public function __construct( $cabinet ){

		if ( !in_array($cabinet, ['WR', 'TL', 'IP']) ) throw new Exception("Invalid cabinet");

		$this->loadModules( $cabinet );

		$this->cabinet = $cabinet;
		$this->module = 'checkFbo_' . $cabinet;

		$this->bot_threshold = 5;
		$this->threshold = 9999;
		$this->sppAnalyticsData = [];

		$this->competitorsPrices = $this->getCompetitorsPrices();
		$this->competitorsSettings = $this->getCompetitorsSettings();

	}

  private function loadModules( string $cabinet ):void
  {
  		Loader::includeModule("main");
  	  Loader::includeModule("iblock");
      $this->dbPanel = new DBPanel();
      $this->dbMain = \Bitrix\Main\Application::getConnection();
      $this->bot = new TGNotifier;

			$this->validator = new FboStockValidator(
				panel: $this->dbPanel,
				main: $this->dbMain,
				cabinet: $cabinet
			);
  }

  private function getCostDataMS()
  {
    $costSubst = [];
    $modelsImport = [];
    $modelsImport = $this->getStockImportItems();

    $rows = $this->dbPanel->select(['*'], 'ms_cost_substitution')->where('supplier_id', 144)->make();

    foreach ( $rows as $row ) {
      $costSubst[ $row['model'] ] = $row['price'];
    }

    $rows = $this->dbPanel->select(['*'], 'ms_turnover_wb')->make();

    foreach ($rows as $row) {
      if ( in_array( $row['model'], $modelsImport ) && !empty( $costSubst[ $row['model'] ] ) ){
        $this->fromMS[$row['model']] = $costSubst[ $row['model'] ];
				$this->costSubstituted[] = $row['model'];
        continue;
      }
      $this->fromMS[$row['model']] = intval($row['quantity']); // На самом деле это не количество товара, а количество рублей
    }
    unset( $rows );
  }

  private function getHeaders():array
  {
    $strSql = "SELECT api FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $res = $this->dbMain->Query( $strSql );

    return
    [
      'Content-Type: application/json',
      'Authorization: ' . $res->Fetch()['api'],
    ];
  }

  public function run()
  {
	  $timeStart = date('Y.m.d G:i:s');
		$this->arLog['TIME_START'] = $timeStart;
		$date = date('Y-m-d');

    //Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart
		];
		$this->updateStatus($this->module, $arStat);

		$this->updateStatus($this->module, ['status_text' => 'Получение отчёта FBO', 'percent' => 10]);
    $this->getStockItems();
		$this->getCostNF();

		$startT = microtime(true);
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров из BT', 'percent' => 20]);
		$this->getItems();

		$this->updateStatus($this->module, ['status_text' => 'Получение себесов из BT', 'percent' => 30]);

		$this->getIndMarkups();
    $this->getCostDataMS();
		$this->getSebes();

		$this->getCiBrandID();

		$this->updateStatus($this->module, ['status_text' => 'Выполняем проверку ФБО', 'percent' => 50]);
		$this->checkFBOStock();

		try{
			$this->checkStockDifference();
		}
		catch( Throwable $e ){
			//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/checkFboDebug.txt', print_r($e->getMessage(), true));
			var_dump($e);
		}

		$itemsT = microtime(true);
		$this->PrintResult();

		$arStat = [
			'status' => 'COMPLETED',
			'status_text' => 'Выполнено',
			'percent' => '100',
			'time_end' => date('Y.m.d G:i:s')
		];
		$this->updateStatus( $this->module, $arStat );
	}

  private function getItems()
  {
    $priceProp = $this->getPropertyConfigValue('price');
    $articleProp = $this->getPropertyConfigValue('article');
	  $arSelect = [
      "ID",
      "IBLOCK_ID",
      "IBLOCK_SECTION_ID",
			"PROPERTY_BRAND",
      "PROPERTY_CML2_ARTICLE",
      "PROPERTY_WBARTICLE2",
      "PROPERTY_WBARTICLE3",
      "PROPERTY_WBPRICE",
      "PROPERTY_WBTL_PRICE"
    ];

	  $arFilter = [
     "IBLOCK_ID" => 16,
     "!PROPERTY_{$articleProp}" => false
    ];

	  $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

    while ($el = $result->GetNext()){
      if ( !isset( $this->stockFbo[$el["PROPERTY_{$articleProp}_VALUE"]] ) ) continue;
      if ( empty($el["PROPERTY_{$articleProp}_VALUE"]) ) {
        $this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
        continue;
      }
      if ( empty($el["PROPERTY_{$priceProp}_VALUE"]) ) {
        $this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
        continue;
      }
      $arSection = getSectionsElement($el["ID"]);

      $this->items[$el["PROPERTY_{$articleProp}_VALUE"]] = [
        "ID" => $el["ID"],
        "ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
        "WB_ARTICLE" => $el["PROPERTY_{$articleProp}_VALUE"],
        "PRICE" => $el["PROPERTY_WBPRICE_VALUE"],
				"BRAND_BX" => $el['PROPERTY_BRAND_VALUE'],
      ];
    }

    print_r("Получено товаров из БТ: " . count($this->items ?? []) . PHP_EOL);
	}

	private function getItemsStatus():void
	{
		$models = array_map(
			fn($item) => end( explode('_', $item) ),
			array_keys($this->stockFbo)
		);

		$this->validator->getInfo( $models );
	}

  private function getStockItems()
  {

    $headers = $this->getHeaders();
    $res = $this->request(
      url: 'https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains?groupBySa=true',
      headers: $headers,
    );
    $taskid = $res['result']['data']['taskId'];

    unset($res);
    sleep(20);

    $res = $this->request(
      url: "https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/{$taskid}/download",
      headers: $headers,
    );

		if ( $res['code'] != 200 ){
			$arStat = [
				'status' => 'COMPLETED',
				'status_text' => 'Не удалось получить отчет ВБ',
				'percent' => '100',
				'time_end' => date('Y.m.d G:i:s')
			];
			$this->updateStatus( $this->module, $arStat );
			print_r( $res['result'] );
			print_r( PHP_EOL );
			die;
		}

    foreach ( $res['result'] as $key => $value ) {
      if (count($value['warehouses']) <= 0) continue;

      foreach ( $value['warehouses'] as $wh ) {
        // if ( in_array( $wh['warehouseName'], $this->excludedWHNames) ){
				// 	print_r("{$value['vendorCode']} - {$wh['warehouseName']} - Исключен\n");
				// 	continue;
				// }
				if ( !isset($this->whWhitelist[ $wh['warehouseName'] ]) ){
					print_r("{$value['vendorCode']} - {$wh['warehouseName']} - Исключен\n");
					continue;
				}

        if ( isset($this->stockFbo[$value['vendorCode']]) ) {
          $this->stockFbo[$value['vendorCode']] = intval( $this->stockFbo[$value['vendorCode']] ) + intval( $wh['quantity'] );
          continue;
        }

        $this->stockFbo[$value['vendorCode']] = intval( $wh['quantity'] );
      }
    }
    print_r("Получено товаров от ВБ: " . count($this->stockFbo ?? []) . PHP_EOL);
  }

  private function getCiBrandIDLeg()
  {
	  foreach ($this->items as $key => &$v) {
	    $brands = array();
	    $brand = '0';
	    $strSql = "SELECT brand_id FROM ci_price WHERE model = '".$v['ARTICLE']."'" ;
	    $results = $this->dbMain->Query($strSql, false, $err_mess.__LINE__);
	    while ($row = $results->Fetch()){
	      $brands[] = $row['brand_id'];
	    }
	    foreach($brands as $k => $value){
	      $brand = $value;
	    }

	    if (!empty($brand)) {
	      $v['BRAND_ID'] = $brand;
	    } else {
	      $v['BRAND_ID'] = '';
	    }
	  }
	}

	private function getCiBrandID():void
	{
		$strSql = "SELECT id, bitrix_id FROM ci_brands";
		$res = $this->dbMain->Query( $strSql );
		$brandsDict = [];
		while ( $row = $res->Fetch() ){
			$brandsDict[ $row['bitrix_id'] ] = $row['id'] ?? 0;
		}
		foreach ( $this->items as $key => &$item ){
			$item['BRAND_ID'] = $brandsDict[ $item['BRAND_BX'] ] ?? 0;
		}
	}

  public function getIndMarkups()
  {
    $templateSql = "SELECT * FROM individual_markups WHERE source = '%s'";
    $strSql = sprintf(
      $templateSql,
      $this->cabinet == 'WR' ? 'wb' : 'wbtl'
    );

    $results = $this->dbMain->Query( $strSql );
    $this->markups = [];
    while ( $row = $results->Fetch() ){
      $this->markups[ $row['model'] ] = floatval($row['markup']);
    }
  }

	private function getSebes():void
	{
		switch( $this->cabinet ){
			case 'WR':
				$priceType = 'WB';
				break;
			case 'TL':
				$priceType = 'WBTL';
				break;
		}
		if ( empty($this->stockFbo) ) throw new Exception("FBO report was not collected or data incorrect");

		$models = array_map(
			fn($item) => end( explode('_', $item) ),
			array_keys($this->stockFbo)
		);
		$models = array_filter( $models );

		if ( empty($models) ) throw new Exception("Cannot extract models list from FBO report");

		$service = PanelManager::getPriceManager();
		$servicePrice = $service->updatePriceService( $priceType, "debug" );
		$servicePrice->market->setPriceFilter( [ 'article' => $models ] );
		$servicePrice->market->setConfig('tbl_sebes_fbo', false);
		$result = $servicePrice->getMinPurchasePrice();

		$prices = array_map( fn($item) => reset($item)['price'], $result );

		foreach ( $this->items as $key => &$v ){
			$v['SEBES'] = $prices[ $v['ARTICLE'] ] ?? '';
		}
	}

  public function getSebesLeg()
  {
    $counter = 0;
    foreach ($this->items as $key => &$v) {
      $tmpPrice = [];
      unset($minPrice);
      unset($price_id);

      $templateSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '%s' AND %s = 'Y' ORDER BY price ASC";

      $strSql = sprintf(
        $templateSql,
        $v['ARTICLE'],
        $this->cabinet == 'WR' ? 'active_wb' : 'active_wbtl'
      );

      $results = $this->dbMain->Query( $strSql );

      while ( $row = $results->Fetch() ){
        // if ( in_array($row['supplier_id'],["47", "129", "128", "144", "141"]) ) continue;
        $tmpPrice[ $row['id'] ] = [
          'price' =>floatval($row['price']),
          'model' =>$row['model'],
          'count' => intval($row['count']),
          'supplier_id' => $row['supplier_id']
        ];
      }

      $strSql = "SELECT * FROM ci_reserved WHERE ARTICLE = '{$v['ARTICLE']}'";
      $resultRes = $this->dbMain->Query($strSql, false, $err_mess.__LINE__);
      $excludeReserved = [];
      while ( $row = $resultRes->Fetch() ){
        $excludeReserved[ $row['ARTICLE'] ] = $row['RESERVED'];
      }

      $curReserved = null;
      $suppExcl = [];
      foreach($tmpPrice as $k => $value){
        if ( $curReserved != null ){
          if ( ($curReserved - $value['count'] >= 0) && !in_array($value['supplier_id'], $suppExcl) ){
            $curReserved = $curReserved - $value['count'];
            $suppExcl[] = $value['supplier_id'];
          }else{
            $minPrice = $value['price'];
            $price_id = $k;
            break;
          }
        }else{
          if ( isset($excludeReserved[$value['model']]) && ($excludeReserved[$value['model']] - $value['count'] >= 0) ){
            $curReserved = $excludeReserved[$value['model']] - $value['count'];
            $suppExcl[] = $value['supplier_id'];
          }else{
            $minPrice = $value['price'];
            $price_id = $k;
            break;
          }
        }
      }

      if (!empty($tmpPrice) && (!empty($minPrice) && !empty($price_id) )) {
        $counter++;
        $v['SEBES'] = $minPrice;
        $v['PRICE_ID'] = $price_id;
      } else {
        $v['SEBES'] ='';
        $v['PRICE_ID'] = '';
      }
    }
    print_r("Получено себесов из БТ: " . $counter . PHP_EOL);
  }

	private function getCostNF()
	{
		$strSql = "SELECT model, price FROM ci_price WHERE supplier_id = 144";
		$rows = $this->dbMain->Query( $strSql );
		$items = [];
		while ( $row = $rows->Fetch() ){
			$items[$row['model']] = $row['price'];
		}
		$this->stockNFCost = $items;

	}

	private function getCompetitorsSettings():array
	{
		return [
			'cpp' => COption::GetOptionString('panel.manager', 'PRICEUPDATE_CO_INVEST_WB'),
			'com' => COption::GetOptionString('panel.manager', 'PRICEUPDATE_MP_COMMISSION_WB'),
			'perc' => COption::GetOptionString('panel.manager', 'PRICELIST_MARGIN_WB'),
		];
	}

	private function calculateCompetitorPrice( string $model, float $cost ):bool|float
	{
		$priceData = $this->competitorsPrices[ $model ] ?? false;

		if ( $priceData === false ) return $priceData; // Нет цены - возвращаем false

		$min_profit_perc = 20;
		$perc = $this->competitorsSettings['perc'];
		$cpp = $this->competitorsSettings['cpp'];
		$com = $this->competitorsSettings['com'];


		foreach ( $priceData as $price ){
			try{
				$salePrice = $price * (1 + $perc / 100 );

			} catch( Throwable $e ){
				var_dump( $price );
				var_dump( $perc );
				die( $e->getMessage() );
			}
			$profit_perc = ( ( $salePrice * ( 1 - $com / 100) - $cost ) / $cost ) * 100;

			if ( $profit_perc >= $min_profit_perc ){
				return $salePrice;
			}

			return false;
		}

		return false;
	}

	private function getNewPrice( $model, $brand_id, $newprice )
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;

    switch( $this->cabinet ){
      case 'WR':
        $price_id = 'wb';
        break;
      case 'IP':
        $price_id = 'wbtl';
        break;
      case 'TL':
        $price_id = 'wbtl';
        break;
    }

		$arSettings = [
      "rate" => 1
    ];

		$arDefaultRRC = json_decode( CProSet::getOption("SETTINGS_RRC"), true )[$price_id];
		$arCurrency = $objCurrency->getDetail( $arSettings["currency"] );

		$itemPrice = floatval($newprice);
		$productID = 0;
		$markup = 1;

		$profile = $analysis->getListByFilter([
      "brand_id" => $brand_id,
      'price_id' => $price_id
    ]);

		if ( is_array($profile) && !empty($profile) ) {
			$profile = $profile[0];
			$profile["settings"] = json_decode( $profile["settings"], true );

			foreach( $profile["settings"] as $key => $arItem ){
        $inBetween = self::checkInBetween( $itemPrice, $arItem );
				if ( $inBetween && $arItem["markup"] > 0 ) {
          $markup = (float)$arItem["markup"];
        }
			}

		}
    elseif( is_array($arDefaultRRC["rules"]) ){
			foreach( $arDefaultRRC["rules"] as $key => $arItem ){
        $inBetween = self::checkInBetween( $itemPrice, $arItem );
				if ( $inBetween && $arItem["markup"] > 0 ) {
          $markup = (float)$arItem["markup"];
        }
			}
		}

    if ( !empty( $this->markups[$model] ) ){
      $itemPrice = $itemPrice * $this->markups[$model];
      return [
        'price' => $itemPrice,
        'm' => $this->markups[$model],
      ];
    }

		$itemPrice = round($itemPrice * $markup, 0);

		return [
      'price' => $itemPrice,
      'm' => $markup,
    ];
	}

  private function request( string $url, array $headers, string|bool $data = false ):array
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    if ( $data ) curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close( $ch );

		file_put_contents(
			"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/fboResponse.json",
			$res
		);

    return [
			'result' => json_decode($res, true),
			'code' => $code,
		];
  }

  private function getPropertyConfigValue( $key ):string
  {
    return $this->config[ $this->cabinet ][$key] ?? '';
  }

  private static function checkInBetween( $itemPrice, $arItem ):bool
  {
    return $itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"];
  }

  private function calculatePercentageDifferenceAlt($number1, $number2)
  {
			return 100 - ($number1 / ($number2 / 100)) ;
	}

  private function getStockImportItems():array
  {
    $strSql = "SELECT model FROM ci_price WHERE supplier_id IN (141, 144)";
    $res = $this->dbMain->Query( $strSql );

    if ( $res->getSelectedRowsCount() <= 0 ) return [];

    $models = [];
    while ( $row = $res->Fetch() ){
      $models[] = $row['model'];
    }

    return $models;
  }

	private function getCompetitorsPrices():array
	{
		$strSql = "SELECT * FROM ci_price_competitor WHERE PRICE_TYPE = 'wb' ORDER BY price ASC";
		$res = $this->dbMain->Query( $strSql );
		$result = [];

		while ( $row = $res->Fetch() ){
			$result[ $row['ARTICLE'] ][] = floatval($row['PRICE']);
		}

		return $result;
	}

  private function checkFBOStock()
  {
		if ( empty($this->stockFbo)) die("СДОХ, НЕ ПОЛУЧИВ ДАННЫЕ\n"); // Пока так

		$canValidate = true;
		try{
			$this->getItemsStatus();
		} catch ( UnauthorizedRequestException $e ){
			$this->bot->sendMessage("<b>Модуль ФБО WB:</b>\n\n Ошибка корректировки остатков. Кука умерла. Остатки не будут скорректированы");
			$canValidate = false;
		} catch ( Throwable $e ){
			$this->bot->sendMessage("<b>Модуль ФБО WB:</b>\n\n Ошибка корректировки остатков. Ошибка неизвестна. Остатки не будут скорректированы");
			var_dump($e);
			$canValidate = false;
		}

    $this->dbPanel->Query( "DELETE FROM wb_fbo_price_{$this->cabinet} WHERE 1=1" );
    $this->dbPanel->Query( "DELETE FROM wb_fbo_stock_{$this->cabinet} WHERE 1=1" );
    $this->dbPanel->Query( "DELETE FROM wb_fbo_cost_{$this->cabinet} WHERE 1=1" );

    $importDataStock = [];
    $importDataPrice = [];
    $importDataCost = [];

    echo "##########################\n";
    echo "Метод checkFBOStock {$this->cabinet} вызван\n";
    echo "stockFbo count: " . count($this->stockFbo) . "\n";
    echo "items count: " . count($this->items ?? []) . "\n";
    echo "fromMS count: " . count($this->fromMS ?? []) . "\n";
    echo "##########################\n";


		$corrections = [];

    foreach ( $this->stockFbo as $key => $value ) {
			if ( $canValidate ){
				$model = end( explode('_', $key) );
				if ( !$this->validator->checkIfVisible( $model ) ){
					$corrections[ $model ] = $value;
					continue;
				}
			}
			// if ( $value <= 1 ){
			// 	echo "{$this->items[$key]['ARTICLE']}: пропущен из-за остатка (<= 1)\n";
			// 	continue;
			// }
			$whatCost = '';
			$competitorPrice = false;
			$isCompetitor = false;
      $this->answer[$this->items[$key]['ARTICLE']]['asnw'] = '';

      if ( !isset($this->fromMS[$this->items[$key]['ARTICLE']]) ) { // Сообщение и ранний выход из цикла
        $priceArray = self::getNewPrice(
          $this->items[$key]['ARTICLE'],
          $this->items[$key]['BRAND_ID'],
          $this->items[$key]['SEBES']
        );
				$this->answer[ $this->items[$key]['ARTICLE'] ]['count'] = $value;
        $this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "
        Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
        Остаток FBO WB: {$value}<br>
        <span style=\"color:red\">Отсутствует в отчете МС</span><br>
        Себес из БТ:{$this->items[$key]['SEBES']}<br><br>
				Наценка: {$priceArray['m']}<br><br>
				<span style=\"color:green\">Оставляем цену РРЦ</span>: {$priceArray['price']}<hr><br><br>
        ";
				$this->control[] = [ $this->items[$key]['ARTICLE'] ];

				if ( isset($priceArray['price']) && $priceArray['price'] > 0 ){
					$importDataCost[] = [
						'article' => $this->items[$key]['ARTICLE'],
						'brand_id' => $this->items[$key]['BRAND_ID'],
						'cost' => $this->items[$key]['SEBES'],
					];
					$importDataStock[] = [
						'article' => $this->items[$key]['ARTICLE'],
						'stock' => $value,
					];
					$importDataPrice[] = [
						'article' => $this->items[$key]['ARTICLE'],
						'price' => $priceArray['price'],
					];
				}

        continue;
      }

      if ( !empty($this->items[$key]['SEBES']) ) {
        $diff = self::calculatePercentageDifferenceAlt(
          $this->items[$key]['SEBES'],
          $this->fromMS[$this->items[$key]['ARTICLE']]
        );
      } else {
        $diff = null;
      }

      if ( $diff == null ){
        $cost = $this->fromMS[$this->items[$key]['ARTICLE']];
        $whatCost = "<span style=\"color:red\">Отсутствует себес БТ или себес БТ равен МС. Устанавливаем от себеса МС</span><br>";
      }
      elseif( $diff >= $this->threshold ){
        $cost = $this->fromMS[$this->items[$key]['ARTICLE']];
        $whatCost = "<span style=\"color:green\">Цена в БТ меньше на ".round(abs($diff),3)."%. Устанавливаем от себеса МС</span><br>";
      }
      else{
        if ( !empty($this->fromMS[$this->items[$key]['ARTICLE']]) && !empty($this->items[$key]['SEBES']) ){
          $cost = min( $this->fromMS[$this->items[$key]['ARTICLE']], $this->items[$key]['SEBES'] );
					if ( $this->items[$key]['ARTICLE'] == 'A-158WA-1' ){
						var_dump( $this->items[$key]['ARTICLE'] );
						var_dump( $this->fromMS[$this->items[$key]['ARTICLE']] );
						var_dump( $this->items[$key]['SEBES'] );
						var_dump($cost);
					}
          $whatCost = "<span style=\"color:red\">Отличаются на ".round(abs($diff), 3)."%. Устанавливаем от наименьшего</span><br>";
        }
        if( !empty($this->fromMS[$this->items[$key]['ARTICLE']]) && empty($this->items[$key]['SEBES']) ){
          $cost = $this->fromMS[$this->items[$key]['ARTICLE']];
          $whatCost = "<span style=\"color:red\">Отсутствует себес БТ. Устанавливаем от МС</span><br>";
        }
      }
			$isSubst = '';
			if ( in_array($this->items[$key]['ARTICLE'], $this->costSubstituted) ){
				$isSubst = '&nbsp<span style="color:#eed202">(Подмена)</span>';
			}
      $priceArray = self::getNewPrice(
        $this->items[$key]['ARTICLE'],
        $this->items[$key]['BRAND_ID'],
        $cost
      );

			$competitorPrice = $this->calculateCompetitorPrice(
				$this->items[$key]['ARTICLE'],
				$cost
			);

			if ( $competitorPrice !== false ){
				if ( $priceArray['price'] < $competitorPrice ){
					$price = $priceArray['price'];
					$isCompetitor = false;
					$whatCost = "<span style=\"color:red\">Цена конкурента {$competitorPrice} выше РРЦ {$priceArray['price']} </span><br>";
				}else{
					$price = $competitorPrice;
					$isCompetitor = true;
				}
			}else{
				$price = $priceArray['price'];
			}

			if ( !empty( $this->stockNFCost[$this->items[$key]['ARTICLE']] ) ){

				$cost = $this->stockNFCost[ $this->items[$key]['ARTICLE'] ];

				$priceArray = self::getNewPrice(
	        $this->items[$key]['ARTICLE'],
	        $this->items[$key]['BRAND_ID'],
	        $cost
	      );

				$competitorPrice = $this->calculateCompetitorPrice(
					$this->items[$key]['ARTICLE'],
					$cost
				);

				if ( $competitorPrice !== false ){
					if ( $priceArray['price'] < $competitorPrice ){
						$price = $priceArray['price'];
						$whatCost = "<span style=\"color:red\">Цена конкурента {$competitorPrice} выше РРЦ {$priceArray['price']} </span><br>";
						$isCompetitor = false;
					}else{
						$price = $competitorPrice;
						$isCompetitor = true;
					}
					$whatCost .= "<span style=\"color:red\">Товар есть на складе ВР. Себестоимость {$cost} установлена принудительно</span><br>";
				}else{
					$price = $priceArray['price'];
					$whatCost .= "<span style=\"color:red\">Товар есть на складе ВР. Себестоимость {$cost} установлена принудительно</span><br>";
				}

			}
			if ( $isCompetitor === true ){
				$whatCost .= "<span style=\"color:red\">Установлена цена конкурента {$price}</span><br>";
			}

      $this->answer[ $this->items[$key]['ARTICLE'] ]['count'] = $value;

      $this->answer[ $this->items[$key]['ARTICLE'] ]['asnw'] .= "
      Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
      Остаток FBO WB: {$value}<br>
      Себес из МС: {$this->fromMS[ $this->items[$key]['ARTICLE'] ]}{$isSubst}<br>
      Себес из БТ: {$this->items[$key]['SEBES']}<br><br>
			Наценка: {$priceArray['m']}<br><br>
      {$whatCost}
      <span style=\"color:green\">Устанавливаем новую цену</span>: {$price}<hr><br><br>
      ";

      $importDataCost[] = [
        'article' => $this->items[$key]['ARTICLE'],
				'brand_id' => $this->items[$key]['BRAND_ID'],
        'cost' => $cost,
      ];

      $importDataStock[] = [
        'article' => $this->items[$key]['ARTICLE'],
        'stock' => $value,
      ];
			if ( empty($price) ){
				var_dump( $this->items[$key]['ARTICLE'] );
				var_dump($isCompetitor);
			}
      $importDataPrice[] = [
        'article' => $this->items[$key]['ARTICLE'],
        'price' => $price,
      ];

      $this->sppAnalyticsData[ $this->items[$key]['ARTICLE'] ] = $value;
			$this->control[] = [ $this->items[$key]['ARTICLE'] ];
    }

    // Импорт в таблицу будет здесь
    // фбо сток
    if ( !empty($importDataStock) ){
      $this->dbPanel->insert( "wb_fbo_stock_{$this->cabinet}", $importDataStock);
      print_r("Импортированы данные об остатках\n");
    }
    // фбо прайс
    if ( !empty($importDataPrice) ){
      $this->dbPanel->insert("wb_fbo_price_{$this->cabinet}", $importDataPrice);
      print_r("Импортированы данные о ценах\n");
    }
    // фбо себес
    if ( !empty($importDataCost) ){
      $this->dbPanel->insert( "wb_fbo_cost_{$this->cabinet}", $importDataCost );
      print_r("Импортированы данные о себестоимости\n");
    }
		// foreach ( $importDataStock as $data ){
		// 	$this->control[] = [ $data['article'] ];
		// }
		file_put_contents(
			"{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/logs/fbo/{$this->cabinet}/log_corrections.json",
			json_encode( $corrections )
		);
		$this->corrections = $corrections;
  }

	public function PrintResult()
	{
		file_put_contents("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/logs/fbo/{$this->cabinet}/log_test.txt", print_r(json_encode($this->answer), true).PHP_EOL);
		file_put_contents("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/export/analytics.json", print_r(json_encode($this->sppAnalyticsData), true));
	}

	public function updateStatus( string $code, array $arStat ):void
	{
    print_r($arStat['status_text'] . PHP_EOL);
		if ( empty($arStat) ) return;
		$strSql = "UPDATE wb_agents SET ";
		foreach ($arStat as $field => $value) {
			if ( array_key_last($arStat) == $field ){
				$str = "{$field} = '{$value}'";
			}else{
				$str = "{$field} = '{$value}', ";
			}
			$strSql .= $str;
		}
		$strSql .= " WHERE code = '{$code}'";
		try{
			$this->dbPanel->query( $strSql );
		}catch( Throwable $ignored){
			print_r('Не удалось обновить статус' . $ignored . "\n");
		}
	}

	private function checkStockDifference():void
	{
		$cab = $this->cabinet;
		$module = "wb_checkFbo_{$cab}";
		$nameTemplateSys = "checkFbo_{$cab}";
		$nameTemplateSys2 = "checkFbo_diff_{$cab}";
		$nameTemplateBot = "ФБО_WB_{$cab}";
		$nameTemplateBot2 = "ФБО_Разница_WB_{$cab}";
		$messageHeader = "<b>Модуль ФБО WB {$cab}:</b>\n\n";

		$filenamePrev = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_prev.xlsx";
		$filenameLast = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_last.xlsx";

		$row = $this->dbPanel->select(['*'], 'modules_control')->where('module', $module)->make();
		$oldValue = intval( $row[0]['old_value'] );
		$newValue = intval( $row[0]['new_value'] );
		$corCount = count( $this->corrections );

		$lastUploadTime = $row[0]['date'];
		$control = count($this->control);
		$dataForBot = $this->control;

		if ( $newValue != 0 ){
			$diff = ($newValue - $control) / $newValue * 100;
		}else{
			$diff = 0;
		}

		$diffInfo = abs( round($diff, 2) );
		print_r("difference - {$diff}\n");
		// $this->bot_threshold
		if ( $diffInfo >= $this->bot_threshold ){

			if ($diff > 0 ){
				$cWord = 'меньше';
			}else{
				$cWord = 'больше';
			}

			$this->bot->sendMessage("{$messageHeader}<b>⚠На ФБО числится на <u>{$diffInfo}%</u> {$cWord} товаров!</b>\n\nВремя предыдущего запуска: {$lastUploadTime}\n\n✅ <b>Корректировки: <u>{$corCount}</u></b>\n\n✅ <b>Прошлая итерация: <u>{$newValue}</u></b>.\n❌ <b>Текущая итерация: <u>{$control}</u></b>\n\n<i>Убедитесь в отсутствии ошибок!</i>\n<i>Ниже прикреплены файлы с артикулами выгрузок</i>");
			if ( file_exists($filenamePrev) ){
				$this->bot->sendFile($filenamePrev, "{$nameTemplateBot}_prev.xlsx");
			}

		}

		if ( file_exists($filenameLast) )	rename($filenameLast, $filenamePrev);

		$newFile = $this->buildXlsx( 'wb', "{$nameTemplateSys}_last", ['Модель'], $dataForBot );
		// $arDiff = $this->compareUpload( $filenamePrev, $dataForBot );
		if ( $diffInfo >= $this->bot_threshold ){

			if ($diff > 0 ){
				$arDiff = $this->compareUploadLess( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'wb', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}else{
				$arDiff = $this->compareUploadMore( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'wb', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}

			if ( $newFile ){
				$this->bot->sendFile($newFile, "{$nameTemplateBot}_last.xlsx");
				if ( $diffFile ) $this->bot->sendFile($diffFile, "{$nameTemplateBot2}.xlsx");
			}else{
				$this->bot->sendMessage("Файл {$nameTemplateBot}_last.xlsx не может быть отправлен ввиду непредвиденной ошибки: массив пустой");
			}

		}

		if ( $newValue == 0 && $control == 0 ){
			$this->bot->sendMessage("{$messageHeader}<b>❌Числится нулевое количество товаров в двух или более итерациях подряд.</b>\n\n⚠<i>Необходимо срочно исправить ошибку!</i>");
		}
		$date = date('Y-m-d G:i:s');
		$strSql = " UPDATE modules_control SET old_value = '{$newValue}', new_value = '{$control}', date = '{$date}' WHERE module = '{$module}'";
		$this->dbPanel->query( $strSql );
	}

	private function buildXlsx( string $module, string $name, array $headers, array $data ):string|bool
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		if ( empty($data) ) return false;

		$xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('List 1');

		$alphabet = range('A', 'Z');
		foreach ( $headers as $key => $value ){
			$sheet->setCellValueExplicit("{$alphabet[$key]}1", $value, PHPExcel_Cell_DataType::TYPE_STRING);
		}
		foreach ( $data as $i => $value ){
			$row = $i + 2;
			foreach ( $value as $k => $elem ){
				$sheet->setCellValueExplicit("{$alphabet[$k]}{$row}", $elem, PHPExcel_Cell_DataType::TYPE_STRING);
			}
		}
		$objWriter = new PHPExcel_Writer_Excel2007($xls);
    $filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/{$module}/export/{$name}.xlsx";
    $objWriter->save( $filename );

		return $filename;
	}

	private function compareUploadLess(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		$arUpload = [];
		foreach( $data as $row ){
			$arUpload[ $row[0] ] = $row[0];
		}

		$arDiff = [];
		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			if ( empty($arUpload[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}

	private function compareUploadMore(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			$arModelsXlsx[ $row[0] ] = [ $row[0] ];
		}

		$arDiff = [];
		$arUpload = [];
		foreach( $data as $row ){
			if ( empty($arModelsXlsx[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}

}

(new checkFBONEW($argv[1]))->run();
