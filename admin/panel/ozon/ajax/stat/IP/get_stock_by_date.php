<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$options = $_POST;
$day = $_POST['date'];


$tmp = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/ajax/stat/IP/selected_warehouses.txt");

$active_warehouses = [];
if (!empty($tmp)) {
  $active_warehouses = explode(', ' ,$tmp);
}


$strSql = "SELECT model,warehouse_name FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$warehouses = [];
while ( $row = $resultDB->Fetch() ){
  if (!empty($row['warehouse_name']) && $row['warehouse_name'] != 'null') {
    $tmpWh = json_decode($row['warehouse_name'],true);
    $warehouses[$row['model']] = 0;

    foreach ($tmpWh as $key => $value) {
      $warehousesTemplate[$key] = $key;
      if (in_array($key,$active_warehouses)) {
        $warehouses[$row['model']] = $warehouses[$row['model']] + $value;
      }
    }
  }
}





$flag = false;
$strSql = "SELECT 1 FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
if ( $resultDB->SelectedRowsCount() == 0 ){
  $flag = true;
  $day = date( 'Y-m-d',strtotime($day . ' - 1 day') );
}

$dayBefore = date('Y-m-d',strtotime($day . ' - 1 day' ));
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
$strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$res = $DB->Query($strSql, false, $err_mess.__LINE__);
$stock = [];
while( $row = $res->Fetch() ){
  $stock[$row['model']] = ['stock' => $row['stock'], 'wh' => $row['warehouse_name']];
}

$topChunks = [10,50,100,200];
$topStat = [
  'top_10' => 0,
  'top_50' => 0,
  'top_100' => 0,
  'top_200' => 0,
  // 'top_309' => 0,
];

$topStatMpscow = [
  'top_10' => 0,
  'top_50' => 0,
  'top_100' => 0,
  'top_200' => 0,
];

$topCheck = [
  'top_10' => [],
  'top_50' => [],
  'top_100' => [],
  'top_200' => [],
];
$arDebug = [];
foreach ( $topChunks as $lim ){
  for ( $i = 0; $i < $lim; $i++ ){
    if ( isset($tops[$i]) && isset($stock[$tops[$i]]) && $stock[$tops[$i]]['stock'] > 0 ){
      $topStat['top_' . $lim] += 1;
      $topCheck['top_' . $lim][] = $tops[$i];
      if (isset($warehouses[$tops[$i]]) && intval($warehouses[$tops[$i]] != 0)) {
        $topStatMpscow['top_' . $lim] += 1;
        $arDebug['MSC_top_' . $lim][$tops[$i]] = intval($warehouses[$tops[$i]]);
      }
    }
  }
}

// print_r($arDebug);
$topName = [
  'top_10' => 'Топ 10',
  'top_50' => 'Топ 50',
  'top_100' => 'Топ 100',
  'top_200' => 'Топ 200',
  // 'top_309' => 'Топ 309',
];
?>
<div class="top-stat-block" style="padding-top: 20px; width:100%; display: flex; gap: 20px;">
  <table class="table table-striped" style="">
    <thead>
      <th>В наличии</th>
      <th>Всего</th>
      <th>В москве</th>
    </thead>
    <tbody>
      <? foreach ( $topStat as $key => $val): ?>
      <tr>
        <td><?=$topName[$key]?></td>
        <td><?=$val?></td>
        <td><?=$topStatMpscow[$key]?></td>
      </tr>
    <? endforeach; ?>
  </tbody>
  </table>
  <div class="display:flex;flex-direction:column;gap:20px;">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal" style="height: fit-content;width:100%;">
      Склады Москва
    </button>
    <a href="/admin/panel/ozon/ajax/stat/IP/get_stock_by_date_csv.php?date=<?=date('Y-m-d')?>" target="_blank" type="button" class="btn btn-primary"  style="height: fit-content;width:100%;margin-top:20px;">
      Скачать CSV
    </a>
  </div>
</div>
<!-- Модальное окно -->
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Выбор складов Москвы</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="warehouseForm">
          <div class="mb-3">
            <label class="form-label">Выберите склады:</label>
            <?php
            foreach ($warehousesTemplate as $index => $warehouse): ?>
            <div class="form-check">
              <input class="form-check-input warehouse-checkbox" type="checkbox" name="warehouses[]" value="<?= $warehouse ?>"
              <?if (in_array($warehouse,$active_warehouses)) { echo "checked"; }?>
               id="warehouse_<?= $index ?>" >
              <label class="form-check-label" for="warehouse_<?= $index ?>">
                <?= $warehouse ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
        <button type="button" class="btn btn-primary btn-save-warehouses">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  $(document).on('click', '.btn-open-warehouse-modal', function() {
    $('#warehouseModal').modal('show');
  });

  $(document).on('click', '.btn-save-warehouses', function() {
    saveWarehouses();
  });
});

function saveWarehouses() {
  var selectedWarehouses = [];
  $('#warehouseModal .warehouse-checkbox:checked').each(function() {
    selectedWarehouses.push($(this).val());
  });

  $.ajax({
    url: '/admin/panel/ozon/ajax/stat/IP/save_warehouses.php',
    type: 'POST',
    data: {
      warehouses: selectedWarehouses
    },
    success: function(response) {
      alert('Склады успешно сохранены!');
      $('#warehouseModal').modal('hide');
    },
    error: function(xhr, status, error) {
      alert('Ошибка при сохранении: ' + error);
    }
  });
}
</script>
