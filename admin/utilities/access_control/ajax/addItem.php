<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$db = new DBPanel;

$name = $_POST['name'];
$link = $_POST['link'];
$group = $_POST['group'];
$access = $_POST['access'];

$errorTemplate = "<div class='alert alert-danger notification'>%s</div>";
$successTemplate = "<div class='alert alert-success notification'>%s</div>";

$res = $db->select(['*'], 'admin_utilities_list')->where('link', $link)->make();

if ( !empty($res) ) die( sprintf($errorTemplate, 'Утилита с этой ссылкой уже существует') );
if ( !preg_match('/\/admin\/.+/', $link) ) die( sprintf($errorTemplate, 'Неверный формат ссылки') );
if ( empty($group) ) die( sprintf($errorTemplate, 'Ошибка обработки параметра группы') );

$insert[] = [
  'name' => $name,
  'link' => $link,
  'group_id' => intval($group),
];
try{
  $db->insert('admin_utilities_list', $insert);
} catch( Throwable $e ){
  die( sprintf($errorTemplate, 'Ошибка создания записи в таблице списка') );
}

if ( empty($access) ) die( sprintf($successTemplate, 'Утилита успешно создана. Права доступов не заданы') );

$utility = $db->select(['id'], 'admin_utilities_list')->where('name', $name)->where('link', $link)->make()[0]['id'];

$insert = [];
foreach ( $access as $id ){
  $insert[] = [
    'utility_id' => $utility,
    'user_group_id' => $id,
  ];
}

try{
  $db->insert('admin_utilities_access', $insert);
} catch( Throwable $e ){
  var_dump( $e->getMessage() );
  var_dump( $insert );
  die( sprintf($errorTemplate, 'Ошибка создания записи в таблице доступов') );
}

die( sprintf($successTemplate, 'Утилита успешно создана. Права доступов заданы') );

 ?>
