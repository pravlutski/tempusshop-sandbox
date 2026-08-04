<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

global $DB;
$minDate = $_POST['min-date-postings'];
$maxDate = $_POST['max-date-postings'];

$strSql = "SELECT date, type, count(model) AS count FROM `ozon_postings_shares`";

if( empty($minDate) && empty($maxDate) ){
  die('Не задан период');
}
if ( !empty($minDate) && empty($maxDate) ){
  $strSql .= " WHERE date >= '{$minDate}'";
}
if ( !empty($maxDate) && empty($minDate) ){
  $strSql .= " WHERE date <= '{$maxDate}'";
}
if ( !empty($maxDate) && !empty($minDate) ){
  $strSql .= " WHERE date >= '{$minDate}' AND date <='{$maxDate}'";
}
$strSql .=" GROUP BY date,type";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$postingsRaw = [];
while ( $row = $resultDB->Fetch() ){
  if ( isset($allPeriodPostings[$row['date']]) ){
    $allPeriodPostings[$row['date']] += $row['count'];
  }else{
    $allPeriodPostings[$row['date']] = $row['count'];
  }
  // $allPeriodPostings += $row['count'];
  $postingsRaw[$row['date']][$row['type']] = $row['count'];
}
$postings = [];
foreach ( $postingsRaw as $date => $types){
  foreach ($types as $type => $value) {
    if ( $allPeriodPostings[$date] != 0 ){
      $postings[$date][$type] = $value / $allPeriodPostings[$date] * 100;
    }else{
      $postings[$date]['Нет_данных'] = 100;
    }
  }
}

echo json_encode($postings);


// WHERE date = '2024-07-15'
// GROUP BY type"

 ?>
