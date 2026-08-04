<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>

<?
    $APPLICATION->SetPageProperty("page_h1", "Контроль выгрузки - модуль сайтов");
    $APPLICATION->SetTitle('Ошибки в товарах - модуль сайтов');

opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");
$CurDB = new DBPanel();

$result = $CurDB->query("SELECT * FROM sites_control_exclude WHERE sites = 'all'");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $modelsExclude = implode("\n",json_decode($row['models']));
  $arrExclude = json_decode($row['models']);
}
// $result = $CurDB->query("SELECT * FROM ozon_control_exclude WHERE cabinet = '{$cabinet}'");
// $rows = $CurDB->fetchAll($result);
// foreach ($rows as $row) {
//   $modelsExclude = implode("\n",json_decode($row['models']));
// }
// unset($result);
// unset($rows);
$tmp = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/reportBad.txt");
$arLog = json_decode($tmp,true);
// print_r($arLog);
?>
<a id="exclude" class="btn btn-primary"/>Исключения</a>
<!-- Popup Modal -->
<div id="excludeModal" class="modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Исключения</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p>Вводить через разрыв строки</p>
        <textarea id="excludeText" class="form-control" rows="20" placeholder="Введите данные..."><?=$modelsExclude?></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
        <button type="button" id="SaveExcludeModels" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>
<br>
<?if (!empty($arLog)) {?>
  <?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/reportBad.txt")) );?><br>
  Товаров: <?print_r(count($arLog));?><br><br>
  <!-- <a href="/admin/panel/ozon/logs/GetReportBad.php" taget="_blank">Скачать EXCEL</a> -->
  <??>
<?}?>

<?

if (!empty($arLog)) {?>
  <table class="table table-striped">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">Артикул</th>
        <th scope="col">Цена RU (BACK)</th>
        <th scope="col">Цена RU (SITE)</th>
        <th scope="col">Цена BY (BACK)</th>
        <th scope="col">Цена BY (SITE)</th>
        <th scope="col">Кол-во (BACK)</th>
        <th scope="col">Кол-во (SITE)</th>
        <th scope="col">Первостепенная причина</th>
      </tr>
      </thead>
      <tbody>
  <?$i = 1;?>
  <?//print_r($arLog);

  ?>
  <?php foreach ($arLog as $key => $value): ?>
    <?if (in_array($value['MODEL'],$arrExclude)) { continue; }?>
    <tr>
      <th style="background: #cfe2ff;" scope="row"><?=$i?></th>
      <td style="background: #d1e7dd;"><?=$value['MODEL']?></td>
      <td style="background: <?if (!empty($value['P_RU_OLD'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['P_RU_OLD']?></td>
      <td style="background: <?if (!empty($value['P_RU_NEW'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['P_RU_NEW']?></td>
      <td style="background: <?if (!empty($value['P_BY_OLD'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['P_BY_OLD']?></td>
      <td style="background: <?if (!empty($value['P_BY_NEW'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['P_BY_NEW']?></td>
      <td style="background: <?if (!empty($value['Q_OLD'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['Q_OLD']?></td>
      <td style="background: <?if (!empty($value['Q_NEW'])) { echo "#d1e7dd"; } else { echo "#f8d7da"; }?>"><?=$value['Q_NEW']?></td>
      <td style="background: #f8d7da;">
          <?foreach ($value['REASON'] as $reason) {?>
            <?=$reason?> <br>
          <?}?>

      </td>
    </tr>
    <?$i++;?>
  <?php endforeach; ?>


  </tbody>
  </table>
<?} else {?>
  <div class="no-error-message">Нет ошибок</div>
  <style>
  .no-error-message {
    margin-top: 40px;
    font-size: 22px;
    font-weight: 600;
    color: #30b10c;
  }
  </style>
<?}?>
<script>
$('#exclude').click(function(e) {
  e.preventDefault();
  $('#excludeModal').modal('show');
});
// Сохранение данных через AJAX
$('#SaveExcludeModels').click(function() {
  var data = $('#excludeText').val();
  $.ajax({
    url: '/admin/panel/sites/ajax/controlExclude.php',
    method: 'POST',
    data: { top: 'Y', text: data},
    success: function(response) {
      alert('Данные успешно сохранены!');
      $('#topModelsModal').modal('hide');
    },
    error: function(xhr, status, error) {
      alert('Произошла ошибка при сохранении данных: ' + error);
    }
  });
});
</script>
<style>
td {
  text-align: center!important;
  border: 0.5px solid #9d9d9d!important;
}
th {
  border: 0.5px solid #9d9d9d!important;
}
</style>
<script>
$('#topModels_TI').click(function(e) {
  e.preventDefault();
  $('#topModelsModal_TI').modal('show');
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
