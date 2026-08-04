<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

$settings = new CRUDManager(
  mp: $_POST['marketplace'],
  cab: $_POST['cabinet']
);
$settings->clearItemsList();
 ?>
