<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$db = new DBPanel;

$id = intval($_POST['id']);

$errorTemplate = "<div class='alert alert-danger notification'>%s</div>";
$successTemplate = "<div class='alert alert-success notification'>%s</div>";

if ( $id <= 0 ) die( sprintf($errorTemplate, 'Ошибка обработки параметра id') );

$strSql = "DELETE FROM admin_utilities_list WHERE id = '{$id}'";
try{
  $db->query( $strSql );
}catch( Excpetion $e ){
  die( sprintf($errorTemplate, 'Ошибка удаления записи из базы') );
}
die( sprintf($successTemplate, 'Элемент успешно удалён') );
 ?>
