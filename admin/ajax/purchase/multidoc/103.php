<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application,
    Bitrix\Sale\Order,
	  Bitrix\Main\Loader;

class DocsCreate103
{
  private $connection;
  private $db;

  private $supplier; // ИД поставщика
  private $currencyObj; // Валюта
  private $ms_msk;

  private $purchaseList = []; // Закупаемые товары
  private $productsDict = []; // Словарь айдишников закупаемых товаров
  private $product_ids = []; // ИД товаров для выборки ИД МС

  private $agentCheckBY = 99216; // Покупатель '21 век' будет иметь отдельный документ
  private $agentCheckWB = 135989; // Покупатель 'WB' будет иметь отдельный документ

  private $organization;
  private $counterparty;
  private $counterpartyDict;
  private $description;

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

    // $this->supplier = intval( $req["supp_id"] );
    // $this->action = $req['action'];
    $this->supplier = 103;
    $this->action = 'SDR';


    $this->metaDictionary = [
      'organization' => 'organization',
      'store' => 'store',
      'sourceStore' => 'store',
      'targetStore' => 'store',
      'agent' => 'counterparty',
      'state' => 'state'
    ];

    $this->organization = [
      'msk' => [
        'novatime' => '60850714-ad71-11ef-0a80-163f007c4295',
        'chronos' => '96a1b40f-652b-11ef-0a80-108b0010b66a',
      ],
      's1' => [
        'ip' => '8a4f1ca9-30d3-11f0-0a80-1198001374c8',
        'watches_retail' => '27af8b5c-58d1-11ec-0a80-08e7000a6716',
      ]
    ];

    $this->counterparty = [
      'msk' => [
        'watch_trade' => 'a61907cd-f1c5-11ee-0a80-0102001642d5',
        'watches_retail' => 'ccaa773a-1073-11ee-0a80-10c50002f8eb',
        'ip' => '5391696c-318f-11f0-0a80-11e50010c8ba',
      ],
      's1' => [
        'chronos' => 'a389e34e-6901-11ef-0a80-0c11003c56a7',
        'novatime' => '2b831384-f9a1-11ef-0a80-07570009a737'
      ]
    ];

    $this->counterpartyDict = [
      's2' => 'watch_trade',
      's1_ip' => 'ip',
      's1_wr' => 'watches_retail',
    ];

    $this->description = [
      's1' => 'TEST',
      's2' => 'TEST',
    ];

