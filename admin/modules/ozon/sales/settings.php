<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки акций и PI - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки акций и PI");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?
opcache_reset();

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT * FROM wdhs_sales_pi WHERE pi_sets = 'main'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $pi_sebes = $row['per_sebes'];
  $unset = $row['unset'];
  $min_profit = $row['min_profit'];
  $com = $row['com'];
  $tops = implode("\n",json_decode($row['tops']));
}

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[] = $row;
}

foreach ($salesActive as $key => &$value) {
  $currentDate = new DateTime();
  $dateTimeF = new DateTime($value['date_start']);
  $dateTimeT = new DateTime($value['date_end']);
  $dateFrom = $dateTimeF->format('d.m.Y');
  $dateTo = $dateTimeT->format('d.m.Y');
  if ($currentDate < $dateTimeF) {
    $value['class'] = "table-success";
  } else if ($currentDate > $dateTimeF && $currentDate->diff($dateTimeT)->days > 5) {
    $value['class'] = "table-warning";
  } else if ($currentDate->diff($dateTimeT)->days <= 5) {
    $value['class'] = "table-danger";
  } else {
    $value['class'] = "table-primary";
  }
  $value['id'] = $value['sale_id'];
}
if (!empty($salesActive)) {
  usort($salesActive, function($a, $b) {
      return strtotime($a['date_start']) - strtotime($b['date_start']);
  });
}
?>

<?
?>
<form id="pi_settings" action="/admin/modules/ozon/sales/ajax/save_pi.php" method="post">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Настройки общие</h5>
      <div class="card-text pi_sets">
        <div class="pi_group" style="align-items: center;">
          <div style="display:none;">
            <span>Минимальная наценка для PI</span>
            <input style="max-width:100px" name="per_sebes" value="<?=$pi_sebes?>"/>
          </div>
          <div style="">
            <span>Минимальная маржа для входа в акцию</span>
            <input style="max-width:100px" name="min_profit" value="<?=$min_profit?>"/>
          </div>
          <div style="">
            <span>Комиссия OZON</span>
            <input style="max-width:100px" name="com" value="<?=$com?>"/>
          </div>
          <div style="">
            <span>Исключить бренды</span>
            <input style="max-width:100px" name="unset" value="<?=$unset?>"/>
          </div>
          <div style="">
            <a id="topModels" class="btn btn-primary"/>ТОП ОЗОНА</a>
          </div>
        </div>
        <div>
          <button id="save_sebes" type="submit" style="width: 250px;" type="button" class="btn btn-warning">Сохранить настройки PI</button>
        </div>
      </div>
    </div>
  </div>
</form>
<!-- Popup Modal -->
<div id="topModelsModal" class="modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ТОП ОЗОНА</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p>Вводить через разрыв строки</p>
        <textarea id="topModelsText" class="form-control" rows="20" placeholder="Введите данные..."><?=$tops?></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
        <button type="button" id="saveTopModels" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>
