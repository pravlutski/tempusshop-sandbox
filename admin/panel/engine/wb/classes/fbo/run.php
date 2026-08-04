<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("bootstrap.php");

$path = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/logs/fbo/WR/log_corrections.json";
$json = file_get_contents($path);
$corr = json_decode($json, true);

$rows = (new DBPanel)->select(['*'], 'wb_fbo_stock_WR')->make();
$models = array_map( fn($item) => $item['article'], $rows );

var_dump( count($models) );
$models = array_merge( $models, array_keys($corr) );
var_dump( count($models) );
// die;

$validator = new FboStockValidator(
  panel: new DBPanel,
  main: \Bitrix\Main\Application::getConnection(),
  cabinet: 'WR'
);
$validator->getInfo( $models );

$result = [
  'visible' => 0,
  'hidden' => 0,
];

foreach ( $models as $model ){
  $isVisible = $validator->checkIfVisible( $model );

  if ( is_bool($isVisible) && $isVisible ) {
    $result['visible']++;
    continue;
  }

  var_dump($model);
  $result['hidden']++;
}

var_dump($result);
 ?>
