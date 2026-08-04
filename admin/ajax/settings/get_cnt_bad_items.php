<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$APPLICATION->IncludeComponent("adm:analiz.bad.items", "", array(), false);
$arControl = json_decode(CProSet::getOption("ITEMS_CONTROL"), true);
?>
<a href="/admin/analiz/?control_rrc2=Y"><span class="badge"><?=$arControl["CNT_RRC_ALL"]?><?/*&nbsp-&nbsp(<?=$arControl["COUNT"]?>)*/?></span></a>
<?
//prent($arControl);
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');