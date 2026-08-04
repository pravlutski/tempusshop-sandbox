<div class="modal-background" style="display:none">

  <div class="modal-window add-modal">
    <div class="head">
      <h3 class="modal-name">Добавление артикулов</h3>
    </div>
    <hr>
    <div class="body">
      <form id="add-form">
        <textarea id="txt-add" class="add-txt" placeholder="Артикул;План;Мин.маржин;Мин.маржа;Шаг" name="txt"></textarea>
      </form>
      <hr>
      <div style="display:flex; flex-direction: row">
        <div class="loader-container">
          <span class="loader"></span>
        </div>
        <button id="save-items" class="btn btn-warning save-btn">Добавить \ Обновить</button>
      </div>
    </div>
    <div class="error-block">

    </div>
  </div>

  <div class="modal-window settings-modal" style="display:none;">
    <h3 class="modal-name">Настройки ДЦ</h3>
    <div class="head-tabs">
      <button class="settings-tab active" value="body-defaults">Общие</button>
      <button class="settings-tab" value="body-goals">Планы</button>
      <button class="settings-tab" value="body-coeffs">Коэффициенты</button>
    </div>
    <hr>
    <div class="body-defaults tab">

    </div>
    <div class="body-goals tab" style="display: none">

    </div>
    <div class="body-coeffs tab" style="display: none">

    </div>
  </div>

  <div class="modal-window coefficients-modal" style="display:none;">
    <div class="head">
      <h3 class="modal-name">Почасовые коэффициенты</h3>
    </div>
    <hr>
  </div>

  <div class="modal-window log-modal">
    <div class="head">
      <h3 class="modal-name">Лог (Технический)</h3>
    </div>
    <hr>
    <div class="body">

    </div>
  </div>

</div>

<style media="screen">
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
    margin: 8% auto auto auto;
    z-index: 999;
  }
  .window-spp{
    width: 84%;
    margin: 5% auto auto auto;
  }

  .add-txt{
    resize: none;
    width: 100%;
    height: 320px;
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 4px;
    padding: 5px;
  }
  .btn{
    height: fit-content;
  }
  .save-btn{
    display: flex !important;
    width: fit-content;
    margin-left: auto;
  }

  #default-settings-form, #coefficient-form, #goals-settings-form{
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 10px;
  }

  .ds-card, .c-card{
    display: flex;
    flex-direction: row;
  }
  .ds-card .name, .ds-card .input{
    display: flex;
    width: 50%;
    align-items: center;
  }
  .c-card{
    border-bottom: 1px solid rgba(0,0,0,0.07);
    padding-bottom: 10px;
  }
  .c-header{
    border: none !important;
  }
  .c-card .hour, .c-card .coef, .c-card .button{
    display: flex;
  }
  .c-card .hour{
    width: 50%;
    padding-top: 5px;
    font-size: 17px;
    font-weight: bolder;
  }
  .c-card .coef{
    width: 50%;

  }
  .c-card .button{
    width: 0%;
  }
  .c-card .header{
    padding-bottom: 10px;
    padding-top: 0px;
    border-bottom: 1px solid rgba(0,0,0,0.12);
    text-align: center;
    font-weight: bolder;
    font-size: 16px;
  }
  .del-coef-btn{
    margin-left: auto;
  }
  .head-tabs{
    display: flex;
    flex-direction: row;
    overflow: hidden;
    border-radius: 4px;
  }
  .settings-tab{
    display: flex;
    justify-content: center;
    align-items: center;
    height: 40px;
    width: 50%;
    border: none;
    background-color: #f5f5f5;
    font-size: 16px;
  }
  .active{
    font-weight: bolder;
    background-color: #ffc107;
  }
  .settings-block-name{
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.15);
    padding-bottom: 5px;
  }
  .red-buttons-block{
    margin-top: 20px;
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  .red-buttons-block button{
    width: 50%;
  }

  .modal-name{
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.2);
  }
</style>

