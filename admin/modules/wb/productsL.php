<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Настройки товаров - WB модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки товаров");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<?php
global $DB;
$strSql = "SELECT * FROM wdhs_wb_product_props";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$propsDB = [];
$dictChar = [];
while ( $row = $result->Fetch() ){
  $propsDB[] = $row;
  $established[$row['char_id']] = $row['bitrix_id'];
  $dictChar[$row['char_id']] = $row['name'];
}
unset($row);
unset($result);
$strSql = "SELECT * FROM wdhs_wb_product_props_dependencies";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$dependecies = [];
while ( $row = $result->Fetch() ){
  $key = $row['property_name'] . '_' . $row['property_id'] . '_' . $row['char_id'];
  $dependecies[$key][] = $row;
  $dictDep[$row['char_id']] = 1;
}
unset($row);
unset($result);
$strSql = "SELECT * FROM wdhs_wb_product_base";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$baseDB = [];
while ( $row = $result->Fetch() ){
  $baseDB[] = $row;
}

$res = CIBlockProperty::GetList(
    ["name" => "asc"],
    ["IBLOCK_ID" => 16]
);
$propBX = [];
while ( $row = $res->Fetch() ) {
  $propBX[$row["ID"]] = [
      "name" => $row["NAME"],
      "id" => $row["ID"],
      "type" => $row["PROPERTY_TYPE"],
      "code" => $row['CODE']
    ];
}


?>

