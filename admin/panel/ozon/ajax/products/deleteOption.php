<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

if ( empty( $_POST['mode'] ) ){
  die('Модель не указана');
}

$dbPanel = new DBPanel;

if ( $_POST['mode'] == 'single' ){
  if ( empty( $_POST['model'] ) ){
    die('Модель не указана');
  }
  $arWhere[] = [
    'column' => 'model',
    'operator' => '=',
    'value' => $_POST['model']
  ];
  $dbPanel->delete('ozon_model_collection', $arWhere);
  echo "<span class='message-complete'>Выполнено! Изменено строк: {$dbPanel->affectedRows}</span>";
}
if ( $_POST['mode'] == 'all' ){
  $strSql = "TRUNCATE TABLE ozon_model_collection";
  $dbPanel->query( $strSql );
  echo "<span class='message-complete'>Выполнено! Таблица очищена</span>";
}

 ?>