<script type="text/javascript">

  function getModalContents( target_body )
  {
    $('.tab').hide();
    switch( target_body ){
      case 'body-defaults':
        $('.' + target_body).show();
        getDefaultSettings();
        break;
      case 'body-coeffs':
        $('.' + target_body).show();
        getCoefficientsSettings();
        break;
      case 'body-goals':
        $('.' + target_body).show();
        getGoalsSettings();
        break;
      default:
        console.log('Как ты вообще умудрился так сделать?');
    }
  }

  function getDefaultSettings()
  {
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'getDefaultSettings.php',
      method: "POST",
      data: {
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        $('.body-defaults').html(response);
      },
      error: function(response){
        alert('Не удалось получить настройки по умолчанию');
      }
    })
  }

  function getCoefficientsSettings()
  {
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'getCoefficientsSettings.php',
      method: "POST",
      data: {
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        $('.body-coeffs').html(response);
      },
      error: function(response){
        alert('Не удалось получить почасовые коэффициенты');
      }
    })
  }

  function getGoalsSettings()
  {
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'getGoalsSettings.php',
      method: "POST",
      data: {
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        $('.body-goals').html(response);
      },
      error: function(response){
        alert('Не удалось получить настройки корректировки планов');
      }
    })
  }

  function getLog( modalName ){
    $.ajax({
      url: ajax_folder + 'getLog.php',
      method: "POST",
      success: function(response){
        $('.' + modalName + ' .body').html(response);
      },
      error: function(response){
        alert('Не удалось получить лог');
      }
    })
  }

  function addItems(){
    $('.loader-container').show();
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'addItems.php',
      method: "POST",
      data: {
        txt: $('#txt-add').val(),
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        $('.add-modal .error-block').html(response);
        $('.loader-container').hide();
        $('.modal-background').fadeOut();
        $('.add-txt').val('').change();
        getList( $('#yesterday-charts').is(':checked'), false, $('#out-of-stock').is(':checked') );
      },
      error: function(response){
        alert('Возникла ошибка при добавлении / обновлении');
      }
    })
  }

  function saveDefaultSettings( data ){
    $.ajax({
      url: ajax_folder + 'saveDefaultSettings.php',
      method: "POST",
      data: data,
      success: function(response){
        $('.modal-background').fadeOut();
        getList();
      },
      error: function(response){
        alert('Не удалось сохранить настройки по умолчанию');
      }
    })
  }

  function saveCoefficientsSettings(){
    $.ajax({
      url: ajax_folder + 'saveCoefficientsSettings.php',
      method: "POST",
      data: $('#coefficient-form').serialize(),
      success: function(response){
        var result = $.parseJSON(response);
        if ( result.status == 'ok' ){
          $('.modal-background').fadeOut();
          getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
          return;
        }
        alert( 'Ошибка: ' + result.status );
      },
      error: function(response){
        alert("Не удалось сохранить настройки коэффициентов");
      }
    })
  }

  function clearPriceTable()
  {
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'clearPriceTable.php',
      method: "POST",
      data: {
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        alert("Эффект модуля сброшен для всех товаров");
        $('.modal-background').fadeOut();
        getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
      },
      error: function(response){
        alert("Не удалось сбросить эффект");
      }
    })
  }

  function clearItemsList()
  {
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'clearItemsList.php',
      method: "POST",
      data: {
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val()
      },
      success: function(response){
        alert("Список товаров очищен");
        $('.modal-background').fadeOut();
        getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
      },
      error: function(response){
        alert("Не удалось очистить список товаров");
      }
    })
  }

  $(document).on('click', '#clear-price-table', function(e){
    e.preventDefault();
    if ( confirm("Действие необратимо. Необходимо подтверждение") ){
      clearPriceTable();
      return;
    }
    console.log("Действие отменено");
  })

  $(document).on('click', '#clear-items-list', function(e){
    e.preventDefault();
    if ( confirm("Действие необратимо. Необходимо подтверждение") ){
      clearItemsList();
      return;
    }
    console.log("Действие отменено");
  })

  $(document).on('click', '#save-coefficients', function(e){
    saveCoefficientsSettings();
  })

  $(document).on('click', '#save-defaults', function(e){
    saveDefaultSettings( $('#default-settings-form').serialize() );
  })

  $(document).on('click', '#save-goals', function(e){
    saveDefaultSettings( $('#goals-settings-form').serialize() );
  })

  $(document).on('click', '#save-items', function(e){
    addItems();
  })

  $(document).on('click', '.btn-modal', function(e){
    e.preventDefault();
    var modal = $(this).attr('data-target');
    $('.modal-name').html("Настройки ДЦ ("+$('#mp-selector').val()+")")
    $('.modal-window').hide();
    $('.modal-background').fadeIn();
    if ( modal != 'add-modal' ){
      getModalContents( 'body-defaults' );
    }
    $( '.' + modal ).show();
  });

  $(document).on('click', '.settings-tab', function(e){
    $('.settings-tab').removeClass('active');
    $(this).addClass('active');
    getModalContents( $(this).val() );
  })

  $(document).on('click', '.modal-background', function(e){
    e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').fadeOut();
    };
  })
</script>
