<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Св-ва стикеров - модуль сайтов');?>
<?$APPLICATION->SetPageProperty("page_h1", "Свойства \"стикеры\"");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>

<?
opcache_reset();

$CurDB = new DBPanel();
global $DB;
global $USER;
CModule::IncludeModule("iblock");

$result = $CurDB->query("SELECT * FROM site_props_log
WHERE PROPS = 'NEW'
ORDER BY id DESC
LIMIT 100");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $logNew[] = $row;
}
unset($result);
unset($rows);

$result = $CurDB->query("SELECT * FROM site_props_log
WHERE PROPS = 'HIT'
ORDER BY id DESC
LIMIT 100");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $logHit[] = $row;
}
unset($result);
unset($rows);

$strSql = "SELECT bitrix_id, model,sell_quantity FROM ci_top_models WHERE site_id = 's1'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arrayMS[$row["bitrix_id"]] = $row["sell_quantity"];
}


$arSelect = array("ID", "PROPERTY_HIT",'PROPERTY_CML2_ARTICLE');
$arFilter = Array(
	"IBLOCK_ID" => 16,
	"PROPERTY_HIT_VALUE" => "Да"
);

$res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
while($arFld = $res->GetNext()){
	$hitArray[$arFld["ID"]] = [
    'article' => $arFld["PROPERTY_CML2_ARTICLE_VALUE"],
    'count' => $arrayMS[$arFld["ID"]],
  ];
}
uasort($hitArray, function($a, $b) {
    return $b['count'] <=> $a['count'];
});

$arSelect = array("ID", 'DATE_CREATE',"PROPERTY_NEWEST",'PROPERTY_CML2_ARTICLE');
$arFilter = Array(
	"IBLOCK_ID" => 16,
	"PROPERTY_NEWEST_VALUE" => "Да"
);

$res = CIBlockElement::GetList(['DATE_CREATE' => 'DESC'], $arFilter, false, false, $arSelect);
while($arFld = $res->GetNext()){
	$newArray[$arFld["ID"]] =  [
      'article' => $arFld["PROPERTY_CML2_ARTICLE_VALUE"],
      'date' => $arFld["DATE_CREATE"],
  ];
}


?>
<div class="row">
  <div class="col-12">
    <div class="bd-example">
      <nav>
        <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
          <button class="nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-home" type="button" role="tab" aria-controls="nav-home" aria-selected="true">
            Популярные модели <?if (!empty($hitArray)) { print_r('('.count($hitArray).')'); }?>
          </button>
          <button class="nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-profile" type="button" role="tab" aria-controls="nav-profile" aria-selected="false">
            Новинки <?if (!empty($newArray)) { print_r('('.count($newArray).')'); }?>
          </button>
          <a href="/admin/panel/sites/settings.php" style="margin-left:auto !important; text-decoration:none"><button class="nav-link">
            Настройки
          </button></a>
        </div>
      </nav>
      <div class="tab-content" id="nav-tabContent">
        <div class="tab-pane fade active show" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab">
          <div class="row">
            <div class="col-6">
              <table class="table table-striped">
              <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Артикул</th>
                <th scope="col">Кол-во продаж</th>
              </tr>
              </thead>
              <tbody>
              <?
                  $i = 1;
                  foreach ($hitArray as $id => $vld) {
                    if (empty($article)) {
                      $article = 'Не задан';
                    }
                  ?>
                  <tr>
                    <th scope="row"><?=$i?></th>
                    <td><a href="https://tempusshop.ru/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=16&type=aspro_max_catalog&lang=ru&ID=<?=$id?>"><?=$vld['article']?></a></td>
                    <td><?=$vld['count']?></td>
                  </tr>
                <?
                $i++;
              }
              unset($article);
              unset($id);
              ?>


              </tbody>
            </table>
            </div>
            <div class="col-6">
              <h3>Последние изменения св-ва "Популярная модель"<h3>
              <div class="system-log" >
                <div id ="textarea" class="textarea">
                  <?
                  if (!empty($logHit)) {
                      foreach ($logHit as $value) {
                        echo '<pre>' . $value['DATE'] . ' ' .$value['ARTICLE']. ' измененно значение с '.$value['PREV'].' на '.$value['NEXT'].'</pre>';
                      }
                  } else {
                      echo 'Лог пуст';
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav-profile-tab">
          <div class="row">
            <div class="col-6">
              <table class="table table-striped">
              <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Артикул</th>
                <th scope="col">Дата создания</th>
              </tr>
              </thead>
              <tbody>
              <?
                  $i = 1;
                  foreach ($newArray as $id => $arrObj) {
                    if (empty($article)) {
                      $article = 'Не задан';
                    }
                  ?>
                  <tr>
                    <th scope="row"><?=$i?></th>
                    <td><a href="https://tempusshop.ru/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=16&type=aspro_max_catalog&lang=ru&ID=<?=$id?>"><?=$arrObj['article']?></a></td>
                    <td><?=$arrObj['date']?></td>
                  </tr>
                <?
                $i++;
              }?>
              </tbody>
            </table>
            </div>
            <div class="col-6">
              <h3>Последние изменения св-ва "Новинка"<h3>
              <div class="system-log" >
                <div id ="textarea" class="textarea">
                  <?
                  if (!empty($logNew)) {
                      foreach ($logNew as $value) {
                        echo '<pre>' . $value['DATE'] . ' ' .$value['ARTICLE']. ' измененно значение с '.$value['PREV'].' на '.$value['NEXT'].'</pre>';
                      }
                  } else {
                      echo 'Лог пуст';
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
.textarea {
  max-width: 800px;
  width: 100%;
  height: 70vh;
  border: 1px solid #ccc;
  padding: 5px;
  overflow-y: scroll;
  resize: none;
  font-family: inherit;
  font-size: 14px;
  background-color: #fff;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
