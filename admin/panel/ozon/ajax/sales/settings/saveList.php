<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$panel = new DBPanel;

if ( empty($_POST) ) throw new Exception("empty data");
if ( !isset($_POST['data']) ) throw new Exception("incorrect data");

$data = $_POST['data'];

foreach ( $data as $id => $values ){
  var_dump($id);
  var_dump($values);
  $where = [ 'column' => 'sale_id', 'operator' => '=', 'value' => $id ];
  $panel->update('ozon_sales_IP', $values, [$where]);
}
 ?>
