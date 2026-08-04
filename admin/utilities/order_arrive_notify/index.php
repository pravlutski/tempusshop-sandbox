<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Уведомление о поступлении товаров');?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>

<h2>Уведомление о поступлении товаров</h2>
<hr>
<div id="container">
  <div class="control-block" style="display: flex">
    <button id="show-log" class="btn btn-warning" >Показать/Скрыть лог</button>
    <button id="send-notification" class="btn btn-primary" style="margin-left: auto">Уведомить</button>
  </div>
  <hr>
  <div class="log-block" style="display: none">
    <input type="date" class="form-control" style="width: fit-content; margin-left: auto" id="log-date" value="">
    <br>
    <div class="log-body">

    </div>
    <hr>
  </div>
  <div class="table-block">

  </div>
</div>

<div class="background-black" style="display:none">

</div>

<style media="screen">
  .model-str{
    border-bottom: 1px solid rgba(0,0,0,0.15);
    width: 50%;
    margin: 3px 0px 5px 0px;
    display: flex;
  }
  .log-body{
    max-height: 300px;
    overflow-y: auto;
    background-color: #f5f5f5;
    padding-left: 20px;
    border-radius: 6px;
  }
  .background-black{
    position: absolute;
    width: 100%;
    height: 100vh;
    z-index: 999;
    background-color: rgba(0, 0, 0, 0.5);
    left: 0;
    top: 0;
    backdrop-filter: blur(3px);
  }
</style>

<script type="text/javascript">
const ajax_path = '/admin/utilities/order_arrive_notify/ajax/'
var logShown = false;
function getList(){
  $.ajax({
    url: ajax_path + 'getList.php',
    method: 'POST',
    success: function(response){
      $('.table-block').html(response);
    },
    error: function(response){
      alert("Не удалось получить список заказов");
    }
  })
}

function sendOrders2(){
  var activeElements = [];

  $('.checkbox').each(function(index, elem){
    if ( $(elem).prop('checked') ){
      let orderId = $(elem).attr('id').split('_')[0];
      activeElements.push( orderId );
    }
  })

  $('.background-black').show();

  $.ajax({
    url: ajax_path + 'sendOrdersCrm.php',
    method: 'POST',
    data: {orders: activeElements},
    success: function(response){
      getList();
      alert("Запрос успешно обработан. см. лог");
      if ( !logShown ){
        $('#show-log').trigger('click');
      }else{
        getLog( false );
      }
      $('.background-black').fadeOut();
    },
    error: function(response){
      alert("Не удалось обновить свойства");
      $('.background-black').fadeOut();
      getList();
    }
  })
}

function getLog(date){
  $.ajax({
    url: ajax_path + 'getLog.php',
    method: "POST",
    data: {date: date},
    success: function(response){
      $('.log-body').html(response);
      $('.log-block').show();
      // $('#send-notification').prop('disabled', true);
    },
    error: function(response){
      alert('Не удалось получить лог');
    }
  })
}

$(document).on('click', '#show-log', function(e){
  e.preventDefault();
  if ( !logShown ){
    let logDate = $('#log-date').val() == '' ? false : $('#log-date').val();
    getLog( logDate );
    logShown = !logShown;
    return;
  }
  $('.log-block').hide();
  // $('#send-notification').prop('disabled', false);
  logShown = !logShown;
})

$(document).on('change', '#log-date', function(e){
  e.preventDefault();
  let logDate = $(this).val() == '' ? false : $(this).val();
  getLog( logDate );
})

$('#select-all').on('change', function() {
  $('.checkbox').prop('checked', $(this).prop('checked'));
});

// $(document).on('click', '.order-row', function(e){
//   if ( !$('.order-link').is(e.target) && !$('.checkbox').is(e.target) ){
//     let rowId = $(this).attr('id');
//     let checkStatus = $('#'+rowId+'_checkbox').prop('checked');
//     $('#'+rowId+'_checkbox').prop('checked', !checkStatus);
//   }
// })

$(document).on('click', '#send-notification', function(e){
  e.preventDefault();
  sendOrders2();
})

getList();
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
