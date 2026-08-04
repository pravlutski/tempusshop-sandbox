<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$panel = new DBPanel;

$id = $_POST['sale_id'];

$where = [
  'column' => 'sale_id',
  'operator' => '=',
  'value' => (int) $id,
];

$panel->delete( 'ozon_sales_IP', [$where] );
 ?>
