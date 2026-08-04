<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

$rows = $panel->select([
  'date',
  'sum(cost * stock) as stock_sum',
  'sum(cost * reserved) as reserved_sum',
  'sum(stock) as stock',
  'sum(reserved) as reserved',
], 'ozon_fbo_stat_IP')->where('date', date('Y-m-d', strtotime('- 17 days')), '>=')->group('date')->make();

$data = array_column($rows, null, 'date');

echo json_encode($data);
 ?>
