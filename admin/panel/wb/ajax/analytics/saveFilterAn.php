<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( !empty($_POST['data']) ){
  $arPairs = explode('&', $_POST['data']);
  foreach ($arPairs as $pair) {
    $seller[] = urldecode( explode('=',$pair)[0] );
  }
  $res = json_encode($seller);
  $filePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/wb/ajax/analytics/filter.json';
  file_put_contents($filePath, $res);
}

 ?>
