<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if (isset($_POST['data']) and count($_POST['data']) > 0) {
  foreach ($_POST['data'] as $key => $value) {

    $DB->Query("DELETE FROM wdhs_ozon_attribute_bitrix WHERE attribute_id = {$key}", false, $err_mess.__LINE__);
    if ($value == 'default-value'){
      $defval = $_POST['default-value'][$key];
    } else {
      $defval = '';
    }
    $in = array(
      "attribute_id" => "'".$key."'",
      "property_id" => "'".$value."'",
      "default_value" => "'".$defval."'",
    );
    $DB->Insert("wdhs_ozon_attribute_bitrix", $in, $err_mess.__LINE__);
  }
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
