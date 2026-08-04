<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

if ( empty($_POST) || empty($_POST['cabinet']) ){
  die('Не хватает данных');
}

$db = new DBPanel;
$strSql = "SELECT * FROM wb_fbo_stat_{$_POST['cabinet']}";
if ( empty($_POST['dateFrom']) ) $_POST['dateFrom'] = date( 'Y-m-d', strtotime("- 3 month") );
if ( !empty($_POST['dateFrom']) && !empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}' AND stock_date <= '{$_POST['dateTo']}'";
}elseif( isset($_POST['dateFrom']) && empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}'";
}elseif ( isset($_POST['dateTo']) && empty($_POST['dateFrom']) ) {
  $strSql .= " WHERE  stock_date <= '{$_POST['dateTo']}'";
}

$strSql .= " ORDER BY stock_date ASC";

$resultDB = $db->query($strSql, false, $err_mess.__LINE__);
$stockDynamic = [];
$chartData = [];
$modelAssort = [];
$avgData = [
  'all' => [],
  'stock' => []
];
$data = $db->fetchAll( $resultDB );
foreach ( $data as $row ){
  if ( isset($chartData[$row['stock_date']]) ){
    $stockDynamic[$row['stock_date']]['from_client'] = $stockDynamic[$row['stock_date']]['from_client'] + $row['from_client'];
    $stockDynamic[$row['stock_date']]['to_client'] = $stockDynamic[$row['stock_date']]['to_client'] + $row['to_client'];
    $stockDynamic[$row['stock_date']]['stock'] = $stockDynamic[$row['stock_date']]['stock'] + $row['stock'];

    if ( $row['stock'] != 0){
      $modelAssort[$row['stock_date']] += 1;
    }

    $chartData[$row['stock_date']]['all'] = $chartData[$row['stock_date']]['all'] + ($row['to_client'] * $row['cost'] + $row['from_client'] * $row['cost'] + $row['stock'] * $row['cost']);
    $chartData[$row['stock_date']]['stock'] = $chartData[$row['stock_date']]['stock'] + $row['stock'] * $row['cost'];
  }else{
    $stockDynamic[$row['stock_date']]['from_client'] = $stockDynamic[$row['stock_date']]['from_client'];
    $stockDynamic[$row['stock_date']]['stock'] = $stockDynamic[$row['stock_date']]['stock'];
    $stockDynamic[$row['stock_date']]['to_client'] = $stockDynamic[$row['stock_date']]['to_client'];
    if ( $row['stock'] != 0){
      $modelAssort[$row['stock_date']] = 1;
    }

    $chartData[$row['stock_date']]['all'] = $row['to_client'] * $row['cost'] + $row['from_client'] * $row['cost'] + $row['stock'] * $row['cost'];
    $chartData[$row['stock_date']]['stock'] = $row['stock'] * $row['cost'];
  }
}

$strSql = "SELECT avg(avg_stock) as avg FROM (SELECT stock_date, sum(stock + from_client + to_client) as avg_stock FROM wb_fbo_stat_{$_POST['cabinet']}";

if ( !empty($_POST['dateFrom']) && !empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}' AND stock_date <= '{$_POST['dateTo']}'";
}elseif( isset($_POST['dateFrom']) && empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}'";
}elseif ( isset($_POST['dateTo']) && empty($_POST['dateFrom']) ) {
  $strSql .= " WHERE  stock_date <= '{$_POST['dateTo']}'";
}

$strSql .= " GROUP BY stock_date) as sum_stock";

$strSql .= " ORDER BY stock_date ASC";

$res = $db->query($strSql);
$res = $db->fetchAll($res);

$avgData['all_stock'] = round($res[0]['avg']);

$strSql = "SELECT avg(avg_stock) as avg FROM (SELECT stock_date, sum(stock) as avg_stock FROM wb_fbo_stat_{$_POST['cabinet']}";

if ( !empty($_POST['dateFrom']) && !empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}' AND stock_date <= '{$_POST['dateTo']}'";
}elseif( isset($_POST['dateFrom']) && empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}'";
}elseif ( isset($_POST['dateTo']) && empty($_POST['dateFrom']) ) {
  $strSql .= " WHERE  stock_date <= '{$_POST['dateTo']}'";
}

$strSql .= " GROUP BY stock_date) as sum_stock";

$strSql .= " ORDER BY stock_date ASC";

$res = $db->query($strSql);
$res = $db->fetchAll($res);

$avgData['stock_stock'] = round($res[0]['avg']);

$strSql = "SELECT avg(avg_stock) as avg FROM (SELECT stock_date, sum( (stock + from_client + to_client) * cost ) as avg_stock FROM wb_fbo_stat_{$_POST['cabinet']}";

if ( !empty($_POST['dateFrom']) && !empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}' AND stock_date <= '{$_POST['dateTo']}'";
}elseif( isset($_POST['dateFrom']) && empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}'";
}elseif ( isset($_POST['dateTo']) && empty($_POST['dateFrom']) ) {
  $strSql .= " WHERE  stock_date <= '{$_POST['dateTo']}'";
}

$strSql .= " GROUP BY stock_date) as sum_stock";

$strSql .= " ORDER BY stock_date ASC";

$res = $db->query($strSql);
$res = $db->fetchAll($res);

$avgData['all_cost'] = round($res[0]['avg']);

$strSql = "SELECT avg(avg_stock) as avg FROM (SELECT stock_date, sum( stock * cost ) as avg_stock FROM wb_fbo_stat_{$_POST['cabinet']}";

if ( !empty($_POST['dateFrom']) && !empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}' AND stock_date <= '{$_POST['dateTo']}'";
}elseif( isset($_POST['dateFrom']) && empty($_POST['dateTo']) ){
  $strSql .= " WHERE stock_date >= '{$_POST['dateFrom']}'";
}elseif ( isset($_POST['dateTo']) && empty($_POST['dateFrom']) ) {
  $strSql .= " WHERE  stock_date <= '{$_POST['dateTo']}'";
}

$strSql .= " GROUP BY stock_date) as sum_stock";

$strSql .= " ORDER BY stock_date ASC";

$res = $db->query($strSql);
$res = $db->fetchAll($res);

$avgData['stock_cost'] = round($res[0]['avg']);

$result = [
  'stockDynamic' => $stockDynamic,
  'chartData' => $chartData,
  'modelAssort' => $modelAssort,
  'avgData' => $avgData,
];

echo json_encode( $result );
 ?>
