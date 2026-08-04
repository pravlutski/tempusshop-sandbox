<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

$dbPanel = new DBPanel;

$rows = $dbPanel->select(['DISTINCT date'], 'ozon_sales_detail_log_IP')->make();

$dates = [];
foreach ( $rows as $row ){
  $dates[] = $row['date'];
}
$lastTime = strtotime( end($dates) );
$lastDate = date('Y-m-d G:i', $lastTime);

$rows = $dbPanel->select(['*'], 'ozon_sales_detail_log_IP')->where('date', "%{$lastDate}%", 'LIKE')->where('status', 'Y')->make();

$sales = [];

foreach( $rows as $row ){
  $sales[ $row['saleId'] ][] = $row;
}
$result = [];

foreach ( $sales as $id => $rows ){
  $result[] = [
    'saleId' => $id,
    'count' => count($rows)
  ];
}

echo json_encode($result);

 ?>
