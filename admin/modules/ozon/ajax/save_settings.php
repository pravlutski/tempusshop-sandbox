<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;

if (!empty($_POST)) {

  $tmp['client_id'] = $_POST['client_id'];

  $tmp['key'] = $_POST['key'];
  $tmp['api_url'] = $_POST['api_url'];
  $tmp['com'] = $_POST['com'];
  $tmp['newCom'] = $_POST['newCom'];
  
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



  $DB->Query("DELETE FROM wdhs_ozon_main_settings WHERE 1=1", false, $err_mess.__LINE__);

  foreach ($tmp as $key => $value) {
  $in = array(
    "name" => "'".$key."'",
    "value" => "'".$value."'",
  );
  $DB->Insert("wdhs_ozon_main_settings", $in, $err_mess.__LINE__);
  }

}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
