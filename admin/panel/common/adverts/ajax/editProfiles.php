<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

if ( empty($_POST['profiles']) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}

$update = [];
foreach( $_POST['profiles'] as $id => $data ){
  $data = array_map( fn($el) => $el ? "'{$el}'": "NULL", $data );
  $template = "UPDATE am_brand_profiles SET minCost = %s, maxCost = %s, stockDays = %s, bid = %s WHERE id = {$id}";
  $strSql = sprintf( $template, ...array_values($data) );
  $panel->query( $strSql );
}
 ?>
