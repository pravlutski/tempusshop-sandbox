<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) throw new Exception("empty data");
if ( !isset($_POST['data']) ) throw new Exception("incorrect data");

$panel = new DBPanel;
$data = $_POST['data'];

foreach ( $data as $id => $values ){
  $where = [ 'column' => 'pi_sets', 'operator' => '=', 'value' => $id ];
  $panel->update('ozon_sales_pi_IP', $values, [$where]);
}
 ?>
