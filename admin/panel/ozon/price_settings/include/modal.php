<div class="modal-background" style="display:none">

  <div class="modal-window add-modal">
    <div class="head">
      <h3 class="modal-name">Добавление артикулов</h3>
    </div>
    <hr>
    <div class="body">
      <form id="add-form">
        <textarea class="add-txt" placeholder="Артикул;План / Артикул  План" name="txt"></textarea>
      </form>
      <hr>
      <button id="save-items" class="btn btn-warning save-btn">Добавить \ Обновить</button>
    </div>
    <div class="error-block">

    </div>
  </div>

  <div class="modal-window settings-modal" style="display:none;">
    <div class="head-tabs">
      <button class="settings-tab active" value="body-defaults">Настройки по умолчанию</button>
      <button class="settings-tab" value="body-coeffs">Настройки коэффициентов</button>
    </div>
    <hr>
    <div class="body-defaults tab">

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
    margin: 10% auto auto auto;
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

  #default-settings-form, #coefficient-form{
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
      default:
        console.log('Как ты вообще умудрился так сделать?');
    }
  }

  function getDefaultSettings()
  {
    $.ajax({
      url: ajax_folder + 'getDefaultSettings.php',
      method: "POST",
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
    $.ajax({
      url: ajax_folder + 'getCoefficientsSettings.php',
      method: "POST",
      success: function(response){
        $('.body-coeffs').html(response);
      },
      error: function(response){
        alert('Не удалось получить почасовые коэффициенты');
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
    $.ajax({
      url: ajax_folder + 'addItems.php',
      method: "POST",
      data: $('#add-form').serialize(),
      success: function(response){
        $('.add-modal .error-block').html(response);
        $('.modal-background').fadeOut();
        $('.add-txt').val('').change();
        getList();
      },
      error: function(response){
        alert('Возникла ошибка при добавлении / обновлении');
      }
    })
  }

  function saveDefaultSettings(){
    $.ajax({
      url: ajax_folder + 'saveDefaultSettings.php',
      method: "POST",
      data: $('#default-settings-form').serialize(),
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
          getList();
          return;
        }
        alert( 'Ошибка: ' + result.status );
      },
      error: function(response){
        alert("Не удалось сохранить настройки коэффициентов");
      }
    })
  }

  $(document).on('click', '#save-coefficients', function(e){
    saveCoefficientsSettings();
  })

  $(document).on('click', '#save-defaults', function(e){
    saveDefaultSettings();
  })

  $(document).on('click', '#save-items', function(e){
    addItems();
  })

  $(document).on('click', '.btn-modal', function(e){
    e.preventDefault();
    var modal = $(this).attr('data-target');
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
