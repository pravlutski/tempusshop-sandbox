<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Детальный лог акций');?>
<?$APPLICATION->SetPageProperty("page_h1", "Детальный лог акций");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?


if (isset($_POST['cabinet']) && !empty($_POST['cabinet'])) {
  $cabinet = $_POST['cabinet'];
} else {
  $cabinet = 'IP';
}
global $DB;
global $USER;
$cabinetArr = array('IP','TI');
$CurDB = new DBPanel();
$arGroups = $USER->GetUserGroupArray();

$result = $CurDB->query("SELECT * FROM ozon_sales_{$cabinet}");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $salesActive[$row['sale_id']] = $row['name'];
}
$status = [
  'not-add' => '#f4b9b9',
  'delete' => '#f4b9b9',
  'stay' => '#a0ddf0',
  'stay-with-up' => '#a0ddf0',
  'stay-with-rrc' => '#a0ddf0',
  'add' => '#ddffa1',
];

$statusDesc = [
  'not-add' => 'не добавлен в акцию',
  'delete' => 'удален из акции',
  'stay' => 'остался в акции в идеальных условиях',
  'stay-with-up' => 'остался в акции с поднятием цены в акцию',
  'stay-with-rrc' => 'остался в акции, уперлись в РРЦ',
  'add' => 'добавлен в акцию',
];
?>

<div class="row">
  <form method="POST" action="" class="s-form" style="">
    <div class="input-group imput-s" style="max-width:500px;">
      <span class="input-group-text">Введите модель</span>
      <input type="text" name="model" class="form-control" aria-label="" value="<?if (!empty($_POST['model'])) { echo $_POST['model'];}?>">
    </div>
    <div class="input-group" style="max-width: 150px;
  display: flex;
  gap: 10px;
  align-items: center;">
      <label>Кабинет</label>
      <select class="form-select form-select-sm" name="cabinet" aria-label=".form-select-sm example">
        <?php foreach ($cabinetArr as $key => $value): ?>
          <option value="<?=$value?>" <?if ($value == $cabinet) { echo "selected";}?>><?=$value?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="input-group" style="max-width: 300px;">
      <select class="form-select form-select-sm" name="sale_id" aria-label=".form-select-sm example">
        <option value="0">---Фильтр по акциям---</option>
        <?php foreach ($salesActive as $key => $value): ?>
          <option value="<?=$key?>" <?if ($key == $_POST['sale_id']) { echo "selected";}?>><?=$value?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="input-group">
      <button type="submit" class="btn btn-warning">Поиск</button>
    </div>
  </form>
</div>

<?

