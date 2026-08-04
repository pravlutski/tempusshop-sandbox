<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');

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
  $utils[ $row['group_id'] ][] = [
    'id' => $row['id'],
    'name' => $row['name'],
    'link' => $row['link'],
    'allowed' => $access[$row['id']] ?? [],
  ];
}
$res = CGroup::getList();
$allGroups = [];
while( $row = $res->GetNext() ){
  $allGroups[ $row['ID'] ] = $row['NAME'];
}
 ?>
 <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/js/bootstrap-multiselect.js"></script>
 <link rel="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/css/bootstrap-multiselect.css" type="text/css"/>
<div class="utils-container">
  <? foreach ( $utils as $group => $list): ?>
  <div class="category">
    <h3><?=$groups[$group]?></h3>
    <? foreach( $list as $data ): ?>
    <details class="util" id="<?=$data['id']?>">
      <summary>⚙️ <?=$data['name']?></summary>
      <hr>
      <div class="util-body">
        <div class="fields">
          <input type="text" class="form-control" id="name_<?=$data['id']?>" value="<?=$data['name']?>" placeholder="Укажите название...">
          <input type="text" class="form-control" id="link_<?=$data['id']?>" value="<?=$data['link']?>" placeholder="Укажите ссылку...">
          <select class="form-control" id="group_<?=$data['id']?>">
            <? foreach( $groups as $id => $name ): ?>
              <? if ( $id == $group ):?>
                <option value="<?=$id?>" selected><?=$name?></option>
              <? else:?>
                <option value="<?=$id?>"><?=$name?></option>
              <? endif; ?>
            <? endforeach; ?>
          </select>
          <select multiple="multiple" name="multiselect[]" class="access-selector" id="access_<?=$data['id']?>">
            <? foreach( $allGroups as $id => $name ): ?>
              <? if ( in_array($id, $data['allowed']) ):?>
                <option value="<?=$id?>" selected><?=$name?></option>
              <? else:?>
                <option value="<?=$id?>"><?=$name?></option>
              <? endif; ?>
            <? endforeach; ?>
          </select>
        </div>
        <div class="control">
            <button data-id="<?=$data['id']?>" class="btn btn-danger del-btn">Удалить</button>
            <button data-id="<?=$data['id']?>" class="btn btn-primary save-btn">Сохранить</button>
        </div>
      </div>
    </details>
    <? endforeach; ?>
  </div>
<? endforeach; ?>
</div>
