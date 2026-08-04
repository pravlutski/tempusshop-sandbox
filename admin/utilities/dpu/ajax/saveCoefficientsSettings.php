<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

if ( empty($_POST) ) throw new Exception("Empty post data");
if ( empty($_POST['marketplace']) || empty($_POST['cabinet']) ) throw new Exception("Empty marketplace or cabinet");

$marketplace = $_POST['marketplace'];
$cabinet = $_POST['cabinet'];
unset( $_POST['cabinet'] );
unset( $_POST['marketplace'] );

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

$settings = new CRUDManager( $marketplace, $cabinet );
$settings->updateCoefficientsSettings( $update );

echo json_encode(['status' => "ok"]);
 ?>
