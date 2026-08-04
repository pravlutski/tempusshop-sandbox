<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;
$CurDB = new DBPanel();
if(!isset($_POST['cabinet'])) die('cabinet not found');
$cabinet = $_POST['cabinet'];

if (!empty($_POST)) {

  $tmp['client_id'] = $_POST['client_id'];

  $tmp['key'] = $_POST['key'];
  $tmp['api_url'] = $_POST['api_url'];
  $tmp['com'] = $_POST['com'];
  $tmp['newCom'] = $_POST['newCom'];
  $tmp['bot_threshold'] = $_POST['bot_threshold'];
  $tmp['fbo_threshold'] = $_POST['fbo_threshold'];

  $tmp['select_threshold'] = $_POST['select_threshold'];

  $tmp['dp_threshold'] = $_POST['dp_threshold'];
  $tmp['step'] = $_POST['step'];
  $tmp['discount'] = $_POST['discount'];
  $tmp['max_discount'] = $_POST['max_discount'];

  if ($_POST['upload_price'] == 'on') {
    $tmp['upload_price'] = 'Y';
  } else {
    $upload_price = 'N';
  }
  if ($_POST['upload_stock'] == 'on') {
    $tmp['upload_stock'] = 'Y';
  } else {
    $tmp['upload_stock'] = 'N';
  }
  if ($_POST['upload_products']  == 'on') {
    $tmp['upload_products'] = 'Y';
  } else {
    $tmp['upload_products'] = 'N';
  }


  $CurDB->query("DELETE FROM ozon_main_settings_{$cabinet} WHERE 1=1");

  foreach ($tmp as $key => $value) {
    $in = array(
      "name" => "'".$key."'",
      "value" => "'".$value."'",
    );
    $fields = implode(",", array_keys($in));
    $values = implode(",",$in);

    $sql = "INSERT INTO ozon_main_settings_{$cabinet} ($fields) VALUES ($values)";
    $CurDB->query($sql);
  }
  unset($in);
  unset($fields);
  unset($values);
  $time = date('d.m H:i:s');
  $SOURCE = \Bitrix\Main\Engine\CurrentUser::get()->getId();
  $in = array(
  	"source	" => "'".$SOURCE."'",
  	"script	" => "'Основные настройки (".$cabinet.")'",
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
