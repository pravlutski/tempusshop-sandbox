<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
$cabinet = trim(htmlspecialchars($_POST["cabinet"]));
$date = trim(htmlspecialchars($_POST["date"]));

$filepath = "/home/bitrix/logs/{$cabinet}/UpdateOrderFBO/.{$date}_loggerErrors";
?>
<?if($cabinet && $date && file_exists($filepath)):?>
	<pre><?=file_get_contents($filepath)?></pre>
<?else:?>
	<h2 class="color"><span>Не удалось получить ошибки(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
<?endif?>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
