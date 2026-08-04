<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

function countActiveItems( DBPanel $panel ):array
{
  $rows = $panel->select(['DISTINCT date'], 'ozon_sales_detail_log_IP')->make();

  $dates = [];
  foreach ( $rows as $row ){
    $dates[] = $row['date'];
  }
  $lastTime = strtotime( end($dates) );
  $lastDate = date('Y-m-d G:i', $lastTime);

  $rows = $panel->select(['*'], 'ozon_sales_detail_log_IP')->where('date', "%{$lastDate}%", 'LIKE')->where('status', 'Y')->make();

  $sales = [];

  foreach( $rows as $row ){
    $sales[ $row['saleId'] ][] = $row;
  }
  $result = [];

  foreach ( $sales as $id => $rows ){
    $result[] = [
      'id' => $id,
      'count' => count($rows),
    ];
  }

  return $result;
}

echo json_encode( countActiveItems($panel) );
 ?>
