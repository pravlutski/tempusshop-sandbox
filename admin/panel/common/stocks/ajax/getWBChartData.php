<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST['cabinet']) ) throw new Exception("empty cabinet");

$panel = new DBPanel;

$rows = $panel->select([
  'stock_date',
  'sum(cost * stock) as stock_sum',
  'sum(cost * from_client) as from_sum',
  'sum(cost * to_client) as to_sum',
  'sum(stock) as stock',
  'sum(from_client) as from_client',
  'sum(to_client) as to_client',
  'sum(cost * (stock + from_client + to_client)) as summary_sum',
  'sum(stock + from_client + to_client) as summary_stock',
], 'wb_fbo_stat_' . $_POST['cabinet'])->where('stock_date', date('Y-m-d', strtotime('- 14 days')), '>=')->group('stock_date')->make();

$data = array_column($rows, null, 'stock_date');

$lastKey = array_key_last($data);
$data[$lastKey]['summary_sum'] = number_format( $data[$lastKey]['summary_sum'], 0, '', ' ' );
$data[$lastKey]['summary_stock'] = number_format( $data[$lastKey]['summary_stock'], 0, '', ' ' );
echo json_encode($data);
 ?>
