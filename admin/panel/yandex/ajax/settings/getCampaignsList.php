<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST['cabinet']) ) throw new Exception("empty cabinet value");
$cabinet = $_POST['cabinet'];

UIProcessor::init();
$campaigns = UIProcessor::data()->settings()->getCampaignsList( $cabinet );

 ?>

 <? foreach ( $campaigns as $c ): ?>
 <div class="input-group mb-3" id="store_<?=$c['campaignId']?>">
   <span class="input-group-text" id="basic-addon3" style="width: 88%;"><?=$c['domain']?></span>
   <button class="btn btn-danger delete-btn" value="<?=$c['campaignId']?>">Удалить</button>
 </div>
 <? endforeach; ?>
