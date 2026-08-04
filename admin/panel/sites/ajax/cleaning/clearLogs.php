<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/wb/classes/CleanerWB.php");

$cabinet = $_POST['cabinet'];
$mode = $_POST['mode'];
$cleaner = new CleanerWB($cabinet, true);

switch( $mode ){
  case 'price':
    $cleaner->clearPricesLogs();
  break;
  case 'stock':
    $cleaner->clearStocksLogs();
  break;
  case 'products':
    $cleaner->clearProductsLogs();
  break;
  case 'orders':
    // print_r('Заказы еще не готовы:(');
    $cleaner->clearOrdersLogs();
  break;
}
 ?>
