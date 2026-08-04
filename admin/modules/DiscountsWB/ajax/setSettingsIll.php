<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$arSettings = [];
foreach ($_POST as $key => $value) {
  if ( !empty($value)){
    switch ($key) {
      case 'nmid_col':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = floatval($comm) - 1;
        break;
      case 'turnover_col':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = floatval($comm) - 1;
        break;
      case 'discMin':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = intval($comm);
        break;
      case 'discMax':
        $comm = str_replace(',','.',$value);
        $arSettings[$key] = intval($comm);
        break;
    }
  }else{
    die('Поля не могут быть пустыми');
  }
}
if ( empty($_POST['cabinet']) ){
  die('Не получен кабинет из формы');
}
$settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsIll_'.$_POST['cabinet'].'.json';
file_put_contents( $settingsPath, json_encode($arSettings) );

 ?>
