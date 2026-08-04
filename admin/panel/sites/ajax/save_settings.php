<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

$CurDB = new DBPanel();

if (!empty($_POST)) {

  foreach ($_POST as $code => $value) {
    $arWhere[] = [
      'column' => 'SETTING',
      'operator' => '=',
      'value' => $code
    ];
    $addArray = [
    'VALUE' => $value
    ];
    $CurDB->update('sites_settings', $addArray, $arWhere);
    unset($arWhere);
    unset($addArray);
  }
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