<div id="container-p" class="">
  <div class="tabs" style="display:flex; flex-direction:row; border-bottom: 1px solid #dbdcdc">
    <button id="main-set" value="main-block" class="tab">Базовые</button>
    <button id="match-set" value="match-block" class="tab t-selected">Атрибуты</button>
    <button id="dep-set" value="dep-block" class="tab">Зависимости</button>
    <button id="upload-set" value="upload-block" class="tab">Выгрузка</button>
    <button id="help-set" value="help-block" class="tab">Справка</button>
  </div>
  <div id="match-block" class="tab-block">

    <button id="save-products" class="btn btn-warning">Сохранить настройки</button>
    <hr>

    <form id="products-list" class="" method="post">

      <? foreach($propsDB as $pdb):?>
        <div class="status-row">
          <div class="status-name">
            <span><?echo $pdb['name'];?></span>
          </div>
          <select class="status-select form-select" name="<?=$pdb['char_id']?>+bitrix_id">
            <option value="">Не выбрано</option>
            <? foreach ( $propBX as $id => $pbx ):?>
              <? if ( $established[$pdb['char_id']] == $id ):?>
                <option selected value="<?=$id?>"><?echo "[{$id}] {$pbx['name']}";?></option>
              <? else: ?>
                <option value="<?=$id?>"><?echo "[{$id}] {$pbx['name']}";?></option>
              <?endif;?>
            <? endforeach;?>
          </select>
          <?
          $text = '';
          if ( !empty($pdb['custom_value']) ){
            $text = json_decode($pdb['custom_value']);
            if ( count($text) > 1 ){
              $text = implode(';', $text);
            }else{
              $text = $text[0];
            }
          }
          ?>
          <input type="text" class="form-control" name="<?=$pdb['char_id']?>+custom_value" value="<?=$text?>" placeholder="<? echo $dictDep[$pdb['char_id']] ? 'Есть зависимость': ''?>">
          <!-- <span class="max-count">Макс. количество значений: </span> -->
        </div>
      <? endforeach; ?>
    </form>

  </div>

  <div id="dep-block" class="tab-block" style="display:none">
    <div class="d-btns-block" style="display:flex; flex-direction: row; padding: 15px 15px 0 15px">
      <select id="property_bx" class="form-select prop-selector" name="property_bx" style="width: 240px;">
        <option value="">Не выбрано</option>
        <? foreach ( $propBX as $id => $pbx ):?>
            <option value="<?=$id?>"><?echo "[{$id}] {$pbx['name']}";?></option>
        <? endforeach;?>
      </select>
      <select id="property_wb" class="form-select prop-selector" name="property_wb" style="width: 240px; margin-left: 20px;">
        <option value="">Не выбрано</option>
        <? foreach ( $propsDB as $pdb ):?>
            <option value="<?=$pdb['char_id']?>"><?echo "[{$pdb['char_id']}] {$pdb['name']}";?></option>
        <? endforeach;?>
      </select>

      <button id="create-dep" class="btn btn-warning" style="margin-left: 20px;">Создать зависимость</button>
      <button id="save-dep" class="btn btn-warning" style="margin-left: auto">Сохранить настройки</button>
    </div>
    <hr>
    <form id="dep-list" style="padding: 15px;">
      <div class="list-field">
        <? foreach ( $dependecies as $nid => $elem ):?>
          <?php
            $name = explode('_', $nid)[0];
            $pid = explode('_', $nid)[1];
            $cid = explode('_', $nid)[2];
          ?>
          <details id="<?=$pid?>_<?=$cid?>" class="dep-piece">
            <summary><?=$name?> --> <?=$dictChar[$cid]?></summary>
            <hr>
            <button id="del_<?=$pid?>_<?=$cid?>" class="btn btn-danger del-btn">Удалить зависимость</button>
            <? foreach ( $elem as $option ):
                  $text = '';
                  if ( !empty($option['value']) ){
                    $value = json_decode($option['value']);
                    if ( count($value) > 1) {
                      $text = implode(';', $value);
                    }else{
                      $text = $value[0];
                    }
                  }
              ?>
            <div class="dep-row row">
              <span class="dep-name"><?=$option['option_name']?></span>
              <input type="text" name="<?=$option['option_id']?>+<?=$option['char_id']?>" class="form-input dep-value" value="<?=$text?>">
            </div>
            <? endforeach;?>
          </details>
        <? endforeach;?>
      </div>
    </form>
  </div>

  <div id="upload-block" class="tab-block" style="display:none;">
    <button id="upload-products" class="btn btn-warning" style="margin-bottom: 15px;">Обновить принудительно</button>
    <div class="">
      <form id="upload-form">
        <div class="radio-block">
          <div class="radio-row">
            <span class="radio-name">Изображения</span>
            <input class="radio-input" type="checkbox" name="is-image" value="1">
          </div>
          <div class="radio-row">
            <span class="radio-name">Информация</span>
            <input class="radio-input" type="checkbox" name="is-info" value="1">
          </div>
        </div>
        <div class="ta-block">
          <textarea class="txt-area" name="models" cols="40" placeholder="Вставьте список артикулов..."></textarea>
        </div>
        <div class="info-block">
          <ol>
            <li>Вставьте артикулы (каждый с новой строки)</li>
            <li>Выберите, что выгружать, отметив чекбоксы</li>
            <li>Нажмите кнопку "Обновить принудительно"</li>
            <li>Ожидайте</li>
          </ol>
        </div>
      </form>
    </div>
    <div class="upload-result-block">
      <h3 style="margin-left: 20px;">Результат</h3>
      <hr>
      <div class="upload-result" style="min-height: 300px;">

      </div>
    </div>
  </div>

  <div id="help-block" class="tab-block" style="display:none;">

  </div>

  <div id="main-block" class="tab-block" style="display:none">

    <button id="save-main" class="btn btn-warning">Сохранить настройки</button>
    <hr>
    <form id="main-settings">
      <? foreach($baseDB as $pdb):?>
        <div class="status-row">
          <div class="status-name">
            <span><?echo $pdb['name'];?></span>
          </div>
          <select class="status-select form-select" name="<?=$pdb['field']?>">
            <option value="">Не выбрано</option>
            <? foreach ( $propBX as $id => $pbx ):?>
              <? if ( $id == $pdb['bitrix_id'] ):?>
                <option selected value="<?=$id?>"><?echo "[{$id}] {$pbx['name']}";?></option>
              <? else: ?>
                <option value="<?=$id?>"><?echo "[{$id}] {$pbx['name']}";?></option>
              <?endif;?>
            <? endforeach;?>
          </select>
        </div>
      <? endforeach; ?>
    </form>
    <form id="main-settings">

    </form>
  </div>

</div>

