<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("page_h1", "Преобразование артикулов");
$APPLICATION->SetPageProperty("title", "Преобразование артикулов");

$settings = [];
if ( file_exists( $_SERVER["DOCUMENT_ROOT"]."/admin/utilities/artdots/db/settings.json" ) ){
  $json = file_get_contents($_SERVER["DOCUMENT_ROOT"]."/admin/utilities/artdots/db/settings.json");
  $settings = json_decode( $json, true );
}

global $DB;
$strSql = "SELECT id, name FROM ci_brands";
$res = $DB->Query( $strSql, false, $err_mess.__LINE__ );
$brands = [];
while ( $row = $res->Fetch() ){
  $brands[ $row['id'] ] = $row['name'];
}
unset( $row );
?>

<div id="container">
  <div class="buttons-block">
    <select id="brand-list" class="form-select">
      <option value="0">---Не выбрано---</option>
      <? foreach ( $brands as $id => $name): ?>
      <option value="<?=$id?>"><?=$name?></option>
      <? endforeach; ?>
    </select>
    <button id="create-brand-profile" class="btn btn-primary" style="margin-left: 10px">Добавить бренд</button>
    <button id="save-settings" class="btn btn-primary">Сохранить настройки</button>
  </div>
  <hr>
  <div class="tabs">
    <? $i = 0; ?>
    <? foreach ($settings as $brand => $profiles): ?>
      <button id="<?=$brand?>-tab" class="tab <? echo $i == 0 ? 'selected' : ''?>" value="<?=$brand?>"><?echo mb_strtoupper( $brands[$brand] );?></button>
    <? $i++; ?>
    <? endforeach; ?>
  </div>

  <div class="content-block">
    <?$i = 0;?>
    <form id="settings-form" action="" method="post">
      <? foreach ( $settings as $brand => $profiles ):?>
      <div class="profile" id="<?=$brand?>" style="display: <? echo $i == 0 ? 'block' : 'none'?>">
        <h2><? echo mb_strtoupper( $brands[$brand] );?></h3>
        <div class="prof-head">
          <input class="str-len-input" type="text" id="<?=$brand?>_set" placeholder="Длина артикула">
          <button id="new_prof" style="margin-left: 10px" value="<?=$brand?>" class="add-new btn btn-primary">Добавить профиль</button>
          <button class="delete-profile btn btn-danger" value="<?=$brand?>">Удалить профиль</button>
        </div>
        <hr>
        <div class="prof-body <?=$brand?>-body">
          <? foreach ( $profiles as $length => $pos): ?>
          <div class="prof-card" id="<?=$brand?>-<?=$length?>">
            <span class="len-info">Длина артикула: <b><?=$length?></b></span>
            <input class="prof-inp" name="<?=$brand?>_<?=$length?>" value="<?echo implode(',',$pos);?>">
            <button class="del-btn btn btn-danger" value="<?=$brand?>-<?=$length?>">Удалить</button>
          </div>
        <? endforeach; ?>
      </div>
    </div>
    <? $i++; ?>
    <? endforeach; ?>
    </form>
  </div>

</div>

<style media="screen">
  #save-settings, .delete-profile{
    display: flex;
    margin-left: auto;
    margin-right: 20px;
  }
  .tab{
    background-color: #337ab7;
    color: white;
    border:none;
    padding: 6px;
    font-size: 17px;
    width: 220px;
    height: 40px;
    font-weight: bolder;
  }
  .tab:hover{
    font-weight: bold;
  }
  .selected{
    background-color: #ffc107;
    color: black;
    font-weight: bold;
  }
  .buttons-block{
    margin-bottom: 15px;
    display: flex;
    flex-direction: row;
  }
  .prof-head{
    margin-bottom: 15px;
    display: flex;
    flex-direction: row;
  }
  .prof-card{
    display: flex;
    flex-direction: row;
    width: 40%;
    gap: 10px;
    margin-bottom: 6px;
  }
  .len-info{
    width: 25%;
    margin-top: 6px
  }
  .str-len-input, .prof-inp, #brand-list{
    background-color: white;
    border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.25);
    height: 35px;
    padding-left: 5px;
  }
</style>
<script type="text/javascript">
  const ajax_folder_path = "/admin/utilities/artdots/ajax/";

  $(document).on('click', '.tab', function(e){
    e.preventDefault();
    $('.tab').removeClass('selected');
    $(this).addClass('selected');

    $('.profile').hide();
    $( '#' + $(this).val() ).show();
  })

  $(document).on('click', '.del-btn', function(e){
    e.preventDefault();
    $( '#' + $(this).val() ).remove();
  })
  $(document).on('click', '.delete-profile', function(e){
    e.preventDefault();
    $( '#' + $(this).val() ).remove();
    $( '#' + $(this).val() + '-tab' ).remove();
    alert( 'Профиль ' + $(this).val() + 'удален' );
  })
  $(document).on('click', '.add-new',function(e){
    e.preventDefault();
    var brand = $(this).val();
    var length = $( '#' + brand + '_set' ).val();
    console.log( length );
    if ( length == undefined || length == '' || length < 1 ){
      alert( 'Задана некорректная длина строки' );
      return;
    }
    $('.' + brand + '-body').append('\
    <div class="prof-card" id="'+brand+'-'+length+'">\
      <span class="len-info">Длина артикула: <b>'+length+'</b></span>\
      <input class="prof-inp" name="'+brand+'_'+length+'" value="">\
      <button class="del-btn btn btn-danger" value="'+brand+'-'+length+'">Удалить</button>\
    </div>\
    ');
  })
  $(document).on('click', '#save-settings', function(e){
    $.ajax({
      url: ajax_folder_path + "save_settings.php",
      method: "POST",
      data: $('#settings-form').serialize(),
      success: function(response){
        console.log('good');
        alert('Настройки сохранены');
      }
    })
  })
  $(document).on('click', '#create-brand-profile', function(e){
    e.preventDefault();
    var brand = $('#brand-list').val();
    $('.tabs').append('\
    <button id="'+brand+'-tab" class="tab" value="'+brand+'">'+brand.toUpperCase()+'</button>\
    ');
    $('#settings-form').append('\
      <div class="profile" id="'+brand+'" style="display: none">\
        <h2>'+brand.toUpperCase()+'</h3>\
        <div class="prof-head">\
          <input class="str-len-input" type="text" id="'+brand+'_set" placeholder="Длина артикула">\
          <button id="new_prof" style="margin-left: 10px" value="'+brand+'" class="add-new btn btn-primary">Добавить профиль</button>\
          <button class="delete-profile btn btn-danger" value="'+brand+'">Удалить профиль</button>\
        </div>\
        <hr>\
        <div class="prof-body '+brand+'-body">\
      </div>\
    </div>\
    ');
  })
</script>
