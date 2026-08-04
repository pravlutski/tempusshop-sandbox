<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");
UIProcessor::init();
$agents = UIProcessor::data()->settings()->getAgents();

function getStatusColor( string $status ):string
{
  switch ($status) {
    case 'ABORTED':
      return '#e81212';
      break;
    default:
      return 'black';
      break;
  }
}

function getActionButton ( string $status, string $code ):string
{
  $killBtn = '<button class="btn btn-danger action-btn" data-code="%s" data-action="kill">
    <span class="action-icon" data-feather="pause-circle"></span>
    <span style="margin-top: 2px">Остановить</span>
  </button>';
  $restartBtn = '<button class="btn btn-warning action-btn" data-code="%s" data-action="restart">
    <span class="action-icon" data-feather="repeat"></span>
    <span style="margin-top: 2px">Перезапуск</span>
  </button>';
  $startBtn = '<button class="btn btn-success action-btn" data-code="%s" data-action="start">
    <span class="action-icon" data-feather="play"></span>
    <span style="margin-top: 2px">Запустить</span>
  </button>';

  switch ($status) {
    case 'PROCESS':
      $killBtn = sprintf($killBtn, $code, $status);
      $restartBtn = sprintf($restartBtn, $code, $status);
      $element = $restartBtn . $killBtn;
      break;
    case 'COMPLETED':
      $element = sprintf($startBtn, $code, $status);
      break;
    case 'ABORTED':
      $element = sprintf($startBtn, $code, $status);
      break;
  }

  return $element;
}

function getStatusText( string $text, string $status, string $path, string $code )
{
  $element = '<a style="color:inherit; text-decoration: underline dotted;" data-agent="%s" href="#" class="process-log-btn">%s</a>';
  $path = $path . date('Y_m_d') . '.txt';
  if ( !file_exists($_SERVER['DOCUMENT_ROOT'] . $path) ) $status = '';

  switch ( $status ){
    case 'COMPLETED':
      $element = sprintf($element, $code, $text);
      break;
    case 'ABORTED':
      $element = sprintf($element, $code, $text);
      break;
    case 'PROCESS':
      $element = sprintf($element, $code, $text);
      break;
    default:
      $element = $text;
      break;
  }

  return $element;
}

?>

<div class="card" id="table-panel">
  <ul class="list-group list-group-flush">

  <?php foreach ($agents as $agent): ?>
    <?
    $action = getActionButton(code: $agent['code'], status: $agent['status']);
    $status = getStatusColor( status: $agent['status'] );
    $statusText = getStatusText(
      text: $agent['status_text'],
      status: $agent['status'],
      path: $agent['log'] ?? '',
      code: $agent['code']
    );
    ?>
  <li class="list-group-item resize" style="">
    <div class="name"><?=$agent['name']?></div>
    <div class="status">

      <div class="stand-by-block" style="display:<? echo ($agent['status'] != 'PROCESS') ? 'block': 'none'?>">
        <div class="comp-text" style="color:<?=$status?>"><?=$statusText?></a></div>
        <div class="time-text">Начало: <?=$agent['time_start']?> |<br class="mob-break"> <span>Завершено: <?=$agent['time_end']?></div></span>
      </div>

      <div class="progress-block" style="display:<? echo ($agent['status'] == 'PROCESS') ? 'block': 'none'?>">
        <div class="progress">
          <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" aria-valuenow="<?=$agent['percent']?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$agent['percent']?>%">
            <span><?=round($agent['percent'], 2)?>% </span>
          </div>
        </div>
        <div class="time-text">Начало: <?=$agent['time_start']?> |<br class="mob-break"> Статус: <b><?=$statusText?></b></div>
      </div>

    </div>
    <div class="control">
      <?=$action?>
    </div>
  </li>
  <?php endforeach; ?>

  </ul>
</div>
