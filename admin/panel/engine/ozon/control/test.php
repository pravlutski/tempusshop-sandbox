<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Исключения
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/exceptions/InvalidJsonException.php' );
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/exceptions/NoRepositoryException.php' );
// Конфиг
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/config/ConfigProvider.php');
// ДТО
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/dto/Response.php');
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/dto/ResponseData.php');
// Базовые классы
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/base/ApiBase.php');
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/base/RepositoryBase.php');
// Репозитории
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/repositories/ItemsRepository.php');
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/repositories/PricesRepository.php');
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/repositories/SettingsRepository.php');
// Менеджеры
require($_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/api/ApiManager.php');
// Провайдеры
require( $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/lib/data/DataProvider.php' );
// Сервисы
$dbPanel = new DBPanel;
$dbMain = \Bitrix\Main\Application::getConnection();

ConfigProvider::init('IP');

$dataProvider = new DataProvider(
  items: new ItemsRepository( $dbPanel, $dbMain ),
  settings: new SettingsRepository( $dbPanel, $dbMain ),
  prices: new PricesRepository( $dbPanel, $dbMain ),
);

$items = $dataProvider->getItems();
var_dump( $items[0] );

// $panel = new DBPanel;
//
// $rows = $panel->select(['*'], 'ozon_main_settings_IP')->make();
// $settings = [];
// foreach ( $rows as $row ){
//   $settings[$row['name']] = $row['value'];
// }
//
// $api = new ApiManager( $settings );
// $request = [
//   'filter' => [
//     'visibility' => 'ARCHIVED'
//   ],
//   'limit' => 1000
// ];
// $flag = true;
// $archived = [];
//
// while ( $flag ){
//
//   $response = $api->getProductList( $request );
//   $data = $response->getData()->decode();
//   if ( count($data['result']['items']) < $request['limit'] ) $flag = false;
//
//   foreach ( $data['result']['items'] as $item ){
//     $model = end( explode('_', $item['offer_id']) );
//     $archived[$model] = 1;
//   }
//   if ( empty($data['result']['last_id']) ){
//     var_dump( 'last page or error' );
//     break;
//   }
//   $request['last_id'] = $data['result']['last_id'];
//   sleep( 2 );
// }
//
// var_dump( count($archived) );

 ?>
