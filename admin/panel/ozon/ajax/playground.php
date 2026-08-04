<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Управление модулем - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Управление модулем");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/playground.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/playground.js"></script>

<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");


$strSql = "SELECT * FROM wdhs_ozon_upload_status";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arLast[$row['agent']] = $row;
}

$curdate = date('Y-m-d');

$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/'.$curdate.'.txt');
$arTmp = explode('#SPLIT#',$tmp);
foreach ($arTmp as $key => $value) {
  $arSoloLog = json_decode($value,true);
  $arResult['TIME_PRICES'][] = $arSoloLog['TIME_START'];
  if($arSoloLog['UPDATE']['GOOD']){
    $arPrice[] = count($arSoloLog['UPDATE']['GOOD']);
  }
  if($arSoloLog['UPDATE']['BAD']){
    $arPriceBad[] = count($arSoloLog['UPDATE']['BAD']);
  }
}
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/shows/'.$curdate.'.txt');
$arTmp = explode('#SPLIT#',$tmp);
foreach ($arTmp as $key => $value) {
  $arSoloLog = json_decode($value,true);
  if($arSoloLog['UPDATE']['GOOD']){
    $arStock[] = count($arSoloLog['UPDATE']['GOOD'])/5;
  }
  if($arSoloLog['UPDATE']['BAD']){
    $arStockBad[] = count($arSoloLog['UPDATE']['BAD'])/5;
  }
}

?>
<div class="row">
<div class="col-sm-12">
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <b>ВАЖНО</b><br>
    При перезапуске любой выгрузки, прерывается текущая выгрузка, если она запущена. Стоит помнить, что у OZON'а, есть лимиты на кол-во операций по времени.<br>
    Потому, прерывание выгрузки, в некоторых случаях, может нарушить целостность новой!
  </div>
</div>
</div>
<div id="result_update"  class="row" style="margin-top:30px;">
  <?//цены?>
  <div class="col-md-4 col-sm-12">
  <div class="card">
    <div class="card-header">
      <h4 style="font-size: 1.2rem;">Проверка ФБО выгрузка цен и остатков</h4>
    </div>
    <div class="card-body">
      <?if ($arLast['price']['status'] == 'COMPLETE'){?>
          <button id="run_upload_price" style="width: 100%;" type="submit" class="btn btn-warning">Запустить выгрузку</button>
      <?} else {?>
          <button id="run_upload_price" style="width: 100%;" type="submit" class="btn btn-warning" disabled><span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Выполняется...</button>
      <?}?>
      <button id="kill_upload_price" style="width: 100%;" type="submit" class="btn btn-danger">Прервать выгрузку</button>
    </div>
    <ul class="list-group list-group-flush">
      <li class="list-group-item" id="status_price">
        <b style="text-decoration: underline;font-size: 20px;">Цены:</b><br>
        <?if ($arLast['price']['status'] == 'COMPLETE'){?>
            <b>Последняя выгрузка в</b><?=$arLast['price']['time']?><br>
            <b>Уcпешно выгруженно:</b> <span style="color:green;font-style: italic;"><?=$arPrice[count($arPrice)-1]?></span><br>
            <b>Ошибки:</b> <span style="color:redfont-style: italic;"><?=$arPriceBad[count($arPriceBad)-1]?></span><br>
        <?} else if ($arLast['price']['status'] == 'INCOMPLETE') {?>
          <div class="progress custom-bar">
            <div class="progress-bar bg-warning text-dark w-<?=$arLast['price']['percent']?>" style="width: <?=$arLast['price']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['price']['percent']?>" aria-valuemin="0" aria-valuemax="100"><?=$arLast['price']['percent']?>%</div>
          </div>
        <?} else {?>
          <span style="color:red"> Статус не установлен.</span>
        <?}?>
      </li>
      <li class="list-group-item" id="status_stock">
        <b style="text-decoration: underline;font-size: 20px;">Остатки:</b><br>
        <?if ($arLast['stock']['status'] == 'COMPLETE'){?>
          <b>Последняя выгрузка в</b><?=$arLast['stock']['time']?><br>
          <b>Уcпешно выгруженно:</b> <span style="color:green;font-style: italic;"><?=$arStock[count($arStock)-1]?></span><br>
          <b>Ошибки:</b> <span style="color:redfont-style: italic;"><?=$arStockBad[count($arStockBad)-1]?></span><br>
        <?} else if ($arLast['stock']['status'] == 'INCOMPLETE') {?>
          <div class="progress custom-bar">
            <div class="progress-bar bg-warning text-dark w-<?=$arLast['stock']['percent']?>" style="width: <?=$arLast['stock']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['stock']['percent']?>" aria-valuemin="0" aria-valuemax="100"><?=$arLast['stock']['percent']?>%</div>
          </div>
        <?} else {?>
          <span style="color:red"> Статус не установлен.</span>
        <?}?>
      </li>
    </ul>
    <div class="card-body">
      <a href="/admin/modules/ozon/logs/price.php?price=<?=date('Y-m-d')?>" style="width: 100%;" class="btn btn-success" disabled>Лог цен</a>
      <a href="/admin/modules/ozon/logs/stock.php?stock=<?=date('Y-m-d')?>" style="width: 100%;" class="btn btn-success" disabled>Лог остатков</a>
      <a href="/admin/modules/ozon/logs/fbo.php" style="width: 100%;" class="btn btn-success" disabled>Лог проверки ФБО</a>
    </div>
  </div>
