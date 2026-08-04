<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertConfigProvider.php');

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/adverts/AdvertManager.php";
$output = [];
exec( "php {$path}", $output );

var_dump($output);
 ?>
