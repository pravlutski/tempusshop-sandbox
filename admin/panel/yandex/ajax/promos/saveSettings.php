<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("POST cannot be empty");

UIProcessor::init();
$updater = UIProcessor::updater();
$config = Config::instance();

$update = [];
foreach( $_POST as $key => $value ){
  if ( $key == 'cabinet' ){
    $cabinet = $key;
    continue;
  }
  list($id, $field) = explode('_', $key);
  $update[$id][$field] = empty($value) ? null : $value;
}

foreach ( $update as $id => $values ){
  $updater->update(
    table: $config->getTableName('promos_list'),
    values: $values,
    where: ['id' => $id]
  );
}
 ?>
