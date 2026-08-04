<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$db = new DBPanel;
$utility = $_POST['id'];
if ( empty($id) || !is_numeric($id) ) die('Некорректный id правила');

$rows = $db->select(['*'], 'admin_utilities_access')->where('utility_id', $utility)->make();
$data = [];
foreach ( $rows as $row ){
  $data[] = $row['user_group_id'];
}

$utility_name = $db->select(['name'], 'admin_utilities_list')->where('id', $utility)->make()[0]['name'];

$res = CGroup::getList();
$allGroups = [];
while( $row = $res->GetNext() ){
  $allGroups[ $row['ID'] ] = $row['NAME'];
}
 ?>

<div class="access-container" style="display:flex; flex-direction:column; width: 100%">
  <div class="head" style="border-bottom: 1px solid rgba(0,0,0,0.15)">
    <h3 class="modal-name">Настройки доступа (<?=$utility_name?>)</h3>
  </div>
  <div class="select-block" style="margin: 15px 0px 30px 0px">
    <select multiple="multiple" name="multiselect[]" class="access-selector" id="access_<?=$utility?>">
      <? foreach( $allGroups as $id => $name ): ?>
        <? if ( in_array($id, $data) ):?>
          <option value="<?=$id?>" selected><?=$name?></option>
        <? else:?>
          <option value="<?=$id?>"><?=$name?></option>
        <? endif; ?>
      <? endforeach; ?>
    </select>
  </div>
  <div class="control-block" style="display:flex; flex-direction: row; width: 100%">
    <!-- <button class="btn btn-danger close-btn" >Закрыть</button> -->
    <button class="btn btn-light save-btn" style="margin-left: auto" data-id="<?=$utility?>" disabled>Сохранить</button>
  </div>
</div>
