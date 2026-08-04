<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;

$strSql = "SELECT * FROM wdhs_ozon_sales_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[] = $row;
}



$settingsAll = json_decode(CProSet::getOption("SETTINGS_RRC"), true);
$settingsAll = $settingsAll['ozti'];
//print_r($settingsAll);
$minMarkup = PHP_FLOAT_MAX;
    foreach ($settingsAll['rules'] as $rule) {
        if ($rule['markup'] < $minMarkup) {
            $minMarkup = $rule['markup'];
        }
    }

$markup = $minMarkup;
$sebes = 5000;

foreach ($salesActive as $key => $value) {
    $id = $value['id'];
    if (!empty($value['skd'])) {
      $com = (floatval($value['com']) - floatval($value['skd']))/100;
    }else{
      $com = floatval($value['com'])/100;
    }
    if (!empty($value['perc'])) {
      $price = $sebes * $markup * (1 - intval($value['perc'])/100);
    } else {
      $price = $sebes * $markup;
    }
    $merg = round(((($price * (1 -  $com)) - $sebes) / $sebes) * 100);
    if (!empty($merg)) {
      $in = array(
          "merg" => "'".$merg."'",
      );
      $DB->Update("wdhs_ozon_sales_new", $in, "WHERE id = $id", $err_mess.__LINE__);
    }

}


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
