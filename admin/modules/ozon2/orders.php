<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки статусов заказов - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки статусов заказов");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">

<?php
global $DB;
$strSql = "SELECT * FROM wdhs_ozon_order_status_ti";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$established = [];
while ( $row = $result->Fetch() ){
  $established[ $row['status_oz'] ] = $row['status_bx'];
}
$res = CSaleStatus::GetList( [], [], false, false, [] );
$statusListBX = [];
while ( $row = $res->GetNext() ){
  $statusListBX[$row['ID']] = $row['NAME'];
}
$statusListOZ = [
  'acceptance_in_progress' => 'Идёт приёмка',
  'arbitration' => 'Арбитраж',
  'awaiting_approve' => 'Ожидает подтверждения',
  'awaiting_deliver' => 'Ожидает отгрузки',
  'awaiting_packaging' => 'Ожидает упаковки',
  'awaiting_registration' => 'Ожидает регистрации',
  'awaiting_verification' => 'Создано',
  'cancelled' => 'Отменено',
  'cancelled_from_split_pending' => 'Отменён из-за разделения отправления',
  'client_arbitration' => 'Клиентский арбитраж доставки',
  'delivering' => 'Доставляется',
  'driver_pickup' => 'У водителя',
  'not_accepted' => 'Не принят на сортировочном центре',
  'sent_by_seller' => 'Отправлено продавцом',
];
 ?>

<div id="container">
  <!-- <span class="tip"><b>Внимание!</b> Обрабатываются только те закзаы, для которых указано соответствие статуса</span> -->
  <div class="order-settings">
    <button id="save-orders" class="btn btn-warning">Сохранить настройки</button>
    <hr>
    <form id="status-list">
      <? foreach ( $statusListOZ as $codeOZ => $nameOZ): ?>
        <div class="status-row">
          <div class="status-name">
            <span><?echo $nameOZ;?></span>
          </div>
          <select class="status-select form-select" name="<?echo $codeOZ;?>">
            <option value="none">Не выбрано</option>
            <? foreach ( $statusListBX as $codeBX => $nameBX ):?>
              <? if ( $established[$codeOZ] && $established[$codeOZ] == $codeBX ):?>
                <option selected value="<?echo $codeBX;?>"><?echo "[{$codeBX}] " . $nameBX;?></option>
              <? else: ?>
                <option value="<?echo $codeBX;?>"><?echo "[{$codeBX}] " . $nameBX;?></option>
              <?endif;?>
            <? endforeach;?>
          </select>
        </div>
      <? endforeach; ?>
    </form>
  </div>
  <hr>
  <div class="export-block">
    <h2>Экспорт заказов</h2>
    <form id="export-form" class="" action="" method="post">
      <div class="date-row">
        <span class="label-span">C</span>
        <input type="date" class="input-row form-select" name="dateFrom" value="">
        <span class="label-span">по</span>
        <input type="date" class="input-row form-select" name="dateTo" value="">
      </div>
      <div class="mode-row">
        <span class="label-span">Формат</span>
        <select class="input-row form-select" name="mode">
          <option value="csv">CSV</option>
          <option value="xlsx">XLSX</option>
        </select>
      </div>
    </form>
    <div class="e-btns-block">
      <a id="export-report" class="btn btn-warning">Сформировать отчет</a>
      <!-- <a href="#" class="download-btn btn btn-primary">Сохранить CSV</a> -->
      <!-- <div class="download-btn error-msg">Не указан кабинет</div> -->
    </div>
  </div>
  <hr>
  <div class="order-logs">
    <h2>Брёвна</h2>
    <label for="order-date">Дата лога</label>
    <input type="date" id="order-date" class="log-date-picker" value="">
    <label for="mode">Отображение</label>
    <select id="mode" class="log-date-picker" name="">
      <option value="full" selected>Все сообщения</option>
      <option value="short" selected>Создание заказов/ошибка</option>
    </select>
    <button type="button" id="show-logs" class="btn btn-warning">Показать</button>
    <hr>
    <div class="log-text">

    </div>
  </div>
