<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application,
    Bitrix\Sale\Order,
	  Bitrix\Main\Loader;

class AugustDocsCreate
{
  private $connection;
  private $db;

  private $supplier; // ИД поставщика
  private $currencyObj; // Валюта

  private $purchaseList = []; // Закупаемые товары
  private $productsDict = []; // Словарь айдишников закупаемых товаров
  private $product_ids = []; // ИД товаров для выборки ИД МС

  private $agentCheckBY = 99216; // Покупатель '21 век' будет иметь отдельный документ
  private $agentCheckWB = 135989; // Покупатель 'WB' будет иметь отдельный документ

  private $msInfo; // Массив с конфигурациями создаваемых документов

  private $metaDictionary;

  public function __construct()
  {
    $this->loadModules();
    global $DB;
    $this->db = $DB;

    $this->connection = Application::getConnection();

    $context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$req = $request->getQueryList()->toArray();

    $this->supplier = intval( $req["supp_id"] );
    $this->action = $req['action'];
    // $this->supplier = 135;
    // $this->action = 'SDR';


    $this->metaDictionary = [
      'organization' => 'organization',
      'store' => 'store',
      'sourceStore' => 'store',
      'targetStore' => 'store',
      'agent' => 'counterparty',
    ];

    $this->msInfo = [
      // ПОСТАВЩИК ОЛЕГ
      // ОТГРУЗКИ
      '21_vek_124' => [ // Отгрузка из Хроноса для BY. Товары 21 век. Поставщик Олег
        'organization' => '96a1b40f-652b-11ef-0a80-108b0010b66a', // Хронос Групп
        'store' => 'e88d8276-f19c-11ee-0a80-044a000d1702', // Склад Август
        'agent' => 'a61907cd-f1c5-11ee-0a80-0102001642d5', // Вотч-трейд
        'description' => 'Август 21 век',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      'rest_s2_124' => [ // Отгрузка из Хроноса для BY. Товары НЕ 21 век. Поставщик Олег
        'organization' => '96a1b40f-652b-11ef-0a80-108b0010b66a', // Хронос Групп
        'store' => 'e88d8276-f19c-11ee-0a80-044a000d1702', // Склад Август
        'agent' => 'a61907cd-f1c5-11ee-0a80-0102001642d5', // Вотч-трейд
        'description' => 'Август РБ',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      'msk_s1_124' => [ // Отгрузка из Хроноса для INT. Все товары s1. Поставщик Олег
        'organization' => '96a1b40f-652b-11ef-0a80-108b0010b66a', // Хронос Групп
        'store' => 'e88d8276-f19c-11ee-0a80-044a000d1702', // Склад Август
        'agent' => 'ccaa773a-1073-11ee-0a80-10c50002f8eb', // Вотчес-ритейл
        'description' => 'Темпус',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      // ПРИЕМКИ
      's1_default_124' => [ // Приемка для Int. Все товары s1. Поставщик Олег
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес-ритейл
        'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'agent' => 'a389e34e-6901-11ef-0a80-0c11003c56a7', // Хронос Групп
        'description' => 'НЕ ТРОГАТЬ - Август РФ',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],

      // СКЛАД ИМПОРТ
      // ПЕРЕМЕЩЕНИЕ
      'move_141' => [ // Перемещение в INT со склада Дубровка на склад Импорт. Склад Импорт
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес-ритейл
        'sourceStore' => '8f9fc8a4-4b82-11f0-0a80-1af80012c175', // Склад Импорт
        'targetStore' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'description' => 'Импорт NF',
        'applicable' => false,
        // 'type_sys' => 'm', // m - move
      ],
      'move_144' => [ // Перемещение в INT со склада Дубровка на склад Импорт. Склад Импорт
        'organization' => '79ec331d-0aa6-11ea-0a80-004200039aa2', // ИП
        'sourceStore' => 'b8e7c736-3bc2-11f0-0a80-09fd0010bf8f', // Склад Импорт 2
        'targetStore' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка основной
        'description' => 'Импорт 2',
        'applicable' => false,
        // 'type_sys' => 'm', // m - move
      ],
      // ОТГРУЗКИ
      '21_vek_141' => [ // Отгрузка из Хроноса для BY. Товары 21 век. Склад Импорт
        'organization' => '96a1b40f-652b-11ef-0a80-108b0010b66a', // Хронос Групп
        'store' => 'e1146ee2-f19c-11ee-0a80-05c8000bd91c', // Склад Транизит
        'agent' => 'a61907cd-f1c5-11ee-0a80-0102001642d5', // Вотч-трейд msk
        'description' => 'Импорт 21 век',
        'applicable' => false, // тут и должен быть false
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      'rest_s2_141' => [ // Отгрузка из Хроноса для BY. Товары НЕ 21 век. Склад Импорт
        'organization' => '96a1b40f-652b-11ef-0a80-108b0010b66a', // Хронос Групп
        'store' => 'e1146ee2-f19c-11ee-0a80-05c8000bd91c', // Склад Транзит
        'agent' => 'a61907cd-f1c5-11ee-0a80-0102001642d5', // Вотч-трейд msk
        'description' => 'Импорт розница',
        'applicable' => false, // тут и должен быть false
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      // ВОЗВРАТЫ
      's2_21vek_r_141' => [ // Возврат для Int. Товары 21 век. Склад Импорт
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес-ритейл
        'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'agent' => '0355a50a-f25a-11ee-0a80-029b00090b92', // Вотч-трейд s1
        'description' => 'Cоздан из утилиты',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      's2_rest_r_141' => [ // Возврат для Int. Товары НЕ 21 век. Склад Импорт
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес-ритейл
        'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'agent' => '0355a50a-f25a-11ee-0a80-029b00090b92', // Вотч-трейд s1
        'description' => 'Cоздан из утилиты',
        'applicable' => false,
        // 'type_sys' => 'sdr', // s - supply, d - demand, r - return
      ],
      // СКЛАД МОСКВА 2
      // ПЕРЕМЕЩЕНИЕ
      'move_129' => [
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес-ритейл
        'sourceStore' => '51538bd5-6cf3-11ef-0a80-10ba001db77c', // Дубровка 2
        'targetStore' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'description' => 'Дубровка 2',
        'applicable' => false,
        // 'type_sys' => 'm', // m - move
      ],
      // НИКИТА
      // ОТГРУЗКИ
      's1_wb_demand_135' => [
        'organization' => '60850714-ad71-11ef-0a80-163f007c4295', // ООО Новатайм
        'store' => 'ba731b41-cf1b-11ef-0a80-023800043452', // Оптовый новатайм
        'agent' => 'ccaa773a-1073-11ee-0a80-10c50002f8eb', // Вотчес ритейл msk
        'description' => 'Tissot на Вотчес ритейл',
        'applicable' => false
      ],
      's1_rest_demand_135' => [
        'organization' => '60850714-ad71-11ef-0a80-163f007c4295', // ООО Новатайм
        'store' => 'ba731b41-cf1b-11ef-0a80-023800043452', // Оптовый новатайм
        'agent' => '5391696c-318f-11f0-0a80-11e50010c8ba', // ИП Сподыраева
        'description' => 'Tissot на ИП Сподыреву',
        'applicable' => false
      ],
      's2_all_demand_135' => [
        'organization' => '60850714-ad71-11ef-0a80-163f007c4295', // ООО Новатайм
        'store' => 'ba731b41-cf1b-11ef-0a80-023800043452', // Оптовый новатайм
        'agent' => 'ccaa773a-1073-11ee-0a80-10c50002f8eb', // Вотчес ритейл msk
        'description' => 'Tissot на Вотч Трейд',
        'applicable' => false
      ],
      // ПРИЕМКИ
      's1_wb_supply_135' => [
        'organization' => '27af8b5c-58d1-11ec-0a80-08e7000a6716', // Вотчес ритейл
        'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'agent' => '2b831384-f9a1-11ef-0a80-07570009a737', // Хронос Групп
        'description' => 'Tissot. Вотчес Ритейл',
        'applicable' => false
      ],
      's1_rest_supply_135' => [
        'organization' => '8a4f1ca9-30d3-11f0-0a80-1198001374c8', // ИП Сподыраева
        'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка
        'agent' => '2b831384-f9a1-11ef-0a80-07570009a737', // Хронос Групп
        'description' => 'Tissot. ИП Сподыраева',
        'applicable' => false
      ],
    ];


    $this->currencyObj = new CPanelCurrency;
  }

  public function run():void
  {
    $this->getPurchaseList();
    $this->getProductsDict();
    // var_dump( $this->productsDict );
    // die;
    $this->processDocuments( $this->supplier, $this->action);
  }

  private function getPurchaseList():void // Получаем товары из заказа поставщику
  {
    if ( empty($this->supplier) ) die('NO SUPPLIER_ID');
    $strSql = "SELECT * FROM ci_purchase WHERE supp_id = '{$this->supplier}' AND site_id IN ('s1', 's2', 's1_nkz') AND active = 'Y' AND ms_data IS NULL";
    $res = $this->db->Query( $strSql );

    while ( $row = $res->Fetch() ){
      $this->purchaseList[] = $row;
      $this->product_ids[ $row['product_id'] ] = 1;
    }
  }

  private function getProductsDict():void // Получаем ИД МС для товаров в закупках
  {
    if ( empty($this->purchaseList) ) die('NO DATA TO EXTRACT');

    $purchaseProducts = array_keys( $this->product_ids );

    $preparedData = array_map( function($item){
      return "'". $item ."'";
    }, $purchaseProducts );

    $preparedData = implode( ',', $preparedData );

    $strSql = "SELECT SITE_ID, MS_ID, BX_ID FROM ci_ms_assortment WHERE BX_ID IN ({$preparedData})";

    $res = $this->db->Query( $strSql );

    while ( $row = $res->Fetch() ){
      $this->productsDict[ $row['SITE_ID'] ][ $row['BX_ID'] ] = $row['MS_ID'];
    }
  }

  private function distributeModels():array // Распределяем товары по сайтам. Если контрагент - 21 век, делим товары s2 на две группы
  {
    if ( empty( $this->purchaseList ) ) die('NOTHING TO DISTRIBUTE');

    $result = [];
    $s1_purchase = [];
    $s2_21vek = [];
    $s2_purchase = [];

    foreach ( $this->purchaseList as $product ){
      if ( $product['site_id'] == 's1_nkz' ) continue;
      if ( $product['site_id'] != 's2') {
        $s1_purchase[] = $product;
        continue;
      }
      if ( $this->checkOrderAgent( $product['order_id'], $this->agentCheckBY ) ){
        $s2_21vek[] = $product;
        continue;
      }
      $s2_purchase[] = $product;
    }

    $result = [
      's1' => $s1_purchase,
      's2' => [
        '21vek' => $s2_21vek,
        'rest' => $s2_purchase,
      ]
    ];

    return $result;
  }

  private function distributeModelsWB():array // Распределяем товары по сайтам . Если контрагент - WB, делим товары s1 на две группы
  {
    if ( empty( $this->purchaseList ) ) die('NOTHING TO DISTRIBUTE');

    $result = [];
    $s2_purchase = [];
    $s1_WB = [];
    $s1_purchase = [];
    $s1nkz_purchase = [];
    // var_dump( count($this->purchaseList) );
    foreach ( $this->purchaseList as $product ){
      if ( $product['site_id'] == 's2') {
        $s2_purchase[] = $product;
        continue;
      }
      if ( $product['site_id'] == 's1_nkz') {
        $s1nkz_purchase[] = $product;
        continue;
      }
      $order = Order::load( $product['order_id'] );
      $platform = $order->getTradeBindingCollection()->toArray()[0]['TRADING_PLATFORM_ID'];
      $propColl = $order->getPropertyCollection();
      $fio = $propColl->getItemByOrderPropertyCode('FIO')->toArray()['VALUE'];
      if ( ( $platform == 13 && mb_stripos( $fio, 'Авито' ) === false ) || $order->getUserId() == $this->agentCheckWB ){
        $s1_WB[] = $product;
        continue;
      }
      $s1_purchase[] = $product;
    }

    $result = [
      's2' => $s2_purchase,
      's1' => [
        'wb' => $s1_WB,
        'rest' => $s1_purchase,
      ]
    ];
    // var_dump( $result );
    // die;

    return $result;
  }

  private function processDocuments( string $supplier, string $action ):void
  {
    if ( empty($this->purchaseList) ) die("NOTHIG TO PROCESS");

    if ( $supplier == 124 && $action == 'SDR' ){
      $purchaseDistributed = $this->distributeModels();

      foreach ( $purchaseDistributed as $site_id => $item ){
        if ( $site_id == 's2' ){
          $this->createDocument( 'msk', 'demand', $item['21vek'], '21_vek_124' );
          $this->createDocument( 'msk', 'demand', $item['rest'], 'rest_s2_124' );
          continue;
        }
        $this->createDocument( 'msk', 'demand', $item, 'msk_s1_124' );
        $this->createDocument( 's1', 'supply', $item, 's1_default_124' );
      }
    }

    if ( $supplier == 141 && $action == 'SDR' ){
      $purchaseDistributed = $this->distributeModels();

      foreach ( $purchaseDistributed as $site_id => $item ){
        if ( $site_id == 's2' ){
          $this->createDocument( 'msk', 'demand', $item['21vek'], '21_vek_141' );
          $this->createDocument( 'msk', 'demand', $item['rest'], 'rest_s2_141' );
          $this->createDocument( 's1', 'purchasereturn', $item['21vek'], 's2_21vek_r_141' );
          $this->createDocument( 's1', 'purchasereturn', $item['rest'], 's2_rest_r_141' );
          continue;
        }
      }
    }

    if ( $supplier == 135 && $action == 'SDR' ){
      $purchaseDistributed = $this->distributeModelsWB();

      foreach ( $purchaseDistributed as $site_id => $item ){
        if ( $site_id == 's1' ){
          $this->createDocument( 'msk', 'demand', $item['wb'], 's1_wb_demand_135' );
          $this->createDocument( 'msk', 'demand', $item['rest'], 's1_rest_demand_135' );
          $this->createDocument( 's1', 'supply', $item['wb'], 's1_wb_supply_135' );
          $this->createDocument( 's1', 'supply', $item['rest'], 's1_rest_supply_135' );
          continue;
        }
        if ( $site_id == 's1_nkz' ){
          $this->createDocument( 'msk', 'demand', $item, 's1_wb_demand_135' );
          $this->createDocument( 's1', 'supply', $item, 's1_wb_supply_135' );
          continue;
        }
        $this->createDocument( 'msk', 'demand', $item, 's2_all_demand_135' );
      }
    }

    if ( $supplier == 141 && $action == 'M' ){
      $this->createDocument( 's1', 'move', $this->purchaseList, 'move_141' );
    }
    if ( $supplier == 144 && $action == 'M' ){
      $this->createDocument( 's1', 'move', $this->purchaseList, 'move_144' );
    }
    if ( $supplier == 129 && $action == 'M' ){
      $this->createDocument( 's1', 'move', $this->purchaseList, 'move_129' );
    }

  }



  private function createDocument( string $cabinet, string $docType, array $products, string $profile ):void // Формируем шаблон и отправляем в МС
  {
    if ( empty($products) ) return;
    $msData = $this->msInfo[ $profile ];

    if ( $docType == 'purchasereturn' ){
      // $template = $this->getTemplate( $cabinet, $docType );
      $template = [];
    }else{
      $template = [];
    }

    $this->prepareTemplate( $template, $msData );

    $template['positions'] = $this->prepareProducts( $cabinet, $products );

    // var_dump( $template );


    $ms = new MoyskladAPI( $cabinet );
    $response = $ms->send(
      "/entity/{$docType}",
      "POST",
      $template,
      ["Content-Type" => "application/json"]
    );

    if ( $docType == 'move' && count($products) > 0 ){
      $arFields['supply'] = [
        "id" => $response["id"],
        "name" => $response["name"],
        "created" => $response["created"],
        "cabinet" => $cabinet,
        "timestamp" =>  microtime(true),
      ];
      $report = serialize($arFields);

      foreach ( $products as $product ){
        $strSql = "UPDATE ci_purchase SET ms_data = '{$report}' WHERE id = '{$product['id']}'";
        $this->db->Query( $strSql );
      }
    }
    // var_dump( $template );
    var_dump( $response );
  }

  private function getTemplate( string $cabinet, string $type ):array
  {
    if ( empty( $cabinet ) || empty( $type ) ) die("CANNOT GET A TEMPLATE. ONE OF PARAMETERS MISSING");

    $ms = new MoyskladAPI( $cabinet );
    $template = $ms->send(
      "/entity/{$type}/new",
      "PUT",
      [],
      ["Content-Type" => "application/json"]
    );

    return $template;
  }

  private function prepareProducts( string $cabinet, array $products ):array
  {
    if ( empty( $cabinet ) || empty( $products ) ) die("CANNOT PREPARE PRODUCTS. ONE OF PARAMETERS MISSING");

    $rate = $this->getCurrency( $cabinet );
    $prepProducts = [];

    foreach  ( $products as $item ){
      if ( empty($this->productsDict[ $cabinet ][ $item['product_id'] ]) ) continue;
      if ( isset($prepProducts[ $item['product_id'] ]) ){
        $prepProducts[ $item['product_id'] ]['quantity'] += 1;
        continue;
      }
      $price = round( $item["price"], 2 ); // Убрал конвертацию

      $prepProducts[ $item['product_id'] ] = [
        "quantity" => 1,
        "price" => $price * 100,
        "assortment" => [
          "meta" => [
            "href" => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$this->productsDict[ $cabinet ][ $item['product_id'] ]}",
            "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata",
            "type" => "product",
            "mediaType" => "application/json"
          ],
        ],
      ];
    }

    return array_values($prepProducts);
  }

  private function prepareTemplate( array &$template, array $profile ):void
  {

    foreach ( $profile as $key => $value ){

      if ( in_array( $key, ['description', 'applicable'] ) ){
        $template[$key] = $value;
        continue;
      }
      $template[ $key ] = [
        'meta' => [
          'href' => "https://api.moysklad.ru/api/remap/1.2/entity/{$this->metaDictionary[$key]}/{$value}",
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/{$this->metaDictionary[$key]}/metadata",
          "type" => $this->metaDictionary[$key],
          "mediaType" => "application/json"
        ]
      ];
    }
  }

  private function prepareTemplateLeg( array &$template, array $msData ):void // S - supply, D - demand, R - return
  {
    $template["organization"] = [
      "meta" => [
        "href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $msData["organization"],
        "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
        "type" => "organization",
        "mediaType" => "application/json"
      ]
    ];

    if ( $msData['type_sys'] == 'sdr' ){
      $template["store"] = [
        "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $msData["store"],
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
          "type" => "store",
          "mediaType" => "application/json"
        ]
      ];

      $template["agent"] = [
        "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $msData['agent'],
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
          "type" => "counterparty",
          "mediaType" => "application/json"
        ]
      ];
    }

    if ( $msData['type_sys'] == 'm' ) {
      $template["sourceStore"] = [
        "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $msData['sourceStore'],
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
          "type" => "store",
          "mediaType" => "application/json"
        ]
      ];

      $template["targetStore"] = [
        "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $msData['targetStore'],
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
          "type" => "store",
          "mediaType" => "application/json"
        ]
      ];
    }

    $template['description'] = $msData['description'];
		$template["applicable"] = $msData['applicable'];
  }

  private function getCurrency( $cabinet ):int|float
  {
    if ( $cabinet == 's1' ) return 1;
    return $this->currencyObj->getDetail("BYN")['rate'];
  }

  private function checkOrderAgent( int $order_id, int $customer_id ):bool
  {
    if ( empty($order_id) || empty($customer_id) ) return false;
    $order = Order::load( $order_id );
    if ( $order->getUserId() == $customer_id ) return true;

    return false;
  }

  private function loadModules()
  {
		Loader::includeModule("panel.manager");
		Loader::includeModule("iblock");
    Loader::includeModule("catalog");
  }
}

( new AugustDocsCreate() )->run();
 ?>
