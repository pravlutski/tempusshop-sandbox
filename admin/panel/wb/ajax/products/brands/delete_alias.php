<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) throw new Exception("POST array cannot be empty");

$db = \Bitrix\Main\Application::getConnection();

$id = $_POST['id'];

$db->Query( "DELETE FROM wdhs_wb_product_brand_aliases WHERE id = {$id}" );
?>
