<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

$settings = new SettingsManager('IP');

if ( empty($_POST) ) throw new Exception('Empty post data');

$settings->deleteItem( intval($_POST['id']) );
 ?>
