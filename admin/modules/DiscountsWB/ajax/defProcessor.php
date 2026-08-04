<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['file']['tmp_name'];
  }else{
    die('Ошибка загрузки файла');
  }


$settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsDef.json';
$arSettingsDef = json_decode( file_get_contents($settingsPath), true );
$discMax = !empty($_POST['discMax']) ? $_POST['discMax'] : false;


parseXlsx($filename,$discMax,$arSettingsDef);


function parseXlsx($filename, $discMax,$arSettingsDef){
  $xls = PHPExcel_IOFactory::load($filename);
  $xls->setActiveSheetIndex(0);
  $sheet = $xls->getActiveSheet();
  $ar = array();
  if ( $discMax == false ){
    foreach ($sheet->toArray() as $key => $row) {
      if ($key == 0) continue;
      if ( !preg_match('/[0-9]+$/', $row[$arSettingsDef['nmid_col']]) ){
       echo json_encode(['error' => 'nmid не соответствует шаблону. Проверьте настройки колонок']);
       die;
      }
      $nmid = intval($row[$arSettingsDef["nmid_col"]]);
      $currentDisc = intval($row[$arSettingsDef["curDisc_col"]]);
      $desiredDisc = intval($row[$arSettingsDef["uplDisc_col"]]);
      if ($desiredDisc > 50) continue;
      $ar[$desiredDisc][] = ['nmID' => $nmid, 'discount' => $desiredDisc];
    }
    ksort($ar);
    $chartData = [];
    $chartData['countGoods'] = 0;
    $chartData['flag'] = 'chart';
    foreach ($ar as $key => $value) {
      $chartData['xaxis'][] = $key;
      $chartData['yaxis'][] = count($value);
      $chartData['countGoods'] += count($value);
    }
    echo json_encode($chartData);
  }else{
    foreach ($sheet->toArray() as $key => $row) {
      if ($key == 0) continue;
      if ( !preg_match('/[0-9]+$/', $row[$arSettingsDef["nmid_col"]]) ){
        echo json_encode(['error' => 'nmid не соответствует шаблону. Проверьте настройки колонок']);
        die;
      }
      $nmid = $row[$arSettingsDef["nmid_col"]];
      $currentDisc = $row[$arSettingsDef["curDisc_col"]];
      $desiredDisc = $row[$arSettingsDef["uplDisc_col"]];
      // if ($currentDisc == $desiredDisc) continue;
      if ( $desiredDisc <= $discMax ){
        $ar[] = ['nmid' => $nmid, 'discount' => $desiredDisc];
      }
    }
    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('listOne');

    foreach ($ar as $key => $pos) {
      $index = $key + 1;
      $sheet->setCellValueExplicit("A" . $index , $pos['nmid'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("B" . $index , $pos['discount'], PHPExcel_Cell_DataType::TYPE_STRING);
    }

    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/temp/';
    $filename = 'default_discounts.xlsx';
    $objWriter->save( $dirPath . $filename );

    echo json_encode( ['flag' => 'discount', 'discGoods' => count($ar)] );
  }

}


 ?>
