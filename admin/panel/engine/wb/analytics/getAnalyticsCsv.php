<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_wb_analytics_getAnalyticsCsv_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

class AnalyticsCsv
{
  private $dbMain;
  private $dbPanel;
  private $hl;

  private int $maxInterval;
  private string $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/';
  private string $filename = 'cost_WB.csv';

  public function __construct()
  {
    $this->loadModules();
  }

  private function loadModules():void
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->hl = new HighloadApi( 8 );
  }

  public function run():void
  {
    $items = $this->prepareData();
    $this->arrayToCsv( $items, $this->path . $this->filename );
  }

  private function prepareData():array
  {
    $items = $this->getCostDataBitrix();
    $reserved = $this->getReserved();
    $brands = $this->getBrands();
    $productsData = $this->getBitrixProductsData();
    $costFBO = $this->getCostFBO();
    // $ordersCount = $this->getOrdersCount();

    $result = [];

    foreach ( $items as $model => $arItem ){
      $r = $reserved[$model] ?? 0;
      foreach ( $arItem as $itemPrice ){
        $restRes = $itemPrice['count'] - $r;
        if ( $restRes <= 0 ) continue;

        $result[] = [
          'Наименование' => $productsData[$model]['name'] ?? '',
          'Артикул' => $model,
          'Артикул WB' => $productsData[$model]['offer_id'] ?? '',
          'Себестоимость' => strval($costFBO[$model] ?? $itemPrice['price']),
          // 'Продажи 60-30 дней' => $ordersCount[$model]['before_last_month'] ?? 0,
          // 'Продажи 30 дней' => $ordersCount[$model]['last_month'] ?? 0,
          // 'Продажи 7-4 дней' => $ordersCount[$model]['3_days_before_3_days'] ?? 0,
          // 'Продажи 3 дня' => $ordersCount[$model]['3_days'] ?? 0,
          // 'Продажи вчера' => $ordersCount[$model]['yesterday'] ?? 0,
          // 'Продажи Сегодня' => $ordersCount[$model]['today'] ?? 0,
        ];
        break;
      }
    }
    return $result;
  }

  private function arrayToCsv($data, $filename = "export.csv")
  {
      // Открываем файл для записи
      $file = fopen($filename, 'w');

      // Добавляем BOM для корректного отображения кириллицы в Excel
      // fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

      // Записываем заголовки (если массив ассоциативный)
      if (!empty($data)) {
          $firstRow = $data[0];
          if (is_array($firstRow)) {
              fputcsv($file, array_keys($firstRow));
          }

          // Записываем данные
          foreach ($data as $row) {
              fputcsv($file, $row);
          }
      }

      fclose($file);
  }

  private function getOrdersCount():array
  {
    $ordersData = $this->getOrdersData();
    $result = [];
    foreach ( $ordersData as $model => $intervals ){
      foreach ( $intervals as $name => $rows ){
        $result[$model][$name] = count($rows ?? []);
      }
    }
    return $result;
  }

  private function getOrdersHighloadData( $start, $end ):array
  {
    $arFilter = array(
			">=UF_CREATED" => (new \Bitrix\Main\Type\DateTime)->createFromTimestamp( $start ),
			"<=UF_CREATED" => (new \Bitrix\Main\Type\DateTime)->createFromTimestamp( $end ),
      "UF_SOURCE" => 'WB_FBO',
		);

		$orders = $this->hl->getList($arFilter);
    $result = [];

    foreach ( $orders as $order ){
      $model = end( explode(' ', $order["UF_PRODUCT_NAME"]) );
      $result[ $model ][] = 1;
    }

    var_dump( count($result['A-158WA-1']) );

    return $result;
  }

  private function getOrdersData():array
  {
    $intervals = [
      'before_last_month' => [
        'start' => strtotime('- 60 day'),
        'end' => strtotime('- 30 day'),
      ],
      'last_month' => [
        'start' => strtotime('- 30 day'),
        'end' => strtotime('- 1 day'),
      ],
      '3_days_before_3_days' => [
        'start' => strtotime('- 7 day'),
        'end' => strtotime('- 3 day'),
      ],
      '3_days' => [
        'start' => strtotime('- 4 day'),
        'end' => strtotime('- 1 day'),
      ],
      'yesterday' => [
        'start' => strtotime('yesterday 00:00:00'),
        'end' => strtotime('yesterday 23:59:59'),
      ],
      'today' => [
        'start' => strtotime('today 00:00:00'),
        'end' => strtotime('today 23:59:59'),
      ],
    ];

    $items = [];

    foreach ( $intervals as $name => $interval ){
      $strSql = "SELECT wbp.vendor_code as model, wbo.created_at as ts
        FROM wdhs_wb_order_products AS wbp
        JOIN wdhs_wb_orders AS wbo ON wbp.order_id = wbo.order_id
        WHERE wbo.cabinet = 'WR' AND (wbo.timestamp BETWEEN '{$interval['start']}' AND '{$interval['end']}')";
      // if ( $name == 'today' ){
      //   $today = strtotime( date('Y-m-d 00:00:00') );
      //   $strSql = "SELECT wbp.vendor_code as model, wbo.created_at as ts
      //     FROM wdhs_wb_order_products AS wbp
      //     JOIN wdhs_wb_orders AS wbo ON wbp.order_id = wbo.order_id
      //     WHERE wbo.cabinet = 'WR' AND (wbo.timestamp > {$today})";
      // }

      $ordersFBO = $this->getOrdersHighloadData( start: $interval['start'], end: $interval['end'] );

      $result = $this->dbMain->Query( $strSql );
      $tmp = [];
      while ( $row = $result->Fetch() ){
        $tmp[ $row['model'] ][] = 1;
      }
      foreach ( $tmp as $model => $value ){
        $items[$model][$name] = array_merge( $tmp[$model], $ordersFBO[$model] ?? [] );
      }
    }


    return $items;
  }

  function getBitrixProductsData()
  {
    $arFilter = [
      'IBLOCK_ID' => 16,
      '!PROPERTY_WBARTICLE2' => false,
    ];
    $arSelect = ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_WBARTICLE2'];
    $result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
    $items = [];

    while ( $row = $result->GetNext() ){
      $items[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
        'offer_id' => $row['PROPERTY_WBARTICLE2_VALUE'],
        'name' => $row['NAME']
      ];
    }

    return $items;
  }

  private function getCostFBO():array
  {
    $rows = $this->dbPanel->select(['*'], 'wb_fbo_cost_WR')->make();
    $items = [];

    foreach ( $rows as $row ){
      $items[ $row['article'] ] = $row['cost'];
    }

    return $items;
  }

  private function getCostDataBitrix():array
  {
    $strSql = "SELECT model, price, count, brand_id FROM ci_price WHERE active_wb = 'Y' AND model NOT IN ('ФУТЛЯР', 'КОРОБКА', 'КОРО') ORDER BY price ASC";

    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ) {
      $items[ $row['model'] ][] = [
          'brand_id' => $row['brand_id'],
          'count' => $row['count'],
          'price' => $row['price'],
      ];
    }

    return $items ?? [];
  }

  private function getReserved():array
  {
    $strSql = "SELECT ARTICLE, RESERVED FROM ci_reserved";
    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ){
      $items[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $items ?? [];
  }

  private function getBrands():array
  {
    $strSql = "SELECT id, name FROM ci_brands";
    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ){
      $items[ $row['id'] ] = $row['name'];
    }

    return $items ?? [];
  }
}

( new AnalyticsCsv )->run();
 ?>
