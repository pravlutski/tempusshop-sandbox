<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;

$userId = $USER->GetId();
$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/price_settings/settings/{$userId}_config.json";

$data = json_encode( $_POST );
file_put_contents( $path, $data );

 ?>
