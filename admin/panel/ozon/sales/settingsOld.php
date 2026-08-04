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

$cabinetArr = array('IP','TI');
$CurDB = new DBPanel();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();


foreach ($cabinetArr as $key => $cabinet) {
  //pi настройки
  $result = $CurDB->query("SELECT * FROM ozon_sales_pi_{$cabinet} WHERE pi_sets = 'main'");
  $rows = $CurDB->fetchAll($result);
  foreach ($rows as $row) {
    $piSettings[$cabinet] = [
      'pi_sebes' => $row['per_sebes'],
      'unset' => $row['unset'],
      'min_profit' => $row['min_profit'],
      'min_profit_perc' => $row['min_profit_perc'],
      'com' => $row['com'],
      'tops' => implode("\n",json_decode($row['tops']))
    ];
  }
  unset($result);
  unset($rows);

  $result = $CurDB->query("SELECT * FROM ozon_top_models");
  $rows = $CurDB->fetchAll($result);
  $tops = [];
  foreach ($rows as $row) {
  	$tops[] = $row['model'];
  }
  unset($result);
  unset($rows);

  $piSettings[$cabinet]['tops'] = implode("\n", $tops);


  //акции из БД
  $result = $CurDB->query("SELECT * FROM ozon_sales_{$cabinet}");
  $rows = $CurDB->fetchAll($result);
  foreach ($rows as $row) {
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
  $arSales[$cabinet] = $salesActive;
  unset($salesActive);

}
$CurDB->close();
?>

<?
?>
<div class="row">

  <div class="card">
  <div class="card-body">
    <nav>
      <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
      <?$i = 0;
      foreach ($cabinetArr as $key => $cabinet) {?>
      <button class="nav-link <?if ($i==0) { echo "active"; }?>" id="nav-<?=$cabinet?>-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cabinet?>" type="button" role="tab" aria-controls="nav-<?=$cabinet?>" aria-selected="true">Кабинет "<?=$cabinet?>"</button>
      <?$i++;}?>

    </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
      <?$i = 0;
      foreach ($cabinetArr as $key => $cabinet) {?>
      <div class="tab-pane fade <?if ($i==0) { echo "active show"; }?>" id="nav-<?=$cabinet?>">
        <form id="pi_settings_<?=$cabinet?>" action="/admin/panel/ozon/sales/ajax/save_pi.php" method="post">
          <input type="hidden" name="cabinet" value="<?=$cabinet?>"/>
          <div class="">
            <div class="card-body">
              <h5 class="card-title">Настройки общие</h5>
              <div class="card-text pi_sets">
                <div class="pi_group" style="align-items: center;">
                  <div style="display:none;">
                    <span>Минимальная наценка для PI</span>
                    <input style="max-width:100px" name="per_sebes" value="<?=$piSettings[$cabinet]['pi_sebes']?>"/>
                  </div>
                  <div style="">
                    <span>Мин. маржа для входа, ₽</span>
                    <input style="max-width:100px" name="min_profit" value="<?=$piSettings[$cabinet]['min_profit']?>"/>
                  </div>
                  <div style="">
                    <span>Мин. маржа для входа, %</span>
                    <input style="max-width:100px" name="min_profit_perc" value="<?=$piSettings[$cabinet]['min_profit_perc']?>"/>
                  </div>
                  <div style="">
                    <span>Комиссия</span>
                    <input style="max-width:100px" name="com" value="<?=$piSettings[$cabinet]['com']?>"/>
                  </div>
                  <div style="">
                    <span>Исключить бренды</span>
                    <input style="max-width:100px" name="unset" value="<?=$piSettings[$cabinet]['unset']?>"/>
                  </div>
                  <div style="display:none;">
                    <a id="topModels_<?=$cabinet?>" class="btn btn-primary"/>ТОП ОЗОНА</a>
                  </div>
                  <div style="display:none">
                    <a class="btn btn-primary" href="https://tempusshop.ru/admin/panel/engine/ozon/reportTopSales.php?cabinet=<?=$cabinet?>">Отчет по ТОП моделям</a>
                  </div>
                </div>
                <div>
                  <button id="save_sebes_<?=$cabinet?>" type="submit" style="width: 250px;" type="button" class="btn btn-warning save_sebes">Сохранить настройки PI</button>
                </div>
              </div>
            </div>
          </div>
        </form>
        <!-- Popup Modal -->
        <div id="topModelsModal_<?=$cabinet?>" class="modal" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">ТОП ОЗОНА</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body">
                <p>Вводить через разрыв строки</p>
                <textarea id="topModelsText_<?=$cabinet?>" class="form-control" rows="20" placeholder="Введите данные..."><?=$piSettings[$cabinet]['tops']?></textarea>
                <input type="hidden" id="topModelsID_<?=$cabinet?>" value="<?=$cabinet?>"/>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
                <button type="button" style="display:none;" id="saveTopModels_<?=$cabinet?>" class="btn btn-primary">Сохранить</button>
              </div>
            </div>
          </div>
        </div>
        <hr>
        <form id="sales_settings_<?=$cabinet?>" action="/admin/panel/ozon/sales/ajax/save_sales.php" method="post">
          <input type="hidden" name="cabinet" value="<?=$cabinet?>"/>
          <div class="">
            <div class="card-body">
              <h5 class="card-title">Настройки Акций</h5>
              <div class="card-text">
                <?if (empty($arSales[$cabinet])) {?>
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
                      <th scope="col">НАЗВАНИЕ</th>
                      <th scope="col">НАЧАЛО</th>
                      <th scope="col">КОНЕЦ</th>
                      <th scope="col">НАША СКИДКА</th>
                      <th scope="col">ЖЕЛАЕМЫЙ БУСТ</th>
                      <th scope="col">ФИКС СКИДКА</th>
                      <!-- <th scope="col">-КОМ</th>
                      <th scope="col">-КОМ<br>(FBO)</th>
                      <th scope="col">МАРЖА</th> -->
                      <th scope="col">ДОСТУПНО</th>
                      <th scope="col">ТОП<br></th>
                      <th scope="col">УЧАСТВУВУЮТ<br></th>
                      <th scope="col"><br></th>
                    </tr>
                  </thead>
                  <tbody id="result_<?=$cabinet?>">
                    <?php foreach ($arSales[$cabinet] as $id => $v): ?>
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
                        <td><input style="width:50px;" name="data[<?=$v['id']?>][boost]" value="<?=$v['boost']?>" /></td>
                        <td>
                          <select style="width:150px;" class="form-select" name="data[<?=$v['id']?>][perc_entry]">
                            <option value="Y" <?echo ($v['perc_entry'] == 'Y') ? 'selected': '';?> >Фикс. скидка</option>
                            <option value="N" <?echo ($v['perc_entry'] == 'N') ? 'selected': '';?>>Макс. цена вхождения</option>
                          </select>
                        </td>

                        <td><input hidden name="data[<?=$v['id']?>][potencial]" value="<?=$v['potencial']?>" /><span><?=$v['potencial']?></span></td>
                        <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px;"><?=$v['top_models']?></span></td>
                        <td><input hidden name="data[<?=$v['id']?>][uses]" value="<?=$v['uses']?>" /><span class="active_<?=$v['id']?>">Считаю...</span></td>

                        <td><span class="delete_item" data-id="<?=$v['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr style="display:none;"></tr>
                  </tbody>
                </table>
                <?}?>
                <button id="load_sales_<?=$cabinet?>" type="button" style="width: 250px;" type="button" class="btn btn-success load_sales_<?=$cabinet?>">Загрузить акции</button>
                <button id="save_sales_<?=$cabinet?>" type="submit" style="width: 250px;" type="button" class="btn btn-warning">Сохранить настройки акций</button>
              </div>
            </div>
          </div>
        </form>
      </div>
      <?$i++;}?>

    </div>
  </div>
  </div>

</div>






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
  gap:20px;
  margin-right: 20px;
}
.delete_item:hover {
  text-decoration: underline;
}
</style>
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
<script>

