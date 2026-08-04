<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Автореклама - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Автореклама");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<script src=" https://cdn.jsdelivr.net/npm/bootstrap-multiselect@2.0.0/dist/js/bootstrap-multiselect.min.js "></script>
<link href=" https://cdn.jsdelivr.net/npm/bootstrap-multiselect@2.0.0/dist/css/bootstrap-multiselect.min.css " rel="stylesheet">
<?
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertConfigProvider.php');
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertDataProvider.php');
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertApiManager.php');

$api = new AdvertApiManager;
$api->getAuthoriztionToken();

$dataProvider = new AdvertDataProvider(
  main: \Bitrix\Main\Application::getConnection(),
  panel: new DBPanel,
  api: $api
);

$files = [
  'own' => AdvertConfigProvider::getOwnFilePath(),
  'comp' => AdvertConfigProvider::getCompetitorFilePath(),
  'log' => AdvertConfigProvider::getActionLogPath(),
];
$result = [];
foreach ( $files as $key => $path ){
  $result[$key] = [
    'path' => $path,
    'link' => explode( $_SERVER['DOCUMENT_ROOT'], $path )[1],
    'updateTime' => date( 'Y.m.d G:i:s', filectime($path) )
  ];
}

$settings = $dataProvider->getSettings();
if ( isset($settings['average_coinvest']) ){
  $settings['average_coinvest'] = (1 - (float)$settings['average_coinvest']) * 100;
}
$defaults['average_coinvest'] = (1 - (float)$defaults['average_coinvest']) * 100;

// $settings['adverts'] = implode(';', $settings['adverts']);
$defaults = AdvertConfigProvider::getPreparedSettings();

$json = file_get_contents( $files['log'] );
$data = json_decode( $json, true );
$itemsGoodCount = count( $data['good'] );
$globalLimit = $settings['global_limit'] ?? $defaults['global_limit'];
$displayCounter = "{$itemsGoodCount} / {$globalLimit}";

$advertList = $dataProvider->getAdvertList();

