<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Сортировка - модуль обмена с сайтами');?>
<?$APPLICATION->SetPageProperty("page_h1", "Настройки индекса сортировки");?>
<?
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$strSql = "SELECT * FROM sites_sort_list_ru";
$res = $dbPanel->query( $strSql );
$rows_ru = $dbPanel->fetchAll( $res );

$strSql = "SELECT * FROM sites_sort_list_by";
$res = $dbPanel->query( $strSql );
$rows_by = $dbPanel->fetchAll( $res );
?>

<div id="container">
  <div class="tabs">
    <button class="btn btn-primay tab-btn selected" value="ru">Сайт RU</button>
    <button class="btn btn-primay tab-btn" value="by" style="display:none">Сайт BY</button>
  </div>

  <div class="tab-blocks">
    <div id="block-by" class="tab" style="display:none">

      <div class="control-block">
        <form id="txt-area-by" action="" method="post">
          <textarea class="txt-input" name="groups-txt" rows="8" cols="80" placeholder="Введите названия групп, каждую с новой строки..."></textarea>
        </form>
        <!-- <button id="delete-set-btn-by" class="btn btn-danger" style="flex-shrink:start; margin-left:auto">Удалить все</button> -->
        <button value="by" class="btn btn-warning save-set-btn" style="flex-shrink:start; margin: 0px 0px 0px 10px">Сохранить настройки</button>
      </div>
      <hr>
      <div class="show-block">
        <form id="active-groups-by" action="" method="post">
          <? foreach ( $rows_by as $row ): ?>
           <div class="group-item" draggable="true" id="<?=$row['id']?>-by">
             <div class="item-control">
               <svg class="grab-icon" width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                 <path d="M4 18L20 18" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                 <path d="M4 12L20 12" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                 <path d="M4 6L20 6" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
               </svg>
             </div>
             <div class="item-input">
               <input type="text" class="form-control" name="<?=$row['id']?>" value="<?=$row['group_name']?>">
             </div>
             <div class="item-btn">
               <button class="del-btn btn btn-danger" value="<?=$row['id']?>-by">Удалить</button>
             </div>
           </div>
           <? endforeach; ?>

        </form>
      </div>

    </div>
    <div id="block-ru" class="tab">

      <div class="control-block">
        <div style="display: flex">
          <form id="txt-area-ru" action="" method="post">
            <textarea class="txt-input" name="groups-txt" rows="8" cols="80" placeholder="Введите названия групп, каждую с новой строки..."></textarea>
          </form>
          <div class="alert alert-success" style="margin-left: 10px">
            <ul style="padding: 15px;">
              <li>Индекс сортировки устанавливается на основе <b>таблицы прибыльности за год</b></li>
              <li>Товары сортируются <b>по количеству продаж</b></li>
              <li>Товарам, <b>отсутвующим в продаже</b> в момент работы скрипта, <b>индекс не устанавливается</b></li>
              <li>Таблицы прибыльности обновляется <b>каждый понедельник в полночь</b></li>
              <li>Индексы сортировки пересчитываются <b>каждый поденедельник в полдень</b></li>
            </ul>
          </div>
        </div>
        <div style="display: flex; width: 100%">
          <button value="ru" class="btn btn-warning save-set-btn" style="flex-shrink:end; margin: 10px 0px 0px auto">Сохранить</button>
        </div>
      </div>
      <hr>
      <div class="parent-block">
        <div class="show-block">
          <form id="active-groups-ru" action="" method="post">
            <? foreach ( $rows_ru as $row ): ?>
            <div class="group-item" draggable="true" id="<?=$row['id']?>-ru">
              <div class="item-control">
                <svg class="grab-icon" width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M4 18L20 18" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                  <path d="M4 12L20 12" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                  <path d="M4 6L20 6" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="item-input">
                <input type="text" class="form-control" name="<?=$row['id']?>" value="<?=$row['group_name']?>">
              </div>
              <div class="item-btn">
                <button class="del-btn btn btn-danger" value="<?=$row['id']?>-ru">Удалить</button>
              </div>
            </div>
          <? endforeach; ?>

        </form>
      </div>

      <div class="sorted-block">

      </div>
    </div>

    </div>
  </div>

</div>

