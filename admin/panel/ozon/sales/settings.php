<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки акций - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки акций");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">


<?
opcache_reset();
$cabinets = [
  'IP' => 'Кабинет "IP"',
  // 'TI' => 'Кабинет "TI"',
];
$panel = new DBPanel;
$settings = $panel->select(['*'], 'ozon_sales_pi_IP')->make()[0] ?? [];
?>

<div class="row">
  <div class="col-md-6 col-sm-12">
    <div class="card">
      <div class="card-body">
        <nav>
          <div class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
            <? foreach ( $cabinets as $cab => $name): ?>
              <button class="nav-link nav-bold <?echo $cab == 'IP' ? 'active' : '';?>" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-<?=$cab?>" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><?=$name?></button>
            <? endforeach; ?>
          </div>
        </nav>
        <div class="tab-content" id="nav-tabContent-<?=$cab?>">
          <div class="global-settings">
            <div style="display:flex; flex-direction: row">
              <form id="settings-form" action="" method="post">
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Мин. маржа, ₽</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="data[main][min_profit]" style="" value="<?=$settings['min_profit']?>">
                </div>
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Мин. маржа, %</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="data[main][min_profit_perc]" value="<?=$settings['min_profit_perc']?>">
                </div>
                <div style="display:flex; flex-direction: row">
                  <span class="input-group-text" id="basic-addon3" style="width: 50%;">Комиссия, %</span>
                  <input class="form form-control" placeholder="0" style="width: 50%;" name="data[main][com]" value="<?=$settings['com']?>">
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
  <div class="alert alert-success alert-dismissible fade show helper" role="alert" style="height: auto!important">
    <div>
      На текущей странице Вы можете управлять списком и настройкам акций OZON
    </div>
    <hr>
    <div>
      <b>Скидка</b> - максимальная скидка, с которой товар может участвовать<br>
      <b>Буст</b> - максимальное значение, получаемого товаром, бустинга в акциях типа "Эластичный бустинг"<br>
      <b>Режим</b> - параметр, которое выступает ограничением минимальной цены, с которой товар может участвовать (<b>MAP</b> - Max Action Price, <b>FIX</b> - установленная скидка)<br>
    </div>
  </div>
</div>

<div class="promos-list card mt-3">
  <div class="card-body promos-list-block" style="padding: 5px">
    <form id="sales-list-form" action="" method="post">
      <table style="width: 100%">
        <thead style="">
          <th style="padding-left: 15px">#</th>
          <th>Название</th>
          <th>Начало</th>
          <th>Конец</th>
          <th>Буст</th>
          <th>Скидка</th>
          <th>Режим</th>
          <th style="text-align:center">Участвует</th>
          <th></th>
        </thead>
        <tbody id="sales-list">
          <tr class="alert alert-success updating">
            <td><input type="text" class="form form-control" style="width:40px" name="" value="9" disabled></td>
            <td><span>Загружаем список...</span></td>
            <td><span>2026-04-01 00:00:00</span></td>
            <td><span>2026-04-01 00:00:00</span></td>
            <td><input class="form form-control" type="text" style="width:70px" name="" value="99" disabled></td>
            <td><input class="form form-control" type="text" style="width:70px" name="" value="99" disabled></td>
            <td>
              <select class="form form-select" name="" disabled>
                <option value="">MAP</option>
                <option value="">FIX</option>
              </select>
            </td>
            <td style="text-align:center"><span>9999</span></td>
            <td><button style="display:flex; margin-left:auto" class="btn btn-danger" disabled>Удалить</button></td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
  <hr>
  <div style="display: flex; flex-direction: row; gap: 10px; padding-bottom: 10px;">
    <button class="btn btn-primary update-promos-btn" value="IP" style="display:flex; margin-left:auto;">Загрузить список</button>
    <button class="btn btn-warning save-promos-btn" value="IP" style="display:flex;" disabled>Сохранить список</button>
  </div>
