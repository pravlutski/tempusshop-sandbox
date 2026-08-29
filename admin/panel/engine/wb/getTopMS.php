<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$arStat = [
  'status' => 'IN_PROCESS',
  'percent' => '30',
  'status_text' => 'Получение отчета из МС',
  'time_start' => date('Y.m.d G:i:s')
];
updateStatus('msTop', $arStat, $dbPanel);

$ms = new MoyskladAPI('s1');
try{
  $ms->getListProfitByAgent( 0, false, ['agent'=>'dd5b00b5-2a6e-11ec-0a80-019e000baf07'] );
}
catch( Throwable $e){
  $arStat = [
    'status' => 'ERROR',
    'percent' => '100',
    'status_text' => 'Ошибка в API МойСклад',
    'time_end' => date('Y.m.d G:i:s')
  ];
  updateStatus('msTop', $arStat, $dbPanel);
  die;
}
$msPosition = array_values($ms->MSPosition);
if ( empty($msPosition) ){
  $arStat = [
    'status' => 'ERROR',
    'percent' => '100',
    'status_text' => 'Пустой ответ от МС',
    'time_end' => date('Y.m.d G:i:s')
  ];
  updateStatus('msTop', $arStat, $dbPanel);
}

$arStat = [
  'percent' => '60',
  'status_text' => 'Обработка ответа от МС',
];
updateStatus('msTop', $arStat, $dbPanel);
usort($msPosition, function($a, $b) {
  return $b['SELLSUM'] <=> $a['SELLSUM'];
});

$msPosition = array_slice($msPosition, 0, 200);

// пишем в бд
$arStat = [
  'percent' => '90',
  'status_text' => 'Обновление таблицы',
];
updateStatus('msTop', $arStat, $dbPanel);

$strSql = "TRUNCATE TABLE wb_top_models";
try{
  $dbPanel->query( $strSql );
}catch( Throwable $e ){
  $arStat = [
    'status' => 'ERROR',
    'percent' => '100',
    'status_text' => 'Ошибка очистки таблицы',
    'time_end' => date('Y.m.d G:i:s')
  ];
  updateStatus('msTop', $arStat, $dbPanel);
}

$data = [];
foreach ( $msPosition as $elem ){
  $data[] = [
      'model' => $elem['ARTICLE'],
      'sellSum' => $elem['SELLSUM'],
      'date' => date('Y-m-d G:i:s')
  ];
}
try{
  fuckYouBitrixORM('wb_top_models', $data );
}
catch( Throwable $e ){
  $arStat = [
    'status' => 'ERROR',
    'percent' => '100',
    'status_text' => 'Ошибка импорта',
    'time_end' => date('Y.m.d G:i:s')
  ];
  updateStatus('msTop', $arStat, $dbPanel);
  die( 'Критическая ошибка: таблица очищена, данные не были импортированы. ' . $e );
}

$arStat = [
  'status' => 'COMPLETED',
  'percent' => '100',
  'status_text' => 'Выполнено',
  'time_end' => date('Y.m.d G:i:s')
];
updateStatus('msTop', $arStat, $dbPanel);


function fuckYouBitrixORM($tableName , $arrayData)
{
  $dbPanel = new DBPanel;

  $cardSample = $arrayData[0];
  $fields = [];
  foreach ($cardSample as $key => $value) {
    $fields[] = $key;
  }
  if (empty($fields) || count($fields) < 2) return false;
  $strSql = "INSERT INTO {$tableName} " . '(';

  $i = 0;
  foreach ($fields as $fname) {
    $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
    $i++;
  }
  $strSql .= ') VALUES ';
  $c = 0;
  foreach ($arrayData as $card){
    $strSql .= '(';
    $k = 0;
    foreach ($card as $field) {
      $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
      $k++;
    }
    $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
    $c++;
  }
  $dbPanel->query( $strSql );
}

function updateStatus( string $code, array $arStat, $db ):void
{
  if ( empty($arStat) ) return;
  $strSql = "UPDATE wb_agents SET ";
  foreach ($arStat as $field => $value) {
    if ( array_key_last($arStat) == $field ){
      $str = "{$field} = '{$value}'";
    }else{
      $str = "{$field} = '{$value}', ";
    }
    $strSql .= $str;
  }
  $strSql .= " WHERE code = '{$code}'";
  try{
    $db->query( $strSql );
  }catch( Throwable $ignored){
  }
}

 ?>
