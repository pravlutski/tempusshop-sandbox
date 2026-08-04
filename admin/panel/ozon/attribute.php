<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройка атрибутов - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройка атрибутов");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/attribute.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/attribute.js"></script>
<?

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$strSql = "SELECT * FROM wdhs_ozon_attribute_category_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult['ACTIVE_CATEGORY'][$row['type_id']] = $row;
}


$strSql = "SELECT * FROM wdhs_ozon_attribute_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arResult['ACTIVE_ATT'][$arResult['ACTIVE_CATEGORY'][$row['type_id']]['name']][] = $row;
}
?>
<div class="row">
  <div class="col-sm-12" style="margin-bottom:50px;">
    <button id="ozon_get_categories" style="width: 290px;" type="button" class="btn btn-warning">Загрузить список категорий озон</button>
    <button id="update_attribute" style="width: 290px;" type="button" class="btn btn-warning">Обновить список атрибутов</button>
  </div>
  <div class="col-sm-5">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Категории</h5>
          <p class="card-text">Активные категории</p>
        </div>
      <?if (!empty($arResult['ACTIVE_CATEGORY'])):?>
      <ul class="list-group list-group-flush">
          <?foreach ($arResult['ACTIVE_CATEGORY'] as $key => $value):?>
            <li class="list-group-item"><?=$value['name']?></li>
          <?endforeach;?>
      </ul>
      <?else:?>
      <ul class="list-group list-group-flush">
          <li class="list-group-item"><span style="color:red;">Отсутствуют активные категории</span></li>
      </ul>
      <?endif;?>
      </div>
      <div id="result" style="margin-bottom:50px;" class=col-12></div>
  </div>
  <div class="col-sm-7">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Атрибуты</h5>
        <p class="card-text">Текущие загруженные атрибуты</p>
      </div>
    <?if (!empty($arResult['ACTIVE_ATT'])):?>
    <ul class="list-group list-group-flush">
        <?foreach ($arResult['ACTIVE_ATT'] as $key => $value):?>
          <li class="list-group-item head_li">Раздел: <?=$key?>
            <span class="arrow"></span>
            <ul class="drop_head">
              <?foreach ($value as $k => $v):?>
              <li><?=$v['name']?></li>
              <?endforeach;?>
              </ul>
          </li>
        <?endforeach;?>
    </ul>
    <?else:?>
    <ul class="list-group list-group-flush">
      <li class="list-group-item"><p style="color:red;">Атрибуты не выгружены</span></li>
    </ul>
    <?endif;?>
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
