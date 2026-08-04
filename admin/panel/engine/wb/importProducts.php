<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/wb/classes/ProductsWB.php");
set_time_limit(0);

if ( in_array($argv[1], ['WR', 'TL', 'WT']) ){
  $cab = $argv[1];
}else{
  $cab = 'WR';
}
$debLog = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/classes/deb.txt';
$objProducts = new ProductsWB( $cab, [], false );
$arStat = [
  'status' => 'IN_PPROCESS',
  'percent' => '10',
  'status_text' => 'Получаю товары',
  'time_start' => date('Y.m.d G:i:s')
];
$objProducts->updateStatus( 'importProducts_'.$cab, $arStat );
$objProducts->getItems(); // Получаем карточки из битры

/*
Доступные массивы:
$objProducts->itemsForUpdate
$objProducts->itemsForUpload
*/
$objProducts->updateStatus( 'importProducts_'.$cab, ['status_text' => 'Создаю карточки', 'percent' => '20'] );
$objProducts->uploadCards(); // Создаем новые карточки
$objProducts->updateStatus( 'importProducts_'.$cab, ['status_text' => 'Обновляю карточки', 'percent' => '30'] );
$objProducts->updateCards(); // Обновляем информацию у уже созданных карточек
/*
Методы создания и обновления карточек работают по принципу все или ничего. Если в одной карточке ошибка, то не создастся/обновится вся группа
*/
$objProducts->updateStatus( 'importProducts_'.$cab, ['status_text' => 'Формирую массив с медиа', 'percent' => '50'] );
$objProducts->updateMediaAll(); // Обновляем медиа, для каждой карточки отдельным запросом (Специфика API)

sleep(300);
/*
Карточки создаются асинхронно, поэтому сразу nmid вытянуть нельзя
*/
$objProducts->updateStatus( 'importProducts_'.$cab, ['status_text' => 'Получаю nmid', 'percent' => '70'] );
$objProducts->getNmids(); // Получаем nmid и chrtid карточек, чтобы записать их в базу
$arStat = [
  'status' => 'COMPLETED',
  'percent' => '100',
  'status_text' => 'Выполнено',
  'time_end' => date('Y.m.d G:i:s')
];
$objProducts->updateStatus( 'importProducts_'.$cab, $arStat );
$objProducts->writeLog( 'END' );
 ?>
