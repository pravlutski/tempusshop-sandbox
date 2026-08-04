<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("POST cannot be empty");

$fields = [
  'campaignId',
  'warehouse',
  'stock',
  'markup',
  'minPrice',
  'cabinet'
];

$settings = [];
foreach ( $_POST as $key => $value ){
  if ( $key == 'cabinet' ) {
    $cabinet = $value;
    continue;
  }
  list($id, $field) = explode( '|', $key );
  if ( $id == 0 ) continue;
  $settings[$id]['campaignId'] = (int)$id;
  $settings[$id]['cabinet'] = (string)$cabinet;
  $settings[$id][$field] = empty($value) ? null : $value;

  foreach ( $fields as $reqField ){
    $settings[$id][$reqField] = $settings[$id][$reqField] ?? null;
  }
}

$settings = array_values($settings);

if ( empty($settings) ) throw new Exception("Cannot save an empty array");

// var_dump($settings);
// throw new Exception('ПОШЕЛ НАХУЙ ЗАЕБАЛ');

UIProcessor::init();
$updater = UIProcessor::updater();
$table = Config::instance()->getTableName('campaigns_match_list');

$updater->delete(
  table: $table,
  field: 'cabinet',
  value: $cabinet
);

$updater->insertSome(
  into: $table,
  values: $settings,
);
 ?>