?>
<div id="container">
  <div class="header-block">
    <div class="">
      <button id="show-upload" class="btn btn-primary show-modal" value="upload" title="Updated at: <?=$result['comp']['updateTime']?>">Файлы</button>
    </div>
    <div style="margin-left: 10px">
      <button id="show-settings" class="btn btn-warning show-modal" value="settings">Настройки</button>
    </div>
    <div style="margin-left: auto; margin-right: auto; width: 420px">
      <input type="search" id="search-field" class="form form-control" placeholder="Поиск..." value="">
    </div>
    <div style="margin-left:auto; display: flex; flex-direction: column; width: 310px; color: rgba(0,0,0,0.5); font-size: 14px">
      <a href="<?=$result['comp']['link']?>" download style="text-decoration: none; color: rgba(0,0,0,0.5)">
        <div style="display: flex; flex-direction: row;gap: 12px;">
          <span style="width: 40%">Файл конкурента:</span>
          <span><?=$result['comp']['updateTime']?></span>
        </div>
      </a>
      <div style="display: flex; flex-direction: row; gap: 12px;">
        <span style="width: 40%" >Рассчитано:</span>
        <span><?=$result['log']['updateTime']?></span>
      </div>
      <div style="display: flex; flex-direction: row; gap: 12px;">
        <span style="width: 40%" >Товары в РК:</span>
        <span><?=$displayCounter?></span>
      </div>
    </div>
    <div style="">
      <button id="update-adverts" class="btn btn-warning" value="settings">Обновить кампании</button>
    </div>
  </div>
  <hr>
  <div class="log">

  </div>

  <div class="modal-background" style="display:none">
    <div class="modal-window settings-modal" style="display:none">
      <div class="head">
        <h3 class="modal-name">Настройки</h3>
      </div>
      <hr>
      <div class="body">
        <form id="settings-form" action="" method="post">
          <div class="settings-row">
            <span class="row-name">Рекламные кампании</span>
            <!-- <input class="row-input" type="text" name="adverts" value="<?//echo $settings['adverts'] ?? $defaults['adverts'];?>"> -->
            <select id="advert-list" class="form-select form-field" name="multiselect[]" multiple="multiple" data-toggle="multiselect">
              <? foreach( $advertList as $id => $info): ?>
              <option value="<?=$id?>" <?echo in_array($id, $settings['adverts']) ? 'selected': '';?>><?=$info['title']?></option>
              <? endforeach; ?>
            </select>
          </div>
          <div class="settings-row">
            <span class="row-name">Глобальный лимит товаров</span>
            <input id="global_limit" class="row-input" type="text" name="global_limit" value="<?echo $settings['global_limit'] ?? $defaults['global_limit'];?>">
          </div>
          <div class="settings-row">
            <span class="row-name">Лимит товаров в кампании</span>
            <input id="advert_limit" class="row-input" type="text" name="advert_limit" value="<?echo $settings['advert_limit'] ?? $defaults['advert_limit'];?>">
          </div>
          <div class="settings-row">
            <span class="row-name">Минимальная цена</span>
            <input id="minimum_price_limit" class="row-input" type="text" name="minimum_price_limit" value="<?echo $settings['minimum_price_limit'] ?? $defaults['minimum_price_limit'];?>">
          </div>
          <div class="settings-row">
            <span class="row-name">Ставка</span>
            <input id="bid" class="row-input" type="text" name="bid" value="<?echo $settings['bid'] ?? $defaults['bid'];?>">
          </div>
          <div class="settings-row">
            <span class="row-name">Соинвест, %</span>
            <input id="average_coinvest" class="row-input" type="text" name="average_coinvest" value="<?echo $settings['average_coinvest'] ?? $defaults['average_coinvest'];?>">
          </div>
        </form>
        <button class="btn btn-warning" style="margin-left:auto; margin-bottom: 15px; margin-top: 25px; display:flex" id="save-settings">Сохранить</button>
      </div>
    </div>

    <div class="modal-window upload-modal" style="display: none">
      <div class="head">
        <h3 class="modal-name">Загрузка файлов</h3>
      </div>
      <hr>
      <div class="body">
        <div class="" style="display:flex; flex-direction: row">
          <form id="file-form" class="input sub-block" enctype="multipart/form-data">
            <input type="file" id="comp-file" name="comp-file" class="file-input" value="">
            <label class="btn btn-primary comp-label" for="comp-file">Ассортимент конкурента</label>
          </form>
          <div class="confirm-button-block sub-block" style="margin-left: auto;">
            <button class="btn btn-warning" id="confirm-button">Загрузить</button>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<style media="screen">
  .btn{
    height: fit-content;
  }
  .name-label{
    /* min-height: 2.4rem; */
    /* padding: .375rem .75rem; */
    color: rgba(0,0,0,0.5);
    font-style: italic;
    text-decoration: none;
  }
  .header-block{
    display: flex;
    flex-direction: row;
  }
  .sub-block{
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  .input input{
    display: none;
  }
  .last-update{
    margin-left: auto;
    margin-right: 40px;
    flex-direction: column !important;
    gap: 5px !important;
  }
  .table-log{
    width: 100%;
  }
  .table-log tbody tr td{
    padding: 10px;
  }
  .table-log tbody tr {
    border-bottom: 1px solid rgba(0,0,0,0.15);
  }
  .table-log thead tr th{
    padding: 10px;
  }
  .approved{
    background-color: rgba(0, 255, 0, 0.2);
  }
  .denied{
    background-color: rgba(255, 0, 0, 0.2);
  }
  .selected{
    background-color: #f5f5f5 !important;
    color: rgba(0,0,0,0.4) !important;
    border-color: #f5f5f5 !important;
  }

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
    padding: 25px;
    border-radius: 6px;
    flex-direction: column;
    /* height: 580px; */
    width: 36%;
    background-color: white;
    margin: 10% auto auto auto;
    z-index: 999;
  }
  .settings-row{
    display: flex;
    flex-direction: row;
    gap: 10px;
    margin-top: 5px;
  }
  .row-name, .row-input{
    width: 50%
  }
  .row-input{
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.15);
    padding: 4px 8px;
  }
  .multiselect-native-select{
    width: 50% !important;
    height: 34px !important;
  }
  .multiselect-search{
    margin-left: 1px !important;
    /* margin-right: auto !important; */
    outline: none !important;
  }
  .multiselect-container .multiselect-filter > input.multiselect-search{
    margin-left: -0.75rem !important;
  }