<form id="sales_settings" action="/admin/modules/ozon/sales/ajax/save_sales.php" method="post">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Настройки Акций</h5>
      <div class="card-text">
        <?if (empty($salesActive)) {?>
          <div id ="result">
            <p style="color: red;  margin-bottom: 0px;  font-size: 20px;  font-weight: 500;">Активных и настроенных акций нет.</p>
            <p style="color:green;font-size: 15px;  font-style: italic;">Нажмите кнопку загрузить акции, чтобы забрать с озона актуальные акции.</p>
          </div>
        <?} else {?>
          <table class="table table-striped" style="margin-top: 30px; margin-bottom: 30px;">
          <thead>
            <tr>
              <th scope="col">ПР-Т</th>
              <th scope="col">АКТИВНА</th>
              <th scope="col">ИМЯ</th>
              <th scope="col">ДАТА НАЧАЛА</th>
              <th scope="col">ДАТА КОНЦА</th>
              <th scope="col">НАША СКИДКА</th>
              <th scope="col">-КОМ</th>
              <th scope="col">-КОМ<br>(FBO)</th>
              <th scope="col">МАРЖА</th>
              <th scope="col">ДОСТУПНО</th>
              <th scope="col">ТОП<br></th>
              <th scope="col">УЧАСТВУВУЮТ<br></th>
              <th scope="col"><br></th>
            </tr>
          </thead>
          <tbody id="result">
            <?php foreach ($salesActive as $id => $v): ?>
              <tr id="<?=$v['id']?>" class="<?=$v['class']?>">
                <th scope="row" ><input  style="width:50px;" name="data[<?=$v['id']?>][sort]" value="<?=$v['sort']?>" /></th>
                <td><input hidden name="data[<?=$v['id']?>][active]" value="<?=$v['active']?>" />
                  <span>
                    <?if ($v['active'] == "1"){echo "ДА";}else{echo "НЕТ";}?>
                  </span>
                </td>
                <td><input hidden name="data[<?=$v['id']?>][name]" value="<?=$v['name']?>" /><span><?=$v['name']?></span></td>
                <td><input hidden name="data[<?=$v['id']?>][date_start]" value="<?=$v['date_start']?>" /><span><?=$v['date_start']?></span></td>
                <td><input hidden name="data[<?=$v['id']?>][date_end]" value="<?=$v['date_end']?>" /><span><?=$v['date_end']?></span></td>
                <td><input style="width:50px;" name="data[<?=$v['id']?>][perc]" value="<?=$v['perc']?>" /></td>
                <td><input style="width:50px;" name="data[<?=$v['id']?>][skd]" value="<?=$v['skd']?>" /></td>
                <td><input style="width:50px;" name="data[<?=$v['id']?>][skd_fbo]" value="<?=$v['skd_fbo']?>" /></td>
                <td style="width:150px;"><b><?=$v['merg']?></b></td>
                <td><input hidden name="data[<?=$v['id']?>][potencial]" value="<?=$v['potencial']?>" /><span><?=$v['potencial']?></span></td>
                <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px;"><?=$v['top_models']?></span></td>
                <td><input hidden name="data[<?=$v['id']?>][uses]" value="<?=$v['uses']?>" /><span><?=$v['uses']?></span></td>

                <td><span class="delete_item" data-id="<?=$v['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
              </tr>
            <?php endforeach; ?>
            <tr style="display:none;"></tr>
          </tbody>
        </table>
        <?}?>
        <button id="load_sales" type="button" style="width: 250px;" type="button" class="btn btn-success">Загрузить акции</button>
        <button id="save_sales" type="submit" style="width: 250px;" type="button" class="btn btn-warning">Сохранить настройки акций</button>
      </div>
    </div>
  </div>
</form>


<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="coplete_toast" class="toast hide align-items-center bg-warning" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="...">
      <strong class="me-auto">Tempus Ozon Module</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
    </div>
    <div class="toast-body">
      Операция выполнена успешно
    </div>
  </div>
</div>

<style>
.pi_sets {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pi_group {
  display: flex;
  gap:50px;
}
.delete_item:hover {
  text-decoration: underline;
}
</style>
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
<script>
$(document).ready(function() {
  // Открытие popup
  $('#topModels').click(function(e) {
    e.preventDefault();
    $('#topModelsModal').modal('show');
  });

  // Сохранение данных через AJAX
  $('#saveTopModels').click(function() {
    var data = $('#topModelsText').val();

    $.ajax({
      url: '/admin/modules/ozon/sales/ajax/save_pi.php',
      method: 'POST',
      data: { top: 'Y', text: data },
      success: function(response) {
        alert('Данные успешно сохранены!');
        $('#topModelsModal').modal('hide');
      },
      error: function(xhr, status, error) {
        alert('Произошла ошибка при сохранении данных: ' + error);
      }
    });
  });
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
