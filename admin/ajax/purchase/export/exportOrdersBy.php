<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class ExportOrdersBY
{
  private $db;
  private $spreadSheet;
  private $path;

  private $purchaseList = [];
  private $printData = [];
  private $service;

  public function __construct()
  {
    CModule::IncludeModule('panel.manager');
    global $DB;
    $this->db = $DB;
    $this->service = new OrderService;

    $this->headers = [
      'Артикул',
      'Номер заказа',
      'Комментарий',
    ];
  }

  public function run():string
  {
    $this->getPurchaseList();
    $this->getOrdersData();
    $path = $this->makeExcel();

    return $path;
  }

  private function getPurchaseList():void
  {
    $supps = $this->getSuppliersRU();
    // $supps[] = 135; // жоский костыль для умника Никиты
    $prepared = array_map( function($item){
      return "'" . $item . "'";
    }, $supps );

    $prepared = implode(',', $prepared);

    $strSql = "SELECT * FROM ci_purchase WHERE site_id = 's2' AND active = 'Y' AND supp_id IN ({$prepared})";
    $res = $this->db->Query( $strSql );

    while ( $row = $res->Fetch() ){
      $this->purchaseList[ $row['order_id'] ][] = $row['model'];
    }
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

  private function getOrdersData():void
  {
    if ( empty($this->purchaseList) ){
      die;
    }
    $arFilter = [
      'ID' => array_keys( $this->purchaseList ),
    ];
    $orders = $this->service->getOrder( [], $arFilter );

    $ordersPrint = [];
    foreach($orders as $arItem){
      $ordersPrint[ $arItem['ID'] ] = [
        'ID' => $arItem['ID'],
        'ORDER_ID' => $arItem['ORDER_ID'] ?? '',
        'COMMENTS' => $arItem['COMMENTS'] ?? '',

      ];
		}
    foreach ( $this->purchaseList as $order_id => $arModels ){
      foreach ( $arModels as $key => $model ){
        $this->printData[] = [
          $model,
          $ordersPrint[$order_id]['ORDER_ID'] ?? '-',
          $ordersPrint[$order_id]['COMMENTS'] ?? 'Витрина',
        ];
      }
    }
  }

  private function makeExcel():string
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
		foreach ( $this->headers as $key => $value ){
			$sheet->setCellValueExplicit("{$alphabet[$key]}1", $value, PHPExcel_Cell_DataType::TYPE_STRING);
		}
		foreach ( $this->printData as $i => $value ){
			$row = $i + 2;
			foreach ( $value as $k => $elem ){
				$sheet->setCellValueExplicit("{$alphabet[$k]}{$row}", $elem, PHPExcel_Cell_DataType::TYPE_STRING);
			}
		}

    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/ajax/purchase/export/minsk_export.xlsx";
    $objWriter->save( $filename );
    return $filename;
  }

}

try{
  $obj = new ExportOrdersBY;
  $path = $obj->run();

  if ( file_exists($path) ){
    // Убеждаемся, что до функции header() не было никакого вывода
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    // Убеждаемся, что нет пробелов перед readfile()
    readfile($path);
  }else{
    echo 'Файл не найден';
  }
}catch( Throwable $e ){
  var_dump($e);
}
 ?>