    $this->currencyObj = new CPanelCurrency;
    $this->ms_msk = new MoyskladAPI( 'msk' );
  }

  public function run():void
  {
    $this->getPurchaseList();
    $this->getProductsDict();
    $this->processDocuments( $this->supplier, $this->action);
  }

  private function getPurchaseList():void // Получаем товары из заказа поставщику
  {
    if ( empty($this->supplier) ) die('NO SUPPLIER_ID');
    $strSql = "SELECT * FROM ci_purchase WHERE supp_id = '{$this->supplier}' AND site_id IN ('s1', 's2') AND active = 'Y'";
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
      if ( $row['SITE_ID'] == 'msk' ){
        $this->productStore[ $row['BX_ID'] ] = $this->getProductStore( $row['MS_ID'] );
      }
    }
  }

  private function getProductStore( string $ms_id ):string
  {
    if ( empty($ms_id) ) die('Cannot get store without ID');

    $res = $this->ms_msk->send(
      "/report/stock/bystore/current?filter=assortmentId=".$ms_id."",
       "GET",
       [],
       ["Content-Type" => "application/json"]
     );

     foreach ( $res as $value ){
       return $value['storeId'];
     }
  }

  private function distributeModels():array
  {
    if ( empty( $this->purchaseList ) ) die('NOTHING TO DISTRIBUTE');

    $result = [];
    $s2_purchase = [];
    $s1_WB = [];
    $s1_purchase = [];

    foreach ( $this->purchaseList as $product ){
      $store = $this->productStore[ $product['product_id'] ];
      if ( $product['site_id'] != 's1') {
        $s2_purchase[$store][] = $product;
        continue;
      }
      $order = Order::load( $product['order_id'] );
      $platform = $order->getTradeBindingCollection()->toArray()[0]['TRADING_PLATFORM_ID'];
      $propColl = $order->getPropertyCollection();
      $fio = $propColl->getItemByOrderPropertyCode('FIO')->toArray()['VALUE'];
      if ( ( $platform == 13 && mb_stripos( $fio, 'Авито' ) === false ) || $order->getUserId() == $this->agentCheckWB ){
        $s1_WB[ $store ][] = $product;
        continue;
      }
      $s1_purchase[ $store ][] = $product;
    }

    $result = [
      's2' => $s2_purchase,
      's1_wr' => $s1_WB,
      's1_ip' => $s1_purchase,
    ];

    return $result;
  }

  private function processDocuments( string $supplier, string $action ):void
  {
    if ( empty($this->purchaseList) ) die("NOTHIG TO PROCESS");
    $items = $this->distributeModels();

    $config_send = [];
    $config_recieve = [];

    foreach ( $items as $key => $arStore ){
      foreach ( $arStore as $store_id => $products ){
        if (!empty($products)) {
          if ( $key == 's2' ){
            $config_send = [
              'organization' => $this->getOrganiztionByStoreChG( 'org', $store_id ),
              'store' => $store_id,
              'agent' => $this->getCounterpartyByCode( $key, 'msk' ),
              // 'description' => $this->description['s2'],
              // 'state' => '',
              'applicable' => true,
            ];
            $this->createDocument( 'msk', 'demand', $products, $config_send );
            continue;
          }
          $config_send = [
            'organization' => $this->getOrganiztionByStoreChG( 'org', $store_id ),
            'store' => $store_id,
            'agent' => $this->getCounterpartyByCode( $key, 'msk' ),
            // 'description' => $this->description['s1'],
            // 'state' => '',
            'applicable' => true,
          ];
          $op1 = $this->createDocument( 'msk', 'demand', $products, $config_send );

          $config_recieve = [
            'organization' => $this->getOrganiztionByCode( $key, 's1' ),
            'store' => '79ed7d71-0aa6-11ea-0a80-004200039aa4', // Дубровка 1
            'agent' => $this->getOrganiztionByStoreChG($store_id, 'agent'),
            // 'description' => $this->description['s1'],
            'applicable' => false,
          ];
          $op2 = $this->createDocument( 's1', 'supply', $products, $config_recieve );
        }
      }
      if($op1 && $op2){
        $type = "supply_transfer";
        $microtime = microtime(true);
        foreach($this->purchaseList as $r1){
          $strSql = "SELECT * FROM ci_purchase WHERE id = '{$r1['id']}'";
          $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
          if ($row = $results->Fetch()){

            if($row["ms_data"]){
              $arMS = unserialize($row["ms_data"]);
            }else{
              $arMS = [];
            }

            $arMS[$type] = array(
              "id" => $op2["id"],
              "name" => $op2["name"],
              "created" => $op2["created"],
              "timestamp" => $microtime,
            );

            $this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$r1['id']}'", $err_mess.__LINE__);

          }
        }
      }
    }
  }



  private function createDocument( string $cabinet, string $docType, array $products, array $profile ):array // Формируем шаблон и отправляем в МС
  {
    if ( empty($products) ) die( 'no products' );
    if ( empty($profile) ) die('no config data');

    $template = $this->prepareTemplate( $profile );;
    $template['positions'] = $this->prepareProducts( $cabinet, $products );

    // var_dump($template);
    // die;

    $ms = new MoyskladAPI( $cabinet );
    $response = $ms->send(
      "/entity/{$docType}",
      "POST",
      $template,
      ["Content-Type" => "application/json"]
    );
    return $response;
    // var_dump( $response );
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

    return array_values( $prepProducts );
  }

  private function prepareTemplate( array $profile ):array
  {
    $template = [];

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

    return $template;
  }

  private function getOrganiztionByStoreChG( string $type, string $store ):string
  {

    if ( $store == 'ba731b41-cf1b-11ef-0a80-023800043452' ){
      return ($type == 'org') ? $this->organization['msk']['novatime'] : $this->counterparty['s1']['novatime'];
    }
    return ($type == 'org') ? $this->organization['msk']['chronos'] : $this->counterparty['s1']['chronos'];
  }

  private function getOrganiztionByCode( string $code, string $cabinet ):string
  {
    $key = $this->counterpartyDict[ $code ];
    return $this->organization[ $cabinet ][ $key ];
  }

  private function getCounterpartyByCode( string $code, string $cabinet ):string
  {
    $key = $this->counterpartyDict[ $code ];
    return $this->counterparty[ $cabinet ][ $key ];
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

( new DocsCreate103() )->run();
 ?>
