<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки цен - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки цен");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$strSql = "SELECT * FROM wdhs_ozon_price_settings";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult[$row['name']] = $row['value'];
}

?>
<div class="row">
<div class="col-6">
<form id="main_settings" action="/admin/modules/ozon/ajax/save_settings.php" method="post">
	<div class="input-group mb-3">
	  <span class="input-group-text" id="basic-addon3" style="width: 200px;">Client ID</span>
	  <input required type="text" class="form-control" name="client_id" id="basic-url" aria-describedby="basic-addon3" value="<?=$arResult['client_id']?>">
	</div>
	<div class="input-group mb-3">
    <span class="input-group-text" id="basic-addon3" style="width: 200px;">API ключ</span>
    <input required type="text" class="form-control" name="key" id="basic-url" aria-describedby="basic-addon3" value="<?=$arResult['key']?>">
  </div>
  <div class="input-group mb-3">
    <span class="input-group-text" id="basic-addon3" style="width: 200px;">API URL</span>
    <input required type="text" class="form-control" name="api_url" id="basic-url" aria-describedby="basic-addon3" value="<?=$arResult['api_url']?>">
  </div>
	<div class="card">
  <div class="card-body">
    <h5 class="card-title">Настройки агета</h5>
    <p class="card-text">Если настройка включена. То агент будет автоматически выгружать выбранный параметр.<br>
		<i>Для остатков и цен выгрузка происходит каждый час.</i><br>
		<i>Для товаров выгрузка происходит раз в сутки.</i></p>
  </div>
	  <ul class="list-group list-group-flush">
	    <li class="list-group-item">
				<div class="form-check form-switch">
					<input class="form-check-input" name="upload_price" type="checkbox" id="flexSwitchCheckChecked" <?if ($arResult['upload_price'] == 'Y') echo 'checked';?>>
					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать цены</label>
				</div>
			</li>
	    <li class="list-group-item">
				<div class="form-check form-switch">
				  <input class="form-check-input" name="upload_stock" type="checkbox" id="flexSwitchCheckChecked" <?if ($arResult['upload_stock'] == 'Y') echo 'checked';?>>
				  <label class="form-check-label" for="flexSwitchCheckChecked">Выгружать остатки</label>
				</div>
			</li>
	    <li class="list-group-item">
				<div class="form-check form-switch">
					<input class="form-check-input" name="upload_products" type="checkbox" id="flexSwitchCheckChecked" <?if ($arResult['upload_products'] == 'Y') echo 'checked';?> disabled>
					<label class="form-check-label" for="flexSwitchCheckChecked">Выгружать товары</label>
				</div>
			</li>
	  </ul>
</div>
<button id="save_main_setting" style="width: 100%;margin-top: 30px;" type="submit" class="btn btn-warning">Сохранить настройки</button>
</form>
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
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
