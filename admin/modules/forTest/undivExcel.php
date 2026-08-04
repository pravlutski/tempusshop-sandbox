<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("iblock");

//Получаем товары с МС Хронос
$objMS = new MoyskladAPI('msk');
$allStores = array("83c00532-0f74-11ee-0a80-143a0014a102","796d5aa2-bab0-11ee-0a80-03440010c9e0","97706d75-5b6f-11ee-0a80-14cc002bb00d","e7c0d649-55ef-11ee-0a80-1186002ba09f");
// $store_id = '83c00532-0f74-11ee-0a80-143a0014a102';
foreach ($allStores as $store_id) {
  // code...
  $filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id}";
  $objMS->getStock(0, $filter);
}
$fromMS = [];

// global $DB;
//
// $strSql = "TRUNCATE TABLE illiquid_wb";
// $DB->Query($strSql, false, $err_mess.__LINE__);
// print_r($strSql . '<br>');
// var_dump($objMS->MSPosition);
// die;
foreach ($objMS->MSPosition as $value) {
  $fromMS[$value["XML_ID"]] = [
    'stockDays' => $value["stockDays"],
    'price' => $value['PRICE'],
    'stock' => $value['stock']
    ];
}

//Получаем артикул ВБ и прочее из битрикса
$arFilter = Array(
  "IBLOCK_ID"	=> 16,
  "XML_ID" => array_keys($objMS->MSPosition),
  "!PROPERTY_TYPE" => false,
  "!PROPERTY_CML2_ARTICLE" => false,
  "!PROPERTY_WBARTICLE2" => false,
  "!PROPERTY_PROP_MAXYSS_NMID_CREATED_WB" => false
);

$arSelect = array("ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE","PROPERTY_FACE" ,"PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_TYPE", "PROPERTY_WBARTICLE2");
$rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

//Формируем массив и фильтруем по количеству дней на складе
$filtered = [];
 while($art = $rs->GetNext()){
  if ( floor($fromMS[$art["XML_ID"]]['stockDays']) > 1 && $fromMS[$art["XML_ID"]]['stock'] > 0 ){

    foreach ($art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_DESCRIPTION'] as $key => $value) {
      if ($value == 'WR') {
        $nmid = $art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE'][$key];
      }
    }

    $filtered[] = [
      'bitrixId' => $art['ID'],
      'wbarticle' => $art['PROPERTY_WBARTICLE2_VALUE'],
      'nmid' => $nmid,
      'face' => $art['PROPERTY_FACE_VALUE'],
      'article' => $art['PROPERTY_CML2_ARTICLE_VALUE'],
      'type' => array_values( $art['PROPERTY_TYPE_VALUE'] )[0],
      'stockDays' => $fromMS[$art["XML_ID"]]['stockDays'],
      'price' => $fromMS[$art["XML_ID"]]['price'] / 100 //Делим на 100, так как себес в МС хранится в копейках
    ];
    // foreach ($art['PROPERTY_WBARTICLE2_VALUE'] as $value) {
    // }

  }
}
// var_dump($filtered);
// die;
// $filtered = $filteredBuffer;

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

//Содержимое последних подгрупп собираем в массивы по 7 элементов
splitIntoChunks($groups);
// writeSectionsDB($groups);

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}
writeXls($groups);

echo '<pre>';
var_dump( $groups );
echo '</pre>';
// die;
//А также в ролях
function divideIntoSubGroups(&$groups, $key, $value){
  // $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/dividerLog.txt';
  $faceType = $value['face'] == 'Аналоговый' ? 'analog' : 'digital';
  $faceType =  $value['face'] == 'Аналогово-цифровой' ? 'combined' : $faceType;
  if ( $value['price'] > 0 && $value['price'] <= 3000 ){
    $groups[$key]['below_3k'][$faceType][] = $value;
    // file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' до 3-х тысяч"' . PHP_EOL, FILE_APPEND);
  }
  else if ( $value['price'] > 3000 && $value['price'] <= 7000 ){
    $groups[$key]['below_7k'][$faceType][] = $value;
    // file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' от 3-х до 7 тысяч"' . PHP_EOL, FILE_APPEND);
  }
  elseif ( $value['price'] > 7000 && $value['price'] <= 9999 ) {
    $groups[$key]['below_9k'][$faceType][] = $value;
    // file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' от 7-ми до 9-ти тысяч"' . PHP_EOL, FILE_APPEND);
  }
  elseif ( $value['price'] > 9999 ) {
    $groups[$key]['above_9k'][$faceType][] = $value;
    // file_put_contents($path, $value['article'] . ' попал в группу "'. $value['type'] .' от 7-ми до 9-ти тысяч"' . PHP_EOL, FILE_APPEND);
  }
}

function splitIntoChunks(&$groups){
  foreach ($groups as &$typeGroup){
    foreach ($typeGroup as &$priceGroup){
      foreach ($priceGroup as &$faceGroup) {
        $faceGroup = array_chunk($faceGroup, 99);
      }
    }
  }
}

function writeSectionsDB(&$groups){

  foreach ($groups as $type => &$typeGroup){
    foreach ($typeGroup as $priceType => &$priceGroup){
      foreach ($priceGroup as $faceType => &$faceGroup) {
        foreach ($faceGroup as $key => &$cardGroup) {
          foreach ($cardGroup as $card) {
            global $DB;
            $model = $card['article'];
            $nmid = (int)$card['nmid'];
            $group = $type;
            $groupId = $key + 1;
            $section = $priceType;
            $face = $faceType;
            $bitrixId = $card['bitrixId'];
            $completeId = $type . '_' . $section . '_' . $face . '_' . $groupId;
            $strSql = "INSERT INTO illiquid_wb (bitrixId, model, groupType, faceType, sectionType, nmid, groupId, completeId)
            VALUES ('{$bitrixId}', '{$model}', '{$group}', '{$face}','{$section}', '{$nmid}','{$groupId}', '{$completeId}')";
            // print_r($strSql . '<br>');
            $DB->Query($strSql, false, $err_mess.__LINE__);
          }
        }
      }
    }
  }
}

function writeXls(&$groups){
  foreach ($groups as $type => &$typeGroups){
    foreach ($typeGroups as $typePrice => &$priceGroup) {
      foreach ($priceGroup as $faceType => &$faceGroup) {
        $k = 1;
        foreach ($faceGroup as $key => &$cardGroup){

          $xls = new PHPExcel();
          $xls->setActiveSheetIndex(0);
          $sheet = $xls->getActiveSheet();
          $sheet->setTitle('listOne');

          $i = 1;
          foreach ($cardGroup as $card) {
            // var_dump($card['nmid']);
            // die;
            $sheet->setCellValueExplicit("A" . $i, $card['nmid'], PHPExcel_Cell_DataType::TYPE_STRING);
            $i++;
          }

          //Сохранение файла
          $objWriter = new PHPExcel_Writer_Excel2007($xls);
          $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/export/bigone/';
          $filename = 'BIG_' . $type . '_' . $typePrice . '_' . $faceType . '_' . $key + 1 . '.xlsx';
          $cardGroup['link'] = '/admin/modules/promcom/export/bigone/' . $filename;
          $objWriter->save( $dirPath . $filename );
          $k++;
        }
      }

    }
  }
}

 ?>
