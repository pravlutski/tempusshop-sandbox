<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

$settings = new SettingsManager('IP');
$items = $settings->getCoefficientConfig();


 ?>
<form id="coefficient-form">
  <div class="c-card c-header">
    <div class="hour header">
      Час
    </div>
    <div class="coef header">
      Коэффициент
    </div>
    <div class="button header">
    </div>
  </div>
  <? foreach ( $items as $name => $value ): ?>
    <div class="c-card">
      <div class="hour">
        <?=$value['hour']?>
      </div>
      <div class="coef">
        <input class="form-control" type="text" name="<?=$value['id']?>" value="<?=$value['coefficient']?>">
      </div>
      <div class="button">
      </div>
    </div>
  <? endforeach; ?>
</form>
<hr>
<div style="display:flex; flex-direction: row">
  <button id="save-coefficients" class="btn btn-warning save-btn">Сохранить настройки</button>
</div>
