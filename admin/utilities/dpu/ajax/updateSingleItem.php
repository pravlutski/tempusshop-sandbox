<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/common/DynamicPrice/DPManual.php");

if ( empty($_POST) ) throw new Exception("Empty POST request");
if ( empty($_POST['cabinet']) || empty($_POST['marketplace']) || empty($_POST['model']) ) throw new Exception("One of required parameters missing");

$user = \Bitrix\Main\Engine\CurrentUser::get()->getId();
$controller = new DPManual(
  marketplace: $_POST['marketplace'],
  cabinet: $_POST['cabinet'],
  model: $model,
  userId: $user
);

$controller->run();
 ?>
