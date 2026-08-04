<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

if ( empty($_POST) ) throw new Exception("Empty Data");
if ( empty($_POST['marketplace']) || empty($_POST['cabinet']) ) throw new Exception("Empty marketplace or cabinet");

$marketplace = $_POST['marketplace'];
$cabinet = $_POST['cabinet'];
unset( $_POST['cabinet'] );
unset( $_POST['marketplace'] );

$data = $_POST;
$update = [];

foreach ( $data as $key => $row )
{
  list($cab, $field) = explode( '|', $key );
  if ( empty($row) and $row !== '0' ) continue;
  $update[$field] = $row;
}

if ( empty($update) ) throw new Exception("Nothing to update");

$settings = new CRUDManager( $marketplace, $cabinet );
$settings->updateDefaultSettings( $update );
 ?>