</div>
  <?//скидки?>
  <div class="col-md-4 col-sm-12">
    <div class="card">
      <div class="card-header">
        <h4 style="font-size: 1.2rem;">Скидки</h4>
      </div>
      <div class="card-body">
        <a href="/admin/modules/OzonImport/importSales.php" target="_blank" style="width: 100%;" type="submit" class="btn btn-warning">Перевыгрузить акции</a>
      </div>
      <ul class="list-group list-group-flush">
      </ul>
      <div class="card-body">
        <a href="/admin/modules/ozon/sales/log.php" style="width: 100%;" class="btn btn-success">Лог акций</a>
      </div>
    </div>
  </div>
  <?//остатки?>
  <? /*
  <!--<div class="col-md-4 col-sm-12">
    <div class="card">
      <div class="card-header">
        <h4 style="font-size: 1.2rem;">Остатки</h4>
      </div>
      <div class="card-body">
        <?if ($arLast['stock']['status'] == 'COMPLETE'){?>
            <button id="run_upload_stock" style="width: 100%;" type="submit" class="btn btn-warning">Перевыгрузить остатки цены</button>
        <?} else {?>
            <button id="run_upload_stock" style="width: 100%;" type="submit" class="btn btn-warning" disabled><span class="spinner-border spinner-border-sm load_cat" role="status" aria-hidden="false"></span> Выполняется...</button>
        <?}?>
        <button id="kill_upload_stock" style="width: 100%;" type="submit" class="btn btn-danger">Прервать выгрузку остатков</button>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item" id="status_stock">
          <?if ($arLast['stock']['status'] == 'COMPLETE'){?>
            <b>Последняя выгрузка в</b><?=$arLast['stock']['time']?><br>
            <b>Уcпешно выгруженно:</b> <span style="color:green;font-style: italic;"><?=$arStock[count($arStock)-1]?></span><br>
            <b>Ошибки:</b> <span style="color:redfont-style: italic;"><?=$arStockBad[count($arStockBad)-1]?></span><br>
          <?} else if ($arLast['stock']['status'] == 'INCOMPLETE') {?>
            <div class="progress custom-bar">
              <div class="progress-bar bg-warning text-dark w-<?=$arLast['stock']['percent']?>" style="width: <?=$arLast['stock']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['stock']['percent']?>" aria-valuemin="0" aria-valuemax="100"><?=$arLast['stock']['percent']?>%</div>
            </div>
          <?} else {?>
            <span style="color:red"> Статус не установлен.</span>
          <?}?>
        </li>
      </ul>
      <div class="card-body">
        <a href="/admin/modules/ozon/logs/stock.php?stock=<?=date('Y-m-d')?>" style="width: 100%;" class="btn btn-success" disabled>Лог остатков</a>
      </div>
    </div>
  </div>--> */?>
  <?//товары?>
  <div class="col-md-4 col-sm-12">
    <div class="card">
      <div class="card-header">
        <h4 style="font-size: 1.2rem;">Товары</h4>
      </div>
      <div class="card-body">
        <button id="run_upload_products" style="width: 100%;" type="submit" class="btn btn-warning" >Перевыгрузить товары</button>
        <button id="kill_upload_products" style="width: 100%;" type="submit" class="btn btn-danger" >Прервать выгрузку товаров</button>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item" id="status_product">
          <?if ($arLast['products']['status'] == 'COMPLETE'){?>
              Последняя выгрузка в <?=$arLast['products']['time']?>
          <?} else if ($arLast['products']['status'] == 'INCOMPLETE') {?>
            <div class="progress custom-bar">
              <div class="progress-bar bg-warning text-dark w-<?=$arLast['products']['percent']?>" style="width: <?=$arLast['products']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['products']['percent']?>" aria-valuemin="0" aria-valuemax="100"><?=$arLast['products']['percent']?>%</div>
            </div>
          <?} else {?>
            <span style="color:red"> Статус не установлен.</span>
          <?}?>
        </li>
      </ul>
      <div class="card-body">
        <a href="/admin/modules/ozon/logs/product.php" style="width: 100%;" class="btn btn-success">Лог товаров</a>
      </div>
    </div>
  </div>
</div>
<div class="row" style="margin-top:60px;">
  <div class="col-md-8 col-sm-12">
  </div>
  <div class="col-md-4 col-sm-12">
      <div class="card">
        <div class="card-body" style="flex-direction:row;">
          <button id="run_upload_all" style="width: 100%;" type="submit" class="btn btn-warning">Перевыгрузить все</button>
          <button id="kill_upload_all" style="width: 100%;" type="submit" class="btn btn-danger">Прервать все выгрузки</button>
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
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
