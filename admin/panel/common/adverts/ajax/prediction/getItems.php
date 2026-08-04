<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/adverts/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("Empty post");


$cachePath = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/adverts/cache/items_{$_POST['platform']}.json";
$lifespan = 600;
$isFileExists = file_exists( $cachePath );
$isCacheValid = false;

if ( $isFileExists ) $isCacheValid = (time() - filectime( $cachePath )) < $lifespan;
if ( $isCacheValid ) die( json_encode(['status' => 'ok','context' => 'is up to date']) );

CModule::includeModule('panel.manager');

$config = Loader::loadConfig( $_POST['platform'] );
Config::init( $config );

CommunicationService::silence();

$data = new DataProvider(
  main: \Bitrix\Main\Application::getConnection(),
  panel: new DBPanel,
  ms: new MoyskladAPI('s1'),
  platform: $_POST['platform'],
);

$items = $data->getItems();

if ( count($items) <= 0 ) die( json_encode(['status' => 'failed', 'context' => '']) );

$res = file_put_contents( $cachePath, json_encode($items) );

if ( $res === false ) die( json_encode(['status' => 'failed', 'context' => '']) );
die( json_encode(['status' => 'ok', 'context' => 'updated']) );
 ?>
