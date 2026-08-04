<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$strSql = "SELECT * FROM ozon_model_collection ORDER BY code ASC";
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );

$arCollection = [];
foreach ( $rows as $row ){
  $arCollection[ $row['model'] ] = $row['code'];
}

 ?>

<? foreach( $arCollection as $model => $row ): ?>
  <div class="model-row" id="<?=$model?>">
    <div class="model-block row-piece">
      <span><?=$model?></span>
    </div>
    <div class="code-block row-piece">
      <input type="text" class="form-control" name="<?=$model?>" value="<?=$row?>">
    </div>
    <div class="delete-block row-piece">
      <button type="button" class="del-btn btn btn-danger" value="<?=$model?>">Удалить</button>
    </div>
  </div>
<? endforeach; ?>
