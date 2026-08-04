<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

$rows = $panel->select([
  'date',
  'sum(cost * stock) as stock_sum',
  'sum(cost * from_client) as from_sum',
  'sum(cost * to_client) as to_sum',
  'sum(cost * utilization) as utilization_sum',
  'sum(stock) as stock',
  'sum(from_client) as from_client',
  'sum(to_client) as to_client',
  'sum(utilization) as utilization',
  'sum(cost * (stock + from_client + to_client + utilization)) as summary_sum',
  'sum(stock + from_client + to_client + utilization) as summary_stock',
], 'yandex_stock_analytics')->where('date', date('Y-m-d', strtotime('- 14 days')), '>=')->group('date')->make();

$data = array_column($rows, null, 'date');

$lastKey = array_key_last($data);
$data[$lastKey]['summary_sum'] = number_format( $data[$lastKey]['summary_sum'], 0, '', ' ' );
$data[$lastKey]['summary_stock'] = number_format( $data[$lastKey]['summary_stock'], 0, '', ' ' );
echo json_encode($data);

 ?>
