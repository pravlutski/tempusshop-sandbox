<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

if ( empty($_POST) ) throw new Exception("Empty post data");

$data = $_POST;

$update = [];
$summ = 0;
foreach ( $data as $id => $row ){
  $value = str_replace(',', '.', $row);
  $update[] = [
    'id' => $id,
    'coefficient' => floatval($value)
  ];

  $summ += floatval($value);
}
if ( round($summ, 3) != 1 ){
  echo json_encode(['status' => "Сумма коэффициентов не равна 1 ({$summ})"]);
  die;
}
die('Пошел дальше сохранять');
$settings = new SettingsManager('IP');
$settings->updateCoefficientsTable( $update );

 ?>
