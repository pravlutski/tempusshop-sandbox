<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];

$advertId = 16317328;

$url = 'https://advert-api.wb.ru/adv/v1/promotion/adverts';
$data = [$advertId];
$result = reqcurl($url, $auth, $data);
// var_dump( json_decode($result, 1) );
$nmids = json_decode($result, 1)[0]['autoParams']['nms'];
$advName = json_decode($result, 1)[0]['name'];

$url = 'https://advert-api.wb.ru/adv/v2/fullstats';
$data = [
  'id' => $advertId,
  'interval' => [
    'begin' =>date("Y-m-d", strtotime("-7 day")),
    'end' => date("Y-m-d")
  ]
];
// var_dump($data);
$result = reqcurl($url, $auth, [$data]);
// var_dump( json_decode($result, 1) );
$result = json_decode($result, 1);
$sum = $result[0]['sum'];

$groups = [];
// var_dump($nmids);
foreach ($nmids as $value) {
  $url = 'https://suppliers-api.wildberries.ru/content/v2/get/cards/list';
  $data['settings'] = [
    'filter' => [
      'withPhoto' => -1,
      'textSearch' => (string)$value
    ]
  ];
  $result = reqcurl($url, $auth, $data);
  $result = json_decode($result, 1);
  $groups[$result['cards'][0]['imtID']] = $value;
}
foreach ($nmids as $imtid => $value) {
  $url = 'https://suppliers-api.wildberries.ru/content/v2/get/cards/list';
  $data['settings'] = [
    'filter' => [
      'withPhoto' => -1,
      'imtID' => (string)$imtid
    ]
  ];
  $result = reqcurl($url, $auth, $data);
  $result = json_decode($result, 1);
  foreach ($result['cards'] as $key => $value) {
    $groups[$value['imtID']] = $value['nmID'];
  }
}

$report = [];
// var_dump($groups);
$report = [];
foreach ($groups as $imtid => $nmid) {
  $url = 'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/detail';
  $data = [
    'nmIDs' => [$nmid],
    'period' => [
      'begin' =>date("Y-m-d h:i:s", strtotime("-7 day")),
      'end' => date("Y-m-d h:i:s")
    ],
    'page' => 1
  ];
  $result = reqcurl($url, $auth, $data);
  $result = json_decode($result, 1);
  // var_dump($result);
  $dataReport = $result['data']['cards'];
  foreach ($dataReport as $card) {
    $report[] = [
      'nmid' => $card['nmID'],
      'vendorCode' => $card['vendorCode'],
      'imtID' => $imtid,
      'ordersSum' => $card['statistics']['selectedPeriod']['ordersSumRub'],
      'spentSum' => $sum,
      'advName' => $advName
    ];
  }
  // var_dump($report);
  sleep(20);
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('listOne');

$sheet->setCellValueExplicit("A1", 'ID кампании', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("A1")->getFont()->setBold(true);
$sheet->getStyle("A1")->getFont()->setSize(13);
$sheet->getColumnDimension("A")->setWidth(20);

$sheet->setCellValueExplicit("B1", 'Название камании', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("B1")->getFont()->setBold(true);
$sheet->getStyle("B1")->getFont()->setSize(13);
$sheet->getColumnDimension("B")->setWidth(20);

$sheet->setCellValueExplicit("C1", 'Артикул продавца', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("C1")->getFont()->setBold(true);
$sheet->getStyle("C1")->getFont()->setSize(13);
$sheet->getColumnDimension("C")->setWidth(20);

$sheet->setCellValueExplicit("D1", 'ID группы', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("D1")->getFont()->setBold(true);
$sheet->getStyle("D1")->getFont()->setSize(13);
$sheet->getColumnDimension("D")->setWidth(20);

$sheet->setCellValueExplicit("E1", 'Артикул WB', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("E1")->getFont()->setBold(true);
$sheet->getStyle("E1")->getFont()->setSize(13);
$sheet->getColumnDimension("E")->setWidth(20);

$sheet->setCellValueExplicit("F1", 'Потрачено, руб.', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("F1")->getFont()->setBold(true);
$sheet->getStyle("F1")->getFont()->setSize(13);
$sheet->getColumnDimension("F")->setWidth(20);

$sheet->setCellValueExplicit("G1", 'Сумма заказов, руб.', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("G1")->getFont()->setBold(true);
$sheet->getStyle("G1")->getFont()->setSize(13);
$sheet->getColumnDimension("G")->setWidth(20);

$sheet->setCellValueExplicit("H1", 'ДРР, %', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->getStyle("H1")->getFont()->setBold(true);
$sheet->getStyle("H1")->getFont()->setSize(13);
$sheet->getColumnDimension("H")->setWidth(20);
//Высота первой строки
$sheet->getRowDimension("1")->setRowHeight(25);
//Внешняя рамка
$outBorder = array(
  'borders'=>array(
     'outline' => array(
         'style' => PHPExcel_Style_Border::BORDER_THIN,
           'color' => array('rgb' => '000000')
          ),
  )
);
$sheet->getStyle("A1:H1")->applyFromArray($outBorder); // Внешняя рамка для шапки

$i = 2;
foreach ($report as $key => $card) {
  $spentSum = $card['spentSum'];
  $ordersSum = $card['ordersSum'] == 0 ? $card['spentSum'] : $card['ordersSum'];
  
  $sheet->setCellValueExplicit("A" . $i, $key == 0 ? $advertId : '', PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $key == 0 ? $advName : '', PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("C" . $i, $card['vendorCode'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("D" . $i, $card['imtID'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("E" . $i, $card['nmid'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("F" . $i, $card['spentSum'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("G" . $i, $card['ordersSum'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("H" . $i, ($spentSum / $ordersSum) * 100, PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}
$inBorder = array(
    'borders'=>array(
        'inside' => array(
             'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => '000000')
         ),
     )
);
$sheet->getStyle("A1:H".$i)->applyFromArray($inBorder);
$sheet->getStyle("A2:H" .$i)->applyFromArray($outBorder); //Внешняя рамка для всего остального

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
$filename = 'reportWBADV.xlsx';
$objWriter->save( $dirPath . $filename );


function reqcurl($url, $auth, $data = false){
  $ch = curl_init($url);
  curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    array(
      "Content-Type: application/json",
      "Authorization: {$auth}"
    )
  );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  if ($data != false){
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}

 ?>
