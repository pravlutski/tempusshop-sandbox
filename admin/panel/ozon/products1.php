<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки товаров - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки товаров");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?
?>
<div id="topModelsModal_TI" class="modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ТОП ОЗОНА</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <textarea id="topModelsText_<?=$cabinet?>" class="form-control" rows="20" placeholder="Введите данные..."><?=$tops?></textarea>
        <input type="hidden" id="topModelsID_<?=$cabinet?>" value="<?=$cabinet?>"/>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
        <button type="button" style="display:none;" id="saveTopModels_<?=$cabinet?>" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<div class="tabs-block">
  <button class="tab-btn is-selected" value="main-settings-container">Основные настройки</button>
  <button class="tab-btn" value="collection-settings-container">Склейки</button>
</div>


<?require('include/collection.php');?>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="coplete_toast" class="toast hide align-items-center bg-warning" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="...">
      <strong class="me-auto">Tempus Ozon Module</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
    </div>
    <div class="toast-body">
      Операция выполнена успешно
    </div>
  </div>
</div>

  <?require('include/mobile.php');?>

<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
<style>
.full_width{
  max-width: 100%!important;
}
.modal-dialog-scrollable .modal-body {
  overflow-y: auto!important;
}
.modal-dialog-scrollable .modal-content {
  overflow: auto!important;
}
.tab-btn{
  width: 240px;
  padding: 10px;
  background-color: #6c757d;
  color: white;
  border:none;
}
.tab-btn:hover{
  font-weight: bolder;
}
.is-selected{
  background-color: #ffca2c !important;
  font-weight: bolder;
  color: black;
}
@media (max-width: 867px){
  .p-head{
    display: flex;
    flex-direction: column;
  }
  .p-head button{
    width: 100% !important;
  }
  .list-group{
    padding: 10px !important;
  }
  .att_li{
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,0,0,0.25) !important;
    gap: 10px;
    margin-bottom: 10px;
    padding-bottom: 10px !important;
  }
  .att_li div{
    width: 100% !important;
  }
}
</style>
<script>
$('#topModels_TI').click(function(e) {
  e.preventDefault();
  $('#topModelsModal_TI').modal('show');
});
$(document).on('click', '.tab-btn', function(e){
  $('.tab-btn').removeClass('is-selected');
  $('.main-settings-container').hide();
  $('.collection-settings-container').hide();
  $('.' + $(this).val() ).show();
  $(this).addClass('is-selected');
});
$(document).ready(function() {
  $('.att_set_value').each(function() {
    var attId = $(this).attr("data-att-id");
    var btId = $(this).attr("data-bt-id");
    $(this).autocomplete({
      source: function(request, response) {
        $.ajax({
          method: 'GET',
          url: '/admin/modules/ozon2/ajax/get_directory_values.php',
          dataType: 'json',
          data: { input: request.term, id: attId },
          success: function(data) {
            response($.map(data, function(item) {
              return {
                label: item.value,
                value: item.value,
                attValueId: item.value_id
              };
            }));
          },
          error: function(xhr, status, error) {
            console.error(error);
            response([]);
          }
        });
      },
      minLength: 2,
      select: function(event, ui) {
        $(this).attr('name', 'data[' + btId + '][' + ui.item.attValueId + ']');
      }
    });
  });


});
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
