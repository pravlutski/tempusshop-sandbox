<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$wh = $_POST['warehouses'];

file_put_contents(
  '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/warehouses_fbo.json',
  json_encode( $wh ?? [] )
);
 ?>
