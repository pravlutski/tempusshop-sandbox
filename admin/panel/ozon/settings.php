<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Основные настройки - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Основные настройки");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();
$cabinetArr = array('IP','TI');
$CurDB = new DBPanel();
global $DB;
global $USER;
CModule::IncludeModule("iblock");
$arGroups = $USER->GetUserGroupArray();


foreach ($cabinetArr as $key => $cabinet) {
  $result = $CurDB->query("SELECT * FROM ozon_main_settings_{$cabinet}");
  $rows = $CurDB->fetchAll($result);
  foreach ($rows as $row) {
    $arSetting[$cabinet][$row['name']] = $row['value'];
  }
  unset($result);
  unset($rows);
}


?>
<div class="row">
<div class="col-md-6 col-sm-12">
  <div class="card">
  <div class="card-body">
    <nav>
      <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
      <?$i = 0;
      foreach ($cabinetArr as $key => $cabinet) {?>
      <button class="nav-link <?if ($i==0) { echo "active"; }?>" id="nav-<?=$cabinet?>-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cabinet?>" type="button" role="tab" aria-controls="nav-<?=$cabinet?>" aria-selected="true">Кабинет "<?=$cabinet?>"</button>
      <?$i++;}?>
      <button class="nav-link show-wh-modal" style="margin-left: auto" >Склады ФБО</button>
    </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
      <?$i = 0;
      foreach ($cabinetArr as $key => $cabinet) {?>
        <div class="tab-pane fade <?if ($i==0) { echo "active show"; }?>" id="nav-<?=$cabinet?>">
          <form id="main_settings_<?=$cabinet?>" action="/admin/panel/ozon/ajax/save_settings.php" method="post">
            <input type="hidden" name="cabinet" value="<?=$cabinet?>"/>
        	<div class="input-group mb-3">
        	  <span class="input-group-text" id="basic-addon3" style="width: 220px;">Client ID</span>
        	  <input required type="text" class="form-control" name="client_id" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['client_id']?>">
        	</div>
        	<div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">API ключ</span>
            <input required type="text" class="form-control" name="key" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['key']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">API URL</span>
            <input required type="text" class="form-control" name="api_url" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['api_url']?>">
          </div>
          <hr>
          <h4>Настройки уведомлений бота</h4>
          <div class="input-group mb-3" style="margin-top: 20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Процент отклонения</span>
            <input required type="text" class="form-control" name="bot_threshold" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['bot_threshold']?>">
          </div>
          <hr>
          <h4>Настройки ФБО</h4>

          <div class="input-group mb-3" style="margin-top: 20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Макс. разница, %</span>
            <input required type="text" class="form-control" name="fbo_threshold" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['fbo_threshold']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Комиссия OZON, %</span>
            <input required type="text" class="form-control" name="com" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['com']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Комиссия ФБО, %</span>
            <input required type="text" class="form-control" name="newCom" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['newCom']?>">
          </div>
          <h5 class="card-title">Динамическое ценообразование</h5>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Порог срабатывания, дн.</span>
            <input required type="text" class="form-control" name="dp_threshold" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['dp_threshold']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Шаг, дн.</span>
            <input required type="text" class="form-control" name="step" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['step']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Скидка, %</span>
            <input required type="text" class="form-control" name="discount" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['discount']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Макс. Скидка, %</span>
            <input required type="text" class="form-control" name="max_discount" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['max_discount']?>">
          </div>
          <h5 class="card-title">Прочие настройки</h5>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 220px;">Порог цены для селекта</span>
            <input required type="text" class="form-control" name="select_threshold" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSetting[$cabinet]['select_threshold']?>">
          </div>

        	<div class="card" style="display:none">
            <div class="card-body">
              <h5 class="card-title">Настройки агента</h5>
              <p class="card-text">Если настройка включена. То агент будет автоматически выгружать выбранный параметр.<br>
          		<i>Для остатков и цен выгрузка происходит каждый час.</i><br>
          		<i>Для товаров выгрузка происходит раз в сутки.</i></p>
            </div>
        	  <ul class="list-group list-group-flush">
        	    <li class="list-group-item">
        				<div class="form-check form-switch">
        					<input class="form-check-input" name="upload_price" type="checkbox" id="flexSwitchCheckChecked" <?if ($arSetting[$cabinet]['upload_price'] == 'Y') echo 'checked';?>>
        					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать цены</label>
        				</div>
        			</li>
        	    <li class="list-group-item">
        				<div class="form-check form-switch">
        				  <input class="form-check-input" name="upload_stock" type="checkbox" id="flexSwitchCheckChecked" <?if ($arSetting[$cabinet]['upload_stock'] == 'Y') echo 'checked';?>>
        				  <label class="form-check-label" for="flexSwitchCheckChecked">Выгружать остатки</label>
        				</div>
        			</li>
        	    <li class="list-group-item">
        				<div class="form-check form-switch">
        					<input class="form-check-input" name="upload_products" type="checkbox" id="flexSwitchCheckChecked" <?if ($arSetting[$cabinet]['upload_products'] == 'Y') echo 'checked';?> disabled>
        					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать товары</label>
        				</div>
        			</li>
        	  </ul>
          </div>
          <button id="save_main_setting_<?=$cabinet?>" style="width: 100%;margin-top: 30px;" type="submit" class="btn btn-warning">Сохранить настройки</button>
        </form>
        </div>
      <?$i++;}?>
    </div>
    </div>
  </div>


</div>
<div class="col-md-4 col-sm-12">
	<div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: fit-content">
  	<div>
			<b>Client ID</b>- Идентификатор клиента в магазине OZON.
		</div>
		<div>
			<b>API ключ</b> - Уникальный ключ для обмена данными по API.</b>
		</div>
		<div>
			<p><br>
				API-ключи хранятся в личном кабинете в скрытом виде. Вы увидите API-ключ только при создании.<br>
				Восстановить доступ к несохранённым API-ключам.<br><br>
			</p>
		</div>
		<div>
			<p><b>Чтобы получить API-ключ:</b><br>
				1. В личном кабинете перейдите в Настройки → API ключи.<br>
				2. Нажмите Сгенерировать ключ.<br>
				3. Придумайте название для ключа и выберите его уровень доступа.<br>
				4. Нажмите Сгенерировать.<br>
			</p>
		</div>
	</div>
</div>
<div class="col-md-2 col-sm-12">
</div>
</div>
<? require('include/wh_picker.php'); ?>
<?require('include/mobile.php');?>

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
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>

<script>
$(document).ready(function() {
  <?foreach ($cabinetArr as $key => $cabinet) {?>
    $(document).on("submit", "#main_settings_<?=$cabinet?>", function(event) {
        event.preventDefault();
        let type = $(this).attr('method')
        let url = $(this).attr('action')
        let data = new FormData(this)

    		var button = $('#save_main_setting_<?=$cabinet?>');
    		button.prop('disabled', true);
    		button.html('<span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Загрузка...');

    		$.ajax({
    			type: type,
    			url: url,
    			data: data,
    			contentType: false,
    			cache: false,
    			processData: false,
    			success: function(result){
    				button.prop('disabled', false);
    				button.html('Сохранить настройки');
    				var t_comp = document.getElementById('coplete_toast');
    				toast=new bootstrap.Toast(t_comp);
    				toast.show();
    				playSuccessSound();
    			},
    			error: function(result){
    			},
    		});

    });
<?}?>
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
