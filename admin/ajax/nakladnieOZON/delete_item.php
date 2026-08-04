<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
set_time_limit(0);

if (!empty($_POST)) {
  if ($_POST['type'] == 'nakl' and !empty($_POST['delValue'])) {
    $strSql = "DELETE FROM ozon_ci_nakladnie WHERE id = '{$_POST['delValue']}'";
  	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
    $strSql = "DELETE FROM ozon_ci_nakladnie_pos WHERE naklad_id = '{$_POST['delValue']}'";
  	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
  }
  if ($_POST['type'] == 'report' and !empty($_POST['delValue'])) {
    $strSql = "DELETE FROM ozon_ci_nk_reports WHERE id = '{$_POST['delValue']}'";
  	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
    $strSql = "DELETE FROM ozon_ci_nk_reports_position WHERE nk_id = '{$_POST['delValue']}'";
  	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
  }
}
