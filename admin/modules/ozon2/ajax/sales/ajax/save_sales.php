<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;


if (isset($_POST)) {
  $DB->Query("DELETE FROM wdhs_ozon_sales WHERE 1 = 1", false, $err_mess.__LINE__);
  foreach ($_POST['data'] as $id => $v) {
    $in = array(
      "sale_id" => "'".$id."'",
      "sort" => "'".$v['sort']."'",
      "active" => "'".$v['active']."'",
      "name" => "'".$v['name']."'",
      "date_start" => "'".$v['date_start']."'",
      "date_end" => "'".$v['date_end']."'",
      "skd" => "'".$v['skd']."'",
      "skd_fbo" => "'".$v['skd_fbo']."'",
      "perc" => "'".$v['perc']."'",
      "potencial" => "'".$v['potencial']."'",
      "uses" => "'".$v['uses']."'",
      "top_models" => "'".$v['top_models']."'",
    );
    $DB->Insert("wdhs_ozon_sales", $in, $err_mess.__LINE__);
  }
}

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[] = $row;
}
$strSql = "SELECT * FROM wdhs_sales_pi WHERE pi_sets = 'main'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $pi_sebes = $row['per_sebes'];
  $unset = $row['unset'];
  $min_profit = $row['min_profit'];
  $com_d = $row['com'];
}

$settingsAll = json_decode(CProSet::getOption("SETTINGS_RRC"), true);
$settingsAll = $settingsAll['os'];
$minMarkup = array();
    foreach ($settingsAll['rules'] as $rule) {
        if ($rule['markup']) {
            $minMarkup[] = $rule['markup'];
        }
    }

//$markup = $minMarkup;
$sebes = 5000;

foreach ($salesActive as $key => $value) {
    foreach ($minMarkup as $key => $markup) {
      $id = $value['id'];

      if (!empty($value['perc'])) {
        $price = $sebes * $markup * (1 - intval($value['perc'])/100);
      } else {
        $price = $sebes * $markup;
      }
      if (!empty($value['skd'])) {
        $com = ($com_d - $value['skd'])/100;
        $tmpprice = $price * (1 - ($com_d/100));
        $price = $tmpprice / (1 - $com);
      }else{
        $com = $com_d/100;
        $price = $price;
      }
      $merg[] = round(((($price * (1 -  $com)) - $sebes) / $sebes) * 100);
    }
    $merg = implode(' / ',$merg);
    if (!empty($merg)) {
      $in = array(
          "merg" => "'".$merg."'",
      );
      $DB->Update("wdhs_ozon_sales", $in, "WHERE id = $id", $err_mess.__LINE__);
    }
    unset($merg);
}

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
