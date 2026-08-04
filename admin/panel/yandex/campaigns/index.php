<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки складов - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки складов");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">


<?
opcache_reset();
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

UIProcessor::init();
$data = UIProcessor::data();
$config = Config::instance();

$warehouseList = $data->getWarehousesList();
$campaignsList = $data->settings()->getCampaignsList( 'WR' );
$cmList = $data->settings()->getCampaignsMatchList( 'WR' );

foreach ( $rows as $row ){
  $settings[ $row['cabinet'] ] = $row;
}

?>
<div class="row">
  <div class="col-md-7 col-sm-12">

    <div class="card">
      <div class="card-body">
        <nav>
          <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">

            <? foreach ( $config->getAllCabinets() as $cab => $name): ?>
              <button class="nav-link nav-bold <?echo $cab == 'WR' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cab?>" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
            <? endforeach; ?>
          </div>
        </nav>
        <div class="tab-content settings-list" id="nav-tabContent">

        </div>
      </div>
    </div>
  </div>
  <div class="col-md-5 col-sm-12" >
  	<div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: auto!important">
    	<div>
  			<b>Наличие по умолчанию определяется активностью в ci_price </b>
        <hr>
        <b>Цена по умолчанию берется из свойства "Цена Yandex"</b>
  		</div>
      <hr>
  		<div>
  			На текущей странице Вы можете настроить, на какой магазин и в зависимости от какого склада будут транслироваться заданные остаток и наценка
  		</div>
      <hr>
      <div>Список магазинов модерируется на странице <b><a href="/admin/panel/yandex/settings/">Основные настройки</a></b></div>
  	</div>
  </div>
  <div class="col-md-2 col-sm-12">
  </div>
</div>

<script src="<?=SITE_TEMPLATE_PATH?>/js/campaigns.js"></script>
<?require("../include/completeToast.php");?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
