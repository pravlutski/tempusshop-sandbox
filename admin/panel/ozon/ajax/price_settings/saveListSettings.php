<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

if ( empty($_POST) ) throw new Exception("Empty post data");

$data = $_POST;

$update = [];

foreach ( $data as $key => $row ){
  list($id, $field) = explode('|', $key);
  $value = str_replace(',', '.', $row);
  $update[$id][$field] = $value;
}

$settings = new SettingsManager('IP');
$settings->updateListSettings( $update );
 ?>