<style media="screen">
  .ui-front {
    z-index: 1000000000!important;
  }
  #main-settings{
    width: 40%;
    padding: 5px 0 20px 20px;
  }

  #upload-form{
    display: flex;
    flex-direction: row;
    width: 100%;
    padding: 0 0 20px 0;
    border-top: 2px solid #cdcece;
  }
  .radio-block{
    width: 30%;
    border-bottom: 2px solid #cdcece;
  }
  .radio-row{
    padding: 20px;
    display: flex;
    flex-direction: row;
    border-bottom: 1px solid #cdcece;
  }
  .radio-name{
    font-size: 16px;
    font-weight: bolder;
  }
  .radio-input{
    width: fit-content;
    margin-left: auto;
  }
  .ta-block{
    width: 30%;
  }
  .info-block{
    width: 40%;
    padding: 10px 20px 0 20px;
    line-height: 35px;
  }
  .txt-area{
    resize: none;
    width: 100%;
    height: 400px;
    padding: 8px;
    border-bottom: 2px solid #cdcece;
    border-top: none;
    border-right: 2px solid #cdcece;
  }

  #container-p{
    border: 1px solid #dbdcdc;
    border-radius: 4px;
  }
  #save-products, #upload-products, #save-main{
    display: flex;
    margin-left: auto;
    margin-top: 15px;
    margin-right: 15px;
  }
  #products-list{
    padding: 0px 0px 20px 20px;
    display: flex;
    flex-direction: column;
    width: 70%;
  }
  .status-row{
    display: flex;
    flex-direction: row;
    width: 100%;
    margin-top: 20px;
    gap: 30px;
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
    width: 75%;
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
  .tab{
    background-color: #6c11c9;
    color: white;
    border:none;
    padding: 6px;
    font-size: 17px;
    width: 340px;
    height: 50px;
  }
  .tab:hover{
    font-weight: 600;
  }
  .t-selected{
    background-color: #ffc107;
    color: black;
    font-weight: 600;
  }
  .dep-piece{
    width: 70%;
    margin-bottom: 20px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    border: 1px solid black;
  }
  .dep-row{
    display: flex;
    margin-bottom: 20px;
    gap: 15px;
    padding-left: 15px;
    padding-right: 15px;
  }
  .dep-name{
    width: 27% !important;
    background-color: #6c757d;
    text-align: center;
    color: white;
    padding: 6px 8px 6px 8px;
    border-radius: 4px;
  }
  .dep-value{
    width: 40% !important;
  }
  .del-btn{
    margin-left: auto;
  }
  .required-err{
    border: 1px solid red !important;
  }
</style>

<script type="text/javascript">

  const ajax_folder_path = '/admin/modules/wb/ajax/products/';

  $(document).on('click', '#upload-products', function(e){
    e.preventDefault();
    $.ajax({
      url: ajax_folder_path + 'force_update_card.php',
      method: 'POST',
      data: $('#upload-form').serialize(),
      success: function(response){
        $('.upload-result').html(response);
      }
    })
  })
  $(document).on('click', '#save-dep', function(e){
    e.preventDefault();
    $.ajax({
      url: ajax_folder_path + 'save_dependencies.php',
      method: 'POST',
      data: $('#dep-list').serialize(),
      success: function(response){
        console.log('ok');
      }
    })
  })
  $(document).on('click', '.del-btn', function(e){
    e.preventDefault();
    var prop_id = $(this).attr('id').split('_')[1];
    var char_id = $(this).attr('id').split('_')[2];
    $.ajax({
      url: ajax_folder_path + 'delete_dependency.php',
      method: 'POST',
      data: {pid: prop_id, cid: char_id},
      success: function(response){
        console.log('success');
        $('#' + prop_id + '_' + char_id).remove();
      },
      error: function(response){
        console.log('error');
      }
    })
  })
  $(document).on('click', '#save-products', function(e){
    e.preventDefault();
    $.ajax({
      url: ajax_folder_path + 'save_products.php',
      method: 'POST',
      data: $('#products-list').serialize(),
      success: function(response){
        console.log('ok');
        alert('settings applied');
      },
      error: function(response){
        console.log('error');
        alert(response);
      }
    })
  })
  $(document).on('click', '#save-main', function(e){
    e.preventDefault();
    $.ajax({
      url: ajax_folder_path + 'save_main.php',
      method: 'POST',
      data: $('#main-settings').serialize(),
      success: function(response){
        console.log('ok');
        alert('settings applied');
      },
      error: function(response){
        console.log('error');
        alert(response);
      }
    })
  })
  $(document).on('change', '.prop-selector', function(){
    $(this).removeClass('required-err');
  })
  $(document).on('click', '#create-dep', function(e){
    e.preventDefault();
    var pbx = false;
    var pwb = false;
    if ( $('#property_bx').val() != '' ){
      pbx = true;
    }
    if ( $('#property_wb').val() != '' ){
      pwb = true;
    }
    if ( pwb == true && pbx == true){
      $.ajax({
        url: ajax_folder_path + 'create_dependency.php',
        method: 'POST',
        data: {idbx: $('#property_bx').val(), idwb: $('#property_wb').val()},
        success: function(response){
          console.log('ok');
          $('.list-field').html(response)
        },
        error: function(response){
          console.log('error');
          alert(response);
        }
      })
    }else{
      $('#property_bx').addClass('required-err');
      $('#property_wb').addClass('required-err');
    }
  })
  $(document).on('click', '.tab', function(e){
    e.preventDefault();
    $('.tab').removeClass('t-selected');
    $(this).addClass('t-selected');
    $('.tab-block').hide();
    $( '#' + $(this).val() ).show();
  })
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
