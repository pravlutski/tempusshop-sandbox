<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();
$CurDB = new DBPanel();

$query = "SELECT * FROM ozon_tech_log";
$conditions = [];

if (isset($_POST['source']) && !empty($_POST['source']) && $_POST['source'] != 'all') {
    $source = $_POST['source'];
    $conditions[] = "source = '$source'";
}

if (isset($_POST['script']) && !empty($_POST['script']) && $_POST['script'] != 'all') {
    $script = $_POST['script'];
    $conditions[] = "script = '$script'";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}
//print_r($query);
$result = $CurDB->query($query);

$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $string = '<span>'.$row['time'].'</span>';
  switch ($row['status']) {
    case 'RUN':
        $string .= '   <b style="color:#4fb317">Запуск скрипта</b>';
        break;
    case 'RERUN':
        $string .= '   <b style="color:#f49e2e">Перезапуск скрипта</b>';
        break;
    case 'STOP':
        $string .= '   <b style="color:#f24545">Остановка скрипта</b>';
        break;
    case 'SAVE':
        $string .= '   <b style="color:#19c1e8">Пересохраненение</b>';
        break;
    }
    $string .= ' <b><i>'.$row['script'].'</i></b>';
    $string .= ' произведено';
    switch ($row['source']) {
      case 'AVTO':
          $string .= ' автоматически';
          break;
      default:
          $rsUser = CUser::GetByID($row['source']);
          $arUser = $rsUser->Fetch();
          if (!empty($arUser)) {
            $string .= ' пользователем <a href="/bitrix/admin/user_edit.php?lang=ru&ID='.$arUser['ID'].'">'.$arUser['LOGIN'].'</a>';
            unset($rsUser);
            unset($arUser);
          } else {
            $string .= ' пользователем \'НЕ УСТАНОВЛЕНО\'';
          }
          break;

      }
        $arResult[] = $string;
}
$arResult = array_reverse($arResult);
unset($result);
unset($rows);?>

<?php foreach ($arResult as $key => $value): ?>
<?=$value?><br>
<?php endforeach; ?>
