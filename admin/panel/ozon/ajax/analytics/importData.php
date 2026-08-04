<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/ZennolabParser.php');
CModule::IncludeModule('panel.manager');

$path = $_POST['name'];
var_dump( $path );

if ( !file_exists($path) ) throw new Exception('Выбранного файла не существует');

$zenno = new ZennolabParser( basename($path) );

$zenno->run(
  importAsMainData: false,
  forceImport: true
);
 ?>
