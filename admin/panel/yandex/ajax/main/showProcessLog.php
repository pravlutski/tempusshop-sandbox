<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");
UIProcessor::init();

if ( empty($_POST) ) throw new Exception("Post cannot be empty");

$agent = UIProcessor::data()->settings()->findAgent( $_POST['code'] )[0] ?? [];
if ( empty($agent) ) throw new Exception("Agent was not found");

$path = $_SERVER['DOCUMENT_ROOT'] . $agent['log'] . date('Y_m_d'). '.txt';

$contents = file_get_contents( $path );

echo "<pre>{$contents}</pre>";
 ?>
