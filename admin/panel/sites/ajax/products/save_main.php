<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ) die('Empty POST');

foreach ($_POST as $field => $bitrix_id) {
  $strSql = "UPDATE wdhs_wb_product_base SET bitrix_id = '{$bitrix_id}' WHERE field = '{$field}'";
  // var_dump($strSql);
  $DB->Query($strSql, false, $err_mess.__LINE__);
}


 ?>
