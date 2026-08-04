<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();
$cabinetArr = array('IP','TI');
CModule::IncludeModule("iblock");

$CurDB = new DBPanel();
$result = $CurDB->query("SELECT * FROM ozon_agents");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $arAgents[] = $row;
}
unset($result);
unset($rows);
//print_r($arAgents);
?>
<meta charset="UTF-8">
<div class="card" id="table-panel">
  <ul class="list-group list-group-flush">

  <?php foreach ($arAgents as $agent): ?>
  <li class="list-group-item resize" style="">
    <div class="name"><?=$agent['name']?></div>
    <div class="status">
      <?if ($agent['status'] == 'COMPLETE') {?>
        <div class="comp-text"><?=$agent['status_text']?> </div>
        <div class="time-text">Начало: <?=$agent['time_start']?> |<br class="mob-break"> Завершено: <?=$agent['time_end']?></div>
      <?} else if ($agent['status'] == 'ABORTED') {?>
        <div class="comp-text" style="color:#e81212;"><?=$agent['status_text']?> </div>
        <div class="time-text">Начало: <?=$agent['time_start']?> |<br class="mob-break"> <span style="color:#e81212;">Завершено: <?=$agent['time_end']?></div></span>
      <?} else {?>
        <div class="progress">
          <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" aria-valuenow="<?=$agent['percent']?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$agent['percent']?>%">
            <span><?=$agent['percent']?>% </span>
          </div>
        </div>
        <div class="time-text">Начало: <?=$agent['time_start']?> |<br class="mob-break"> Статус: <b><?=$agent['status_text']?></b></div>
      <?}?>
    </div>
    <div class="control">
      <?if ($agent['status'] == 'COMPLETE' || $agent['status'] == 'ABORTED' ) {?>
      <button type="button" class="btn btn-success run-script" data-script="<?=$agent['sript_url']?>" data-code="<?=$agent['code']?>"  data-name="<?=$agent['name']?>" style="display: flex;align-items: center;gap: 10px;height: 30px;width: 140px;">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#fff" width="14px" height="14px">
          <path d="M20.4086 9.35258C22.5305 10.5065 22.5305 13.4935 20.4086 14.6474L7.59662 21.6145C5.53435 22.736 3 21.2763 3 18.9671L3 5.0329C3 2.72368 5.53435 1.26402 7.59661 2.38548L20.4086 9.35258Z" stroke-width="1.5" stroke="#fff"></path>
        </svg>
      Запустить
      </button>
      <?} else {?>
        <button type="button" class="btn btn-warning reboot-script" data-script="<?=$agent['sript_url']?>" data-code="<?=$agent['code']?>" data-name="<?=$agent['name']?>" style="display: flex;align-items: center;gap: 10px;height: 30px;width: 140px;">
          <svg fill="#000000" width="14px" height="14px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 16q0-2.784 1.088-5.312t2.912-4.384 4.384-2.912 5.344-1.088q2.784 0 5.312 1.088t4.384 2.912 2.912 4.384 1.088 5.312h2.304q0.736 0 1.28 0.416t0.8 1.024 0.16 1.28-0.64 1.184l-4.576 4.576q-0.672 0.672-1.6 0.672t-1.632-0.672l-4.576-4.576q-0.512-0.512-0.608-1.184t0.128-1.28 0.8-1.024 1.312-0.416h2.272q0-2.464-1.216-4.576t-3.328-3.328-4.576-1.216-4.608 1.216-3.328 3.328-1.216 4.576 1.216 4.608 3.328 3.328 4.608 1.216q1.728 0 3.36-0.64l3.424 3.392q-3.136 1.824-6.784 1.824-2.816 0-5.344-1.088t-4.384-2.912-2.912-4.384-1.088-5.344z"></path>
          </svg>
          Перезапуск
        </button>
        <button type="button" class="btn btn-danger kill-script" data-script="<?=$agent['sript_url']?>" data-code="<?=$agent['code']?>"  data-name="<?=$agent['name']?>" style="display: flex;align-items: center;gap: 10px;height: 30px;width: 140px;">
          <svg width="14px" height="14px" viewBox="0 -0.5 8 8" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
            <defs>
            </defs>
            <g id="Page-1" stroke="none" stroke-width="1" fill="#fff" fill-rule="evenodd">
              <g id="Dribbble-Light-Preview" transform="translate(-385.000000, -206.000000)" fill="#fff">
                <g id="icons" transform="translate(56.000000, 160.000000)">
                  <polygon id="close_mini-[#1522]" points="334.6 49.5 337 51.6 335.4 53 333 50.9 330.6 53 329 51.6 331.4 49.5 329 47.4 330.6 46 333 48.1 335.4 46 337 47.4">
                  </polygon>
                </g>
              </g>
            </g>
          </svg>
          Остановить
        </button>
      <?}?>
    </div>
  </li>
  <?php endforeach; ?>

  </ul>
</div>
