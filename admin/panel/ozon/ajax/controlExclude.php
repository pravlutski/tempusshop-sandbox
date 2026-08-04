<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager'))return;
if (isset($_POST) && !empty($_POST['cabinet'])) {
  $CurDB = new DBPanel();
  //$DB->Query("DELETE FROM wdhs_sales_pi WHERE pi_sets = 'main'", false, $err_mess.__LINE__);

  if ($_POST['top'] == 'Y') {

    $text = explode("\n",$_POST['text']);

    $in = array(
        "models" => "'".json_encode($text)."'",
    );

    $update = [];
    foreach ($in as $key => $value) {
        $update[] = "$key = $value";
    }
    $update = implode(", ", $update);

    $result = $CurDB->query("UPDATE ozon_control_exclude SET {$update} WHERE cabinet = '{$cabinet}'");

  }
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
