<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("panel.manager");
$ms_site = 's2';
$token = CProSet::getOption("MS_ACCESS_TOKEN_s2");

var_dump($token);
 ?>
