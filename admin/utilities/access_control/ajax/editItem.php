<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$db = new DBPanel;

$utility_id = intval($_POST['id']);

$errorTemplate = "<div class='alert alert-danger notification'>%s</div>";
$successTemplate = "<div class='alert alert-success notification'>%s</div>";

$postData = [
  'id' => $utility_id,
  'name' => $_POST['name'],
  'link' => $_POST['link'],
  'group_id' => intval($_POST['group']),
  'access' => $_POST['access'],
];

if ( $utility_id <= 0 ) die( sprintf($errorTemplate, 'Неккоректный ID элемента') );

$res = $db->select(['*'], 'admin_utilities_list')->where( 'id', $utility_id )->make();
if ( count($res) == 0 ) die( sprintf($errorTemplate, 'Данного элемента не существует') );

$utility = $res[0];

$update = [];
foreach ( $postData as $key => $value ){
  if ( $key == 'access' ) continue;
  if ( $key == 'link' && !preg_match('/\/admin\/.+/', $value) ) die( sprintf($errorTemplate, 'Некорректный формат ссылки') );
  if ( $utility[$key] != $value ){
    $update[$key] = $value;
  }
}
if ( !empty($update) ){
  $where[] = [
    'column' => 'id',
    'operator' => '=',
    'value' => $utility_id,
  ];
  try{
    $db->update( 'admin_utilities_list', $update, $where );
  }catch( Throwable $e ){
    die( sprintf($errorTemplate, 'Ошибка обновления списка утилит') );
  }
}

$insert = [];
foreach ( $postData['access'] as $id ){
  if ( intval($id) <= 0 ) continue;
  $insert[] = [
    'utility_id' => $utility_id,
    'user_group_id' => intval($id)
  ];
}

if ( empty($insert) && !empty($postData['access']) ) die( sprintf($errorTemplate, 'Ошибка обновления таблицы доступов (Шаг 1)') );

$strSql = "DELETE FROM admin_utilities_access WHERE utility_id = '{$utility_id}'";
try{
  $db->query( $strSql );
}catch( Exception $e ){
  die( sprintf($errorTemplate, 'Ошибка обновления таблицы доступов (Шаг 2)') );
}

try{
  $db->insert('admin_utilities_access', $insert);
}catch( Exception $e ){
  die( sprintf($errorTemplate, 'Ошибка обновления таблицы доступов (Шаг 3)') );
}


die( sprintf($successTemplate, 'Данные успешно обновлены') );
 ?>
