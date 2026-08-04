<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
$dbPanel = new DBPanel;

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$options = $_POST;
$day = $_POST['date'];


$tmp = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/wb/ajax/charts/selected_warehouses_{$_POST['cabinet']}.txt");

$active_warehouses = [];
if (!empty($tmp)) {
  $active_warehouses = explode(', ' ,$tmp);
}



$warehousesTemplate = [];
$warehouses = [];
$strSql = "SELECT model, warehouseName FROM wb_fbo_stat_{$_POST['cabinet']} WHERE stock_date = '{$day}'";
$resultDB = $dbPanel->query($strSql, false, $err_mess.__LINE__);
$data = $dbPanel->fetchAll($resultDB);
foreach ( $data as $row ){
  if (!empty($row['warehouseName']) && $row['warehouseName'] != 'null') {
    $tmpWh = json_decode($row['warehouseName'],true);
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
  $stock[$row['model']] = ['stock' => $row['stock']];
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

$arDebug = [];
foreach ( $topChunks as $lim ){
  for ( $i = 0; $i < $lim; $i++ ){
    if ( isset($tops[$i]) && isset($stock[$tops[$i]]) && $stock[$tops[$i]]['stock'] > 0 ){
      $topStat['top_' . $lim] += 1;
      // $arDebug['top_' . $lim][] = $tops[$i];
      if (isset($warehouses[$tops[$i]]) && intval($warehouses[$tops[$i]] != 0)) {
        $topStatMpscow['top_' . $lim] += 1;
        $arDebug['MSC_top_' . $lim][$tops[$i]] = intval($warehouses[$tops[$i]]);
      }
    }
  }
}

// print_r($tops);

$topName = [
  'top_10' => 'Топ 10',
  'top_50' => 'Топ 50',
  'top_100' => 'Топ 100',
  'top_200' => 'Топ 200',
  'top_309' => 'Топ 309',
];
?>
<div class="top-stat-block" style="display:flex;gap:20px;padding-top: 20px; width:100%">
  <table class="table table-striped">
    <thead>
      <th>В наличии</th>
      <th>Всего</th>
      <th>В Москве</th>
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
  <a href="/admin/panel/wb/ajax/charts/getStockDetailCsv.php?date=<?=date('Y-m-d')?>&cabinet=<?=$_POST['cabinet']?>" target="_blank" type="button" class="btn btn-primary"  style="height: fit-content;width:100%;margin-top:20px;">
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
            <?//print_r($warehousesTemplate);?>
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
    url: '/admin/panel/wb/ajax/charts/save_warehouses.php',
    type: 'POST',
    data: {
      warehouses: selectedWarehouses,
      cabinet: '<?=$_POST['cabinet']?>'
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
