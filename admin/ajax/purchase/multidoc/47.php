<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application,
    Bitrix\Sale\Order,
	  Bitrix\Main\Loader;

class DocsCreate47
{
  private $connection;
  private $db;

  private $supplier_id; // ИД поставщика
  private $currencyObj; // Валюта

  private $purchaseList = []; // Закупаемые товары
  private $productsDict = []; // Словарь айдишников закупаемых товаров
  private $product_ids = []; // ИД товаров для выборки ИД МС
  private $purchaseAll = [];
  private $description = '';

  private $organization = [];
  private $agent = [];

  private $ndsMultiplier = 1.2;

  private $metaDictionary = [];

  private $arDebug = [];

  public function __construct()
  {
    $this->loadModules();
    global $DB;
    $this->db = $DB;

    $this->metaDictionary = [
      'organization' => 'organization',
      'store' => 'store',
      'sourceStore' => 'store',
      'targetStore' => 'store',
      'agent' => 'counterparty',
      'state' => 'state'
    ];

    $this->organization = [
      's2' => [
        'watch_trade' => '6812f6e0-aa06-11ee-0a80-138b002c126e',
      ],
      's1' => [
        'ip' => '79ec331d-0aa6-11ea-0a80-004200039aa2',
        'ti' => '655c12e4-ae44-11ee-0a80-08ce006d3fdc',
        'watches_retail' => '27af8b5c-58d1-11ec-0a80-08e7000a6716',
      ]
    ];

    $this->agent = [
      's2' => [
        'ti' => 'c6cae7a1-f25a-11ee-0a80-0c6f000957eb',
        'ip' => '8c31a202-2e99-11ed-0a80-072c000ba35c',
        'novatime' => '073f9b16-dd7d-11ef-0a80-08ad0010b9f0',
        'watches_retail' => 'd9f84aa8-9a7e-11ef-0a80-0fc20065d55e',
      ],
      's1' => [
        'watch_trade' => '0355a50a-f25a-11ee-0a80-029b00090b92',
      ]
    ];

    $this->store = [
      's1' => [
        'main' => '79ed7d71-0aa6-11ea-0a80-004200039aa4',
      ],
      's2' => [
        'nemiga' => '6f6d2169-180c-11ea-0a80-00b30004eaef',
      ],
      'msk' => [
        'novatime' => 'ba731b41-cf1b-11ef-0a80-023800043452',
      ],
    ];

    $this->configStructure = [
      'organization' => 'string',
      'store' => 'string',
      'agent' => 'string',
      'applicable' => 'bool',
    ];

    $this->supplier_id = 47;

    $this->currencyObj = new CPanelCurrency;
    $this->ms_msk = new MoyskladAPI('msk');

    $this->description = '';
  }

  public function run():void
  {
    $this->getPurchaseList();
    $this->getProductsDict();
    $result = $this->processDocuments();
    var_dump($result);
  }

