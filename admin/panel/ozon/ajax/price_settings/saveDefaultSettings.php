<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

if ( empty($_POST) ) throw new Exception("Empty Data");

$data = $_POST;
$update = [];
foreach ( $data as $key => $row )
{
  list($cab, $field) = explode( '|', $key );
  if ( empty($row) ) continue;
  $update[$field] = $row;
}

if ( empty($update) ) throw new Exception("Nothing to update");

$settings = new SettingsManager('IP');
$settings->updateDefaultSettings( $update );
 ?>
