<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Управление рекламными кампаниями');?>
<?$APPLICATION->SetPageProperty("page_h1", "Управление рекламными кампаниями");?>
<?php

$marketplaces = [
  'ozon' => 'OZON',
  'wb' => 'WB',
];

$rows = CIBlockElement::getList(["NAME" => "ASC"], ["IBLOCK_ID" => 11], false, false, ["ID", "NAME"] );
$brands = [];
while ( $row = $rows->getNext() ){
  $brands[ $row['ID'] ] = $row['NAME'];
}

$panel = new DBPanel;
$rows = $panel->select(['*'], 'am_mp_settings')->make();

$settings = [];
foreach ( $rows as $row ){
  $settings[ $row['platform'] ] = $row;
  $settings[ $row['platform'] ]['store'] = explode( '|', $row['store'] );
  $settings[ $row['platform'] ]['activityCheckBox'] = ($row['activity'] == 1) ? 'checked' : '';
}

$stores = [
  "79ed7d71-0aa6-11ea-0a80-004200039aa4" => "Склад Москва 1",
  "51538bd5-6cf3-11ef-0a80-10ba001db77c" => "Склад Москва 2",
  "8f9fc8a4-4b82-11f0-0a80-1af80012c175" => "Склад Импорт NF",
  "b8e7c736-3bc2-11f0-0a80-09fd0010bf8f" => "Склад Импорт WR",
];
$logFolderPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/adverts/logs/%s/*-*-*.txt";
$lastStart = [];
foreach ( $marketplaces as $type => $name ){
  $path = sprintf( $logFolderPath, $type );
  $last = end( glob($path) );
  $lastStart[ $type ] = date('Y-m-d G:i:s', filectime($last));
}
 ?>


<div id="adv-container">
  <div class="tabs-control">
    <? foreach( $marketplaces as $type => $name ): ?>
      <button class="tab-btn <?echo $type == 'ozon' ? 'active-tab': '';?>" onclick="getProfiles('<?=$type?>')" value="<?=$type?>"><?=$name?></button>
    <? endforeach; ?>
  </div>
  <hr>
  <? foreach( $marketplaces as $type => $name ): ?>
  <div id="<?=$type?>" class="tab " style="display:<?echo $type == 'ozon' ? 'block': 'none';?>">
    <div class="control-btns" style="display:flex; flex-direction:row; gap: 5px;">
      <button class="btn btn-primary btn-add" value="<?=$type?>">Новый профиль</button>
      <button class="btn btn-light btn-settings" value="<?=$type?>">Настройки</button>
      <a class="btn btn-light" href="logs/?platform=<?=$type?>">Журнал</a>
      <div class="divider"></div>
      <span class="last-date">Последнее обновление: <?=$lastStart[$type]?></span>
      <button class="btn btn-warning btn-save-all" value="<?=$type?>" onclick="editProfiles($(this))">Сохранить</button>
    </div>
    <div style="display:none" class="form-add">
      <hr>
      <form class="create-profile-form-<?=$type?>">
        <h3>Новый профиль (<?=$name?>)</h3>
        <div class="divider"></div>
        <div class="option" style="display:flex; flex-direction:row">
          <span class="option-field">Бренд</span>
          <select class="option-field" name="brand_id">
            <? foreach ( $brands as $id => $brand): ?>
            <option value="<?=$id?>"><?=$brand?></option>
            <? endforeach; ?>
          </select>
        </div>
        <div class="option" style="display:flex; flex-direction:row">
          <span class="option-field">Себестоимость, ₽</span>
          <div class="option-field cost-input-block" style="flex-direction: row; gap: 5px; display:flex">
            <input class="cost-input cost-min" type="text" name="minCost" value="" placeholder="0">
            <span>-</span>
            <input class="cost-input cost-max" type="text" name="maxCost" value="" placeholder="99999">
          </div>
        </div>
        <div class="option" style="display:flex; flex-direction:row">
          <span class="option-field">Дней на складе, дн.</span>
          <input class="option-field" type="text" name="stockDays" value="" placeholder="0">
        </div>
        <div class="option" style="display:flex; flex-direction:row">
          <span class="option-field">Ставка, ₽</span>
          <input class="option-field" type="text" name="bid" value="" placeholder="Мин. ставка">
        </div>
        <input type="hidden" name="platform" value="<?=$type?>">
      </form>
      <button class="btn btn-warning btn-create-profile" style="display:flex; margin-left: auto" onclick="createProfile('<?=$type?>')">Добавить</button>
      <div class="log-create" style="padding: 20px 0px">

      </div>
      <div class="divider"></div>
    </div>

    <div class="<?=$type?>-profile-list profile-list">

    </div>

  </div>

  <div class="modal-background modal-<?=$type?>" style="display:none">
    <div class="modal-window">
      <h3>Настройки маркетплейса (<?=$name?>)</h3>
      <div class="divider"></div><br>
      <div class="items">

        <form class="settings-form-<?=$type?>" method="post">

          <div class="settings-item">
            <span class="settings-field">Активность</span>
            <input style="width: 15px; margin: 0 auto" class="settings-field" type="checkbox" <?echo $settings[$type]['activityCheckBox'] ?: '';?> name="activity">
          </div>

          <hr>

          <div class="settings-item">
            <span class="settings-field">Склады</span>
            <select class="settings-field" name="store[]" multiple>
              <? foreach ( $stores as $id => $name): ?>
                <? if ( in_array($id, $settings[$type]['store'] ?? []) ): ?>
                <option value="<?=$id?>" selected><?=$name?></option>
                <? else: ?>
                <option value="<?=$id?>"><?=$name?></option>
                <? endif; ?>
              <? endforeach; ?>
            </select>
          </div>

          <input type="hidden" name="platform" value="<?=$type?>">

        </form>

      </div>
      <hr>
      <div class="red-buttons">
      </div>
      <hr>
      <div class="modal-footer" style="margin-top: auto">
        <!-- <button class="btn btn-danger" value="<?=$type?>" style="">Очистить список профилей</button> -->
        <button class="btn btn-warning btn-save-set" value="<?=$type?>" onclick="saveSettings($(this), event)">Сохранить</button>
      </div>
    </div>
  </div>
  <? endforeach; ?>
</div>

<? require("include/toast.php"); ?>

<style media="screen">
  .tab-btn{
    width: 210px;
    height: 40px;
    border: none;
    font-size: 16px;
    background-color: rgba(200,235,235,1);
    font-weight: bolder;
  }
  .active-tab{
    background-color: #ffc107;
  }
  .divider{
    margin-left: auto;
  }
  .last-date{
    margin-top: 8px;
    font-size: 14px;
    color: rgba(0,0,0,0.5);
    margin-right: 10px
  }
  .profile-list{
    padding-top: 30px;
  }
  .profile{
    width: 100%;
    padding: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
    border-radius: 4px;
    margin-top: 10px;
  }
  .profile-settings{
    width: 100%;
  }
  .profile-name{
    cursor: pointer;
  }
  .profile-name:hover{
    color: #ffc107;
  }
  .profile-name h3:hover{
    font-weight: 700 !important;
  }

  .divider{
    margin-top: 10px;
    margin-bottom: 16px;
    border-bottom: 1px solid rgba(0,0,0,0.15);
  }
  .profile-control{
    margin-left: auto;
  }
  .btn-control{
    height: fit-content;
  }
  .option{
    display: flex;
    flex-direction: row;
  }
  .option-field{
    width: 24%;
    margin-bottom: 5px;
    height: 30px;
  }
  .option input, .option select{
    border: 1px solid rgba(0,0,0,0.15);
    border-radius: 4px;
    background-color: white;
    padding: 0 7px 0 7px;
  }
  .option span{
    font-size: 16px;
  }
  .form-add{
    margin-bottom: 50px;
  }
  .cost-input{
    width: 48%;
  }
  .settings-item{
    display: flex;
    flex-direction: row;
    /* border-bottom: 1px solid rgba(0,0,0,0.15); */
    /* margin-bottom: 10px; */
    padding-left: 10px;
    padding-right: 10px;
  }
  .settings-item span{
    font-size: 17px;
  }
  .settings-item select{
    height: 200px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.15);
    padding: 7px;
  }
  .settings-field{
    display: flex;
    width: 50%;
  }

  /* MODAL WINDOW */
  .modal-background{
    display: none;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    position: fixed;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    z-index: 9999;
    overflow-y: auto;
  }
  .modal-window{
    display: flex;
    padding: 20px;
    border-radius: 6px;
    flex-direction: column;
    /* height: 580px; */
    height: 55s%;
    width: 33%;
    background-color: white;
    margin: 5% auto auto auto;
    z-index: 99999;
  }
  .btn-warning{
    color: black !important;
    font-size: 15px !important;
  }
  .updating{
    filter: blur(2px);
    background-color: rgba(0,0,0,0.02) !important;
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

<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js" integrity="sha384-uO3SXW5IuS1ZpFPKugNNWqTZRRglnUJK6UAZ/gxOX80nxEkN9NcGZTftn6RzhGWE" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<script type="text/javascript">
  feather.replace({ 'aria-hidden': 'true' });
  const ajax = "/admin/panel/common/adverts/ajax/";
  let showFormAdd = false;
  var selectedPlatfrom = 'ozon';
  var profileList = {ozon:{}, wb:{}};

  function createProfile(platform){
    $.ajax({
      url: ajax + 'createProfile.php',
      method: "POST",
      data: $('.create-profile-form-'+platform).serialize(),
      success: function(response){
        getProfiles(platform);
        $('.log-create').html(response);
        showSuccessToast();
      },
      error: function(response){
        showErrorToast();
      }
    })
  }

  function getProfiles(platform){
    $.ajax({
      url: ajax + 'getProfiles.php',
      method: "POST",
      data: {platform: platform},
      success: function(response){
        $('.' + platform + '-profile-list').html(response);
        $('.profile').each(function(element){
          let item = $(element);
          profileList[platform][item.attr('id')] = false;
        });
      },
      error: function(response){
        showErrorToast();
      }
    })
  }

  function saveSettings(obj, e){
    e.preventDefault();
    console.log( obj );
    console.log( e );
    console.log( '.settings-form-' + obj.val() );
    $.ajax({
      url: ajax + 'settings/save.php',
      method: 'POST',
      data: $('.settings-form-' + obj.val()).serialize(),
      success: function(response){
        console.log("Settings successfully saved");
        showSuccessToast();
      },
      error: function(response){
        showErrorToast();
      }
    })
  }

  function deleteProfile( obj, e ){
    e.preventDefault();

    var shouldDelete = confirm("С удалением профиля будут отключены все ассоциированные кампании. Продолжить?");
    if ( !shouldDelete ) return;
    $('#'+obj.val()).addClass('updating');
    $.ajax({
      url: ajax + 'deleteProfile.php',
      method: "POST",
      data: {profile_id: obj.val()},
      success: function(response){
        $('#' + obj.val()).remove();
        showSuccessToast();
      },
      error: function(response){
        showErrorToast();
        $('#'+obj.val()).removeClass('updating');
      }
    });
  }

  function editProfiles(obj){
    let platform = obj.val()
    $.ajax({
      url: ajax + 'editProfiles.php',
      method: "POST",
      data: $('.profile-list-form-'+platform).serialize(),
      success: function(response){
        console.log("saved successfully");
        getProfiles( platform );
        showSuccessToast();
      },
      error: function(response){
        showErrorToast();
      }
    })
  }

  function loadItems(platform){
    showPendingToast();
    $.ajax({
      url: ajax + 'prediction/getItems.php',
      method: "POST",
      data: {platform: platform},
      success: function(response){
        console.log(response);
      },
      error: function(response){
        showErrorToast();
      }
    });
  }

  function calculateAvailable( profile, id, platform, brand){
    $.ajax({
      url: ajax + 'prediction/calculateAvailable.php',
      method: "POST",
      data: {profile: profile, platform: platform, brandId: brand},
      success: function(response){
        var result = $.parseJSON(response);
        $('#counter_' + id).html("Количество доступных товаров: " + result.count);
      },
      error: function(response){
        console.log('Error');
      }
    })
  }

  function showSuccessToast() {
    var t_comp = document.getElementById('complete-toast');
    $('#complete-toast').show();
    setTimeout(function(){
      $('#complete-toast').fadeOut();
    }, 3000)
  }

  function showErrorToast() {
    $('#error-toast').show();
    setTimeout(function(){
      $('#error-toast').fadeOut();
    }, 3000)
  }

  function showPendingToast() {
    $('#pending-toast').show();
    setTimeout(function(){
      $('#pending-toast').fadeOut();
    }, 3000)
  }

  $(document).on('change', '.profile-param', function(e){
    e.preventDefault();
    var profileId = $(this).attr('data-id');
    var brandId = $('#' + profileId + '_brand').val();
    var platform = $('#' + profileId + '_platform').val();

    var profile = {};

    profile[brandId] = {
      minCost: $('#' + profileId + '_cmin').val(),
      maxCost: $('#' + profileId + '_cmax').val(),
      stockDays: $('#' + profileId + '_sd').val(),
      bid: $('#' + profileId + '_bid').val(),
    };

    calculateAvailable( profile, profileId, platform, brandId );
  })

  $(document).on('click', '.tab-btn', function(e){
    e.preventDefault();
    let type = $(this).val();
    $('.tab-btn').removeClass('active-tab');
    $(this).addClass('active-tab');
    $('.tab').hide();
    $('#' + type).show();
    selectedPlatfrom = type;
    loadItems(type);
    $('.log-create').html('');
  });

  $(document).on('click', '.profile-name', function(e){
    e.preventDefault();
    let profile_id = $(this).parents().eq(2).attr('id');

    let profileStatus = profileList[ selectedPlatfrom ][ profile_id ];

    if ( profileStatus ){
      $('.body-' + profile_id).slideUp('fast');
      profileList[ selectedPlatfrom ][ profile_id ] = !profileStatus;
    }else{
      $('.body-' + profile_id).slideDown('fast');
      profileList[ selectedPlatfrom ][ profile_id ] = !profileStatus;
    }
  })

  $(document).on('click', '.btn-add', function(e){
    if ( showFormAdd ){
      showFormAdd = !showFormAdd;
      $('.form-add').slideUp();
      $('.ctrl-divider').show();
    }else{
      showFormAdd = !showFormAdd;
      $('.form-add').slideDown();
      $('.ctrl-divider').hide();
    }
  })

  // modal window
  $(document).on('click', '.btn-settings', function(e){
    e.preventDefault();
    let type = $(this).val();
    $('.modal-' + type).show();
  })
  $(document).on('click', '.modal-background', function(e){
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').fadeOut();
    }
  })

  getProfiles('ozon');
  loadItems('ozon');
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
