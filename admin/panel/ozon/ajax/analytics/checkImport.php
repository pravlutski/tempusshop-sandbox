<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;
$date = empty($_POST['control-date']) ? false : $_POST['control-date'];

if ( ! $date ){
  $date = date('Y-m-d');
}

$arControlNames = [
  'main' => 'Главный модуль',
  'fbo' => 'ФБО',
  'parser' => 'Парсер'
];
$arControlCodes = [
  1404 => '<span style="rgba(255,0,0,0.5)">Нет данных</span>',
  2404 => '<span style="rgba(255,0,0,0.5)">Нет данных</span>',
  3404 => '<span style="color:rgba(255,0,0,0.5)">Не получен файл</span>',
  3500 => '<span style="color:rgba(255,0,0,0.5)">Ошибка импорта / Несовпадение таймингов</span>',
];

$arControl = [
  'main' => 0,
  'fbo' => 0,
  'parser' => 0
];


// Главный модуль
$strSql = "SELECT * FROM ozon_top_analytics WHERE date = '{$date}'";
$rows = [];
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );
if ( count($rows) == 0 ){
  $arControl['main'] = '1404';
}
unset($res);
unset( $rows );

// Парсер
$rows = [];
$checkErros = 0;
$fileName = '/home/bitrix/python/price_' . date( 'd.m.Y', strtotime($date) ) . '.csv';
$strSql = "SELECT * FROM ozon_competitors WHERE date = '{$date}' AND seller = 'TEMPUS - наручные часы'";
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );
if ( count($rows) == 0 ){
  if ( file_exists($fileName) ){
    $arControl['parser'] = 3500;
    $moduleStatus['parser'] = '<span style="color:rgba(255,0,0,0.7)">Ошибка</span>';
  }else{
    $arControl['parser'] = 3404;
    $moduleStatus['parser'] = '<span style="color:rgba(255,0,0,0.7)">Ошибка</span>';
  }
}else{
  foreach ($rows as $row) {
    if ( $row['b_price'] == 0 && $row['g_price'] == 0 ){
      $checkErros += 1;
    }
  }
  $perecentError = round( $checkErros / count($rows) * 100);
  $action = $perecentError > 15 ? '<span style="color:rgba(255,0,0,0.5)">Требуется обновление</span>' : '<span           style="color:rgba(0,200,0,0.7)">не требуется</span>';
  $status['parser'] = '<span style="color:rgba(0,200,0,0.7)">Выполнено</span>';
}

unset($res);
unset($rows);
//ФБО
global $DB;
$strSql = "SELECT * FROM ozon_stock_fbo_stat_ti WHERE date = '{$date}'";
$res = $DB->Query( $strSql, false, $err_mess.__LINE__ );
if ( $res->SelectedRowsCount() == 0 ){
  $arControl['fbo'] = 2404;
}else{
  $status['fbo'] = '<span style="color:rgba(0,200,0,0.7)">Выполнено</span>';
}
$arTime = [
  'main' => '10:05:00',
  'parser' => '11:35:00',
  'fbo' => '10:30:00'
];
$arNextStart = [];
$moduleStatus = [];
foreach ( $arTime as $module => $time ){
  $curTime = strtotime( date('Y-m-d G:i:s') );
  $startTime = strtotime( date('Y-m-d ' . $time) );
  if ( $curTime < $startTime ){
    $arNextStart[$module] = strtotime( date('Y-m-d ' . $time) );
    $moduleStatus[$module] = '<span style="color:rgba(200,200,0,0.7)">Ожидание</span>';
  }else{
    $arNextStart[$module] = strtotime( date( 'Y-m-d ' . $time, strtotime(' + 1 day') ) );
  }
  $arTime[$module] = strtotime( date('Y-m-d ' . $time) );
}

 ?>
<? foreach( $arControl as $module => $status): ?>
  <div class="status-block">
    <h4><?=$arControlNames[$module]?></h4>
    <div class="status-body">
      <? if ($module == 'parser'): ?>
        <? if ( $status == 0 ): ?>
        <div class="si-block">
          <div class="s-row"><b>Статус:</b><?=empty($status) ? '<span style="color:rgba(0,200,0,0.7)">Выполнено</span>' : $arControlCodes[$status]?></div>
          <div class="s-row"><b>Нули, %:</b> <?=$perecentError?></div>
          <div class="s-row"><b>Действие:</b><? echo $action; ?></div>
          <? if ($date == date('Y-m-d') ):?>
            <div class="s-row"><b>След. запуск:</b><? echo date('Y.m.d G:i:s',$arNextStart[$module]); ?></div>
          <? endif;?>
        </div>
        <div class="c-block">
          <? if ( $perecentError > 15 && $date == date('Y-m-d') ): ?>
            <button class="btn btn-warning update-data" value="<?=$module?>">Обновить</button>
          <? endif; ?>
        </div>
        <? else:?>
        <div class="si-block">
          <div class="s-row"><b>Статус:</b><?=empty($status) ? '<span style="color:rgba(0,200,0,0.7)">Выполнено</span>' : $arControlCodes[$status]?></div>
          <? if ($date == date('Y-m-d') ):?>
            <div class="s-row"><b>След. запуск:</b><? echo date('Y.m.d G:i:s',$arNextStart[$module]); ?></div>
          <? endif;?>
        </div>
        <div class="c-block">
          <? if ( $arTime[$module] < time() && $status != 3404 ): ?>
            <button class="btn btn-warning update-data" name="mode" value="<?=$module?>">Обновить</button>
          <? endif; ?>
        </div>
        <? endif;?>
      <? else:?>
      <? if ( $status == 0 ): ?>
      <div class="si-block">
        <div class="s-row"><b>Статус:</b><?=empty($status) ? '<span style="color:rgba(0,200,0,0.7)">Выполнено</span>' : $arControlCodes[$status]?></div>
        <? if ($date == date('Y-m-d') ):?>
          <div class="s-row"><b>След. запуск:</b><? echo date('Y.m.d G:i:s',$arNextStart[$module]); ?></div>
        <? endif;?>
      </div>
        <? else:?>
        <div class="si-block">
          <div class="s-row"><b>Статус:</b><?=empty($status) ? 'Выполнено' : $arControlCodes[$status]?></div>
          <? if ($date == date('Y-m-d') ):?>
            <div class="s-row"><b>След. запуск:</b><? echo date('Y.m.d G:i:s',$arNextStart[$module]); ?></div>
          <? endif;?>
        </div>
        <div class="c-block">
          <button class="btn btn-warning update-data" value="<?=$module?>">Обновить</button>
        </div>
      <? endif;?>
      <? endif; ?>
    </div>
  </div>
<? endforeach; ?>
