<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("wb_classes_control_PriceControl_php_WR");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require("ControlDataProvider.php");
require("ControlCommunicationService.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/classes/StocksDataProvider.php");

class PriceControl
{
  private array $dict = [];
  private ControlDataProvider $data;

  public function __construct(
      private \Bitrix\Main\DB\MysqliConnection $main,
      private DBPanel $panel,
      private string $cabinet,
  ){
    $this->dict = [
      'price' => [
        'WR' => 'PROPERTY_WBPRICE',
        'WT' => 'PROPERTY_MINIMUM_PRICE_RB',
        'TL' => 'PROPERTY_WBTL_PRICE',
      ],
      'wbarticle' => [
        'WR' => 'PROPERTY_WBARTICLE2',
        'WT' => 'PROPERTY_WBARTICLE2',
        'TL' => 'PROPERTY_WBARTICLE3',
      ],
      'discount' => [
        'WR' => 'CATALOG_SALE_wb',
        'WT' => 'CATALOG_SALE_wb',
        'TL' => 'CATALOG_SALE_wbtl',
      ],
      'filter' => [
        'WR' => 'active_wb',
        'WT' => 'active_wb',
        'TL' => 'active_wbtl',
      ],
    ];
    CModule::IncludeModule('panel.manager');
    $this->data = new ControlDataProvider(
      main: $this->main,
      panel: $this->panel,
      cabinet: $this->cabinet
    );

    $this->sdp = new StocksDataProvider(
      main: $this->main,
      panel: $this->panel
    );

    ControlCommunicationService::init(
      cabinet: $cabinet,
      panel: new DBPanel,
      module: "control_price_$cabinet"
    );
  }

  public function run()
  {
    ControlCommunicationService::updateStatus(
      text: 'Получение товаров',
      perc: '10',
      status: 'PROCESS',
      start: date('Y.m.d G:i:s'),
    );
    $items = $this->getItems();

    ControlCommunicationService::updateStatus( text: "Получение данных с WB", perc: 30 );
    $prices = $this->getActualPrices();

    ControlCommunicationService::updateStatus( text: "Получение акутальных данных", perc: 60 );
    $ci_price = $this->getCiPriceData();

    ControlCommunicationService::updateStatus( text: "Сравнение", perc: 80 );
    $result = $this->compareData(
      bx: $items,
      wb: $prices,
      ci_price: $ci_price
    );

    $path = $this->buildXlsx( $result['deviations'] );
    // $this->sendNotification( $deviations );
    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reportStock/{$this->cabinet}/control_price.json",
      json_encode( $result['all'] )
    );

    var_dump( count($result['deviations']) );

