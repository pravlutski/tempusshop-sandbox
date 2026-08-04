<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Основные настройки - WB модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Основные настройки");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$strSql = "SELECT * FROM wdhs_wb_main_settings";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult[$row['cabinet']] = $row;
  $arSettings[$row['cabinet']] = json_decode($row['settings'],true);
  $exclude = $arSettings[$row['cabinet']]['exclude'];
  $arSettings[$row['cabinet']]['exclude'] = str_replace(',', PHP_EOL, $exclude);
}
$cabinets = [
  'WR' => "Кабинет WR",
  'TL' => "Кабинет IP",
  'WT' => "Кабинет WT",
];
?>
<div class="row">
<div class="col-md-6 col-sm-12">

  <div class="card">
  <div class="card-body">
    <nav>
      <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">

        <? foreach ( $cabinets as $cab => $name): ?>
          <button class="nav-link nav-bold <?echo $cab == 'WR' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cab?>" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
        <? endforeach; ?>

        <button class="nav-link show-wh-modal" style="margin-left: auto" >Склады ФБО</button>
      </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">

      <? foreach ( $cabinets as $cab => $name): ?>
        <div class="tab-pane fade <?echo $cab == 'WR' ? 'active' : '';?> show" id="nav-<?=$cab?>">

        <form action="/admin/panel/wb/ajax/save_settings.php" method="POST" id="save_settings_<?=$cab?>" role="tabpanel" aria-labelledby="nav-home-tab">
          <h4 style="margin-top:20px;">Настройки соединения</h4>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Идентификатор поставщика WB</span>
            <input required type="text" class="form-control" name="clientId" id="basic-url" aria-describedby="basic-addon3" value="<?=$arResult[$cab]['clientId']?>">
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Токен API</span>
            <input required type="text" class="form-control" name="api" id="basic-url" aria-describedby="basic-addon3" value="<?=$arResult[$cab]['api']?>">
          </div>
          <hr>
          <h4 style="margin-top:20px;">Настройки уведомлений бота</h4>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Процент отклонения</span>
            <input required type="text" class="form-control" name="settings[threshold]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['threshold']?>">
          </div>
          <hr>
          <h4 style="margin-top:20px;">Настройки FBO</h4>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Макс. разница, %</span>
            <input required type="text" class="form-control" name="settings[fbo_threshold]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['fbo_threshold']?>">
          </div>
          <h5 style="margin-top:20px;">Дианмическое ценообразование</h5>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Порог срабатывания, дн.</span>
            <input required type="text" class="form-control" name="settings[dp_threshold]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['dp_threshold']?>">
          </div>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Шаг, дн.</span>
            <input required type="text" class="form-control" name="settings[step]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['step']?>">
          </div>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Скидка, %</span>
            <input required type="text" class="form-control" name="settings[discount]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['discount']?>">
          </div>
          <div class="input-group mb-3" style="margin-top:20px;">
            <span class="input-group-text" id="basic-addon3" style="width: 280px;">Макс. скидка, %</span>
            <input required type="text" class="form-control" name="settings[max_discount]" id="basic-url" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['max_discount']?>">
          </div>
          <hr>
          <h4 style="margin-top:20px;">Настройки выгрузки цен и остатков</h4>
          <div class="row">
              <div class="input-group mb-3 col-6" style="margin-top:20px;width:auto;">
                  <span class="input-group-text" id="basic-addon3" style="width: 100px;">min себес</span>
                  <input required type="text" class="form-control" name="settings[minSebes]" id="basic-url1" aria-describedby="basic-addon3" value="<?=$arSettings[$cab]['minSebes']?>">
              </div>
              <div class="input-group mb-3 col-6" style="margin-top:20px;width:auto;">
                  <span class="input-group-text" id="basic-addon4" style="width: 100px;">max себес</span>
                  <input required type="text" class="form-control" name="settings[maxSebes]" id="basic-url2" aria-describedby="basic-addon4" value="<?=$arSettings[$cab]['maxSebes']?>">
              </div>
          </div>

          <div style="display:flex;flex-direction:column;">
            <span style="font-size: 16px;  font-weight: 500;  margin-bottom: 10px;">Исключение для выгрузки остатков</span>
            <textarea type="text" class="form-control" name="settings[exclude]" id="basic-url" aria-describedby="basic-addon3" style="height:200px;"><?=$arSettings[$cab]['exclude']?></textarea>
          </div>
          <h4 style="margin-top:20px;">Настройки агентов</h4>
          <div class="card">
          	  <ul class="list-group list-group-flush">
          	    <li class="list-group-item">
          				<div class="form-check form-switch">
          					<input class="form-check-input" name="settings[upload_price]" type="checkbox" <?if ($arSettings[$cab]['upload_price'] == 'on') { echo 'checked';}?> id="flexSwitchCheckChecked">
          					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать цены</label>
          				</div>
          			</li>
          	    <li class="list-group-item">
          				<div class="form-check form-switch">
          				  <input class="form-check-input" name="settings[upload_stock]" type="checkbox" <?if ($arSettings[$cab]['upload_stock'] == 'on') { echo 'checked';}?> id="flexSwitchCheckChecked">
          				  <label class="form-check-label" for="flexSwitchCheckChecked">Выгружать остатки</label>
          				</div>
          			</li>
          	    <li class="list-group-item">
          				<div class="form-check form-switch">
          					<input class="form-check-input" name="settings[upload_products]" type="checkbox" <?if ($arSettings[$cab]['upload_products'] == 'on') { echo 'checked';}?> id="flexSwitchCheckChecked" disabled="">
          					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать товары</label>
          				</div>
          			</li>
          	  </ul>
          </div>

          <input type="hidden" name="cabinet" value="<?=$cab?>">
          <button style="width: 100%;margin-top: 30px;" type="submit" class="btn btn-warning save_main_setting">Сохранить настройки кабинета "<?=$cab?>"</button>
        </form>

      </div>
      <? endforeach; ?>

    </div>
  </div>
  </div>
</div>
<div class="col-md-4 col-sm-12" >
	<div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: auto!important;">
  	<div>
			<b>Идентификатор поставщика wildberries.ru</b>- Выдаётся при регистрации поставщика.
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
  <div id="coplete_toast" class="toast hide align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
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
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
