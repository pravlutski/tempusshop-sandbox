<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("POST cannot be empty");

UIProcessor::init();

$insert = [];
foreach ( $_POST as $field => $value ){
  $insert[$field] = $value;
}

UIProcessor::updater()->delete(
  table: Config::instance()->getTableName('promos_settings'),
  field: 'cabinet',
  value: $cabinet
);

UIProcessor::updater()->insertOne(
  into: Config::instance()->getTableName('promos_settings'),
  values: $insert
);
 ?>
