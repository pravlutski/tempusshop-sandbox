<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$user_id = intval($_POST["id"]);
if(CModule::IncludeModule("panel.manager") && $user_id > 0){
	global $DB;
	$objCurrency = new CPanelCurrency;
	$arCurrency = $objCurrency->getList();
	//prent($arCurrency);
	$arFilter = array(
		"ID" => $user_id,
		"GROUPS_ID"			=> array(9),
	);
	$dbRes = CUser::GetList($by = 'ID', $order = 'ASC', $arFilter, array("SELECT"=>array()));
	if($arUser = $dbRes->Fetch()){
		$arResult["ID"] = $arUser["ID"];
		$arResult["LOGIN"] = $arUser["LOGIN"];
	}else{
		die("Пользователь не найден");
	}
	$arResult['PRICE_SETTINGS'] = [];
	$strSql = "SELECT * FROM ci_opt WHERE USER_ID = '{$arResult["ID"]}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$arResult["SETTINGS"] = json_decode($row["SETTINGS"], true);
		$arResult["PRICE_SETTINGS"] = json_decode($row["PRICE_SETTINGS"], true);
	}

	//print_r($arResult["PRICE_SETTINGS"]);
	$strSql = "SELECT * FROM b_sale_tp";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["TRADING_PLATFORM"][] = $row;
	}

	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title">Оптовик - <?=$arResult["LOGIN"]?></h4>
	</div>
	<form class="form-horizontal" id="apply-opt">
		<input type="hidden" name="USER_ID" value="<?=$arResult["ID"]?>" id="data-id">
		<div class="modal-body">
			<div class="col-lg-12">
				<div class="col-lg-3 ">
					<label style="">Наценка</label>
					<input type="number" class="form-control " name="MARGIN" value="<?if(isset($arResult["SETTINGS"]["MARGIN"])):?><?=$arResult["SETTINGS"]["MARGIN"]?><?endif?>">
				</div>
				<div class="col-lg-4 ">
					<label>Валюта</label>
					<select class="form-control select_w" name="CURRENCY">
						<option value="RUB" <?if($arResult["SETTINGS"]["CURRENCY"] == "RUB"):?>selected="selected"<?endif?>>RUB</option>
						<?foreach($arCurrency as $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if($arResult["SETTINGS"]["CURRENCY"] == $arItem["id"]):?>selected="selected"<?endif?>><?=$arItem["id"]?></option>
						<?endforeach?>
					</select>
				</div>
				<?/*<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">НДС</label>
					<input type="number" class="form-control " name="VAT" value="<?if(isset($arResult["SETTINGS"]["VAT"])):?><?=$arResult["SETTINGS"]["VAT"]?><?endif?>">
				</div>*/?>
				<div class="col-lg-4 ">
					<label>Канал продаж</label>
					<select class="form-control select_w" name="TRADING_PLATFORM">
						<option value="" <?if(!$arResult["SETTINGS"]["TRADING_PLATFORM"]):?>selected="selected"<?endif?>>-- выберите Канал продаж --</option>
						<?foreach($arResult["TRADING_PLATFORM"] as $arItem):?>
							<option value="<?=$arItem["ID"]?>" <?if($arResult["SETTINGS"]["TRADING_PLATFORM"] == $arItem["ID"]):?>selected="selected"<?endif?>><?=$arItem["NAME"]?></option>
						<?endforeach?>
					</select>
				</div>
			</div>
			<div class="col-lg-12">
				<div class="col-lg-4 ">
					<label for="price_vat">Брать цены с НДС</label>
					<input type="checkbox" name="PRICE_VAT" id="price_vat" <?if($arResult["SETTINGS"]["PRICE_VAT"] == "Y"):?>checked<?endif?>>
				</div>
			</div>
				<div class="col-lg-12 " style="display: flex;  flex-direction: column;  margin-top: 20px;  margin-bottom: 20px;">
					<label for="price_vat">Исключить товары (через запятую)</label>
					<textarea style="height: 300px;" name="EXCLUDE_ARTICLES" id="EXCLUDE_ARTICLES"><?=$arResult["SETTINGS"]["EXCLUDE_ARTICLES"]?></textarea>
				</div>
			</div>
			<div class="col-lg-12">
				<div class="wrap-settings row">
					<div class="col-lg-8">
						<label>Правило</label>
					</div>
					<div class="col-lg-4">
						<label>Наценка</label>
					</div>
					<?if (!empty($arResult['PRICE_SETTINGS'])) {?>
						<?$i = 1;?>
						<?php foreach ($arResult['PRICE_SETTINGS'] as $k => $profile): ?>
							<div class="list" data-key="<?=$k?>">
								<div class="col-lg-5">
									<input type="text" class="form-control rules_input" name="profile[<?=$k?>][price_from]" placeholder="От" value="<?=$profile['price_from']?>">
									<input type="text" class="form-control rules_input fl-right" name="profile[<?=$k?>][price_to]" placeholder="До" value="<?=$profile['price_to']?>">
								</div>
								<div class="col-lg-5">
									<input type="text" class="form-control rules_input fl-right" name="profile[<?=$k?>][markup]" placeholder="2" value="<?=$profile['markup']?>">
								</div>
								<div class="col-lg-2">
									<button type="button" class="close"><span aria-hidden="true">×</span></button>
								</div>
							</div>
						<?php endforeach; ?>
					<?} else {?>
						<div class="list" data-key="1">
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input" name="profile[0][price_from]" placeholder="От" value="">
								<input type="text" class="form-control rules_input fl-right" name="profile[0][price_to]" placeholder="До" value="">
							</div>
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input fl-right" name="profile[0][markup]" placeholder="2" value="">
							</div>
							<div class="col-lg-2">
								<button type="button" class="close"><span aria-hidden="true">×</span></button>
							</div>
						</div>
						<div class="list" data-key="2">
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input" name="profile[1][price_from]" placeholder="От" value="">
								<input type="text" class="form-control rules_input fl-right" name="profile[1][price_to]" placeholder="До" value="">
							</div>
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input fl-right" name="profile[1][markup]" placeholder="2" value="">
							</div>
							<div class="col-lg-2">
								<button type="button" class="close"><span aria-hidden="true">×</span></button>
							</div>
						</div>
						<div class="list" data-key="3">
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input" name="profile[2][price_from]" placeholder="От" value="">
								<input type="text" class="form-control rules_input fl-right" name="profile[2][price_to]" placeholder="До" value="">
							</div>
							<div class="col-lg-5">
								<input type="text" class="form-control rules_input fl-right" name="profile[2][markup]" placeholder="2" value="">
							</div>
							<div class="col-lg-2">
								<button type="button" class="close"><span aria-hidden="true">×</span></button>
							</div>
						</div>
					<?}?>
				</div>
				<div class="row modal_rules">
					<div class="col-lg-12" style="margin: 5px 0 0 0;">
						<button type="button" class="btn btn-success" id="profile-add-item">+ Добавить правило</button>
						<button type="submit" class="btn btn-primary" id="profile-save-default">Сохранить</button>
					</div>
				</div>
			</div>
		</div>
		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button type="submit" class="btn btn-primary" id="opt_submit">Сохранить</button>
		</div>
	</form>
	<style>
		.wrap-settings {
			padding: 20px;
		}
		.list{
			position: relative;
		  display: flex;
		  width: 100%;
		  margin-bottom: 10px;`
		}
	</style>
	<script>
		$(document).on("click", "#profile-add-item", function (event) {
				event.preventDefault();
				var next_id = 0;

				var settingsBlock = $(this).closest("form").find(".wrap-settings");
				var last = $(settingsBlock).find(".list").last();
				var last_id = parseInt($(last).attr("data-key"), 10);
				if(last_id >= 0) next_id = last_id + 1;
				//	console.log(last_id);

				var html = '<div class="list" data-key="' + next_id + '">';//начало

				html += '<div class="col-lg-5">';
				html += '<input type="text" class="form-control rules_input" name="profile[' + next_id + '][price_from]" placeholder="От">';
				html += '<input type="text" class="form-control rules_input fl-right" name="profile[' + next_id + '][price_to]" placeholder="До">';
				html += '</div>';
				html += '<div class="col-lg-5">';
				html += '<input type="text" class="form-control rules_input fl-right" name="profile[' + next_id + '][markup]" placeholder="200%">';
				html += '</div>';
				html += '<div class="col-lg-2">';
				html += '<button type="button" class="close"><span aria-hidden="true">×</span></button>';
				html += '</div></div>';//конец

				if(next_id == 0)
					$(settingsBlock).html(html);
				else
					$(html).insertAfter($(last));


				return false;
			});
			$(document).on("click", "#profile-save-default", function (event) {
			    event.preventDefault();

			    var formData = new FormData();
			    formData.append('USER_ID', $('#data-id').val());

			    // Собираем все input'ы из .wrap-settings
			    $('.wrap-settings').find('input').each(function() {
			        var name = $(this).attr('name');
			        if (name) {
			            formData.append(name, $(this).val());
			        }
			    });

			    $.ajax({
			        type: "POST",
			        url: "/admin/ajax/opt/saveDynamicPrice.php",
			        data: formData,
			        processData: false,
			        contentType: false,
			        success: function(response) {
			            alert('Настройки наценок сохранены');
			        },
			        error: function(xhr, status, error) {
			            alert("Не удалось сохранить настройки РРЦ");
			            console.error(error);
			        }
			    });

			    return false;
			});
	</script>

			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
