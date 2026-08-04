<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');

$dbPanel = new DBPanel;

$rows = $dbPanel->select(['*'], 'wb_fbo_warehouses')->make();
$config = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/warehouses_fbo.json';

$wh = [];
if ( file_exists($config) ){
  $json = file_get_contents( $config );
  $wh = json_decode( $json );
}

 ?>

<div class="wh-block">
  <select class="form-control" id="warehouses" multiple style="min-height: 350px;">
    <? foreach ( $rows as $row ): ?>
      <? if ( in_array( $row['name'], $wh) ): ?>
        <option value="<?=$row['name']?>" selected><?=$row['name']?></option>
      <? else: ?>
        <option value="<?=$row['name']?>"><?=$row['name']?></option>
      <? endif; ?>
    <? endforeach; ?>
  </select>
</div>
<hr>
<button style="display:flex; margin-left: auto" class="btn btn-warning save-stock-settings">Сохранить</button>
