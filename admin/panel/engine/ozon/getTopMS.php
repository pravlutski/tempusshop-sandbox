<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_ozon_getTopMS_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$arStat = [
  'status' => 'INCOMPLETE',
  'percent' => '30',
  'status_text' => 'Получение отчета из МС',
  'time_start' => date('Y.m.d G:i:s')
];
updateStatus('msTop', $arStat, $dbPanel);

$ms = new MoyskladAPI('s1');
try{
  $ms->getListProfitByAgent( 0, false, ['agent'=>'3268f56e-3595-11f0-0a80-03f70028a80c'] );
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
print_r(count($msPosition));

if ( empty($msPosition) ){
  $arStat = [
    'status' => 'ERROR',
    'percent' => '100',
    'status_text' => 'Пустой ответ от МС',
    'time_end' => date('Y.m.d G:i:s')
  ];
  updateStatus('msTop', $arStat, $dbPanel);
  die;
}

$arStat = [
  'percent' => '60',
  'status_text' => 'Обработка ответа от МС',
];
updateStatus('msTop', $arStat, $dbPanel);

// $msPosition = array_slice($msPosition, 0, 200);

// пишем в бд
$arStat = [
  'percent' => '90',
  'status_text' => 'Обновление таблицы',
];
updateStatus('msTop', $arStat, $dbPanel);


foreach ( $msPosition as $key => $val ){
  if (intval($val['COUNT']) < 4) {
    unset($msPosition[$key]);
  }
}


$allProf = 0;
foreach ( $msPosition as $key => $val ){
  $allProf = $allProf + $val['PROFIT'];
}



foreach ( $msPosition as $key => $val ){
  $proc = round(($val['PROFIT'] / $allProf) * 100 , 2 ,PHP_ROUND_HALF_UP);
  if ($proc < 0.15) {
    unset($msPosition[$key]);
  } else {
    $msPosition[$key]['PROC'] = $proc;
  }
}
$msPosition = array_values($msPosition);



usort($msPosition, function($a, $b) {
  return $b['PROC'] <=> $a['PROC'];
});



$strSql = "TRUNCATE TABLE ozon_top_models";
$dbPanel->query( $strSql );

$data = [];
foreach ( $msPosition as $elem ){
  $data[] = [
      'model' => $elem['ARTICLE'],
      'sellSum' => $elem['PROFIT'],
      'date' => date('Y-m-d G:i:s')
  ];
}

try{
  fuckYouBitrixORM('ozon_top_models', $data );
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
  'status' => 'COMPLETE',
  'percent' => '100',
  'status_text' => 'Завершено',
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
  $strSql = "UPDATE ozon_agents SET ";
  foreach ($arStat as $field => $value) {
    if ( array_key_last($arStat) == $field ){
      $str = "{$field} = '{$value}'";
    }else{
      $str = "{$field} = '{$value}', ";
    }
    $strSql .= $str;
  }
  $strSql .= " WHERE code = '{$code}'";
  $db->query( $strSql );
}

$workers->updateStatus("N");
 ?>
