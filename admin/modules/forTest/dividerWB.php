<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

//Получаем товары с МС Хронос
$objMS = new MoyskladAPI('msk');
$store_id = '83c00532-0f74-11ee-0a80-143a0014a102';
$filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id}";
$objMS->getStock(0, $filter);

$fromMS = [];

foreach ($objMS->MSPosition as $value) {
  $fromMS[$value["XML_ID"]] = [
    'stockDays' => $value["stockDays"],
    'price' => $value['PRICE']
    ];
}

//Получаем артикул ВБ и прочее из битрикса
$arFilter = Array(
  "IBLOCK_ID"	=> 16,
  "XML_ID" => array_keys($objMS->MSPosition),
  "!PROPERTY_TYPE" => false,
  "!PROPERTY_CML2_ARTICLE" => false,
  "!PROPERTY_WBARTICLE2" => false,
);

$arSelect = array("ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_TYPE", "PROPERTY_WBARTICLE2");
$rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

//Формируем массив и фильтруем по количеству дней на складе
$filtered = [];
while($art = $rs->GetNext()){
  if ( floor($fromMS[$art["XML_ID"]]['stockDays']) > 20 ){
    $filtered[ $art["PROPERTY_WBARTICLE2_VALUE"] ] = [
      'wbarticle' => $art['PROPERTY_WBARTICLE2_VALUE'],
      'article' => $art['PROPERTY_CML2_ARTICLE_VALUE'],
      'type' => array_values( $art['PROPERTY_TYPE_VALUE'] )[0],
      // 'stockDays' => $fromMS[$art["XML_ID"]]['stockDays'],
      'price' => $fromMS[$art["XML_ID"]]['price'] / 100 //Делим на 100, так как себес в МС хранится в копейках
    ];
  }
}

//Разбиваем массив на четыре группы по типу и каждую группу разбиваем на на три подгруппы
$groups = [];
foreach ( $filtered as $value ){
  switch ( trim($value['type']) ) {
    case 'Мужские':
      divideIntoSubGroups($groups, 'male', $value);
    break;
    case 'Женские':
      divideIntoSubGroups($groups, 'female', $value);
    break;
    case 'Унисекс':
      divideIntoSubGroups($groups, 'uni', $value);
    break;
    case 'Детские':
      divideIntoSubGroups($groups, 'child', $value);
    break;
  }
}

//Получаем все, что надо для формирования эксель таблицы каждой подгруппы
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

foreach ($groups as $type => $typeGroups){
  foreach ($typeGroups as $typePrice => $priceGroup) {
    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('listOne');

    //Указываем шапку и ее стиль
    //A
    $sheet->setCellValueExplicit("A1", 'Артикул', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle("A1")->getFont()->setBold(true);
    $sheet->getStyle("A1")->getFont()->setSize(13);
    $sheet->getColumnDimension("A")->setWidth(20);
    //B
    $sheet->setCellValueExplicit("B1", 'Артикул WB', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle("B1")->getFont()->setBold(true);
    $sheet->getStyle("B1")->getFont()->setSize(13);
    $sheet->getColumnDimension("B")->setWidth(20);
    //C
    $sheet->setCellValueExplicit("C1", 'Тип/Пол', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle("C1")->getFont()->setBold(true);
    $sheet->getStyle("C1")->getFont()->setSize(13);
    $sheet->getColumnDimension("C")->setWidth(20);
    //D
    $sheet->setCellValueExplicit("D1", 'Цена', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle("D1")->getFont()->setBold(true);
    $sheet->getStyle("D1")->getFont()->setSize(13);
    $sheet->getColumnDimension("D")->setWidth(20);
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
    $sheet->getStyle("A1:D1")->applyFromArray($outBorder); // Внешняя рамка для шапки

    $i = 2;
    foreach ($priceGroup as $card){
      $sheet->setCellValueExplicit("A" . $i, $card['article'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("B" . $i, $card['wbarticle'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("C" . $i, $card['type'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("D" . $i, $card['price'], PHPExcel_Cell_DataType::TYPE_STRING);
      $i++;
    }

    //Внутренняя рамка
    $inBorder = array(
	      'borders'=>array(
		        'inside' => array(
			           'style' => PHPExcel_Style_Border::BORDER_THIN,
			              'color' => array('rgb' => '000000')
		         ),
	       )
    );
    $sheet->getStyle("A1:D".$i)->applyFromArray($inBorder);
    $sheet->getStyle("A2:D" .$i)->applyFromArray($outBorder); //Внешняя рамка для всего остального

    //Сохранение файла
    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
    $filename = $type . '_' . $typePrice . '.xlsx';
    $objWriter->save( $dirPath . $filename );
  }
}

//Содержимое последних подгрупп собираем в массивы по 7 элементов
splitIntoChunks($groups);
echo '<pre>';
var_dump($groups);
echo '</pre>';


//А также в ролях
function divideIntoSubGroups(&$groups, $key, $value){
  $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/dividerLog.txt';

  if ( $value['price'] > 0 && $value['price'] <= 3000 ){
    $groups[$key]['below_3k'][] = $value;
    file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' до 3-х тысяч"' . PHP_EOL, FILE_APPEND);
  }
  else if ( $value['price'] > 3000 && $value['price'] <= 7000 ){
    $groups[$key]['below_7k'][] = $value;
    file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' от 3-х до 7 тысяч"' . PHP_EOL, FILE_APPEND);
  }
  elseif ( $value['price'] > 7000 && $value['price'] <= 9999 ) {
    $groups[$key]['below_9k'][] = $value;
    file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' от 7-ми до 9-ти тысяч"' . PHP_EOL, FILE_APPEND);
  }
}

function splitIntoChunks(&$groups){
  foreach ($groups as &$typeGroup){
    foreach ($typeGroup as &$priceGroup){
      $priceGroup = array_chunk($priceGroup, 7);
    }
  }
}


 ?>
