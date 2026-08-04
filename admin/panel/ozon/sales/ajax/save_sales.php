<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;
$cabinetArr = array('IP','TI');
$CurDB = new DBPanel();
if(!isset($_POST['cabinet'])) die('cabinet not found');
$cabinet = $_POST['cabinet'];

if (isset($_POST)) {
  $result = $CurDB->query("DELETE FROM ozon_sales_{$cabinet} WHERE 1 = 1");
  foreach ($_POST['data'] as $id => $v) {
    $in = array(
      "sale_id" => "'".$id."'",
      "sort" => "'".$v['sort']."'",
      "active" => "'" . (!empty($v['active']) ? $v['active'] : 0) . "'",
      "name" => "'".trim($v['name'])."'",
      "date_start" => "'".$v['date_start']."'",
      "date_end" => "'".$v['date_end']."'",
      "skd" => "'".$v['skd']."'",
      "skd_fbo" => "'".$v['skd_fbo']."'",
      "perc" => "'".$v['perc']."'",
      "boost" => "'".$v['boost']."'",
      "perc_entry" => "'".$v['perc_entry']."'",
      "potencial" => "'".$v['potencial']."'",
      "uses" => "'".$v['uses']."'",
      "top_models" => "'".$v['top_models']."'",
    );


    $fields = implode(",", array_keys($in));
    $values = implode(",",$in);

    $sql = "INSERT INTO ozon_sales_{$cabinet} ($fields) VALUES ($values)";
    $CurDB->query($sql);
  }
}


$result = $CurDB->query("SELECT * FROM ozon_sales_{$cabinet}");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $salesActive[] = $row;
}

unset($result);
unset($rows);

$result = $CurDB->query("SELECT * FROM ozon_sales_pi_{$cabinet} WHERE pi_sets = 'main'");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $pi_sebes = $row['per_sebes'];
  $unset = $row['unset'];
  $min_profit = $row['min_profit'];
  $com_d = $row['com'];
}

unset($result);
unset($rows);



$settingsAll = json_decode(CProSet::getOption("SETTINGS_RRC"), true);
$settingsAll = $settingsAll['ozti'];
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
      print_r($markup);
      print_r('#');
      print_r($price);
      print_r('№');
    }
    $merg = implode(' / ',$merg);
    if (!empty($merg)) {
      $in = array(
          "merg" => "'".$merg."'",
      );
      $update = [];
      foreach ($in as $key => $value) {
          $update[] = "$key = $value";
      }
      $update = implode(", ", $update);

      $result = $CurDB->query("UPDATE ozon_sales_{$cabinet} SET {$update} WHERE id = {$id}");
    }
    unset($merg);
}

unset($in);
unset($fields);
unset($values);
$time = date('d.m H:i:s');
$SOURCE = \Bitrix\Main\Engine\CurrentUser::get()->getId();
$in = array(
  "source	" => "'".$SOURCE."'",
  "script	" => "'Настройки акций (".$cabinet.")'",
  "time	" => "'".$time."'",
  "status	" => "'SAVE'",
);

$fields = implode(",", array_keys($in));
$values = implode(",",$in);

$sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
$CurDB->query($sql);
$CurDB->close();

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
