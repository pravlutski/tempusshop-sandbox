<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');

$auth = CMaxyssWb::settings_wb('WR');
var_dump($auth);



 ?>
