<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

if ( empty($_POST['marketplace']) || empty($_POST['cabinet']) ){
  throw new Exception("Empty marketplace or cabinet");
}

$settings = new CRUDManager(
  mp: $_POST['marketplace'],
  cab: $_POST['cabinet']
);

$items = $settings->getDefaultSettings();
$cab = $items['cabinet'];
$dict = [
  'min_profit_perc' => 'Мин. маржинальность, %',
  'min_profit_rub' => 'Мин. маржа, ₽',
  'step' => 'Шаг, %',
  'threshold' => 'Отн. погрешность, %',
  'tolerance' => 'Абс. погрешность, шт.',
  'commission' => 'Комиссия, %',
  'isPeriod' => 'Режим работы'
];

$skipSettings = ['increase_step', 'increase_value', 'decrease_step', 'decrease_value'];
?>
<form id="default-settings-form">
  <? foreach ( $items as $name => $value ): ?>
    <? if ( in_array($name, $skipSettings) ) continue; ?>
    <? if ( $name == 'cabinet' || $name == 'id' ) continue; ?>
    <? if ( $name == 'isPeriod'): ?>
      <div class="ds-card">
        <div class="name">
          <?=$dict[$name]?>
        </div>
        <div class="input">
          <select class="form-control" name="<?=$cab?>|<?=$name?>">
            <option value="1" <?echo ($value == 1) ? 'selected' : '';?>>Интервал запуска</option>
            <option value="0" <?echo ($value == 0) ? 'selected' : '';?>>Начало суток</option>
          </select>
        </div>
      </div>
    <? else: ?>
      <div class="ds-card">
        <div class="name">
          <?=$dict[$name]?>
        </div>
        <div class="input">
          <input class="form-control" type="text" name="<?=$cab?>|<?=$name?>" value="<?=$value?>">
        </div>
      </div>
    <? endif; ?>
    <? if ( $name == 'step') echo '<h4 class="settings-block-name">Расчёты</h4>'; ?>
    <? if ( $name == 'commission') echo '<h4 class="settings-block-name">Работа</h4>'; ?>
  <? endforeach; ?>
  <div class="red-buttons-block">
    <button id="clear-price-table" class="btn btn-danger">Очистить все статусы</button>
    <button id="clear-items-list" class="btn btn-danger">Очистить список товаров</button>
  </div>
  <input type="hidden" name="marketplace" value="<?=$_POST['marketplace']?>">
  <input type="hidden" name="cabinet" value="<?=$_POST['cabinet']?>">
</form>
<hr>
<button id="save-defaults" class="btn btn-warning save-btn">Сохранить настройки</button>
