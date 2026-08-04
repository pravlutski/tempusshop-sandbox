<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/wb/classes/StocksDataProvider.php");

$main = \Bitrix\Main\Application::getConnection();
$panel = new DBPanel;

$sdp = new StocksDataProvider($main, $panel);

$rows = $sdp->getActiveItems('WR');
$items = [];

foreach ( $rows as $row ){
  if ( $row['supplier'] != 63 ) continue;
  $items[ $row['model'] ] = $row;
}

var_dump( count($items) );
var_dump( $items );
 ?>
