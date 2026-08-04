<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ){
  die('<span class="btn btn-danger">Форма пуста</span>');
}

CModule::IncludeModule("panel.manager");
$dbPanel = new DBPanel;

$data = $_POST;
$models = array_keys( $data );

$modelsPrep = array_map( function($item){
  return "'" . $item . "'";
}, $models );
$modelsStr = implode( ',', $modelsPrep );

$strSql = "SELECT * FROM ozon_model_collection WHERE model IN ({$modelsStr})";
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );

$modelsDB = [];
foreach ( $rows as $row ){
  $modelsDB[ $row['model'] ] = $row['code'];
}

$modelsForImport = [];
$modelsForUpdate = [];

foreach ( $data as $model => $code ){
  if ( isset( $modelsDB[$model] ) && $modelsDB[ $model ] != $code && !empty($code) ){
    $modelsForUpdate[ $model ] = $code;
  }
}

if ( !empty($modelsForUpdate) ){
  $arWhere[] = [
    'column' => 'model',
    'operator' => '=',
    'value' => ''
  ];

  $aff_rows = 0;
  foreach ($modelsForUpdate as $model => $code) {
    $arWhere[0]['value'] = $model;
    $dbPanel->update( 'ozon_model_collection', ['code' => $code], $arWhere );
    $aff_rows += $dbPanel->affectedRows;
  }
  echo "<span class='message-complete'>Выполнено! Изменено строк: {$aff_rows}</span>";
}




 ?>