</div>
<div class="notif-footer" style="width: 100%">
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
.will-be-deleted{
  filter: blur(4px);
}
.updating{
  filter: blur(5px);
  background-color: rgba(0,0,0,0.2) !important;
}
.loading-counter{
  filter: blur(5px);
  background-color: rgba(0,0,0,0.8) !important;
  content: '....';
}


.loading-counter::after {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  border: none;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.3) 30%,
    rgba(255, 255, 255, 0.6) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer 1.6s infinite ease-in-out;
  content: '....';
}

.updating::after {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  border: none;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.3) 30%,
    rgba(255, 255, 255, 0.6) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer 1.6s infinite ease-in-out;
  content: '';
}

@keyframes shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
<?php require('../include/completeToast.php'); ?>
<script type="text/javascript">
  const ajax_path = "/admin/panel/ozon/ajax/sales/settings/";

  function playSuccessSound() {
    var audio = document.getElementById("successSound");
    audio.play();
  }

  function showSuccessToast() {
    var t_comp = document.getElementById('complete-toast');
    toast = new bootstrap.Toast( t_comp );
    toast.show();
  }

  function showErrorToast() {
    var t_comp = document.getElementById('error-toast');
    toast = new bootstrap.Toast( t_comp );
    toast.show();
  }

  function getList()
  {
    $.ajax({
      url: ajax_path + 'getList.php',
      method: "POST",
      success: function(response){
        $('#sales-list').html( response );
        $('.save-promos-btn').attr('disabled', false);
        countActiveItems();
        // showSuccessToast();
        // playSuccessSound();
      },
      error: function(response){
        // alert('Не удалось получить список акций');
        showErrorToast();
      }
    });
  }

  function saveList()
  {
    $.ajax({
      url: ajax_path + 'saveList.php',
      method: "POST",
      data: $('#sales-list-form').serialize(),
      success: function(response){
        showSuccessToast();
        playSuccessSound();
      },
      error: function(response){
        // alert('Ошибка сохранения настроек');
        showErrorToast();
      }
    })
  }

  function saveSettings()
  {
    $.ajax({
      url: ajax_path + 'saveSettings.php',
      method: "POST",
      data: $('#settings-form').serialize(),
      success: function(response){
        showSuccessToast();
        playSuccessSound();
      },
      error: function(response){
        // alert('Ошибка сохранения настроек');
        showErrorToast();
      }
    });
  }

  function updateList()
  {
    $('.sale-item').addClass('updating');
    $.ajax({
      url: ajax_path + 'updateList.php',
      method: "POST",
      success: function(response){
        getList();
        $('.sale-item').rempveClass('updating');
        showSuccessToast();
        playSuccessSound();
      },
      error: function(response){
        alert('Не удалось обновить список акций');
        $('.sale-item').removeClass('updating');
        showErrorToast();
      }
    })
  }

  function deleteItem( id )
  {
    $('#' + id).addClass('updating');
    $.ajax({
      url: ajax_path + "deleteItem.php",
      method: "POST",
      data: {sale_id: id},
      success: function(response){
        $('#' + id).remove();
        showSuccessToast();
        // playSuccessSound();
      },
      error: function(response){
        // alert('Не удалось удалить элемент');
        showErrorToast();
      }
    })
  }

  function countActiveItems()
  {
    $('.items-counter').addClass('loading-counter');
    $.ajax({
      url: ajax_path + 'countActiveItems.php',
      method: "POST",
      success: function(response){
        var result = $.parseJSON(response);
        result.forEach((sale)=>{
          $('.' + sale.id + '-counter').html(sale.count);
        })
        $('.items-counter').removeClass('loading-counter');
      },
      error: function(response){
        console.log("Cannot count active items");
      }
    })
  }

  $(document).on('click', '.delete-btn', function(e){
    e.preventDefault();
    var id = $(this).attr('data-id');
    deleteItem( id );
  })

  $(document).on('click', '.update-promos-btn', function(){
    updateList();
  })

  $(document).on('click', '.save-calc-settings', function(){
    saveSettings();
  })

  $(document).on('click', '.save-promos-btn', function(){
    saveList();
  })

  getList();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
