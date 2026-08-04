<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<head>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
  <link rel="stylesheet" href="css/style.css">
  <title>Скидки WB</title>
</head>
<?php
  $settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsDef.json';
  $arSettingsDef = json_decode(file_get_contents($settingsPath), true);
  $settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsIll.json';
  $arSettingsIll = json_decode(file_get_contents($settingsPath), true);
 ?>
<body>
  <div id="type-switcher">
    <button id="def-tab" class="tab-btn" type="button" name="button">Акции</button>
    <button id="ill-tab" class="tab-btn" type="button" name="button">Неликвид</button>
    <button id="settings-tab" class="tab-btn" type="button" name="button">Настройки</button>
    <label class="cab-container">
      Кабинет:
      <select id="cab-selector">
        <option value="WR" selected>WR</option>
        <option value="DEFAULT">WBTL</option>
      </select>
    </label>
  </div>
  <div id="def-disc-block" class="control-header">
    <h2 class="util-name">Скидки WB (Акции)</h2>
    <div class="upload-buttons-block">
      <label id="label-def" class="uni-btn" for="discounts-def-input">Выбрать файл</label>
      <input type="file" id="discounts-def-input" value="" style="display:none;">
      <button type="button" class="uni-btn" id="upload-def-disc-btn" style="display:none">Обновить скидки на WB</button>
      <p class="filename-def"></p>
    </div>
    <div class="margin-calc-block">
      <div class="margin-calc-fields">
        <label for="">Наценка
          <input id="markup" type="text" class="input-field" value="<?echo $arSettingsDef['markup'];?>">
        </label>
        <label>Комиссия
          <input id="comission" type="text" class="input-field" value="<?echo $arSettingsDef['comission'];?>">
        </label>
        <label>Скидка
          <input id="discount-max" type="text" class="input-field" value="">
        </label>
        <label>Скидка (стд)
          <input id="discount-min" type="text" class="input-field" value="<?echo $arSettingsDef['discMin'];?>">
        </label>
        <button type="button" class="uni-btn" id="send-def-data-btn">Рассчитать скидки</button>
      </div>
      <div class="margin-info-block">
        Маржинальность:  <span class="margin-info">пока не посчитана</span>
      </div>
    </div>
    <div id="def-info-block" class="info-block">
      <div id="def-table" class="def-table ib-table">
      </div>
      <div id="def-chart" class="def-chart ib-chart">
      </div>
    </div>
  </div>

  <div id="ill-disc-block" class="control-header" style="display:none;">
    <h2 class="util-name">Скидки WB (Неликвид)</h2>
    <div class="upload-buttons-block">
      <label id="label-ill" class="uni-btn" for="discounts-ill-input">Выбрать файл</label>
      <input type="file" id="discounts-ill-input" value="" style="display:none;">
      <button type="button" class="uni-btn" id="send-ill-data-btn">Рассчитать скидки</button>
      <button type="button" class="uni-btn" id="upload-ill-disc-btn" style="display:none">Обновить скидки</button>
      <p class="filename-ill"></p>
    </div>
    <!-- <div class="table-block">
      <h3>Таблицы последнего расчета скидок</h2>
      <p><a href="/admin/modules/DiscountsWB/temp/default_discounts.xlsx">Скидки (Акции)</a></p>
      <p><a href="/admin/modules/DiscountsWB/temp/illiquid_discounts.xlsx">Скидки (Неликвид)</a></p>
    </div> -->
    <div id="ill-info-block" class="info-block">
      <div class="ill-table" style="min-height: 40%">

      </div>
    </div>
  </div>

  <div id="settings-block" class="control-header" style="display:none;">
    <h2 class="util-name">Настройки парсера акций</h2>
      <details class="def-set">
        <summary>Настройки акций</summary>
        <div class="margin-calc-fields">
          <h3>Настройки параметров маржинальности</h3>
          <label for="">Наценка
            <input id="markup-set" type="text" class="input-field" value="<?echo $arSettingsDef['markup'];?>" style="margin-left:29px;">
          </label>
          <label>Комиссия
            <input id="comission-set" type="text" class="input-field" value="<?echo $arSettingsDef['comission'];?>" style="margin-left:18px;">
          </label>
          <label>Скидка (стд)
            <input id="discount-min-set" type="text" class="input-field" value="<?echo $arSettingsDef['discMin'];?>">
          </label>

          <h3>Настройки колонок документа</h3>
          <label>Артикул WB
            <input id="nmid-set" type="text" class="input-field" value="<?echo $arSettingsDef['nmid_col'] + 1;?>" style="margin-left:56px;">
          </label>
          <label>Текущая скидка
            <input id="discount-current-set" type="text" class="input-field" value="<?echo $arSettingsDef['curDisc_col'] + 1;?>" style="margin-left:32px;">
          </label>
          <label>Загружаемая скидка
            <input id="discount-upload-set" type="text" class="input-field" value="<?echo $arSettingsDef['uplDisc_col'] + 1;?>">
          </label>
          <button type="button" class="uni-btn" id="save-settings-def-btn">Сохранить настройки</button>
          <div class="set-table">

          </div>
        </div>
      </details>
      <details class="ill-set">
        <summary>Настройки неликвида</summary>
        <div class="margin-calc-fields">
          <h3>Настройки колонок документа</h3>
          <label>Артикул WB
            <input id="nmid-set-ill" type="text" class="input-field" value="<?php echo $arSettingsIll['nmid_col'] + 1;?>" style="margin-left:56px;">
          </label>
          <label>Оборачиваемость
            <input id="turnover-set-ill" type="text" class="input-field" value="<?php echo $arSettingsIll['turnover_col'] + 1;?>" style="margin-left:14px;">
          </label>
          <h3>Настройки значения скидок</h3>
          <label>Скидка (Стандартная)
            <input id="discMin-ill" type="text" class="input-field" value="<?php echo $arSettingsIll['discMin'];?>" style="margin-left:14px;">
          </label>
          <label>Скидка (Неликвид)
            <input id="discMax-ill" type="text" class="input-field" value="<?php echo $arSettingsIll['discMax'];?>" style="margin-left:35px;">
          </label>
          <button type="button" class="uni-btn" id="save-settings-ill-btn">Сохранить настройки</button>
          <div class="set-table">

          </div>
        </div>
      </details>
      <div class="logs-block">
        <h2 class="util-name">Логи последней выгрузки</h2>
        <p><a href="/admin/modules/DiscountsWB/logs/default_log.txt" target="_blank">Скидки (Акции)</a></p>
        <p><a href="/admin/modules/DiscountsWB/logs/illiquid_log.txt" target="_blank">Скидки (Неликвид)</a></p>
      </div>
      <div class="logs-block">
        <h2 class="util-name">Таблицы последнего расчета скидок</h2>
        <p><a href="/admin/modules/DiscountsWB/temp/default_discounts.xlsx">Скидки (Акции)</a></p>
        <p><a href="/admin/modules/DiscountsWB/temp/illiquid_discounts.xlsx">Скидки (Неликвид)</a></p>
      </div>
    <!-- <div id="set-info-block" class="info-block">
      <div class="set-table">

      </div>
    </div> -->
  </div>

</body>
<script type="text/javascript" src="js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