</div>

 <style>
 .ui-front {
   z-index: 1000000000!important;
 }
 #container{
   border: 1px solid #dbdcdc;
   border-radius: 4px;
 }
 .tip{
   /* margin: 0px 0px 0px 20px; */
 }
 #save-orders{
   display: flex;
   margin-left: auto;
   margin-top: 15px;
   margin-right: 15px;
 }
 #status-list{
   padding: 0px 0px 20px 20px;
   display: flex;
   flex-direction: column;
   width: 47%;
 }
 .status-row{
   display: flex;
   flex-direction: row;
   width: 100%;
   margin-top: 20px;
 }
 .order-logs{
   padding-left: 20px;
   padding-bottom: 20px;
 }
 .log-date-picker{
   display: flex;
   width: 20%;
   height: fit-content;
   margin-top: 15px;
   margin-bottom: 15px;
 }
 label{
   font-weight: bolder;
 }
 .export-block{
   padding: 20px;
 }
 #export-form{
   display: flex;
   flex-direction: column;
   width: 40%;
   margin-top: 20px;
 }
 .date-row, .cab-row, .mode-row{
   display: flex;
   flex-direction: row;
   gap: 10px;
   margin-bottom: 15px;
 }
 .cab-row, .mode-row{
   width: 71%;
 }
 .e-btns-block{
   margin-top: 20px;
   display: flex;
   flex-direction: row;
   width: 28.5%;
 }
 .error-msg{
   background-color: #eb4034;
   color: white;
 }
 .download-btn{
   margin-left: auto;
 }
 .cab-row .input-row, .mode-row .input-row{
   margin-left: auto;
 }
 .input-row{
   max-width: 185px;
   margin-top: 5px;
 }
 .label-span{
   font-weight: bolder;
   margin-top: auto;
   margin-bottom: auto;
 }

 #export-report, .download-btn{
   display: block;
   padding: 5px;
   border-radius: 4px;
   max-width: 220px;
 }
 .download-btn{
   margin-left: auto;
 }
 #show-logs{

 }
 .status-select{
   display: flex !important;
   margin-left:auto;
   width: 45% !important;
   height: fit-content;
   margin-bottom: auto;
   margin-top: auto;
 }
 .status-name{
   display: flex;
   width: 45%;
   background-color: #6c757d;
   text-align: center;
   color: white;
   padding: 6px 8px 6px 8px;
   border-radius: 4px;
 }
 .status-name span{
   display: inline-block;
   margin: 0 auto;
 }
 .log-status, .log-main{
   margin-top: 30px;
   border: 1px solid black;
   padding: 20px;
 }
 .log-main .text{
   display: flex;
   margin-top: 20px;
   max-height: 600px;
   width: 100%;
   overflow-y: auto;
 }
 .log-status .text{
   display: flex;
   margin-top: 20px;
   max-height: 600px;
   width: 100%;
   overflow-y: auto;
 }
 pre{
   width: 100%;
 }
</style>
<script type="text/javascript">
  $.ajax({
    url: '/admin/modules/ozon2/ajax/get_order_log.php',
    method: 'POST',
    data: {date: new Date().toJSON().slice(0, 10), mode: 'short'},
    success: function(response){
      $('.log-text').html(response);
      $('#order-date').val(new Date().toJSON().slice(0, 10)).change();
    }
  })
  $(document).on('click', '#save-orders',function(e){
    e.preventDefault();
    $.ajax({
      url: '/admin/modules/ozon2/ajax/save_order_status.php',
      method: 'POST',
      data: $('#status-list').serialize(),
      success: function(response){
        alert('settings applied');
        console.log('settings applied');
      },
      error: function(response){
        alert('system error');
        console.log('system error');
      }
    })
  })
  $(document).on('click', '#export-report',function(e){
    e.preventDefault();
    $('.download-btn').remove();
    $.ajax({
      url: '/admin/modules/ozon2/ajax/make_orders_report.php',
      method: 'POST',
      data: $('#export-form').serialize(),
      success: function(response){
        $('.e-btns-block').append(response);
        console.log('report created');
      },
      error: function(response){
        alert('system error');
        console.log('system error');
      }
    })
  })
  $(document).on('click', '#show-logs', function(e){
    e.preventDefault();
    $.ajax({
      url: '/admin/modules/ozon2/ajax/get_order_log.php',
      method: 'POST',
      data: {date: $('#order-date').val(), mode: $('#mode').val()},
      success: function(response){
        $('.log-text').html(response);
      }
    })
  })
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
