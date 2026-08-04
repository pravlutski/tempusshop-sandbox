<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");
UIProcessor::init();

if ( empty($_POST['code']) ) throw new Exception("POST cannot be empty");

$agent = UIProcessor::data()->settings()->findAgent( $_POST['code'] )[0] ?? [];
if ( empty($agent) ) throw new Exception("Agent was not found");

$path = $_SERVER['DOCUMENT_ROOT'] . $agent['path'];

$lines = shell_exec("ps aux | grep {$path}");
$lines = explode("\n", $lines);

foreach ( $lines as $line ){
  $parts = preg_split('/\s+/', $line);
  $pid = $parts[1]; // PID находится во втором столбце
  shell_exec("kill -9 $pid");
}

exec("php {$path} > /dev/null 2>&1 &");
 ?>
