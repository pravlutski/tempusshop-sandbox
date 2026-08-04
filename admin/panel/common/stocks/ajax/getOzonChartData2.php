<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

$rows = $panel->select([
  'date',
  'sum(cost * valid_stock_count) as valid_stock_count_sum',
  'sum(cost * stock_defect_stock_count) as stock_defect_stock_count_sum',
  'sum(cost * return_from_customer_stock_count) as return_from_customer_stock_count_sum',
  'sum(cost * other_stock_count) as other_stock_count_sum',

  'sum(valid_stock_count) as valid_stock_count',
  'sum(stock_defect_stock_count) as stock_defect_stock_count',
  'sum(return_from_customer_stock_count) as return_from_customer_stock_count',
  'sum(other_stock_count) as other_stock_count',

], 'ozon_fbo_analytics_reports')->where('date', date('Y-m-d', strtotime('- 14 days')), '>=')->group('date')->make();

$data = array_column($rows, null, 'date');

$rows = $panel->select([
  'date',
  'sum(quantity * cost) as to_client_sum',
  'sum(quantity) as to_client',
], 'ozon_analytics_move_to')->group('date')->where('date', date('Y-m-d', strtotime('- 14 days')), '>=')->group('date')->make();

$moveTo = array_column($rows, null, 'date');

$rows = $panel->select([
  'date',
  'sum(quantity * cost) as from_client_sum',
  'sum(quantity) as from_client',
], 'ozon_analytics_stock_returns')->group('date')->where('date', date('Y-m-d', strtotime('- 14 days')), '>=')->group('date')->make();

$moveFrom = array_column($rows, null, 'date');

foreach ( $data as $date => $item ){
  if ( $moveTo[$date] ){
    $data[$date]['to_client'] = $moveTo[$date]['to_client'];
    $data[$date]['to_client_sum'] = $moveTo[$date]['to_client_sum'];
  }
  if ( $moveFrom[$date] ){
    $data[$date]['from_client'] = $moveFrom[$date]['from_client'];
    $data[$date]['from_client_sum'] = $moveFrom[$date]['from_client_sum'];
  }
}
$lastKey = array_key_last( $data );
$sum = 0;
$stock = 0;
foreach ( $data[$lastKey] as $key => $value ){
  if ( str_contains($key, '_sum') ){
    $sum += $value;
    continue;
  }
  $stock += $value;
}

$data[$lastKey]['summary_sum'] = number_format($sum, 0, '', ' ');
$data[$lastKey]['summary_stock'] = number_format($stock, 0, '', ' ');

echo json_encode($data);
 ?>
