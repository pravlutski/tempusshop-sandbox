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
  'increase_step' => 'Порог повышения плана, %',
  'decrease_step' => 'Порог понижения плана, %',
  'increase_value' => 'Шаг повышения, ед.',
  'decrease_value' => 'Шаг понижения, ед.',
];

$goalSettings = ['increase_step', 'increase_value', 'decrease_step', 'decrease_value'];
 ?>

 <form id="goals-settings-form">
   <? foreach ( $items as $name => $value ): ?>
     <? if ( !in_array($name, $goalSettings) ) continue; ?>
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
   <input type="hidden" name="marketplace" value="<?=$_POST['marketplace']?>">
   <input type="hidden" name="cabinet" value="<?=$_POST['cabinet']?>">
 </form>
 <hr>
 <button id="save-defaults" class="btn btn-warning save-btn">Сохранить настройки</button>
