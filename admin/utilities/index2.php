<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle('Утилиты');?>
<?php
if(!CModule::IncludeModule('panel.manager'))return;
global $USER;
$arGroups = $USER->GetUserGroupArray();
$editButton = '';

function hasAccess( object $user, array $allowed ):bool
{
    if ( $user->isAdmin() ) return true;
    if ( empty($allowed) ) return true;
    $userGroups = $user->GetUserGroupArray();
    return count( array_intersect($userGroups, $allowed) ) > 0;
}

function filterAllowed( object $user, array $list ):array
{
  $filtered = [];
  foreach ( $list as $groupName => $utilsList ){
    foreach ( $utilsList as $key => $data ){
      if ( !hasAccess($user, $data['allowed']) ) continue;
      $filtered[$groupName][] = $data;
    }
  }
  return $filtered;
}

function getUtilsList()
{
  $db = new DBPanel;

  $groups = [];
  $access = [];
  $utils = [];

  $rows = $db->select(['*'], 'admin_utilities_groups')->make();
  foreach ( $rows as $row ){
    $groups[ $row['id'] ] = $row['name'];
  }
  $rows = $db->select(['*'], 'admin_utilities_access')->make();
  foreach ( $rows as $row ){
    $access[ $row['utility_id'] ][] = $row['user_group_id'];
  }
  $rows = $db->select(['*'], 'admin_utilities_list')->make();
  foreach ( $rows as $row ){
    $groupName = $groups[ $row['group_id'] ];
    $utils[ $groupName ][] = [
      'id' => $row['id'],
      'name' => $row['name'],
      'link' => $row['link'],
      'allowed' => $access[$row['id']] ?? [],
    ];
  }
  return $utils;
}

function generateEditButton( $user, $utilid )
{
  if ( $user->isAdmin() ){
    echo "<button class='edit-btn' data-id='{$utilid}'>🗝️</button>";
  }
  echo '';
}

$utilsGroups = getUtilsList();
$utilsGroups = filterAllowed( $USER, $utilsGroups );
$chunks = array_chunk($utilsGroups, 3, true);
if ( empty($utilsGroups) ) die('<span style="display: flex; width: fit-content; margin: 20px auto 0 auto; font-size: 16px; font-weight: bolder"> ¯\_(ツ)_/¯ Видимо, у вас не хватает прав, для просмотра этой страницы</span>');
 ?>

 <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/js/bootstrap-multiselect.js"></script>
 <link rel="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/css/bootstrap-multiselect.css" type="text/css"/>

<!-- <h1 class="page-header">Утилиты</h1> -->
<div class="list-container">
  <? foreach( $chunks as $key => $chunk ): ?>
  <div class="utils-container chunk-<?=$key?>">
    <?foreach ( $chunk as $nameRaw => $utilsList ):?>
    <div class="utils-group">

      <div class="group-header">
        <h3><?=$nameRaw?></h3>
      </div>

      <div class="utils-list">
        <? foreach ( $utilsList as $data): ?>
        <div style="display:flex; flex-direction:row">
          <a class="utils-link" href="<?=$data['link']?>"><?=$data['name']?></a>
          <?generateEditButton($USER, $data['id']);?>
        </div>
        <?endforeach;?>
      </div>

    </div>

    <?endforeach;?>
  </div>
  <? endforeach; ?>
</div>

<div class="modal-background" style="display:none">
  <div class="modal-window access-modal"></div>
</div>

<style media="screen">
  .list-container{
    display: flex;
    flex-direction: row;
    width: 100%;
  }
  .utils-container{
    display: flex;
    flex-direction: column;
    width: 50%;
    padding-left: 10px;
    padding-right: 10px;
  }
  .chunk-1{
    border-left: 1px solid rgba(0,0,0,0.15)
  }
  .utils-groups{
    display: flex;
    flex-direction: column;
    padding: 5px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
  }
  .group-header{
    border-bottom: 1px solid rgba(0,0,0,0.1);
    border-bottom-right-radius: 4px;
  }
  .utils-list{
    display: flex;
    flex-direction: column;
  }
  .utils-link{
    display: flex;
    width: 100%;
    padding: 15px;
    font-size: 17px;
  }
  .utils-link:hover{
    background-color: #f5f5f5;
    text-decoration: none !important;
  }
  .edit-btn{
    background-color: transparent;
    width: 7%;
    border:none;
  }
  .edit-btn:hover{
    background-color: #f5f5f5;
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
    padding: 30px;
    border-radius: 6px;
    flex-direction: column;
    width: 33%;
    background-color: white;
    margin: 10% auto auto auto;
    z-index: 999;
  }
  .caret{
    display: none
  }
</style>

<script type="text/javascript">
  const ajax = "/admin/utilities/access_control/ajax/";

  function initMutlitselect(){
    $(document).ready(function() {
      $('.access-selector').multiselect({
        buttonWidth: '100%',
        numberDisplayed: 4,
        nonSelectedText: 'Доступно всем',
        nSelectedText: ' имеют доступ'
      });
    });
  }

  function showModal( utility_id ){
    $.ajax({
      url: ajax + 'showModalData.php',
      method: 'POST',
      data: {id: utility_id},
      success: function(response){
        $('.access-modal').html(response);
        initMutlitselect();
      },
      error: function(response){
        alert('Системная ошибка');
        $('.modal-background').hide();
      }
    })
  }

  function editAccess( utility_id ){
    var data = $('#access_'+utility_id).val();
    $.ajax({
      url: ajax + 'editAccess.php',
      method: 'POST',
      data: {id: utility_id, data: data},
      success: function(response){
        alert('Сохранено');
        $('.modal-background').hide();
      },
      error: function(response){
        alert("Системная ошибка");
        $('.modal-background').hide();
      }
    });
  }


  $(document).on('click', '.modal-background', function(e){
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
    };
  })

  $(document).on('click', '.edit-btn', function(){
    var id = $(this).attr('data-id');
    showModal( id );
    $('.modal-background').show();
  })

  $(document).on( 'click', '.save-btn', function(){
    var id = $(this).attr('data-id');
    editAccess(id);
  })

  $(document).on('change', '.access-selector', function(){
    $('.save-btn').removeAttr('disabled');
  })
</script>

 <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
