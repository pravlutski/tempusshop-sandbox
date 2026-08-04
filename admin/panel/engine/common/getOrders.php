<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

function getSuppliersNames( $db )
{
  $strSql = "SELECT id, name FROM ci_suppliers";
  $rows = $db->query( $strSql );
  $result = [];

  while( $row = $rows->fetch() ){
    $result[ $row['id'] ] = $row['name'];
  }
  $result[ 0 ] = 'ФБО';

  return $result;
}

function validateDate( $dateString, $format = 'Y-m-d' )
{
    $dateObj = DateTime::createFromFormat($format, $dateString);
    return $dateObj && $dateObj->format($format) === $dateString;
}

$token = "91e9a9cab33441e1b7b29994d4a489af08ddeb6ca1247cd9f190e3ac2a791492";

if ( $_SERVER['HTTP_X_KEY'] !== $token ) {
  header('HTTP/1.1 403 Forbidden');
  die;
}

if ( empty($_GET['dateFrom']) || empty($_GET['dateTo']) ){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(['error' => 'OneOfParametersMissing']);
  die();
}

if ( !validateDate($_GET['dateFrom']) || !validateDate($_GET['dateFrom']) ){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(['error' => 'invalidDateFormat']);
  die();
}

$strSql = "SELECT * FROM wdhs_ozon_orders AS ord
JOIN wdhs_ozon_order_products AS prod
ON ord.posting_number = prod.posting_number";

if ( !empty($_GET['dateFrom']) ){

  $dateFrom = $_GET['dateFrom'];
  $strSql .= " AND ord.in_process_at >= '{$dateFrom}'";
}
if ( !empty($_GET['dateTo']) ){
  $dateTo = $_GET['dateTo'];
  $strSql .= " AND ord.in_process_at <= '{$dateTo}'";
}

$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$suppliers = getSuppliersNames( $DB );

while ( $row = $result->Fetch() ){
  if ( $row['delivery_type'] == 'fbo' ){
    $supplierName = 'ФБО';
  }else{
    $supplierName = $suppliers[ $row['supplier'] ];
  }
  $exportData[] = [
    $row['order_bid'],
    $row['order_id'],
    $row['in_process_at'],
    $row['delivery_type'],
    $row['status'],
    // $row['cabinet'],
    $row['vendor_code'],
    $row['name'],
    $row['quantity'],
    $row['price'],
    $row['cost'],
    $row['posting_number'],
    $supplierName,
    $row['base_price'],
    $row['saleName'],
    // $row['warehouse_id'],
    // $row['bitrix_id'],
    // $row['nmid'],
  ];
}
// var_dump($exportData);
// die;
unset($row);

$header = [
  'order_bid',
  'order_id',
  'in_proceess_at',
  'delivery_type',
  'status',
  // 'cabinet',
  'vendor_code',
  'name',
  'quantity',
  'price',
  'cost',
  'posting_number',
  'supplier',
  'base_price',
  'saleName',
];
$alphabet = range('A','Z');

$link = "/admin/panel/ozon/export/orders_IP.csv";
$filename = $_SERVER['DOCUMENT_ROOT'] . $link;

$output = fopen($filename,'w');

fputcsv( $output, $header );

foreach ( $exportData as $row ){
  fputcsv( $output, $row );
}

fclose($output);

echo json_encode(['link' => "https://tempusshop.ru" . $link]);
 ?>
