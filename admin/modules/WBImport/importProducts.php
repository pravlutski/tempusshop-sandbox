<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/WBImport/classes/ProductsWB.php");
set_time_limit(0);

if ( in_array($argv[1], ['WR', 'TL']) ){
  $cab = $argv[1];
}else{
  $cab = 'WR';
}

$objProducts = new ProductsWB( $cab, [], false );

$objProducts->getItems(); // Получаем карточки из битры
/*
Доступные массивы:
$objProducts->itemsForUpdate
$objProducts->itemsForUpload
*/
$objProducts->uploadCards(); // Создаем новые карточки
$objProducts->updateCards(); // Обновляем информацию у уже созданных карточек
/*
Методы создания и обновления карточек работают по принципу все или ничего. Если в одной карточке ошибка, то не создастся/обновится вся группа
*/
$objProducts->updateMediaAll(); // Обновляем медиа, для каждой карточки отдельным запросом (Специфика API)

sleep(300);
/*
Карточки создаются асинхронно, поэтому сразу nmid вытянуть нельзя
*/
$objProducts->getNmids(); // Получаем nmid и chrtid карточек, чтобы записать их в базу

$objProducts->writeLog( 'END' );
 ?>
