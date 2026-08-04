<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("iblock");


$ms = new MoyskladAPI('s1');
$arFilter = array(
  "momentFrom" => date("Y-m-d", strtotime("-1 year")),
  "momentTo" => date("Y-m-d"),
);

$res = $ms->getListProfitNew($arFilter);
var_dump( $res );
 ?>
