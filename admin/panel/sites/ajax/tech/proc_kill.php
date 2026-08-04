<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

GLOBAL $DB;

if (!isset($_POST['script']) || !isset($_POST['code'])) die('no script in table');


$SCRIPTNAME = explode('/',$_POST['script']);
$SCRIPTNAME = $SCRIPTNAME[count($SCRIPTNAME)-1];
$CODE = $_POST['code'];

$output = shell_exec('ps aux | grep "'.$SCRIPTNAME.'"');
$lines = explode("\n", trim($output));
//print_r($lines);

// Получаем PID и убиваем процесс
$CurDB = new DBPanel();
if (count($lines) > 2) {
  foreach ($lines as $line) {
      $parts = preg_split('/\s+/', $line);
      $pid = $parts[1]; // PID находится во втором столбце
      shell_exec("kill -9 $pid");


  }
  $time = date('d.m H:i:s');
  $SOURCE = \Bitrix\Main\Engine\CurrentUser::get()->getId();
  $in = array(
    "source	" => "'".$SOURCE."'",
    "script	" => "'".$CODE."'",
    "time	" => "'".$time."'",
    "status	" => "'STOP'",
  );
  $fields = implode(",", array_keys($in));
  $values = implode(",",$in);

  // $sql = "INSERT INTO wb_tech_log ($fields) VALUES ($values)";
  // $CurDB->query($sql);

  $userName = \Bitrix\Main\Engine\CurrentUser::get()->getLogin();
  $sql = "UPDATE sites_agents SET status = 'ABORTED', status_text = 'Прерван пользователем {$userName}',time_end = '{$time}' ,percent = '100' WHERE code = '{$CODE}'";
  $CurDB->query($sql);
  echo json_encode('stopped');
} else {
  $sql = "UPDATE sites_agents SET status = 'ABORTED', status_text = 'Прерван пользователем {$userName}',time_end = '{$time}' ,percent = '100' WHERE code = '{$CODE}'";
  $CurDB->query($sql);
  echo json_encode('not_run');
}
?>