  private function getPurchaseList():void // Получаем товары из заказа поставщику
  {
    if ( empty($this->supplier_id) ) die('NO SUPPLIER_ID');

    $supps = $this->getSuppliersRU();
    $supps[] = 135; // жоский костыль для умника Никиты
    $prepared = array_map( function($item){
      return "'" . $item . "'";
    }, $supps );

    $prepared = implode(',', $prepared);

    $strSql = "SELECT * FROM ci_purchase WHERE site_id = 's2' AND active = 'Y' AND supp_id IN ({$prepared})";
    $res = $this->db->Query( $strSql );

    while ( $row = $res->Fetch() ){
      $this->purchaseList[ $row['supp_id'] ][] = $row;
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

  private function distributeModels():array
  {
    if ( empty( $this->purchaseList ) ) die('NOTHING TO DISTRIBUTE');

    $items_hg = [];
    $items_ti = [];
    $items_wr = [];
    $items_nova = [];

    foreach ( $this->purchaseList as $supp_id => $arProduct ){
      // NOVA GROUP
      if ( in_array($supp_id, [135, 116]) ) {
        // пошутчно перебираем товары поставщика,чтобы каждый проверить на склад
        foreach ( $arProduct as $key => $product ){
          if ( $supp_id == 116 ) { // Если это алексей/стас 1, то дополнительно проверяем склад
            $ms_id = $this->productsDict[ 'msk' ][ $product['product_id'] ];
            $is_store = $this->store['msk']['nova'] == $this->getProductStore( $ms_id );
            if ( $is_store ){
              $items_nova[] = $product;
            }
            continue;
          }
          $items_nova[] = $product;
        }
        continue;
      }
      // WR GROUP
      if ( in_array($supp_id, [144, 124]) ) {
        $items_wr = array_merge($items_wr, $arProduct);
        continue;
      }
      // HG GROUP
      if ( in_array($supp_id, [103]) ) {
        foreach ( $arProduct as $key => $product){
          $ms_id = $this->productsDict[ 'msk' ][ $product['product_id'] ];
          $is_store = $this->store['msk']['nova'] == $this->getProductStore( $ms_id );
          if ( $is_store  ) continue;
          $items_hg[] = $product;
        }
        continue;
      }
      // TI GROUP
      $items_ti = array_merge($items_ti, $arProduct);
    }
    // debug
    // var_dump( 'HG - ' . count($items_hg) );
    // var_dump( 'TI - ' . count($items_ti) );
    // var_dump( 'WR - ' . count($items_wr) );
    // var_dump( 'NOVA - ' . count($items_nova) );
    // die;
    // debug

    return [
      'hg' => $items_hg,
      'ti' => $items_ti,
      'wr' => $items_wr,
      'nova' => $items_nova,
    ];
  }

  private function processDocuments():array
  {
    if ( empty($this->purchaseList) ) die("NOTHING TO PROCESS");
    $items = $this->distributeModels();

    $config = [];
    $result = [];

    foreach ( $items as $key => $products ){
      if ( empty($products) ) continue;
      switch ( $key ){
        case 'hg':
          // Документы s1 HG
          $config = [
            'organization' => $this->organization['s1']['ip'],
            'store' => $this->store['s1']['main'],
            'agent' => $this->agent['s1']['watch_trade'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's1', 'purchasereturn', $products, $config, false );
          $result['s1_HG']['purchasereturn'] = $res == false ? false : true;
          $res = $this->createDocument( 's1', 'demand', $products, $config, false );
          $result['s1_HG']['demand'] = $res == false ? false : true;
          // Документы s2 HG
          $config = [
            'organization' => $this->organization['s2']['watch_trade'],
            'store' => $this->store['s2']['nemiga'],
            'agent' => $this->agent['s2']['ip'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's2', 'supply', $products, $config, true );
          $result['s2_HG']['supply'] = $res == false ? false : true;
          break;
        case 'wr':
          // Документы s1 WR
          $config = [
            'organization' => $this->organization['s1']['watches_retail'],
            'store' => $this->store['s1']['main'],
            'agent' => $this->agent['s1']['watch_trade'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's1', 'purchasereturn', $products, $config, false );
          $result['s1_WR']['purchasereturn'] = $res == false ? false : true;
          $res = $this->createDocument( 's1', 'demand', $products, $config, false );
          $result['s1_WR']['demand'] = $res == false ? false : true;
          // Документы s2 WR
          $config = [
            'organization' => $this->organization['s2']['watch_trade'],
            'store' => $this->store['s2']['nemiga'],
            'agent' => $this->agent['s2']['watches_retail'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's2', 'supply', $products, $config, true );
          $result['s2_WR']['supply'] = $res == false ? false : true;
          break;
        case 'nova':
          // Документы s1 nova
          $config = [
            'organization' => $this->organization['s1']['ip'],
            'store' => $this->store['s1']['main'],
            'agent' => $this->agent['s1']['watch_trade'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's1', 'purchasereturn', $products, $config, false );
          $result['s1_NOVA']['purchasereturn'] = $res == false ? false : true;
          $res = $this->createDocument( 's1', 'demand', $products, $config, false );
          $result['s1_NOVA']['demand'] = $res == false ? false : true;
          // Документы s2 nova
          $config = [
            'organization' => $this->organization['s2']['watch_trade'],
            'store' => $this->store['s2']['nemiga'],
            'agent' => $this->agent['s2']['novatime'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's2', 'supply', $products, $config, true );
          $result['s2_NOVA']['supply'] = $res == false ? false : true;
          break;
        case 'ti':
          // Документы s1 TI
          $config = [
            'organization' => $this->organization['s1']['ti'],
            'store' => $this->store['s1']['main'],
            'agent' => $this->agent['s1']['watch_trade'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's1', 'purchasereturn', $products, $config, false );
          $result['s1_TI']['purchasereturn'] = $res == false ? false : true;
          $res = $this->createDocument( 's1', 'demand', $products, $config, false );
          $result['s1_TI']['demand'] = $res == false ? false : true;

          // Документы s2 TI
          $config = [
            'organization' => $this->organization['s2']['watch_trade'],
            'store' => $this->store['s2']['nemiga'],
            'agent' => $this->agent['s2']['ti'],
            'description' => $this->description ?? '',
            'applicable' => false,
          ];
          $res = $this->createDocument( 's2', 'supply', $products, $config, false );
          $result['s2_TI']['supply'] = $res == false ? false : true;
          break;
          default:
            throw new \Exception('Doc profile is not implemented');
            break;
      }
    }

    return $result;
  }

  private function createDocument(
      string $cabinet = '',
      string $docType = '',
      array $products = [],
      array $profile = [],
      bool $nds = false
     ) // Формируем шаблон и отправляем в МС
  {
    if ( empty($products) ) die( 'no products' );
    if ( empty($profile) ) die('no config data');

    $template = $this->prepareTemplate( $profile );;
    $template['positions'] = $this->prepareProducts( $cabinet, $products, $nds );

    // var_dump($template);
    // die;

    $ms = new MoyskladAPI( $cabinet );

    $response = $ms->send(
      "/entity/{$docType}",
      "POST",
      $template,
      ["Content-Type" => "application/json"]
    );

    // $this->arDebug[] = $template; //debug

    return $response;
  }

  private function prepareProducts( string $cabinet, array $products, bool $nds ):array
  {
    if ( empty( $cabinet ) || empty( $products ) ) die("CANNOT PREPARE PRODUCTS. ONE OF PARAMETERS MISSING");
    $rate = $this->getCurrency( $cabinet );
    $prepProducts = [];
    foreach  ( $products as $item ){
      $this->models[] = $item['model']; // debug
      if ( empty($this->productsDict[ $cabinet ][ $item['product_id'] ]) ) continue;
      if ( isset($prepProducts[ $item['product_id'] ]) ){
        $prepProducts[ $item['product_id'] ]['quantity'] += 1;
        continue;
      }
      $price = round( $item["price"] / $rate, 2 ); // Убрал конвертацию

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

  private function getCurrency( $cabinet ):int|float
  {
    if ( $cabinet == 's1' ) return 1;
    return $this->currencyObj->getDetail("BYN")['rate'];
  }

  private function getSuppliersRU():array
  {
    $strSql = "SELECT id, settings FROM ci_suppliers";
    $res = $this->db->Query( $strSql );

    $result = [];
    while ( $row = $res->Fetch() ){
      $settings = json_decode( $row['settings'], true );
      if ( $settings['currency'] == 'RUB' ){
        $result[] = $row['id'];
      }
    }

    return $result;
  }

  private function getProductStore( string $ms_id ):string|bool
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
     return false;
  }

  private function loadModules()
  {
	   Loader::includeModule("panel.manager");
	   Loader::includeModule("iblock");
     Loader::includeModule("catalog");
  }
}

( new DocsCreate47() )->run();
 ?>
