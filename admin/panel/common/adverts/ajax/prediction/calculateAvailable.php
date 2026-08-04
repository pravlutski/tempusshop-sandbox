<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/adverts/lib/bootstrap.php");

if ( empty($_POST) ){
  throw new Exception("Empty post");
}

$cachePath = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/adverts/cache/items_{$_POST['platform']}.json";

$config = Loader::loadConfig( $_POST['platform'] );
Config::init( $config );

$json = file_get_contents( $cachePath );
$items = json_decode( $json, true );

$profile = [];
foreach ( $_POST['profile'] as $brand => $row ){
  $profile[$brand] = [
    'maxCost' => $row['maxCost'] ? $row['maxCost'] : Config::instance()->getDefaultValue('maxCost'),
    'minCost' => $row['minCost'] ? $row['minCost'] : Config::instance()->getDefaultValue('minCost'),
    'stockDays' => $row['stockDays'] ? $row['stockDays'] : Config::instance()->getDefaultValue('stockDays'),
    'bid' => $row['bid'] ? $row['bid'] : Config::instance()->getDefaultValue('bid'),
  ];
}


$distributed = DistributeService::distribute( $profile, $items );

echo json_encode([
  'count' => count( $distributed[$_POST['brandId']] ?? 0 )
]);
 ?>