</style>

<script type="text/javascript">
  const path = "/admin/panel/ozon/ajax/adverts/";
  var files = {};

  $(document).ready(function() {
    $('#advert-list').multiselect({
      buttonWidth: '100%',
      numberDisplayed: 1,
      maxHeight: 300,
      widthSynchronizationMode: 'always',
      enableCaseInsensitiveFiltering: true,
    });
  });

  function uploadFiles( files )
  {
    var form_data = new FormData;
    console.log( $('#own-file').val() );
    form_data.append( 'own', files['own'] );
    form_data.append( 'comp', files['comp'] );
    $.ajax({
      url: path + "uploadFiles.php",
      method: "POST",
      cache: false,
      processData: false,
      contentType: false,
      // dataType: 'json',
      data: form_data,
      success: function(response){
        console.log(response);
        alert('Файлы обновлены. Обновите страницу');
        document.location.reload();;
      },
      error: function(response){
        alert('Системная ошибка');
      }
    })
  }

  function saveSettings()
  {
    var form_data = new FormData;
    form_data.append( 'global_limit', $('#global_limit').val() );
    form_data.append( 'adverts', $('#advert-list').val() );
    form_data.append( 'advert_limit', $('#advert_limit').val() );
    form_data.append( 'minimum_price_limit', $('#minimum_price_limit').val() );
    form_data.append( 'bid', $('#bid').val() );
    form_data.append( 'average_coinvest', $('#average_coinvest').val() );


    $.ajax({
      url: path + 'saveSettings.php',
      method: 'POST',
      data: form_data,
      cache: false,
      processData: false,
      contentType: false,
      success: function( response ){
        console.log( response );
        $('.modal-background').hide();
      },
      error: function( response ){
        alert('Ошибка сохранения настроек');
      }
    })
  }

  function searchItem( query ){
    $.ajax({
      url: path + 'searchItem.php',
      method: 'POST',
      data: {query: query},
      success: function( response ){
        $('.log').html( response )
      },
      error: function( response ){
        alert("Системная ошибка при поиске");
      }
    });
  }

  function getLog()
  {
    $.ajax({
      url: path + 'getList.php',
      method: 'POST',
      success: function( response ){
        $('.log').html(response)
      },
      error: function( response ){
        alert('Системная ошибка');
      }
    })
  }

  function updateAdverts()
  {
    $('.log').html("Идёт обновление...");
    $.ajax({
      url: path + 'updateAdverts.php',
      method: 'POST',
      success: function ( response ){
        getLog();
      },
      error: function( response ){
        alert('Системная ошибка');
      }
    })
  }

  $(document).on('click', '#save-settings', function(){
    saveSettings();

  })
  $(document).on('click', '#confirm-button', function(){
    uploadFiles( files );
  })
  $(document).on('click', '#update-adverts', function(){
    updateAdverts();
  })

  $(document).on('change', '#search-field', function(){
    if ( $(this).val() == '' ){
      getLog();
      return;
    }
    searchItem( $(this).val() );
  })

  $(document).on('change', '.file-input', function(){
    var type = $(this).attr('id').split('-')[0];
    var file = this.files[0];
    if ( file == undefined ){
      $('#' + type + '-name').html('');
      return;
    }
    files[type] = file;
    $('.' + type + '-label').html( file.name );
    $('.' + type + '-label').addClass('selected');
  })

  $(document).on('click', '.modal-background', function(e){
    // e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
    };
  })

  $(document).on('click', '.show-modal', function(e){
    var type = $(this).val();
    $('.modal-window').hide();
    $('.modal-background').fadeIn();
    $('.' + type + '-modal'). fadeIn();
  })

  getLog();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
