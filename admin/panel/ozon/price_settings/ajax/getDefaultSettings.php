<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

$settings = new SettingsManager('IP');
$items = $settings->getDefaultSettings()[0];
$cab = $items['cabinet'];
$dict = [
  'min_profit_perc' => 'Мин. маржинальность, %',
  'min_profit_rub' => 'Мин. маржинальность, ₽',
  'step' => 'Шаг, %',
];
?>
<form id="default-settings-form">
  <? foreach ( $items as $name => $value ): ?>
  <? if ( $name == 'cabinet' || $name == 'id' ) continue; ?>
    <div class="ds-card">
      <div class="name">
        <?=$dict[$name]?>
      </div>
      <div class="input">
        <input class="form-control" type="text" name="<?=$cab?>|<?=$name?>" value="<?=$value?>">
      </div>
    </div>
  <? endforeach; ?>
</form>
<hr>
<button id="save-defaults" class="btn btn-warning save-btn">Сохранить настройки</button>
