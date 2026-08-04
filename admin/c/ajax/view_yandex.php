<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
prent($_REQUEST);
?>

<iframe src="https://yandex.ru/maps/?mode=search&text=<?=urlencode($_REQUEST["courier_address"])?>" frameborder="0"></iframe>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');?>