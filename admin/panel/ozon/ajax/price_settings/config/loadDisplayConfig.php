<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;

$userId = $USER->GetId();
$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/price_settings/settings/{$userId}_config.json";

if ( !file_exists($path) ) echo '{}';

$data = file_get_contents( $path );

echo $data;
 ?>
