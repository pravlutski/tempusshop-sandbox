<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}

$panel = new DBPanel;
$platform = $_POST['platform'];

$update = [
  'activity' => $_POST['activity'] == 'on' ? 1 : 0,
  'store' => $_POST['store'] ? implode('|', $_POST['store']) : null,
];

$strSql = "UPDATE am_mp_settings SET activity = %s, store = %s WHERE platform = '{$platform}'";
$strSql = sprintf(
  $strSql,
  $update['activity'],
  $update['store'] ? "'{$update['store']}'" : 'NULL'
);

$panel->query( $strSql );

 ?>
