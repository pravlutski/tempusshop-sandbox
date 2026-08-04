<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $db;

$strSql = "SELECT * FROM wdhs_ozon_main_settings";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arSetting[$row['name']] = $row['value'];
}

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[$row['sale_id']] = $row;
}

foreach ($salesActive as $key => &$value) {
  $currentDate = new DateTime();
  $dateTimeF = new DateTime($value['date_start']);
  $dateTimeT = new DateTime($value['date_end']);
  if (DateTime::createFromFormat(DateTime::ATOM, $value['date_start']) !== false) {
      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $value['date_start']);
      $value['date_start'] = $dateFix->format('d.m.Y');
  }
  if (DateTime::createFromFormat(DateTime::ATOM, $value['date_end']) !== false) {
      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $value['date_end']);
      $value['date_end'] = $dateFix->format('d.m.Y');
  }
  $dateFrom = $dateTimeF->format('d.m.Y');
  $dateTo = $dateTimeT->format('d.m.Y');
  if ($currentDate < $dateTimeF) {
    $value['class'] = "table-success";
  } else if ($currentDate > $dateTimeF && $currentDate->diff($dateTimeT)->days > 5) {
    $value['class'] = "table-warning";
  } else if ($currentDate->diff($dateTimeT)->days <= 5) {
    $value['class'] = "table-danger";
  } else {
    $value['class'] = "table-primary";
  }
  $value['id'] = $value['sale_id'];
}


$conn['api_url'] = $arSetting['api_url'];
$conn['client_id'] = $arSetting['client_id'];
$conn['token'] = $arSetting['key'];

$ch = curl_init( $conn['api_url'] . '/v1/actions');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Api-Key:' . $conn['token'],
  'Client-Id:' . $conn['client_id'],
  'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);

