<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;
$source = $_POST['source'];
$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);

echo json_encode( ['count' => $resultDB->SelectedRowsCount()] );

 ?>
