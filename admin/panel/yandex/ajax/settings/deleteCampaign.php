<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST['store']) ) throw new Exception("store value cannot be empty");

UIProcessor::init();
UIProcessor::updater()->delete(
  table: Config::instance()->getTableName('campaigns_list'),
  field: 'campaignId',
  value: $_POST['store']
);
 ?>