// print_r($res);
// die();
foreach ($res['result'] as $key => $value){
  $search = mb_strtolower($value['title']);

  if (strpos($search, ' мегахиты') !== false) {
    $sort = 1;
  } else if (strpos($search, ' суперхиты') !== false) {
    $sort = 2;
  } else if (strpos($search, 'sh') !== false) {
    $sort = 2;
  } else if (strpos($search, ' хиты') !== false) {
    $sort = 3;
  } else if (strpos($search, ' хиты') !== false) {
    $sort = 4;
  } else if (strpos($search, 'лайт') !== false) {
    $sort = 5;
  } else {
    $sort = 6;
  }
  if (DateTime::createFromFormat(DateTime::ATOM, $value['date_start']) !== false) {
      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $value['date_start']);
      $value['date_start'] = $dateFix->format('d.m.Y');
  }
  if (DateTime::createFromFormat(DateTime::ATOM, $value['date_end']) !== false) {
      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $value['date_end']);
      $value['date_end'] = $dateFix->format('d.m.Y');
  }
  $currentDate = new DateTime();
  $dateTimeF = new DateTime($value['date_start']);
  $dateTimeT = new DateTime($value['date_end']);
  $dateFrom = $dateTimeF->format('d.m.Y');
  $dateTo = $dateTimeT->format('d.m.Y');
  if ($currentDate < $dateTimeF) {
    $class = "table-success";
  } else if ($currentDate > $dateTimeF && $currentDate->diff($dateTimeT)->days > 5) {
    $class = "table-warning";
  } else if ($currentDate->diff($dateTimeT)->days <= 5) {
    $class = "table-danger";
  } else {
    $class = "table-primary";
  }
  $arSale[] = [
        'id' => $value['id'],
        'sort' => $sort,
        'active' => $value['is_participating'],
        'name' => $value['title'],
        'date_start' => $dateFrom,
        'date_end' => $dateTo,
        'potencial' => $value['potential_products_count'],
        'perc' => '',
        'skd' => $value['skd'],
        'skd_fbo' => $value['skd_fbo'],
        'uses' => $value['participating_products_count'],
        'top_models' => '',
        'merg' => '---',
        'class' => $class
    ];
    print_r($arSale);
}
usort($arSale, function($a, $b) {
    return strtotime($a['date_start']) - strtotime($b['date_start']);
});
if (!empty($res['result'])) {?>
  <?if (empty($salesActive)) {?>
  <!--<table class="table table-striped" style="margin-top: 30px; margin-bottom: 30px;">
    <thead>
      <tr>
        <th scope="col">ПР-Т</th>
        <th scope="col">АКТИВНА</th>
        <th scope="col">ИМЯ</th>
        <th scope="col">ДАТА НАЧАЛА</th>
        <th scope="col">ДАТА КОНЦА</th>
        <th scope="col">НАЦЕНКА</th>
        <th scope="col">КОМ</th>
        <th scope="col">-КОМ</th>
        <th scope="col">ИСКЛЮЧИТЬ</th>
        <th scope="col">ДОСТУПНО</th>
        <th scope="col">УЧАСТВУВУЮТ<br></th>
        <th scope="col"><br></th>
      </tr>
    </thead>
    <tbody>-->

      <?php foreach ($arSale as $id => $v): ?>
        <tr id="<?=$v['id']?>"  class="<?=$v['class']?>">
          <th scope="row" ><input  style="width:50px;" name="data[<?=$v['id']?>][sort]" value="<?=$v['sort']?>" /></th>
          <td><input hidden name="data[<?=$v['id']?>][active]" value="<?=$v['active']?>" />
            <span>
              <?if ($v['active'] == "1"){echo "ДА";}else{echo "НЕТ";}?>
            </span>
          </td>
          <td><input hidden name="data[<?=$v['id']?>][name]" value="<?=$v['name']?>" /><span><?=$v['name']?></span></td>
          <td><input hidden name="data[<?=$v['id']?>][date_start]" value="<?=$v['date_start']?>" /><span><?=$v['date_start']?></span></td>
          <td><input hidden name="data[<?=$v['id']?>][date_end]" value="<?=$v['date_end']?>" /><span><?=$v['date_end']?></span></td>
          <td><input style="width:50px;" name="data[<?=$v['id']?>][perc]" value="<?=$v['perc']?>" /></td>
          <td><input style="width:50px;" name="data[<?=$v['id']?>][skd]" value="<?=$v['skd']?>" /></td>
          <td><input style="width:50px;" name="data[<?=$v['id']?>][skd_fbo]" value="<?=$v['skd_fbo']?>" /></td>
          <td><?=$v['merg']?></td>
          <td><input hidden name="data[<?=$v['id']?>][potencial]" value="<?=$v['potencial']?>" /><span><?=$v['potencial']?></span></td>
          <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px"><?=$v['top_models']?></span></td>
          <td><input hidden name="data[<?=$v['id']?>][uses]" value="<?=$v['uses']?>" /><span><?=$v['uses']?></span></td>
          <td><span class="delete_item" data-id="<?=$v['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
        </tr>
      <?php endforeach; ?>


  <!--  </tbody>
  </table>-->
  <?} else {?>
    <?php foreach ($arSale as $id => $v): ?>
      <?if (!isset($salesActive[$v['id']])) {?>
      <tr id="<?=$v['id']?>"  class="<?=$v['class']?>">
        <th scope="row" ><input  style="width:50px;" name="data[<?=$v['id']?>][sort]" value="<?=$v['sort']?>" /></th>
        <td><input hidden name="data[<?=$v['id']?>][active]" value="<?=$v['active']?>" />
          <span>
            <?if ($v['active'] == "1"){echo "ДА";}else{echo "НЕТ";}?>
          </span>
        </td>
        <td><input hidden name="data[<?=$v['id']?>][name]" value="<?=$v['name']?>" /><span><?=$v['name']?></span></td>
        <td><input hidden name="data[<?=$v['id']?>][date_start]" value="<?=$v['date_start']?>" /><span><?=$v['date_start']?></span></td>
        <td><input hidden name="data[<?=$v['id']?>][date_end]" value="<?=$v['date_end']?>" /><span><?=$v['date_end']?></span></td>
        <td><input style="width:50px;" name="data[<?=$v['id']?>][perc]" value="<?=$v['perc']?>" /></td>
        <td><input style="width:50px;" name="data[<?=$v['id']?>][skd]" value="<?=$v['skd']?>" /></td>
        <td><input style="width:50px;" name="data[<?=$v['id']?>][skd_fbo]" value="<?=$v['skd_fbo']?>" /></td>
        <td><?=$v['merg']?></td>
        <td><input hidden name="data[<?=$v['id']?>][potencial]" value="<?=$v['potencial']?>" /><span><?=$v['potencial']?></span></td>
        <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px"><?=$v['top_models']?></span></td>
        <td><input hidden name="data[<?=$v['id']?>][uses]" value="<?=$v['uses']?>" /><span><?=$v['uses']?></span></td>
        <td><span class="delete_item" data-id="<?=$v['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
      </tr>
      <?} else {?>
        <?if ($salesActive[$v['id']]['name'] != $v['name']) {?>
          <tr id="<?=$salesActive[$v['id']]['id']?>"  class="<?=$salesActive[$v['id']]['class']?>">
            <th scope="row" ><input  style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][sort]" value="<?=$salesActive[$v['id']]['sort']?>" /></th>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][active]" value="<?=$salesActive[$v['id']]['active']?>" />
              <span>
                <?if ($v['active'] == "1"){echo "ДА";}else{echo "НЕТ";}?>
              </span>
            </td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][name]" value="<?=$v['name']?>" /><span><?=$v['name']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][date_start]" value="<?=$salesActive[$v['id']]['date_start']?>" /><span><?=$salesActive[$v['id']]['date_start']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][date_end]" value="<?=$salesActive[$v['id']]['date_end']?>" /><span><?=$salesActive[$v['id']]['date_end']?></span></td>
            <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][perc]" value="<?=$salesActive[$v['id']]['perc']?>" /></td>
            <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][skd]" value="<?=$salesActive[$v['id']]['skd']?>" /></td>
            <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][skd_fbo]" value="<?=$salesActive[$v['id']]['skd_fbo']?>" /></td>
            <td><?=$salesActive[$v['id']]['merg']?></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][potencial]" value="<?=$salesActive[$v['id']]['potencial']?>" /><span><?=$salesActive[$v['id']]['potencial']?></span></td>
            <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px"><?=$v['top_models']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][uses]" value="<?=$salesActive[$v['id']]['uses']?>" /><span><?=$salesActive[$v['id']]['uses']?></span></td>
            <td><span class="delete_item" data-id="<?=$salesActive[$v['id']]['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
          </tr>
        <?} else {?>
          <tr id="<?=$salesActive[$v['id']]['id']?>"  class="<?=$salesActive[$v['id']]['class']?>">
            <th scope="row" ><input  style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][sort]" value="<?=$salesActive[$v['id']]['sort']?>" /></th>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][active]" value="<?=$salesActive[$v['id']]['active']?>" />
              <span>
                <?if ($v['active'] == "1"){echo "ДА";}else{echo "НЕТ";}?>
              </span>
            </td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][name]" value="<?=$salesActive[$v['id']]['name']?>" /><span><?=$salesActive[$v['id']]['name']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][date_start]" value="<?=$salesActive[$v['id']]['date_start']?>" /><span><?=$salesActive[$v['id']]['date_start']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][date_end]" value="<?=$salesActive[$v['id']]['date_end']?>" /><span><?=$salesActive[$v['id']]['date_end']?></span></td>
            <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][perc]" value="<?=$salesActive[$v['id']]['perc']?>" /></td>
            <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][skd]" value="<?=$salesActive[$v['id']]['skd']?>" /></td>
              <td><input style="width:50px;" name="data[<?=$salesActive[$v['id']]['id']?>][skd_fbo]" value="<?=$salesActive[$v['id']]['skd_fbo']?>" /></td>
            <td><?=$salesActive[$v['id']]['merg']?></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][potencial]" value="<?=$salesActive[$v['id']]['potencial']?>" /><span><?=$salesActive[$v['id']]['potencial']?></span></td>
            <td><input hidden name="data[<?=$v['id']?>][top_models]" value="<?=$v['top_models']?>" /><span style="width:50px"><?=$v['top_models']?></span></td>
            <td><input hidden name="data[<?=$salesActive[$v['id']]['id']?>][uses]" value="<?=$salesActive[$v['id']]['uses']?>" /><span><?=$salesActive[$v['id']]['uses']?></span></td>
            <td><span class="delete_item" data-id="<?=$salesActive[$v['id']]['id']?>" style="cursor:pointer;font-weight:500;color:red;">Удалить</span></td>
          </tr>
        <?}?>
      <?}?>
    <?php endforeach; ?>
  <?}?>
<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
