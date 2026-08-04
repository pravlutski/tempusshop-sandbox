<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if (isset($_POST)) {

  //$DB->Query("DELETE FROM wdhs_sales_pi WHERE pi_sets = 'main'", false, $err_mess.__LINE__);

  if ($_POST['top'] == 'Y') {

    $text = explode("\n",$_POST['text']);

    $in = array(
        "tops" => "'".json_encode($text)."'",
    );

    $update = [];
    foreach ($in as $key => $value) {
        $update[] = "$key = $value";
    }
    $update = implode(", ", $update);

    $sql = "UPDATE wdhs_sales_pi_new SET $update WHERE pi_sets = 'main'";

    $DB->Query($sql, $err_mess.__LINE__);
  } else {
    $in = array(
        "per_sebes" => "'".$_POST['per_sebes']."'",
        "unset" => "'".$_POST['unset']."'",
        "com" => "'".$_POST['com']."'",
        "min_profit" => "'".$_POST['min_profit']."'"
    );

    // Построим строку для UPDATE
    $update = [];
    foreach ($in as $key => $value) {
        $update[] = "$key = $value";
    }
    $update = implode(", ", $update);

    $sql = "UPDATE wdhs_sales_pi_new SET $update WHERE pi_sets = 'main'";

    $DB->Query($sql, $err_mess.__LINE__);
  }
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
