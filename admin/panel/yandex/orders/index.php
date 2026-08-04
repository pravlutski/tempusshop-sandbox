<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки заказов - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки заказов");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<?php
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

UIProcessor::init();
$data = UIProcessor::data();
$updater = UIProcessor::updater();
$config = Config::instance();

$navigation = [
  'settings' => 'Настройки',
  'export' => 'Экспорт'
];
$rows = $updater->panel()->select(['*'], $config->getTableName('order_status_map'))->make();
$map = [];
foreach ( $rows as $row ){
  $map[ $row['status_ya'] ] = [
    'bx' => $row['status_bx'],
    'desc' => $row['status_name'],
  ];
}
$statusDict = $data->items()->getStatusList();

 ?>
<div class="row">
  <div class="col-md-7 col-sm-12">

    <div class="card">
      <div class="card-body">
        <nav>
          <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
            <? foreach ( $navigation as $code => $name): ?>
              <button class="nav-btn-pen nav-link nav-bold <?echo $code == 'settings' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-target="#nav-<?=$code?>-block" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
            <? endforeach; ?>
          </div>
        </nav>
        <div class="tab-content" id="nav-settings-block">
          <? foreach ( $map as $ya => $data):?>
          <div class="card card-o" style="display: flex; flex-direction: row; margin-top: 5px">
            <div class="card-name input-group-text" >
              <?=$data['desc']?>
            </div>
            <div class="card-part">
              <select class="form form-select" name="">
                <option value="none">Не выбрано</option>
                <? foreach( $statusDict as $code => $name ): ?>
                  <? if ( $data['bx'] == $code ): ?>
                    <option selected value="<?$code?>"><?=$name?></option>
                    <? continue; ?>
                  <? endif; ?>
                    <option value="<?$code?>"><?=$name?></option>
                <? endforeach; ?>
              </select>
            </div>
          </div>
          <? endforeach;  ?>
        </div>
        <div class="tab-content" id="nav-export-block" style="display:none">
          <div style="display: flex; flex-direction: column; width: 50%">

            <div class="card card-e" style="display: flex; flex-direction: row">
              <div class="input-group-text e-part">
                Дата начала периода
              </div>
              <div class="e-part">
                <input type="date" id="dateFrom" class="form form-control" value="">
              </div>
            </div>

            <div class="card card-e" style="display: flex; flex-direction: row">
              <div class="input-group-text e-part">
                Дата конца периода
              </div>
              <div class="e-part">
                <input type="date" id="dateTo" class="form form-control" value="">
              </div>
            </div>

            <div class="card card-e" style="display: flex; flex-direction: row">
              <div class="input-group-text e-part">
                Формат экспорт
              </div>
              <div class="e-part">
                <select class="form form-select" id="format" name="">
                  <option value="csv">CSV</option>
                  <option value="xlsx">XSLX</option>
                </select>
              </div>
            </div>

          </div>

          <div style="display: flex; flex-direction: column; width: 50%">
            <button id="export-orders" class="btn btn-primary" style="width: fit-content; margin-left: auto">Экспорт</button>
          </div>

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

<style media="screen">
  .card-name{
    width: 65%;
  }
  .card-part{
    width: 35%;
  }

  #nav-export-block{
    display: flex;
    flex-direction: row;
  }
  .card-e{
    width: 90%;
    margin-top: 5px;
  }
  .e-part{
    width: 50%;
  }
</style>

<script src="<?=SITE_TEMPLATE_PATH?>/js/orders.js"></script>
<?require("../include/completeToast.php");?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
