<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<? AccessValidator::checkIfAllowed(); ?>
<?$APPLICATION->SetTitle('Настройки утилит');?>
<?
global $USER;
if ( !$USER->isAdmin() ){
  die('<span style="display: flex; width: fit-content; margin: 20px auto 0 auto; font-size: 16px; font-weight: bolder"> ¯\_(ツ)_/¯ Видимо, у вас не хватает прав, для просмотра этой страницы</span>');
}
$db = new DBPanel;
$rows = $db->select( ['*'], 'admin_utilities_groups' )->make();
$groups = [];
foreach ( $rows as $row ){
  $groups[ $row['id'] ] = $row['name'];
}
$res = CGroup::getList();
$access = [];
while( $row = $res->GetNext() ){
  $access[ $row['ID'] ] = $row['NAME'];
}
?>

<h1>Настройки утилит</h1>
<div class="main-control-container">
  <button class="btn btn-light add-new-util">Добавить утилиту</button>
  <!-- <button class="btn btn-primary add-new-group" disabled>Добавить группу</button> -->
  <!-- <button class="btn btn-warning edit-groups" disabled>Редактор групп</button> -->
</div>
<hr>
<div class="access-container">

</div>

<div class="modal-background" style="display:none;">
  <div class="modal-window">
    <div class="modal-header">
      <h3>Добавить утилиту</h3>
    </div>
    <div class="modal-body" style="display:flex; flex-direction: column; gap: 10px">
        <input id="util-name" type="text" class="form-control form-text form-field" value="" placeholder="Введите название">
        <input id="util-link" type="text" class="form-control form-text form-field" value="" placeholder="Введите ссылку">
        <select id="util-group" class="form-control" placeholder="Выбреите группу">
          <? foreach( $groups as $id => $name): ?>
          <option value="<?=$id?>"><?=$name?></option>
          <? endforeach; ?>
        </select>
        <select id="util-access" class="form-select form-field" name="multiselect[]" multiple="multiple">
          <? foreach( $access as $id => $name): ?>
          <option value="<?=$id?>"><?=$name?></option>
          <? endforeach; ?>
        </select>
    </div>
    <div class="modal-footer">
      <button id="add-util-submit" class="btn btn-light" disabled>Сохранить</button>
    </div>
  </div>
</div>

<div class="response-block">

</div>

<style media="screen">
  .util{
    padding: 15px;
    width: 80%;
    /* border-top-left-radius: 4px; */
    /* border-top-right-radius: 4px; */
    border-bottom: 1px solid rgba(0,0,0,0.15);
  }
  .util summary{
    font-size: 17px;
    color: #337ab7;
    cursor: pointer;
  }
  .util-body{
    display: flex;
    flex-direction: row;

  }
  .fields, .control{
    display: flex;
    flex-direction: column;
    width: 50%;
  }
  .fields input, .fields select{
    width: 100%;
    margin-bottom: 10px;
  }

  .control button{
    width: 140px;
    margin-bottom: 10px;
    margin-left: auto;
  }

  .checkbox input, .caret{
    display: none
  }
  .main-control-container{
    display: flex;
    width: 100%;
    gap: 10px;
  }
  .add-new-util{
    margin-left: auto;
  }
  .response-block{
    position: fixed;
    bottom: 20px;
    right: 20px;
  }
  /* Modal window */
  .modal-background{
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    position: fixed;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    z-index: 9998;
    overflow-y: auto;
  }
  .modal-window{
    display: flex;
    padding: 15px;
    border-radius: 6px;
    flex-direction: column;
    width: 33%;
    background-color: white;
    margin: 10% auto auto auto;
    z-index: 999;
  }

</style>

<script type="text/javascript">
  const ajax = "/admin/utilities/access_control/ajax/";

  function getList(){
    $.ajax({
      url: ajax + 'getItems.php',
      method: "POST",
      success: function(response){
        $('.access-container').html(response);
        $(document).ready(function() {
          $('.access-selector').multiselect({
            buttonWidth: '100%',
            numberDisplayed: 4
          });
        });
      },
      error: function(response){
        alert('JIBRF');
      }
    })
  }

  function addNewUtility(){
    var name = $('#util-name').val();
    var link = $('#util-link').val();
    var group = $('#util-group').val();
    var access = $('#util-access').val();
    $.ajax({
      url: ajax + 'addItem.php',
      method: "POST",
      data: {
        name: name,
        link: link,
        group: group,
        access: access,
      },
      success: function(response){
        $('.modal-background').hide();
        getList();
        $('.response-block').html(response);
        setTimeout(function(){
          $('.notifictaion').fadeOut();
        }, 1000)
        setTimeout(function(){
          $('.response-block').html();
        }, 3000)
      },
      error: function(response){
        alert('Ошибка сохранения элемента');
      }
    })
  }

  function deleteItem(id){
    $.ajax({
      url: ajax + 'deleteItem.php',
      method: "POST",
      data: {id:id},
      success: function(response){
        $('.response-block').html(response);
        getList();
      },
      error: function(response){
        alert('Системная ошибка');
      }
    })
  }

  function editItem( id ){
    var name = $( '#name_' + id ).val();
    var link = $( '#link_' + id ).val();
    var group = $( '#group_' + id ).val();
    var access = $( '#access_' + id ).val();

    $.ajax({
      url: ajax + 'editItem.php',
      method: "POST",
      data: {
        name: name,
        link: link,
        group: group,
        access: access,
        id: id,
      },
      success: function(response){
        $('.response-block').html(response);
        getList();
      },
      error: function(response){
        alert('Системная ошибка')
      }
    })
  }

  $(document).on('click', '.save-btn', function(){
    var utility_id = $(this).attr('data-id');
    editItem( utility_id );
  })

  $(document).on('click', '.del-btn', function(){
    var utility_id = $(this).attr('data-id');
    var con = confirm( "Подтвердите удаление элемента" );
    if ( con ){
      deleteItem( utility_id );
    }else{
      console.log('Не удалено');
    }
  })

  $(document).on('click', '.util', function(e){
    $('.util').not(this).removeAttr('open');
    $(this).attr('open');
  })

  $(document).on('change', '.form-text', function(){
    var t1 = $('#util-name').val();
    var t2 = $('#util-link').val();
    if ( t1 != '' && t2 != '' ){
      $('#add-util-submit').removeAttr('disabled');
    }else{
      $('#add-util-submit').attr('disabled');
    }
  })

  $(document).on('click', '#add-util-submit', function(){
    addNewUtility();
  });

  $(document).ready(function() {
    $('#util-access').multiselect({
      buttonWidth: '100%',
      numberDisplayed: 4
    });
  });
  $(document).on('click', '.modal-background', function(e){
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
      $('.form-field').val('').change();
    };
  })
  $(document).on('click', '.add-new-util', function(e){
    $('.modal-background').fadeIn();
  })

  getList();


</script>


 <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
