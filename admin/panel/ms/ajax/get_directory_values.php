<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;
global $DB;
$arResult = array();
if (!empty($_GET)) {
  $strSql = "SELECT * FROM wdhs_ozon_attribute_list_new WHERE value LIKE '%{$_GET['input']}%' AND attribute_id = '{$_GET['id']}'";
  $results = $DB->Query($strSql, false, $err_mess.__LINE__);
  while ($row = $results->Fetch()){
    $arResult[] = ['value_id'=>$row['value_id'],'value'=>$row['value']];
  }
}
echo json_encode($arResult);
