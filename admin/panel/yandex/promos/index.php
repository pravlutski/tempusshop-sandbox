<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки акций - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки акций");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">


<?
opcache_reset();
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

UIProcessor::init();
$data = UIProcessor::data();
$config = Config::instance();

$settings = $data->settings()->getPromosSettings('WR')[0];
?>

<div class="row">
  <div class="col-md-6 col-sm-12">
    <div class="card">
      <div class="card-body">
        <nav>
          <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
            <? foreach ( $config->getAllCabinets() as $cab => $name): ?>
              <button class="nav-link nav-bold <?echo $cab == 'WR' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cab?>" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
            <? endforeach; ?>
          </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
          <div class="global-settings">
            <div style="display:flex; flex-direction: row">
              <form id="calc-settings-form-WR" action="" method="post">
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Мин. маржа, ₽</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="min_profit" style="" value="<?=$settings['min_profit']?>">
                </div>
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Мин. маржа, %</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="min_margin" value="<?=$settings['min_margin']?>">
                </div>
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Комиссия, %</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="commission" value="<?=$settings['commission']?>">
                </div>
                <input type="hidden" name="cabinet" value="WR">
              </form>
              <button class="btn btn-warning save-calc-settings" style="display: flex; margin-left:auto; height:fit-content" value="WR">Сохранить</button>
            </div>
          </div>
        </div>
    </div>
  </div>
</div>

<div class="col-md-6 col-sm-12" >
  <div class="faq-block alert alert-success">
    <div class="faq-body faq-short">
      <div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: auto!important">
        <div>
          <b>На текущей странице Вы можете управлять списком и настройкам акций Яндекс Маркета</b>
        </div>
        <hr>
        <div class="">
          <p>Для актуализации списка акций исользуйте кнопку <b>Обновить список</b>. Список пополнится новыми акциями и актуализируется список уже загруженных исходя из даты их активности на маркете</p>
          <p><b>Внимание! </b> Акция не будет обработана модулем, если значение сортировки (<i>1-я колонка</i>) не установлено</p>
        </div>
        <div>
          <hr>
          <p><b>Работа с акциями</b></p>
          <p><b>MAP</b> - режим работы, при котором товар попытается использовать базовую цену товара для вхождения в акцию. В случае, если цена выше максимальной цены вхождения, модуль посчитает экономику и какую скидку необходимо дать, чтобы получить цену равной максимальной цене вхождения. Полученное значение не может превышать указанное в колонке <b>"Скидка"</b>.</p>
          <p><b>FIX</b> - режим работы, при котором первым делом будет предпринята попытка войти в акцию с фиксированной скидкой (колонка <b>"Скидка"</b>) на товар. В случае, если цена с установленной скидкой выше максимальной цены вхождения модуль перейдет в режим работы <b>MAP</b>.</p>
          <p><b>NoMAP</b> - режим работы, необходимый для прозрачной обработки специфического типа акций, которые не имеют максимальной цены вхождения. В таком случае остается только проверка на вхождение по приоритету</p>
        </div>



      </div>
    </div>
    <hr>
    <div class="expand-btn-block">
      <button class="expand-faq-btn">Раскрыть справку ...</button>
    </div>
  </div>
</div>

<div class="promos-list card mt-3">
  <div class="card-body promos-list-block" style="padding: 5px">
    <form id="" action="" method="post">
      <table style="width: 100%">
        <thead style="">
          <th>#</th>
          <th>Название</th>
          <th>Начало</th>
          <th>Конец</th>
          <th>Скидка</th>
          <th>Режим</th>
          <th></th>
        </thead>
        <tbody>
          <tr class="alert alert-success">
            <td><input type="text" class="form form-control" style="width:40px" name="" value="#" disabled></td>
            <td><span>Загружаем список...</span></td>
            <td><span>2026-04-01 00:00:00</span></td>
            <td><span>2026-04-01 00:00:00</span></td>
            <td><input class="form form-control" type="text" style="width:70px" name="" value="99" disabled></td>
            <td>
              <select class="form form-select" name="" disabled>
                <option value="">MAP</option>
                <option value="">FIX</option>
              </select>
              </td>
            <td><button style="display:flex; margin-left:auto" class="btn btn-danger" disabled>Удалить</button></td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
  <hr>
  <div style="display: flex; flex-direction: row; gap: 10px; padding-bottom: 10px;">
    <button class="btn btn-primary update-promos-btn" value="WR" style="display:flex; margin-left:auto;">Обновить список</button>
    <button class="btn btn-warning save-promos-btn" value="WR" style="display:flex;">Сохранить список</button>
  </div>
</div>

<div class="notif-footer" style="width: 100%">
  <?require("../include/completeToast.php");?>
</div>

<style media="screen">
tr{
  border-bottom: 1px solid rgba(0,0,0,0.15) !important;
}
th{
  padding-top: 7px !important;
  padding-bottom: 20px !important;
}
td{
  padding-top: 9px !important;
  padding-bottom: 9px !important;
}
.expand-faq-btn{
  width: 100%;
  color: green;
  background: transparent;
  border:none;
}
.expand-faq-btn:hover{
  color: #056608
}
.faq-short{
  height: 128px;
  overflow-y: hidden;
}
.faq-expanded{
  height: auto;
  overflow-y: hidden;
}
.helper{
  padding: 0 !important;
  border: none !important;
}
</style>

<script type="text/javascript">
var faq = false;
$(document).on('click', '.expand-faq-btn', function(e){
  e.preventDefault();
  if ( !faq ){
    $('.faq-body').removeClass('faq-short');
    $('.faq-body').addClass('faq-expanded');
    $('.expand-faq-btn').html('Свернуть справку ...');
    faq = true;
    return;
  }
  $('.faq-body').addClass('faq-short');
  $('.faq-body').removeClass('faq-expanded');
  $('.expand-faq-btn').html('Раскрыть справку ...');
  faq = false;
})
</script>

<script src="<?=SITE_TEMPLATE_PATH?>/js/promos.js"></script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
