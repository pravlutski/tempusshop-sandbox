<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$arSettings = [];
foreach ($_POST as $key => $value) {
  if ( !empty($value)){
    switch ($key) {
      case 'comission':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = floatval($comm);
        break;
      case 'markup':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = floatval($comm);
        break;
      case 'discMin':
        $comm = $value;
        $arSettings[$key] = intval($comm);
        break;
      case 'nmid_col':
        $comm = $value;
        $arSettings[$key] = intval($comm) - 1;
        break;
      case 'curDisc_col':
        $comm = $value;
        $arSettings[$key] = intval($comm) - 1;
        break;
      case 'uplDisc_col':
        $comm = $value;
        $arSettings[$key] = intval($comm) - 1;
        break;
    }
  }else{
    die('Поля не могут быть пустыми');
  }
}
$settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsDef.json';
file_put_contents( $settingsPath, json_encode($arSettings) );

 ?>
