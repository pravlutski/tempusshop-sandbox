<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) throw new Exception("POST array cannot be empty");

$db = \Bitrix\Main\Application::getConnection();

$rows = $db->Query( "SELECT * FROM wdhs_wb_product_brand_aliases" );
$savedAliases = [];
while ( $row = $rows->Fetch() ){
  $savedAliases[ $row['brand_id'] ] = $row['brand_name'];
}

$brand_id = $_POST['brand_id'];
$brand_name = $_POST['brand_name'];
$timestamp = date('Y-m-d G:i:s');

if ( isset( $savedAliases[$brand_id] ) ) throw new Exception("Alias for selected brand was already set");

$strSql = "INSERT INTO wdhs_wb_product_brand_aliases (brand_id, brand_name, timestamp) VALUES ('{$brand_id}', '{$brand_name}', '{$timestamp}')";
$db->Query( $strSql );
?>
