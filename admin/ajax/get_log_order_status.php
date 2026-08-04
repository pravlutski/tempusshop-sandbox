<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

function getUsername( $id )
{
	return CUser::GetById($id)->GetNext()['LOGIN'];
}

if(CModule::IncludeModule("panel.manager")){

	$filename = "/home/bitrix/logs/utils_set_status_order.txt";

	$handle = @fopen($filename, "r");
	if($handle){

		$i = 0;
		while(($buffer = fgets($handle)) !== false){

			$arTmp = unserialize($buffer);
			if($arTmp){
				$arResult["LOG"][] = $arTmp;
			}else{
				$arResult["LOG"][] = array("TEXT" => $buffer);
			}
			/*	foreach($arTmp as $key => $arItem){
					$ar = explode(" - ", $arItem);

					$arResult[$i][trim($ar[0])] = trim($ar[1]);
					if($ar[0] == "login"){
						$cntReq[$ar[1]]++;
						$arLogin[] = addslashes(trim($ar[1]));
					}
					if($ar[0] == "operation"){
						$cntOperation[$ar[1]]++;
						$arOperation[] = addslashes(trim($ar[1]));
					}
				}

				$i++;
			}*/

		}
		fclose($handle);
		//
	}
	if($_REQUEST["date"]){
		$start = strtotime($_REQUEST["date"]);//prent($_REQUEST["date"]);prent($start);
		foreach($arResult["LOG"] as $k => $arItem){
			if($arItem["DATE"] && strtotime($arItem["DATE"]) < $start){
				unset($arResult["LOG"][$k]);
			}
		}
	}
	$arResult["LOG"] = array_reverse($arResult["LOG"]);
	//prent(count($arResult["LOG"]));
	//$arResult["LOG"] = array_slice($arResult["LOG"], -5000);prent($arResult["LOG"][0]);
	?>
	<div class="" style="display:none; width:100%">
		<pre>
			<?foreach($arResult as $key => $value):?>
			<p><?echo $key;?></p>
			<?endforeach;?>
		</pre>
	</div>
	<p>Количество записей - <?=count($arResult["LOG"])?></p>
	<table class="table" style="width: 100%">
		<tbody>
			<th>Время</th>
			<th>Текст</th>
			<th>Пользователь</th>
		</tbody>
		<body>
			<?foreach($arResult["LOG"] as $key => $arItem):?>
			<?if (!empty($arItem["DATE"])) {?>
			<tr>
				<td><?=$arItem["DATE"]?></b></td>
				<td><?=$arItem["TEXT"]?></td>
				<td><?=getUsername($arItem["USER_ID"])?></td>
			</tr>
			<?}?>
			<?endforeach?>
		</body>
	</table>
	<?
}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
