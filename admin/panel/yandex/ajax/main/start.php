<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");
UIProcessor::init();

if ( empty($_POST['code']) ) throw new Exception("POST cannot be empty");

$agent = UIProcessor::data()->settings()->findAgent( $_POST['code'] )[0] ?? [];
if ( empty($agent) ) throw new Exception("Agent was not found");

$path = $_SERVER['DOCUMENT_ROOT'] . $agent['path'];

$res = exec("php {$path} > /dev/null 2>&1 &");
var_dump($path);
var_dump($res);
?>
