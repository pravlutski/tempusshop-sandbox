<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<head>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
  <script type="text/javascript" src="js/script.js"></script>
  <link rel="stylesheet" href="css/style.css">
  <title>Скидки Yandex</title>
</head>
<?php
  $settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsYA/settings/settings.json';
  $arSettings = json_decode(file_get_contents($settingsPath), true);
  // $settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsYA/settings/settingsIll.json';
  // $arSettingsIll = json_decode(file_get_contents($settingsPath), true);
 ?>
<body>
  <div id="type-switcher">
    <button id="def-tab" class="tab-btn">Акции</button>
    <button id="settings-tab" class="tab-btn">Настройки</button>
  </div>
  <div id="def-disc-block" class="control-header">
    <h2 class="util-name text-[22px] font-semibold mt-6">Скидки Yandex</h2>
    <div class="margin-calc-block">
      <div class="margin-calc-fields">
        <label style="display:none;" for="">Наценка
          <input id="markup" type="text" class="input-field" value="<?echo $arSettings['markup'];?>">
        </label>
        <label>Комиссия, %
          <input id="comission" type="number" style="margin-left: 64px" class="input-field" value="<?echo $arSettings['comission'];?>">
        </label>
        <label>Маржинальность, %
          <input id="margin" type="number" style="margin-left: 12px" class="input-field" value="<?echo $arSettings['margin'];?>">
        </label>
      </div>
    </div>
    <div class="upload-buttons-block">
      <label id="label-def" class="uni-btn" for="discounts-def-input">Выбрать файл</label>
      <input type="file" id="discounts-def-input" value="" style="display:none;">
      <button type="button" class="uni-btn" id="send-def-data-btn">Рассчитать скидки</button><br>
      <p class="filename-def"></p>
    </div>
    <div id="def-info-block" class="info-block">
      <div id="def-table" class="def-table ib-table">
      </div>
    </div>
  </div>

  <div id="settings-block" class="control-header" style="display:none;">
    <h2 class="util-name">Настройки парсера</h2>
        <div class="margin-calc-fields" style="border-top: 1px solid rgba(0,0,0,0.13); padding-top: 10px">
          <h3 class="h3-header">Настройки значений по умолчанию</h3>
          <label for="" style="display:none">Наценка
            <input id="markup-set" type="text" class="input-field" value="<?echo $arSettings['markup'];?>" style="margin-left:29px;">
          </label>
          <label>Комиссия, %
            <input id="comission-set" type="number" class="input-field" value="<?echo $arSettings['comission'];?>" style="margin-left:63px;">
          </label>
          <label>Маржинальность, %
            <input id="margin-set" type="number" class="input-field" style="margin-left:10px" value="<?echo $arSettings['margin'];?>">
          </label>

          <h3 class="h3-header">Настройки колонок документа</h3>
          <label>SKU
            <input id="sku-set" type="number" class="input-field" value="<?echo $arSettings['sku_col'] + 1;?>" style="margin-left:120px;">
          </label>
          <label>Макс. цена
            <input id="max-price-set" type="number" class="input-field" value="<?echo $arSettings['price_col'] + 1;?>" style="margin-left:74px;">
          </label>
          <button type="button" class="uni-btn" id="save-settings">Сохранить настройки</button>
          <div id="set-info-block">

          </div>
        </div>

      <div class="logs-block">
        <h2 class="util-name">Логи последнего расчёта</h2>
        <p><a href="/admin/modules/DiscountsYA/logs/log.txt" target="_blank">Открыть</a></p>
      </div>
  </div>

</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- <script src="https://cdn.tailwindcss.com"></script> -->
