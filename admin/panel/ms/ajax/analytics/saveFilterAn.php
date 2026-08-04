<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $USER;

if ( !empty($_POST['data']) ){
  $arPairs = explode('&', $_POST['data']);
  foreach ($arPairs as $pair) {
    $seller[] = urldecode( explode('=',$pair)[0] );
  }
  $res = json_encode($seller);
  $filePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/ajax/analytics/filter/filter_'.$USER->GetID().'.json';
  file_put_contents($filePath, $res);
}

 ?>
