<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

if ( empty($_POST) || empty($_POST['brand_id']) || empty($_POST['platform']) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}

$status = true;

$rows = $panel->select(['*'], 'am_brand_profiles')->where('platform', $_POST['platform'])->where('brand_id', $_POST['brand_id'])->make();

if ( !$rows ){
  $insert = [ array_filter($_POST) ];
  $panel->insert('am_brand_profiles', $insert);
  die;
}

 ?>

<span class="alert alert-danger">Профиль для указанного бренда (<?=$_POST['brand_id']?>) уже существует</span>