$arResult = array();
if (isset($_POST['model']) && !empty($_POST['model'])) {
  $model = $_POST['model'];

  $directory = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/'.$cabinet.'/sales/detail/';
  $result = array();
  $tmp = array();

  $files = glob($directory . '*.txt');

  foreach ($files as $file) {
      $filename = basename($file, '.txt');
      $content = file_get_contents($file);
      $tmp[$filename] = $content;
  }

  if (empty($tmp)) {
      ?>
    <div class="col-sm-12" style="background-color: rgba(221, 111, 77, 0.42);margin-top: 15px;padding-top: 20px;
    padding-bottom: 20px;">
        <div class="header_table">
            <div class="title_header_table">
              ЛОГ ПУСТ
            </div>
        </div>
    </div>
    <?
  } else {

    foreach ($tmp as $date => $v) {
      $timedecode = explode('###',$v);

      foreach ($timedecode as $key => $value) {
        $res = json_decode($value,true);

      foreach ($res as $time => $arr) {
          if (isset($arr[$model])) {
            foreach ($arr[$model] as $sale_id => $data) {
              if (isset($_POST['sale_id']) && $_POST['sale_id'] != '0' && $_POST['sale_id'] != '' && $_POST['sale_id'] != $sale_id) {
                continue;
              }
              $arResult[] = [
                'date' => $date,
                'time' => $time,
                'model' => $model,
                'sale_id' => $sale_id,
                'data' => $data
              ];
        }
      }
      }
    }
  }
  }
  //print_r($arResult);
  //die();
}
if (count($arResult) > 0) {
  ?><h3 style="margin-top: 3rem;
  margin-bottom: 1rem;">МОДЕЛЬ: <?=$_POST['model']?></h2><?
}
$arResult =  array_reverse($arResult);
foreach ($arResult as $k => $v) {
  ?>
  <?//print_r($v);?>

  <div class="col-sm-12" style="background-color: <?=$status[$v['data']['status']]?>;margin-top: 15px;padding-top: 20px;
  padding-bottom: 20px;">
      <div class="header_table">
          <div class="title_header_table">
             <b><?=$v['date']?> <?=$v['time']?></b> - <?=$v['data']['sale']?><br>
             <b>Статус:</b> <i><?=$statusDesc[$v['data']['status']]?></i><br><br>
                <p style="font-size:14px;"><b>Цена по акции до начала итерации модуля:</b> <?=$v['data']['cur_sale_price']?><br>
                <b>Товар на фбо:</b> <?=$v['data']['fbo']?></p>
                <div class="price-analiz">
                  <div class="raschet">
                    <b></b><br>
                    <i>Цена</i><br>
                    <i>Маржа</i>
                  </div>
                  <div class="needed">
                    <b>Необходимо:</b><br>
                    <?=$v['data']['cur_price']?><br>
                    <?=$v['data']['cur_min_prof']?>
                  </div>
                  <div class="raschet">
                    <b>Расчет:</b><br>
                    <?=$v['data']['price_raschet']?><br>
                    <?=$v['data']['merg_raschet']?>
                  </div>
                  <div class="raschet">
                    <b>Себес:</b><br>
                    <?=$v['data']['sebes']?><br>
                  </div>
                </div>
                <div class="accordion" id="accordionExample<?=$k?>" style="max-width: 300px;margin-top: 1rem;">
                  <div class="accordion-item" style="background-color: unset;  border: none;  padding: 0;">
                    <h2 class="accordion-header" id="headingOne<?=$k?>">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne<?=$k?>" aria-expanded="false" aria-controls="collapseOne<?=$k?>" style="border: 0;
  background: unset;font-size: 16px;
  padding: 0;">
                        Ответа от озона
                      </button>
                    </h2>
                    <div id="collapseOne<?=$k?>" class="accordion-collapse collapse" aria-labelledby="headingOne<?=$k?>" data-bs-parent="#accordionExample<?=$k?>">
                      <div class="accordion-body" style="padding:0px;">
                        <blockquote>
                          <?
                          echo '<table>';
                          foreach ($v['data']['ozon_answer'] as $key => $value) {
                              echo '<tr>';
                              echo '<th>' . htmlspecialchars($key) . '</th>';
                              echo '<td>' . htmlspecialchars($value) . '</td>';
                              echo '</tr>';
                          }
                          echo '</table>';?>
                        </blockquote>
                      </div>
                    </div>
                  </div>
                </div>
          </div>
      </div>
  </div>
  <?
}

if (isset($_POST['model']) && !empty($tmp) && (empty($_POST['model']) || count($arResult) == 0)) {
  ?>
  <div class="col-sm-12" style="background-color: rgba(221, 111, 77, 0.42);margin-top: 15px;padding-top: 20px;
  padding-bottom: 20px;">
      <div class="header_table">
          <div class="title_header_table">
            МОДЕЛЬ <?=$_POST['model']?> НЕ НАЙДЕНА
          </div>
      </div>
  </div>
  <?
}
?>
<style>
table {
width: 50%;
border-collapse: collapse;
margin: 20px 0;
font-size: 16px;
font-family: Arial, sans-serif;
background-color: #d9d9d9;
  border-radius: 10px;
}
th, td {
border: 1px solid #ddd;
padding: 10px;
}
th {
text-align: left;
}
</style>
<style>

.arrow-4 {
    position: relative;
    cursor: pointer;
    margin:20px;
    width: 66px;
    height: 30px;
}
.arrow-4-left {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 0;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-left:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 6px 10px 10px 6px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}

.arrow-4-right {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 14px;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(-35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-right:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 10px 6px 6px 10px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}
.open .arrow-4-left:after {
    transform-origin: center center;
    transform: rotate(-70deg);
}
.open .arrow-4-right:after {
    transform-origin: center center;
    transform: rotate(70deg);
}

.tabled {
    display:none;
}
.header_table {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding-left: 20px;;
    font-size: 18px;;
}
.header_table span {
    font-weight: bold;
    font-size:24px;
}
</style>
<style>
.s-form {display: flex;gap: 1rem;}
.s-form .imput-s {max-width:500px;}
.price-analiz{
  display: flex;
  gap: 2rem;
  font-size: 14px;
}
.accordion-button:focus {
  z-index: 3;
  border-color: unset!important;
  outline: inherit!important;
  box-shadow: none!important;
}
.accordion-button:not(.collapsed)::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23212529'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e")!important;
}
.accordion-button:not(.collapsed) {
  color: inherit!important;
  background-color: inherit!important;
  box-shadow: none!important;
}
@media screen and (max-width: 767px) {
  .s-form {display: flex;gap: 1rem;flex-direction: column;}
  .s-form .imput-s {width: 100%}
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
