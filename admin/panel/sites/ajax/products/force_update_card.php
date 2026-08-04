<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ){
  die('Empty Post');
}

$models = explode( PHP_EOL, $_POST['models'] );
$is_info = $_POST['is-info'] ?? false;
$is_image = $_POST['is-image'] ?? false;
$cabinet = $_POST['cabinet'];

var_dump( $models );
var_dump( $is_info );
var_dump( $is_image );
var_dump( $cabinet );


 ?>
