<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager'))return;
if (isset($_POST) && !empty($_POST['cabinet'])) {
  $CurDB = new DBPanel();
  //$DB->Query("DELETE FROM wdhs_sales_pi WHERE pi_sets = 'main'", false, $err_mess.__LINE__);

  if ($_POST['top'] == 'Y') {

    $text = explode("\n",$_POST['text']);

    $in = array(
        "tops" => "'".json_encode($text)."'",
    );

    $update = [];
    foreach ($in as $key => $value) {
        $update[] = "$key = $value";
    }
    $update = implode(", ", $update);

    $result = $CurDB->query("UPDATE ozon_sales_pi_{$cabinet} SET {$update} WHERE pi_sets = 'main'");

  } else {
    $in = array(
        "per_sebes" => "'".$_POST['per_sebes']."'",
        "unset" => "'".$_POST['unset']."'",
        "com" => "'".$_POST['com']."'",
        "min_profit" => "'".$_POST['min_profit']."'",
        "min_profit_perc" => "'".$_POST['min_profit_perc']."'",
    );

    // Построим строку для UPDATE
    $update = [];
    foreach ($in as $key => $value) {
        $update[] = "$key = $value";
    }
    $update = implode(", ", $update);

    $result = $CurDB->query("UPDATE ozon_sales_pi_{$cabinet} SET {$update} WHERE pi_sets = 'main'");

  }
  unset($in);
  unset($fields);
  unset($values);
  $time = date('d.m H:i:s');
  $SOURCE = \Bitrix\Main\Engine\CurrentUser::get()->getId();
  $in = array(
  	"source	" => "'".$SOURCE."'",
  	"script	" => "'Общие настройки акций (".$cabinet.")'",
  	"time	" => "'".$time."'",
  	"status	" => "'SAVE'",
  );

  $fields = implode(",", array_keys($in));
  $values = implode(",",$in);

  $sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
  $CurDB->query($sql);

  $CurDB->close();
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
