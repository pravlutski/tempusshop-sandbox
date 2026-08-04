<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$strSql = "SELECT * FROM sites_sort_list";
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );

$data = [];
foreach ( $rows as $row ){
    $data[] = [
      'id' => $row['id'],
      'value' => $row['group_name']
    ];
}

echo json_encode($data);
?>
