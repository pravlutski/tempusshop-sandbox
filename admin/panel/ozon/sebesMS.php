<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>

<?
    $APPLICATION->SetPageProperty("page_h1", "Актуальные себестоимости МС");
    $APPLICATION->SetTitle('Актуальные себестоимости МС - OZON модуль');

?>

<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");
$CurDB = new DBPanel();
$arResult = array();
$result = $CurDB->query("SELECT * FROM ms_turnover");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $arResult[$row['model']] = [
    'q' => intval($row['quantity']),
    'm' => $row['model'],
  ];
}
unset($result);
unset($rows);
$arResultPrint = array_chunk($arResult, intval(count($arResult)/3));


?>
<div class="row">


  <div class="col-sm-4">
  <table class="table table-striped">
          <thead>
          <tr>
            <th scope="col">Артикул</th>
            <th scope="col">Себес</th>
          </tr>
          </thead>
          <tbody>

          <?php foreach ($arResult as $vss): ?>
            <tr>
              <th scope="row"><?=$vss['m']?></th>
              <td><?=$vss['q']?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

</div>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
