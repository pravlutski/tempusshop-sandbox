<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');
print_r('start');
$directory = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/TI/sales/detail';

$daysBack = 5;

$currentDate = new DateTime();

for ($i = 0; $i <= $daysBack; $i++) {
    $dates[] = $currentDate->format('d.m.Y');
    $currentDate->modify('-1 day');
}

if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != '.' && $file != '..') {
            $filePath = $directory . '/' . $file;
						$dateFileName = explode('.txt',$file);
						if (!in_array($dateFileName[0],$dates)) {
							print_r($dateFileName[0]);
							unlink($filePath);
						}
					  unset($dateFileName);
        }
    }
    closedir($handle);
}
?>
