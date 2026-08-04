<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;
global $DB;

if (!empty($_POST)) {
  $attribute_id = $_POST['attribute_id'];
  $property_id = $_POST['property_id'];
  $arData = array();
  $DB->Query("DELETE FROM wdhs_ozon_attribute_matches WHERE attribute_id = {$attribute_id} AND property_id = {$property_id}", false, $err_mess.__LINE__);
  foreach ($_POST['data'] as $key => $value) {
    foreach ($value as $v => $nl) {
      if ($v == '0' or empty($nl)) { $v = ''; }
      $in = array(
        "attribute_id" => "'".$attribute_id."'",
        "property_id" => "'".$property_id."'",
        "attribute_value_id" => "'".$v."'",
        "attribute_name" => "'".$nl."'",
        "property_value_id" => "'".$key."'",
      );
      $DB->Insert("wdhs_ozon_attribute_matches", $in, $err_mess.__LINE__);
    }
  }
}
