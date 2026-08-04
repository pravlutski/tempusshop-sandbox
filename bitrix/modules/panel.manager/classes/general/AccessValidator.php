<?php
// При редактировании лучше отключить его в init.php
class AccessValidator
{
  private static string $message = '<div class="alert alert-danger">Доступ запрещен. Обратитесь к администратору</div>';

  public static function checkIfAllowed()
  {
    CModule::IncludeModule('panel.manager');
    global $USER;
    $db = new DBPanel;
    $uri = $_SERVER['REQUEST_URI'];
    $strSql = "SELECT access.user_group_id
      FROM admin_utilities_access as access
      JOIN admin_utilities_list as list
      ON access.utility_id = list.id
      WHERE list.link LIKE '%{$uri}%'";

    $result = $db->query( $strSql );
    $rows = $db->fetchAll($result);
    $allowed = array_map(function($item){
      return $item['user_group_id'];
    }, $rows);

    if ( $USER->isAdmin() ) return true;
    if ( empty($allowed) ) return true;

    $userGroups = $USER->GetUserGroupArray();
    $accessFlag = count( array_intersect($userGroups, $allowed) ) > 0;

    if ( $accessFlag ) return true;

    die( self::$message );
  }
}
 ?>
