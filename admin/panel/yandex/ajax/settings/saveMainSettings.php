<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("POST array is empty");

UIProcessor::init();

$rows = UIProcessor::data()->settings()->getMainSettings();

foreach ( $rows as $row ){
  $settings[ $row['cabinet'] ] = $row;
}

if ( empty($settings[$_POST['cabinet']]) ){
  UIProcessor::updater()->insertOne(
    into: Config::instance()->getTableName('main_settings'),
    values: $_POST
  );
  exit();
}

UIProcessor::updater()->update(
  table: Config::instance()->getTableName('main_settings'),
  values: $_POST,
  where: [ 'cabinet' => $_POST['cabinet'] ]
);
?>
