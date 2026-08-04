<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$id = intval($_POST["id"]);

$_POST["website"] = mb_strtolower($_POST["website"]);
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru","by","pl","ya","os","wb","wbtl","av", "sb", "kz","ozkz","ozti")))
	$price_id = $_POST["website"];

if(CModule::IncludeModule("panel.manager") && $price_id){
	$analysis = new CPanelAnalysis;
	$brand = new CPanelBrand;

	if($id > 0){
		$arResult = $analysis->getDetail($id, $price_id);
		// print_r($arResult);
		// die();
		$arResult["settings"] = json_decode( $arResult["settings"], true );
	}

	$arResult["BRAND_LIST"] = $brand->getList();

	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
	</div>
	<form class="form-horizontal" id="apply-profile">
		<input type="hidden" name="analysis_id" value="<?=intval($arResult["id"])?>">
		<div class="modal-body">
			<div class="col-lg-12" style="margin: 5px 0 0 0;">
				<label>Бренд профиля</label>
				<select class="input-sm col-lg-12" name="brand_id" id="brand_id" style="margin: 3px 0 5px 0;">
					<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
						<option value="<?=$arItem["id"]?>" <?if($arResult["brand_id"] == $arItem["id"]):?>selected<?endif?>><?=$arItem["name"]?></option>
					<?endforeach?>
				</select>
			</div>
			<?php if($arResult["price_id"] === "os" || $arResult["price_id"] === "wb" || $arResult["price_id"] === "wbtl" ||$arResult["price_id"] === "ya"
				|| $arResult["price_id"] === "sb"):?>
				<div class="col-lg-12" style="margin: 5px 0 0 0;">
					<label>При необходимости выберите коллекцию, либо введите артикул</label>
					<div class="row">
						<div class="col-lg-6">
							<select class="input-sm col-lg-12" name="collection_id" id="collection_id" style="margin: 3px 0 5px 0;">
								<option value="0">Выберите коллекцию</option>
							</select>
						</div>
						<div class="col-lg-6">
							<input type="text" class="form-control" name="article_value" id="article_value" value="<?=$arResult["article"];?>"
								   placeholder="Введите артикул">
						</div>
					</div>
					<?php if($arResult["collection_id"]):?>
						<input type="hidden" name="selected_collection_id" id="selected_collection_id" value="<?=$arResult["collection_id"];?>">
					<?php else:?>
						<input type="hidden" name="selected_collection_id" id="selected_collection_id" value="0">
					<?php endif;?>
				</div>
			<?php endif;?>
			<div class="list" data-key="0">
				<div class="col-lg-8">
					<label>Правило</label>
				</div>
				<div class="col-lg-2">
					<label>Наценка</label>
				</div>
				<div class="col-lg-2"></div>
			</div>
			<div id="wrap-settings" class="wrap-settings">
			<?if(is_array($arResult["settings"]) && count($arResult["settings"]) > 0):?>
				<?foreach($arResult["settings"] as $key => $arItem):?>
					<div class="list" data-key="<?=$key+1?>">
						<div class="col-lg-5">
							<input type="text" class="form-control rules_input" name="profile[<?=$key?>][price_from]" placeholder="От" value="<?=$arItem["price_from"]?>">
							<input type="text" class="form-control rules_input fl-right" name="profile[<?=$key?>][price_to]" placeholder="До" value="<?=$arItem["price_to"]?>">
						</div>
						<div class="col-lg-5">
							<input type="text" class="form-control rules_input fl-right" name="profile[<?=$key?>][markup]" placeholder="2%" value="<?=$arItem["markup"]?>">
						</div>
						<div class="col-lg-2">
							<button type="button" class="close"><span aria-hidden="true">×</span></button>
						</div>
					</div>
				<?endforeach?>
			<?else:?>
			<div class="list" data-key="1">
				<div class="col-lg-5">
					<input type="text" class="form-control rules_input" name="profile[0][price_from]" placeholder="От">
					<input type="text" class="form-control rules_input fl-right" name="profile[0][price_to]" placeholder="До">
				</div>
				<div class="col-lg-5">
					<input type="text" class="form-control rules_input fl-right" name="profile[0][markup]" placeholder="200%">
				</div>
				<div class="col-lg-2">
					<button type="button" class="close"><span aria-hidden="true">×</span></button>
				</div>
			</div>
			<?endif?>
			</div>
			<div class="row modal_rules">

				<div class="col-lg-12" style="margin: 5px 0 0 0;">
					<button type="button" class="btn btn-success" id="profile-add-item">+ Добавить правило</button>
				</div>
				<div class="col-lg-12" style="margin: 5px 0 0 0;">
					<button type="submit" class="btn btn-primary" id="profile-save">Сохранить</button>
					<?if($USER->isAdmin() && $arResult["id"]):?>
						<button class="btn btn-danger btn-brand-delete" data-id="<?=$arResult["id"]?>" onClick='confirmDeleteProfile(this);return false;'>Удалить</button>
					<?endif?>
				</div>
			</div>
			<div class="modal-footer">
				<div class="fl-left" style="margin: 3px 0 0 0;"><div class="info-text"></div></div>
				<button type="button" class="btn btn-default close-f"  data-dismiss="modal"  aria-label="Close">Выйти</button>
			</div>
		</div>
	</form>
	<?
}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
