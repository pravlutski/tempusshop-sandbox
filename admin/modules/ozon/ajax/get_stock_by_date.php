<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$options = $_POST;
$day = $_POST['date'];
$dayBefore = date('Y-m-d',strtotime($_POST['date'] . ' - 1 day' ));
$strSql = "SELECT DISTINCT date FROM ozon_stock_fbo_stat";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
while ( $row = $resultDB->Fetch() ){
  $dates[$row['date']] = 1;
}
if ( empty($dates[$day]) ){
  die('<span>Нет данных за сегодня</span>');
}
$strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE ";
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
$strSql .= " AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}')";

$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat
WHERE date = '{$day}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}')
UNION
SELECT td.model, td.sku, td.stock - yd.stock as stock, td.price
FROM ozon_stock_fbo_stat AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}') as yd
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
// $strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}' AND sku NOT IN (SELECT sku FROM auto_adv_wb_stat WHERE date = '{$day}')";
$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat
WHERE date = '{$dayBefore}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$day}')
UNION
SELECT td.model, td.sku, yd.stock - td.stock as stock, td.price
FROM ozon_stock_fbo_stat AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}') as yd
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
