<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
global $USER;

global $DB;
//tmp


$momentFrom = $_REQUEST["date_from"];
$momentTo = $_REQUEST["date_to"];
$momentFrom = '2023-08-08';
$momentTo = '2023-09-08';


$ms = new MoyskladAPI('msk');
$action = $_REQUEST['action'];

//$resDif = $ms->customRequest("https://online.moysklad.ru/api/remap/1.2/report/turnover/all?momentFrom={$momentFrom}&momentTo={$momentTo}");
$resDif = $ms->getWarehouses();
print_r($resDif);
