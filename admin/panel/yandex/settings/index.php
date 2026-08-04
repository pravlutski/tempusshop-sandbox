<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Основные настройки - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Основные настройки");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");
UIProcessor::init();
$data = UIProcessor::data();
$config = Config::instance();

$settings = [];
foreach ( $data->settings()->getMainSettings() as $row ){
  $settings[ $row['cabinet'] ] = $row;
}

$campaigns = UIProcessor::data()->settings()->getCampaignsList('WR');
?>
<div class="row">
  <div class="col-md-6 col-sm-12">

    <div class="card">
      <div class="card-body">
        <nav>
          <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">

            <? foreach ( $config->getAllCabinets() as $cab => $name): ?>
              <button class="nav-link nav-bold <?echo $cab == 'WR' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cab?>" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
            <? endforeach; ?>
          </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">

          <? foreach ( $config->getAllCabinets() as $cab => $name): ?>
            <form class="main-settings-form-<?=$cab?>" action="" method="post">
              <div class="input-group mb-3">
            	  <span class="input-group-text" id="basic-addon3" style="width: 220px;">Business ID</span>
            	  <input required type="text" class="form-control" name="businessId" id="basic-url" aria-describedby="basic-addon3" value="<?=$settings[$cab]['businessId']?>">
            	</div>
            	<div class="input-group mb-3">
                <span class="input-group-text" id="basic-addon3" style="width: 220px;">API ключ</span>
                <input required type="text" class="form-control" name="api" id="basic-url" aria-describedby="basic-addon3" value="<?=$settings[$cab]['api']?>">
              </div>
              <input type="hidden" name="cabinet" value="<?=$cab?>">
            </form>
            <hr>
            <button class="btn btn-warning save-main-settings" value="<?=$cab?>">Сохранить настройки</button>
          <? endforeach; ?>

        </div>
      </div>

    </div>
    <br>
    <h4>Магазины, доступные для АПИ</h4>
    <hr>
    <div class="card">
      <div class="card-body">
        <div class="campaigns-list">
          <? foreach ( $campaigns as $c ): ?>
          <div class="input-group mb-3" id="store_<?=$c['campaignId']?>">
            <span class="input-group-text" id="basic-addon3" style="width: 88%;"><?=$c['domain']?></span>
            <button class="btn btn-danger delete-btn" value="<?=$c['campaignId']?>">Удалить</button>
          </div>
        <? endforeach; ?>
        </div>
        <hr>
        <button class="btn btn-warning update-campaigns-list" value="WR" style="width: fit-content">Обновить список</button>
      </div>
    </div>

  </div>
  <div class="col-md-6 col-sm-12" >
  	<div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: auto!important">
    	<div>
  			<b>Business ID - идентификатор бинзнеса на YM</b>- Выдаётся при регистрации продавца.
  		</div>
  		<div>
  			<b>API ключ</b> - Уникальный ключ для обмена данными по API.</b>
  		</div>
  		<div>
  			<p><br>
  				API-ключи хранятся в личном кабинете в скрытом виде.<br>
          <b>Ни в коем случае не передавайте этот ключ третьим лицам!</b><br>
  			</p>
  		</div>
  	</div>
  </div>
  <div class="col-md-2 col-sm-12">
  </div>
</div>

<?require("../include/completeToast.php");?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
