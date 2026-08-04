<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/wb/classes/ProductsWB.php");
set_time_limit(0);

/*
Это обработчик кнопки "Выгрузить на ВБ" в карточке товара. Сделан он топопрно, потому что я не хотел перегружать класс дополнительными опциями типа выборки по айди. Выборка по артикулу сделана для более наглядной выгрузки карточек пачками в модуле ВБ. Если кто-то снесет модуль максиса, пусть сделает еще и выгрузку по артикулу.
*/

$pid = $_POST['product_id'];
$pid = 180715;
$cab = $_POST['lk'] == 'DEFAULT' ? 'TL' : $_POST['lk'];
$cab = 'WR';
if ( !in_array($cab, ['TL','WR']) ) die('Кабинет не определен');
if ( empty($pid) ) die('Не указан ID карточки');

$arFilter = [
  "IBLOCK_ID" => 16,
  "ID" => $pid
];
$arSelect = ['IBLOCK_ID', 'ID', 'PROPERTY_CML2_ARTICLE'];
$result = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
while ( $card = $result->Fetch() ){
  $model = $card['PROPERTY_CML2_ARTICLE_VALUE'];
}

$debLog = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/classes/deb.txt';
file_put_contents($debLog, '');
$objProducts = new ProductsWB( $cab, [$model], $debLog );

$objProducts->getItems(); // Получаем карточки из битры
/*
Доступные массивы:
$objProducts->itemsForUpdate
$objProducts->itemsForUpload
*/
// $objProducts->uploadCards(); // Создаем новые карточки
$objProducts->updateCards(); // Обновляем информацию у уже созданных карточек
/*
Методы создания и обновления карточек работают по принципу все или ничего. Если в одной карточке ошибка, то не создастся/обновится вся группа
*/
$objProducts->updateMediaAll(); // Обновляем медиа, для каждой карточки отдельным запросом (Специфика API)

// sleep(300);
/*
Карточки создаются асинхронно, поэтому сразу nmid вытянуть нельзя
*/
// $objProducts->getNmids(); // Получаем nmid и chrtid карточек, чтобы записать их в базу

$objProducts->writeLog( 'END' );

$log = file_get_contents($debLog);
echo '<pre>';
echo $log;
echo '</pre>';
 ?>