function countActiveItems()
{
  $.ajax({
    url: '/admin/panel/ozon/sales/ajax/countActiveItems.php',
    method: "POST",
    success: function(response){
      var result = $.parseJSON(response);
      result.forEach( (item) => {
        $('.active_'+item.saleId).html(item.count);
      });
    }
  });
}

countActiveItems();

$(document).ready(function() {
  <?foreach ($cabinetArr as $key => $cabinet) {?>
  // Открытие popup
  $('#topModels_<?=$cabinet?>').click(function(e) {
    e.preventDefault();
    $('#topModelsModal_<?=$cabinet?>').modal('show');
  });

  // Сохранение данных через AJAX
  $('#saveTopModels_<?=$cabinet?>').click(function() {
    var data = $('#topModelsText_<?=$cabinet?>').val();
    var data_id = $('#topModelsID_<?=$cabinet?>').val();
    $.ajax({
      url: '/admin/panel/ozon/sales/ajax/save_pi.php',
      method: 'POST',
      data: { top: 'Y', text: data, cabinet: data_id},
      success: function(response) {
        alert('Данные успешно сохранены!');
        $('#topModelsModal_<?=$cabinet?>').modal('hide');
      },
      error: function(xhr, status, error) {
        alert('Произошла ошибка при сохранении данных: ' + error);
      }
    });
  });
  $(document).on("submit", "#pi_settings_<?=$cabinet?>", function(event) {
          event.preventDefault();
          let type = $(this).attr('method')
          let url = $(this).attr('action')
          let data = new FormData(this)

  		var button = $('#save_sebes_<?=$cabinet?>');
  		button.prop('disabled', true);
  		button.html('<span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Сохранить настройки...');

  		$.ajax({
  			type: type,
  			url: url,
  			data: data,
  			contentType: false,
  			cache: false,
  			processData: false,
  			success: function(result){
  				button.prop('disabled', false);
  				button.html('Сохранить настройки PI');
  				var t_comp = document.getElementById('coplete_toast');
  				toast=new bootstrap.Toast(t_comp);
  				toast.show();
  				playSuccessSound();
  			},
  			error: function(result){
  			},
  		});

  });
  $(document).on("submit", "#sales_settings_<?=$cabinet?>", function(event) {
          event.preventDefault();
          let type = $(this).attr('method')
          let url = $(this).attr('action')
          let data = new FormData(this)

  		var button = $('#save_sales_<?=$cabinet?>');
  		button.prop('disabled', true);
  		button.html('<span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Сохранить настройки...');

  		$.ajax({
  			type: type,
  			url: url,
  			data: data,
  			contentType: false,
  			cache: false,
  			processData: false,
  			success: function(result){
  				button.prop('disabled', false);
  				button.html('Сохранить настройки акций');
  				var t_comp = document.getElementById('coplete_toast');
  				toast=new bootstrap.Toast(t_comp);
  				toast.show();
  				playSuccessSound();
          setTimeout(() => {
              location.reload();
          }, 1000);
  			},
  			error: function(result){
  			},
  		});

  });
  $(document).on('click','#load_sales_<?=$cabinet?>',function(){
    	var button = $('.load_sales_<?=$cabinet?>'); // замените '#yourButtonID' на селектор для вашей кнопки

    	// Добавляем атрибут disabled
    	button.prop('disabled', true);
    	button.html('<span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Загрузка...');
    	// Изменяем содержимое кнопки
      var data_id = $('#topModelsID_<?=$cabinet?>').val();
    	$.ajax({
    		type: "POST",
    		url: "/admin/panel/ozon/sales/ajax/get_sales.php",
    		dataType: "html",
    		cache: false,
        data: {cabinet: data_id},
    		crossDomain: true,
    		xhrFields: {
    			withCredentials: true
    		},
    		success: function(data) {
    			$("#result_<?=$cabinet?>").html();
    			$("#result_<?=$cabinet?>").html(data);
    			button.prop('disabled', false);
    			button.html('Загрузить акции');
    			var t_comp = document.getElementById('coplete_toast');
    			toast=new bootstrap.Toast(t_comp);
    			toast.show();
    			playSuccessSound();
    		},
    		error:function(data) {
    			alert("Не удалось получить данные");
    		}
    	});
    });
  <?}?>
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
