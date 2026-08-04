<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

GLOBAL $DB;

if (!isset($_POST['script']) || !isset($_POST['code'])) die('no script in table');

$userID = \Bitrix\Main\Engine\CurrentUser::get()->getID();
$SCRIPTNAME = 'php '.$_POST['script'] .' '.$userID. ' > /dev/null 2>&1 &';
$CODE = $_POST['code'];
print_r($SCRIPTNAME);

exec($SCRIPTNAME);

echo json_encode('runed');
die();
?>
