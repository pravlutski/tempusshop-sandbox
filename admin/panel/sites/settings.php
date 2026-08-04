<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Различные настройки - модуль сайтов');?>
<?$APPLICATION->SetPageProperty("page_h1", "Различные настройки");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();
$CurDB = new DBPanel();
global $DB;
global $USER;
CModule::IncludeModule("iblock");
$arGroups = $USER->GetUserGroupArray();


$result = $CurDB->query("SELECT * FROM sites_settings");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $arSetting[$row['SETTING']] = [
      'NAME' => $row['NAME'],
      'SETTING' => $row['SETTING'],
      'VALUE' => $row['VALUE']
    ];
}
?>
<div class="row">
<div class="col-md-6 col-sm-12">
  <div class="card">
  <div class="card-body">
    <form id="sites_settings" action="/admin/panel/sites/ajax/save_settings.php" method="post">
      <?
      foreach ($arSetting as $key => $setting) {?>
        	<div class="input-group mb-3">
        	  <span class="input-group-text" id="basic-addon3" style="width: 200px;"><?=$setting['NAME']?></span>
        	  <input required type="text" class="form-control" name="<?=$setting['SETTING']?>" id="basic-url" aria-describedby="basic-addon3" value="<?=$setting['VALUE']?>">
        	</div>
      <?}?>
      <button id="save_sites_settings" style="width: 100%;margin-top: 30px;" type="submit" class="btn btn-warning">Сохранить настройки</button>
      </form>
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
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>

<script>
$(document).ready(function() {
    $(document).on("submit", "#sites_settings", function(event) {
        event.preventDefault();
        let type = $(this).attr('method')
        let url = $(this).attr('action')
        let data = new FormData(this)

    		var button = $('#save_sites_settings');
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
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
