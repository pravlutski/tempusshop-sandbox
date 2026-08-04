<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle('Генерация описания RICH');?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
<?php
$propCodes = ["DETAIL_TEXT", "PROPERTY_DESC_RICH_OZON","PROPERTY_OZON_ACTIVE", "MATERIAL", "FEATURES", "CASE", "GLASS", "CALENDAR", "WR", "BACKLIGHT", "FACE", "vendorcode", "COLOR", "dial_color", "DIAMETER", "HEIGHT", "THIKNESS", "NAME_MARKETPLACE", "MECHANISM"];
$res = CIBlockPropertyEnum::GetList(array(), ["IBLOCK_ID" => 16, "CODE" => $propCodes]);
$propsBX = [];
while ( $prop = $res->GetNext() ){
  $propsBX[ $prop['PROPERTY_NAME']][] = [
    'property_id' => $prop['PROPERTY_ID'],
    'property_name' => $prop['PROPERTY_NAME'],
    'option_id' => $prop['ID'],
    'option_name' => $prop['VALUE'],
  ];
}
unset($prop);
global $DB;
$strSql = "SELECT * FROM sds_property_text_match";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
while ( $row = $result->Fetch() ){
  $propsDB[ $row['option_id'] ] = $row['text'];
}
unset($row);
$excludeProp = ['Цвет корпуса', 'Цвет циферблата'];
$excludeOpt = [1939, 2039, 1940, 2041, 2028, 491];
 ?>

<div id="container">
  <div class="header">
    <h1>Генерация описания RICH</h2>
    <hr>
    <div class="tabs">
      <button id="settings-tab" class="tab active">Настройки</button>
      <button id="upload-tab" class="tab">Принудительное обновление</button>
      <button id="log-tab" class="tab" style="display:none;">Логи</button>
      <button id="save-settings" class="control-btn">Сохранить настройки</button>
    </div>
  </div>
  <div class="status-block">

  </div>
  <div class="body body-settings">
    <form id="settings-form" >
      <?foreach ($propsBX as $p_name => $prop):?>
      <?php if ( in_array($p_name, $excludeProp) ) continue; ?>
      <details id="<?echo $p_name;?>" class="drop">
        <summary><? echo $p_name;?></summary>
        <hr>
        <?foreach ($prop as $value):?>
        <?php if ( in_array($value['option_id'], $excludeOpt) ) continue; ?>
        <?php
        $fieldName = $value['property_id'] . '+' . $value['property_name'] . '+' . $value['option_id'] . '+' . $value['option_name'];
        ?>
        <div class="field-row">
          <div class="field-label">
            <span class="field-name"><?echo $value['option_name']?></span>
          </div>
          <div class="field-input">
            <textarea class="field-txt" name="<?echo $fieldName;?>" rows="3" cols="60" placeholder="Добавьте фразу..."><?echo $propsDB[$value['option_id']];?></textarea>
          </div>
        </div>
        <hr>
        <?endforeach;?>
      </details>
      <?endforeach;?>
    </form>
  </div>
  <div class="body body-upload" style="display:none;">
    <form id="update-form">
      <div class="upload-container">
        <label>
          <span>Детальное описание</span>
          <input type="checkbox" name="detail" value="1">
        </label>
        <label>
          <span>Описание RICH</span>
          <input type="checkbox" name="rich" value="1">
        </label>
        <textarea name="codes" class="field-txt upload-txt" rows="10" cols="90" placeholder="Введите артикулы (каждый с новой строки, без знаков препинания)"></textarea>
        <button id="force-update" class="control-btn">Обновить описание</button>
      </div>
    </form>
  </div>
  <div class="body body-log" style="display:none;">

  </div>
</div>

<style media="screen">
  .tabs{
    display: flex;
    flex-direction: row;
  }
  .drop{
    display: flex;
    flex-direction: column;
    margin-top: 20px;
    padding: 20px;
    border: 1px solid black;
    cursor: pointer;
  }
  .drop summary{
    font-size: 18px;
    font-weight: bold;
  }

  .field-row{
    display: flex;
    flex-direction: row;
    margin-top: 20px;
  }
  .field-label{
    width: 20%;
    padding-right: 20px;
    font-size: 15px;
    font-weight: bolder;
  }
  .field-txt{
    resize: none;
    padding: 10px;
  }
  .upload-txt{
    margin-top: 20px;
  }
  #force-upload{
    margin-top: 15px;
  }

  .control-btn{
    margin-left: auto;
    border: none;
    height: 40px;
    width: 240px;
    background-color: #f0ad4e;
    color: black;
    font-weight: bolder;
  }
  .control-btn:hover{
    background-color: #f4b760;
    color: #1e1d1d;
  }

  .tab{
    border: none;
    margin-right: 10px;
    height: 40px;
    width: 260px;
    color: white;
    background-color: #337ab7;
    font-size: 15px;
    font-weight: normal;
  }
  .active{
    background-color: #f0ad4e !important;
    color: black !important;
    font-size: 16px;
    font-weight: bolder;
  }

  .upload-container{
    display: flex;
    flex-direction: column;
    padding-top: 20px;
  }
  .status{
    width: 100%;
    padding: 40px;
    margin-top: 20px;
    margin-bottom: 20px;
    border-radius: 6px;
    text-align: center;
    font-weight: bold;
    font-size: 20px;
  }
  .status-good{
    background-color: rgba(0,200,0, 0.5);
    color: white;
  }
  .status-bad{
    background-color: rgba(200,0,0, 0.5);
    color: white;
  }
  #force-update{
    margin-top: 20px;
  }
</style>

<script type="text/javascript">

  $(document).on('click', '#settings-tab', function(e){
    e.preventDefault();
    $('.tab').removeClass('active');
    $(this).addClass('active');
    $('.status').hide();
    $('.body').hide();
    $('.body-settings').show();
  })
  $(document).on('click', '#upload-tab', function(e){
    e.preventDefault();
    $('.tab').removeClass('active');
    $(this).addClass('active');
    $('.status').hide();
    $('.body').hide();
    $('.body-upload').show();
  })
  $(document).on('click', '#log-tab', function(e){
    e.preventDefault();
    $('.tab').removeClass('active');
    $(this).addClass('active');
    $('.status').hide();
    $('.body').hide();
    $('.body-log').show();
  })
  $(document).on('click', '#save-settings', function(e){
    e.preventDefault();
    $.ajax({
      url: '/admin/modules/descGen/ajax/saveSettings.php',
      method: 'POST',
      data: $('#settings-form').serialize(),
      success: function(response){
        console.log(response);
        $('.status-block').html('<div class="status-good status">Сохранено!</div>');
      },
      error: function(response){
        console.log(response);
        $('.status-block').html('<div class="status-good status">Ошибка сохранения!</div>');
      }
    })
  })
  $(document).on('click', '#force-update', function(e){
    e.preventDefault();
    $('.status-block').html('');
    $.ajax({
      url: '/admin/modules/descGen/ajax/forceUpdate.php',
      method: 'POST',
      data: $('#update-form').serialize(),
      success: function(response){
        console.log(response);
        $('.status-block').html(response);
      },
      error: function(response){
        console.log(response);
        $('.status-block').html(response);
      }
    })
  })
</script>
