<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$options = $_POST;
$day = $_POST['date'];

$flag = false;
$strSql = "SELECT 1 FROM ozon_stock_fbo_stat_ti WHERE date = '{$day}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
if ( $resultDB->SelectedRowsCount() == 0 ){
  $flag = true;
  $day = date( 'Y-m-d',strtotime($day . ' - 1 day') );
}

$dayBefore = date('Y-m-d',strtotime($day . ' - 1 day' ));
$strSql = "SELECT DISTINCT date FROM ozon_stock_fbo_stat_ti";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);

while ( $row = $resultDB->Fetch() ){
  $dates[$row['date']] = 1;
}
if ( empty($dates[$day]) ){
  die('<span>Нет данных за сегодня</span>');
}
$strSql = "SELECT * FROM ozon_stock_fbo_stat_ti WHERE ";
foreach ($options as $key => $option) {
  switch ($key) {
    case 'date':
     if ($option != ''){
       $strSql .= "{$key} = '{$option}'";
     }else{
       die('Дата - обязательный параметр');
     }
    break;
  }
}
$strSql .= " AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}')";

$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat_ti
WHERE date = '{$day}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}')
UNION
SELECT td.model, td.sku, td.stock - yd.stock as stock, td.price
FROM ozon_stock_fbo_stat_ti AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$goodsAdd = [];
$sumAdd = 0;
$sumAddStock = 0;
while ( $row = $resultDB->Fetch() ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsAdd[] = $row;
  $sumAdd += $row['price'] * $row['stock'];
  $sumAddStock += $row['stock'];
}
// $strSql = "SELECT * FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}' AND sku NOT IN (SELECT sku FROM auto_adv_wb_stat WHERE date = '{$day}')";
$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat_ti
WHERE date = '{$dayBefore}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat_ti WHERE date = '{$day}')
UNION
SELECT td.model, td.sku, yd.stock - td.stock as stock, td.price
FROM ozon_stock_fbo_stat_ti AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$goodsSold = [];
$sumSold = 0;
$sumSoldStock = 0;
while( $row = $resultDB->Fetch() ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsSold[] = $row;
  $sumSold += $row['price'] * $row['stock'];
  $sumSoldStock += $row['stock'];
}
if ( $flag ) {
  echo '<span style="color:rgba(0,0,0,0.5)"><i>Нет данных на указанную дату. Отображены данные за предыдущий день.</span></i><br><br>';
}
?>
<details class="flex flex-col p-2 cursor-pointer">
  <summary><b>Добавлено (<?echo $sumAddStock;?>)</b></summary>
<table class="table table-striped text-left mt-2 w-100">
  <thead>
    <tr>
      <th>Модель (<?echo count($goodsAdd);?>)</th>
      <th>Остаток, шт.</th>
      <th>Себестоимость, ₽</th>
      <th>Сумма, ₽</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ( $goodsAdd as $key => $card ):?>
  <tr>
    <td><?=$card['model']?></td>
    <td><?=$card['stock']?></td>
    <td><?=$card['price']?></td>
    <? if ($key == 0) echo "<td>".number_format($sumAdd, 0, '', ' ')."</td>";?>
  </tr>
<?php endforeach;?>
  </tbody>
</table>
</details>

<details class="flex flex-col p-2 cursor-pointer">
  <summary><b>Продано (<?echo $sumSoldStock;?>)</b></summary>
<table class="table table-striped w-100 text-left mt-2">
  <thead>
    <tr>
      <th>Модель(<?echo count($goodsSold);?>)</th>
      <th>Остаток, шт.</th>
      <th>Себестоимость, ₽</th>
      <th>Сумма, ₽</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ( $goodsSold as $key => $card ):?>
  <tr>
    <td><?=$card['model']?></td>
    <td><?=$card['stock']?></td>
    <td><?=$card['price']?></td>
    <? if ($key == 0) echo "<td>".number_format($sumSold, 0, '', ' ')."</td>";?>
  </tr>
<?php endforeach;?>
  </tbody>
</table>
</details>

<?
CModule::includeModule('panel.manager');
$dbPanel = new DBPanel;

$result = $dbPanel->query("SELECT * FROM ozon_top_models");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops[] = $row['model'];
}

$day = isset($day) ? $day : date('Y-m-d');
$strSql = "SELECT * FROM ozon_stock_fbo_stat_ti WHERE date = '{$day}'";
$res = $DB->Query($strSql, false, $err_mess.__LINE__);
$stock = [];
while( $row = $res->Fetch() ){
  $stock[$row['model']] = $row['stock'];
}

$topChunks = [10,50,100,200];
$topStat = [
  'top_10' => 0,
  'top_50' => 0,
  'top_100' => 0,
  'top_200' => 0,
  // 'top_309' => 0,
];

$topCheck = [
  'top_10' => [],
  'top_50' => [],
  'top_100' => [],
  'top_200' => [],
];

foreach ( $topChunks as $lim ){
  for ( $i = 0; $i < $lim; $i++ ){
    if ( isset($tops[$i]) && isset($stock[$tops[$i]]) && $stock[$tops[$i]] > 0 ){
      $topStat['top_' . $lim] += 1;
      $topCheck['top_' . $lim][] = $tops[$i];
    }
  }
}
$topName = [
  'top_10' => 'Топ 10',
  'top_50' => 'Топ 50',
  'top_100' => 'Топ 100',
  'top_200' => 'Топ 200',
  // 'top_309' => 'Топ 309',
];
?>
<div class="top-stat-block" style="padding-top: 20px; width:50%">
  <table class="table table-striped">
    <thead>
      <th>В наличии</th>
      <th></th>
    </thead>
    <tbody>
      <? foreach ( $topStat as $key => $val): ?>
      <tr>
        <td><?=$topName[$key]?></td>
        <td><?=$val?></td>
      </tr>
    <? endforeach; ?>
  </tbody>
</table>
</div>
