<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;
$mode = $_POST['mode'];
// $date = $_POST['control-date'];
$date = date('Y-m-d');
// sleep(10);
if ( $mode == 'parser'){
  $processor = $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/analytics/importCompetitorsData.php';
  $fileName = '/home/bitrix/python/price_'.date( 'd.m.Y', strtotime($date) ).'.php';
  if ( !file_exists( $fileName ) ){
    die('<span style="background:rgba(255,0,0,0.5); color:white; padding: 7px; border-radius: 7px; margin-top: 40px; display:block; width: fit-content"><b>Ошибка:</b> не получен файл парсинга</span>');
  }
  $strSql = "DELETE FROM ozon_competitors WHERE date = '{$date}'";
  // var_dump( $strSql );
  $dbPanel->query( $strSql );
  try{
    // $penis = 1 / 0;
    require( $processor );
  }
  catch( Throwable $e ){
    die('<span style="background:rgba(255,0,0,0.5); color:white; padding: 7px; border-radius: 7px; margin-top: 40px; display:block; width: fit-content"><b>Ошибка обработчика файла:</b><br>'.$e.'</span><br>');
  }
  die('<span style="background:rgba(0,200,0,0.5); color:white; padding: 7px; border-radius: 7px; margin-top: 40px; display:block; width: fit-content"><b>Импорт завершен успешно</b></span>');
}

 ?>
