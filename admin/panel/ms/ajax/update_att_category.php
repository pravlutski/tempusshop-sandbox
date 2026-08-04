<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;
global $DB;

if (!empty($_POST)) {

  $DB->Query("DELETE FROM wdhs_ozon_attribute_category_new WHERE 1=1", false, $err_mess.__LINE__);

  foreach ($_POST as $k => $v) {
    $tmpArr = explode('@',$k);
    $in = array(
      "name" => "'".$v."'",
      "category_id" => "'".$tmpArr[0]."'",
      "type_id" => "'".$tmpArr[1]."'",
    );
    $DB->Insert("wdhs_ozon_attribute_category_new", $in, $err_mess.__LINE__);
  }

  return 'ok';
} else {
  return 'error';
}


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
