<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) die('empty post');

$prop_id = $_POST['pid'];
$char_id = $_POST['cid'];

$strSql = "DELETE FROM wdhs_wb_product_props_dependencies WHERE property_id = '{$prop_id}' AND char_id = '{$char_id}'";
$DB->Query($strSql, false, $err_mess.__LINE__);
 ?>
