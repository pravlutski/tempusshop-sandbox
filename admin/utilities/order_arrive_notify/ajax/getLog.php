<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$date = $_POST['date'];

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/order_arrive_notify/logs/log_%s.txt";

if ( $date == 'false' ) $date = date('Y-m-d');

if ( !file_exists( sprintf($path, $date) ) ){
  die("Нет истории на эту дату");
}

$rows = file_get_contents( sprintf($path, $date) );

$rows = explode(PHP_EOL, $rows);
$rows = array_reverse( $rows );

echo implode( PHP_EOL, $rows );
 ?>
