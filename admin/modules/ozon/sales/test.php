<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Лог акций');?>

<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?
opcache_reset();

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[$row['sale_id']] = $row;
}

$action_id = $_GET['ID'];
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/sales/log_2.txt');
$arLog = json_decode($tmp,true);
print_r($arLog);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
