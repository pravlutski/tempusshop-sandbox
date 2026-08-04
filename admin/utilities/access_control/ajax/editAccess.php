<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$db = new DBPanel;

$utility = $_POST['id'];
$data = $_POST['data'];

$table = "admin_utilities_access";

if ( empty($id) || !is_numeric($id) ) die('Некорректный id правила');

$strSql = "DELETE FROM {$table} WHERE utility_id = '{$utility}'";
$db->query($strSql);

if ( empty($data) ) die;

$insert = [];
foreach ( $data as $group ){
  $insert[] = [
    'utility_id' => intval($utility),
    'user_group_id' => intval($group),
  ];
}

$db->insert( $table, $insert );
?>
