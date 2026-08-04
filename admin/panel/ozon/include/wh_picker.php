<div class="modal-background" style="display:none">

  <div class="modal-window control-modal">
    <div class="head">
      <h3 class="modal-name">Склады ФБО</h3>
    </div>
    <hr>
    <div class="body">

    </div>

  </div>

</div>

<style media="screen">
/* Modal window */
.modal-background{
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  position: fixed;
  background-color: rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(5px);
  z-index: 998;
  overflow-y: auto;
}
.modal-window{
  display: flex;
  padding: 40px;
  border-radius: 6px;
  flex-direction: column;
  /* height: 580px; */
  width: 36%;
  background-color: white;
  margin: 10% auto auto auto;
  z-index: 999;
}
</style>

<script type="text/javascript">

$(document).on('click', '.modal-background', function(e){
  if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
    $('.modal-background').hide();
  };
})

$(document).on('click', '.show-wh-modal', function(e){
  e.preventDefault();
  getWarehouses();
  $('.modal-background').show();
})

$(document).on('click', '.save-stock-settings', function(){
  saveStockSettings();
  $('.modal-background').hide();
})


function saveStockSettings()
{
  $.ajax({
    url: '/admin/panel/ozon/ajax/settings/save_warehouses.php',
    method: 'POST',
    data: {
      warehouses: $('#warehouses').val()
    },
    success: function(response){
      $('.modal-background').hide();
    }
  })

  }

  function getWarehouses()
  {
    $.ajax({
      url: '/admin/panel/ozon/ajax/settings/get_warehouses.php',
      method: 'POST',
      success: function(response){
        $('.modal-window .body').html(response);
      }
    })
  }
</script>