    ControlCommunicationService::updateStatus(
      text: 'Завершено',
      perc: '100',
      status: 'COMPLETED',
      end: date('Y.m.d G:i:s'),
    );
  }

  private function getHeaders():array
  {
    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $rows = $this->main->Query( $strSql );
    $auth = [];

    while ( $row = $rows->Fetch() ){
      $auth = $row['api'];
    }

    return [
      "Content-Type: application/json",
      "Authorization: " . $auth
    ];
  }

  private function request( string $url, array $headers = [], array $query = [], string $method = 'GET' )
  {
    $url = $url . '?' . http_build_query($query);

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    $res = curl_exec( $ch );

    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close( $ch );

    return json_decode( $res, true );
  }

  private function getActualPrices():array
  {
    $result = [];
    $flag = true;
    $attempt = 1;
    $headers = $this->getHeaders();
    $query = [
      'limit' => 1000,
      'offset' => 0,
    ];

    while ( $flag ){
      if ( $attempt >= 3 ) break;

      $res = $this->request(
        url: 'https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter',
        headers: $headers,
        query: $query
      );

      if ( empty($res['data']['listGoods']) ){
        sleep( 3 * $attempt );
        $attempt++;
        continue;
      }
      if ( count($res['data']['listGoods']) < $query['limit'] ) $flag = false;

      foreach ( $res['data']['listGoods']  as $item ){
        $model = end( explode('_', $item['vendorCode']) );
        $result[ $model ] = [
          'price' => $item['sizes'][0]['price'],
          'discount' => $item['discount'],
          'nmid' => $item['nmID'],
          'chrtid' => $item['sizes'][0]['sizeID'],
        ];
      }

      $query['offset'] += $query['limit'];
      usleep( 600 * 1000 );
    }

    return $result;
  }

  private function getWBTop():array
  {
    $rows = $this->sdp->getActiveItems( $this->cabinet );
    $result = [];

    foreach ( $rows as $id => $data ){
      $result[] = $id;
    }

    return $result;
  }

  private function getItems():array
  {
    $price = $this->dict['price'][$this->cabinet];
    $discount = $this->dict['discount'][$this->cabinet];
    $wbarticle = $this->dict['wbarticle'][$this->cabinet];

    $costs = $this->data->getItemsCost(
      dict: $this->data->getDictionary(),
      useNmid: false,
    );

    $arFilter = [
      "IBLOCK_ID" => 16,
      "ID" => $this->getWBTop(),
    ];
    $arSelect = ['ID', "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", $price, $wbarticle];

    $rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect );
    $result = [];

    $fbo = $this->getFboPrices();
    $dynamic = $this->getDynamicPrices();

    while( $row = $rows->GetNext() ){
      $model = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $result[ $model ] = [
        'price' => $dynamic[$model] ?? $fbo[$model] ?? $row["{$price}_VALUE"],
        'discount' => CProSet::getOption( $discount ),
        'wbarticle' => $row["{$wbarticle}_VALUE"],
        'cost' => $costs[$model] ?? 0
      ];
    }
    return $result;
  }

  private function getFboPrices():array
  {
    if ( $this->cabinet != 'WR' ) return [];
    $discount = CProSet::getOption('CATALOG_SALE_wb');
    $multiplier = 1 / ( 1 - $discount / 100 );
    $rows = $this->panel->select(['*'], 'wb_fbo_price_WR')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = round($row['price'] * $multiplier);
    }

    return $result;
  }

  private function getDynamicPrices():array
  {
    if ( $this->cabinet != 'WR' ) return [];

    $discount = CProSet::getOption('CATALOG_SALE_wb');
    $multiplier = 1 / ( 1 - $discount / 100 );
    $rows = $this->panel->select(['*'], 'wb_dp_prices')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = round($row['price'] * $multiplier);
    }

    return $result;
  }

  private function getCiPriceData():array
  {
    $filter = $this->dict['filter'][ $this->cabinet ];
    $strSql = "SELECT * FROM ci_price WHERE $filter = 'Y'";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $result[ $row['model'] ] = 1;
    }

    return $result;
  }

  private function compareData( array $wb, array $bx, array $ci_price ):array
  {
    $deviations = [];
    $all = [];
    foreach ( $bx as $model => $data ){
      if ( empty($data['price']) && $wb[$model]['price'] == 1000000 ) continue;

      $key = isset($ci_price[$model]) ? 'price' : 'discount';

      $all[$model] = [
        'model' => $model,
        'cost' => $data['cost'],
        'wbarticle' => $data['wbarticle'],
        'price_bx' => $data['price'],
        'price_wb' => $wb[$model]['price'],
        'discount_bx' => $data['discount'],
        'discount_wb' => $wb[$model]['discount'],
      ];

      if ( abs( round($data[$key]) - round($wb[$model][$key]) ) > 1 ) {
        $deviations[$model] = $all[$model];
      }

    }

    return [
      'deviations' => $deviations,
      'all' => $all,
    ];
  }

  private function buildXlsx( array $data ):string
  {
    if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('List 1');

		$alphabet = range('A', 'Z');

    $sheet->setCellValueExplicit("A1", "Артикул", PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B1", "Цена BX", PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C1", "Цена WB", PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D1", "Скидка BX", PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("E1", "Скидка WB", PHPExcel_Cell_DataType::TYPE_STRING);

    $row = 2;
    foreach ( $data as $model => $v ){
      $sheet->setCellValueExplicit("A{$row}", $model, PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("B{$row}", $v['price_bx'] ?? 'Не установлена', PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("C{$row}", $v['price_wb'] ?? 'Не установлена', PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("D{$row}", $v['discount_bx'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("E{$row}", $v['discount_wb'], PHPExcel_Cell_DataType::TYPE_STRING);
      $row++;
    }

    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/price_deviations_{$this->cabinet}.xlsx";
    $objWriter->save( $filename );

    return $filename;
  }

  private function sendNotification( array $price ):void
  {
    $uploadCardsError = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/logs/reportStock/{$this->cabinet}/control_errors.json";
    $json = file_get_contents( $uploadCardsError );
    $data = json_decode( $json, true );
    $countUpload = count( $data );
    $countPrice = count( $price );

    $message = "<b>Модуль контроля WB {$this->cabinet}:</b>\n\nОшибки в товарах: {$countUpload}\nОшибки в ценах/скидках: {$countPrice}\n\n";
    $bot = new TGNotifier;
    $link = "https://tempusshop.ru/admin/panel/engine/wb/export/price_deviations_{$this->cabinet}.xlsx";
    $file = '<a href="'.$link.'">Ошибки в ценах</a>';
    $res = $bot->sendMessage( $message . $file);

    // $bot->sendFile($filename, "Ошибки в ценах {$this->cabinet}.xlsx");

  }
}

$obj = new PriceControl(
  main: \Bitrix\Main\Application::getConnection(),
  panel: new DBPanel,
  cabinet: $argv[1]
);

$obj->run();
$workers->updateStatus("N");
 ?>