<style media="screen">
  #container{
    display: flex;
    flex-direction: column;
    width: 100%;
    padding: 15px;
  }
  #save-set-btn, #delete-set-btn{
    align-self: flex-start;
  }
  #delete-set-btn{
    margin-left: auto;
  }
  .save-set-btn{
    margin-left: auto !important;
  }
  .txt-input{
    resize: none;
    padding: 10px;
    border: 1px solid rgba(0,0,0,0.25);
    border-radius: 4px;
  }
  .control-block{
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }
  .group-item{
    display: flex;
    flex-direction: row;
    gap: 15px;
    width: 85%;
    padding: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
    border-radius: 4px;
  }
  .item-control{
    width: 10%;
  }
  .grab-icon{
    display: flex;
    margin: auto;
    margin-top: 7px;
    cursor: grab
  }
  .item-control:active {
    cursor: grabbing; /* Change cursor when grabbing */
  }
  .item-input{
    width: 70%;
  }
  .item-btn{
    display: flex;
    align-items: center;
    width: 20%;
  }
  .del-btn{
    margin-left: auto;
  }
  .group-item:active {
    cursor: grabbing; /* Меняет курсор при перетаскивании */
  }
  .group-item.dragging {
      opacity: 0.5; /* Необязательно: Делает перетаскиваемый элемент полупрозрачным */
    }
  .group-item.drag-over {
      border-top: 2px dashed blue; /* Визуальный индикатор места вставки */
  }
  .item-control {
      /* Удаляем изменения курсора с иконки захвата. Сам элемент является перетаскиваемым*/
  }
  .tab-btn{
    width: 140px;
  }
  .selected{
    background-color: #30defb !important;
    font-weight: bolder !important;
  }
  .tab-btn:hover{
    font-weight: bolder;
  }

  .parent-block{
    display: flex;
    flex-direction: row;
  }
  .sorted-block{
    width: 40%;
  }
  .show-block{
    width: 60%;
  }
  .list-sorted{
    display: flex;
    flex-direction: column;
    border-left: 1px solid rgba(0,0,0,0.25);
    border-bottom: 1px solid rgba(0,0,0,0.25);
    border-bottom-left-radius: 4px;
    border-top-left-radius: 4px;
    height: 700px;
    overflow-y: auto;
  }
  .chunk{
    width: 50%;
  }
  .item-sorted{
    height: 42px;
    padding: 6px;
    display: flex;
    flex-direction: row;
    border-bottom: 1px solid rgba(0,0,0,0.25);
  }
</style>

<script type="text/javascript">

const base_url = "/admin/panel/sites/ajax/sort/";

getSorted();

function getSorted(){
  $.ajax({
    url: base_url + "showSorted.php",
    method: "POST",
    dataType: "html",
    success: function(response){
      $(".sorted-block").html(response);
    }
  });
}

function saveOptions( site ){
  var form_data = new FormData();
  form_data.append('txt_data', $('#txt-area-' + site).serialize());
  form_data.append('order_data', $('#active-groups-' + site).serialize());
  form_data.append( 'site', site );
  $.ajax({
    url: base_url + "saveOptions.php",
    method: "POST",
    data: form_data,
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      alert('Сохранено! Перезагрузите страницу');
    }
  });
}

function deleteOption( id, site ){
  $.ajax({
    url: base_url + "deleteOption.php",
    method: "POST",
    // dataType: 'json',
    data: {id: id, site: site},
    success: function(response){
      console.log(response);
      $('#' + id + '-ru').fadeOut();
      setTimeout(function(e){$('#' + id + '-ru').remove();},500)
    }
  });
}

$(document).on('click', '.tab-btn', function(e){
  e.preventDefault();
  var tab = $(this).val();
  $('.tab').hide();
  $('.tab-btn').removeClass('selected');
  $('#block-' + tab).show();
  $(this).addClass('selected');
})

$(document).on('click', '.save-set-btn', function(e){
  var site = $(this).val();
  saveOptions( site );
});
$(document).on('click', '.del-btn', function(e){
  e.preventDefault();
  var value = $(this).val();
  var id = value.split('-')[0];
  var site = value.split('-')[1];
  deleteOption( id, site );
})

</script>
<script src="/admin/panel/sites/js/sort_drag.js"></script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
