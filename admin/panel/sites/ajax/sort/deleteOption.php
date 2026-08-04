<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;
//
if ( empty($_POST['id']) || !in_array( $_POST['site'], ['ru', 'by'] ) )
{
  die("EMPTY POST ERROR");
}

$table = 'sites_sort_list_' . $_POST['site'];

$arWhere[] = [
  'column' => 'id',
  'operator' => '=',
  'value' => $_POST['id']
  ];
//
  // var_dump($arWhere);
  // var_dump($table);
  // die;
  $strSql = "DELETE FROM {$table} WHERE id = '{$_POST['id']}'";
  // var_dump( $strSql );

  // var_dump($strSql);
  // die;

  $res = $dbPanel->query( $strSql );
  // var_dump($res);

  echo json_encode( ['status' => 'ok'] );
  header('Content-Type: application/json');

?>
