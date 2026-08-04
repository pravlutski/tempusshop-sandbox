<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
$dbPanel = new DBPanel;

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$options = $_POST;
$day = $_POST['date'];

$flag = false;
$strSql = "SELECT 1 FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$day}'";
$res = $dbPanel->query( $strSql );
if ( count( $dbPanel->fetchAll($res) ) == 0 ){
  $flag = true;
  $day = date( 'Y-m-d', strtotime($day . ' - 1 day') );
}

$dayBefore = date('Y-m-d',strtotime($day . ' - 1 day' ));
$strSql = "SELECT DISTINCT stock_date FROM wb_fbo_stat_{$_POST['cabinet']}";
$resultDB = $dbPanel->query($strSql, false, $err_mess.__LINE__);
$data = $dbPanel->fetchAll($resultDB);
foreach ( $data as $row ){
  $dates[$row['stock_date']] = 1;
}
if ( empty($dates[$day]) ){
  die('<span>Нет данных за сегодня</span>');
}
$strSql = "SELECT * FROM wb_fbo_stat_{$_POST['cabinet']} WHERE ";
foreach ($options as $key => $option) {
  switch ($key) {
    case 'stock_date':
     if ($option != ''){
       $strSql .= "{$key} = '{$option}'";
     }else{
       die('Дата - обязательный параметр');
     }
    break;
  }
}
$strSql .= " AND model NOT IN (SELECT model FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$dayBefore}')";

$strSql = "SELECT model, nmid, stock, cost
FROM wb_fbo_stat_{$_POST['cabinet']}
WHERE stock_date = '{$day}'
    AND model NOT IN (SELECT model FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$dayBefore}')
UNION
SELECT td.model, td.nmid, td.stock - yd.stock as stock, td.cost
FROM wb_fbo_stat_{$_POST['cabinet']} AS td
JOIN (SELECT model, nmid, stock, cost FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.stock_date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $dbPanel->query($strSql, false, $err_mess.__LINE__);
$data = $dbPanel->fetchAll($resultDB);
$goodsAdd = [];
$sumAdd = 0;
$sumAddStock = 0;
foreach ( $data as $row ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsAdd[] = $row;
  $sumAdd += $row['cost'] * $row['stock'];
  $sumAddStock += $row['stock'];
}
// $strSql = "SELECT * FROM ozon_stock_fbo_stat_ti WHERE date = '{$dayBefore}' AND sku NOT IN (SELECT sku FROM auto_adv_wb_stat WHERE date = '{$day}')";
$strSql = "SELECT model, nmid, stock, cost
FROM wb_fbo_stat_{$_POST['cabinet']}
WHERE stock_date = '{$dayBefore}'
    AND model NOT IN (SELECT model FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$day}')
UNION
SELECT td.model, td.nmid, yd.stock - td.stock as stock, td.cost
FROM wb_fbo_stat_{$_POST['cabinet']} AS td
JOIN (SELECT model, nmid, stock, cost FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.stock_date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $dbPanel->query($strSql, false, $err_mess.__LINE__);
$data = $dbPanel->fetchAll($resultDB);
$goodsSold = [];
$sumSold = 0;
$sumSoldStock = 0;
foreach( $data as $row ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsSold[] = $row;
  $sumSold += $row['cost'] * $row['stock'];
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
      <th>Количество, шт.</th>
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
    <td><?=$card['cost']?></td>
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
      <th>Количество, шт.</th>
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
    <td><?=$card['cost']?></td>
    <? if ($key == 0) echo "<td>".number_format($sumSold, 0, '', ' ')."</td>";?>
  </tr>
<?php endforeach;?>
  </tbody>
</table>
</details>

<?
$result = $dbPanel->query("SELECT * FROM wb_top_models");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops[] = $row['model'];
}

$day = isset($day) ? $day : date('Y-m-d');
$strSql = "SELECT * FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$day}'";
$res = $dbPanel->Query($strSql);
$rows = $dbPanel->fetchAll($res);
$stock = [];
foreach( $rows as $row ){
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

foreach ( $topChunks as $lim ){
  for ( $i = 0; $i < $lim; $i++ ){
    if ( isset($tops[$i]) && isset($stock[$tops[$i]]) && $stock[$tops[$i]] > 0 ){
      $topStat['top_' . $lim] += 1;
    }
  }
}
$topName = [
  'top_10' => 'Топ 10',
  'top_50' => 'Топ 50',
  'top_100' => 'Топ 100',
  'top_200' => 'Топ 200',
  'top_309' => 'Топ 309',
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
