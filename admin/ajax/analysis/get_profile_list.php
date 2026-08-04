<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$website = false;
$_POST["website"] = mb_strtolower($_POST["website"]);
if($_POST["website"] && in_array($_POST["website"], [
	"ru","by","pl","ya","os","wb","wbtl","wbby","av", "sb", "kz","ozkz","ozti"
]))
	$website = htmlspecialchars($_POST["website"]);

if(!CModule::IncludeModule("panel.manager")) return;
?>
<?if($website === false):?>
	<p>Не выбрана цена</p>
	<?die;?>
<?endif?>
<?if($_POST["website"] == "wb" || $_POST["website"] == "wbtl"):?>
	<div class="row modal_rules">
		<div class="col-lg-12" style="margin: 5px 0 0 0;">
			<div class="col-lg-4">
				<p>Скидка</p>
				<?if ($_POST["website"] == "wb") {?>
					<input type="text" class="form-control rules_input" name="catalog_sale" placeholder="Скидка" value="<?=CProSet::getOption("CATALOG_SALE_wb")?>">
				<?} else if ($_POST["website"] == "wbtl"){?>
					<input type="text" class="form-control rules_input" name="catalog_sale" placeholder="Скидка" value="<?=CProSet::getOption("CATALOG_SALE_wbtl")?>">
				<?}?>
			</div>
			<div class="col-lg-4">
				<p>Промокод</p>
				<?if ($_POST["website"] == "wb") {?>
					<input type="text" class="form-control rules_input" name="catalog_promo" placeholder="Промокод" value="<?=CProSet::getOption("CATALOG_PROMO_wb")?>">
				<?} else if ($_POST["website"] == "wbtl"){?>
					<input type="text" class="form-control rules_input" name="catalog_promo" placeholder="Промокод" value="<?=CProSet::getOption("CATALOG_PROMO_wbtl")?>">
				<?}?>

			</div>
			<div class="col-lg-4" style="margin: 5px 0 0 0;">
				<button type="button" class="btn btn-success" id="apply_settings_rrc">Сохранить</button>
			</div>
			<div id="apply_settings_text"></div>
		</div>
	</div>
<?endif?>
<div id="asdsad"></div>
<?
if(CModule::IncludeModule("panel.manager")){
	$brand = new CPanelBrand;
	$arResult["BRAND_LIST"] = $brand->getList();

	foreach($arResult["BRAND_LIST"] as $key => $arItem){
		$arResult["BRAND_NAME"][$arItem["id"]] = $arItem["name"];
	}

	$analysis = new CPanelAnalysis;
	$arResult["ANALYSIS_LIST"] = $analysis->getList($website);

	$defaults = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$website];
	?>
	<input type="hidden" name="website" value="<?=$website?>">
	<div class="col-lg-12 row" style="margin-bottom: 10px;display: flex;  align-items: center;">
		<label>Использовать цену</label>
		<div class="col-lg-3">
			<select class="input-sm col-lg-12" name="price_type" style="margin: 3px 0 5px 0;">
					<option value="price"<?if ($defaults["price_type"] == 'price' || !isset($defaults["price_type"])) {echo 'checked'; }?>>Цена</option>
					<option value="price_n" <?if ($defaults["price_type"] == 'price_n') {echo 'selected'; }?>>Цена + НДС</option>
			</select>
		</div>
	</div>

	<div class="col-lg-12 row" style="margin-bottom: 10px;display: flex;  align-items: center;">
		<input type="checkbox" id="take-priority-supplier" name="take-priority-supplier" style="float: left;margin: 3px 4px 0px 0px;" <?if($defaults['take_priority_supplier'] && $defaults['take_priority_supplier'] == 'Y'):?>checked<?endif?>>
		<label for="take-priority-supplier">Учитывать приоритет поставщика</label>
	</div>
	<?/*<div class="col-lg-12 row" style="margin-bottom: 10px;display: flex;  align-items: center;">
		<button type="submit" class="btn btn-primary" id="profile-save-price_type">Применить</button>
	</div>*/?>
	
	<div class="col-lg-12 row" style="">
		<label>Настройка по умолчанию</label>
	</div>
	<div class="list row" data-key="0">
		<div class="col-lg-8">
			<label>Правило</label>
		</div>
		<div class="col-lg-2">
			<label>Наценка</label>
		</div>
		<div class="col-lg-2"></div>
	</div>
	<div class="wrap-settings row">
			<?if(is_array($defaults["rules"]) && count($defaults["rules"]) > 0):?>
				<?foreach($defaults["rules"] as $key => $arItem):?>
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
			<button type="submit" class="btn btn-primary" id="profile-save-default">Сохранить</button>
		</div>
		<div class="col-lg-12" style="margin: 5px 0 0 0; display:none">
			<label>Скидка суперцены</label>
			<div class="col-lg-12 row">
				<input type="text" class="form-control rules_input" name="supersale" placeholder="0%" value="<?=$defaults["supersale"]?>">
			</div>
		</div>
	</div>
	<table class="table" id="brand-list">
		<tbody>
		<?foreach($arResult["ANALYSIS_LIST"] as $key => $arItem):?>
			<tr id="brand-tr-<?=$arItem["id"]?>">
				<td>
					<?=(isset($arResult["BRAND_NAME"][$arItem["brand_id"]]) ? $arResult["BRAND_NAME"][$arItem["brand_id"]] : $arItem["name"])?>
					<?php
						$hasCollection = !empty($arItem["collection_id"]);
						$hasArticle = !empty($arItem["article"]);

						if ($hasCollection || $hasArticle) {
							if ($hasCollection) {
								echo $brand->getListCollection($arItem["collection_id"]);
							}

							if ($hasCollection && $hasArticle) {
								echo " <b>-</b> ";
							}

							if ($hasArticle) {
								echo $arItem["article"];
							}
						}
					?>
				</td>
				<td>
				<?
				$settings = json_decode($arItem["settings"], true);//prent($settings);
				?>
				<?foreach($settings as $k => $v):?>
				<p style="margin: 0 0 0 0;font-size: 10px;font-style: italic;">от <?=$v["price_from"]?> до <?=$v["price_to"]?> - <?=$v["markup"]?></p>
				<?endforeach?>
				</td>
				<td class="right" style="width:200px">
					<button type="button" class="btn btn-primary btn-profile-edit" data-toggle="modal" data-target="#modal_edit_profile" data-id="<?=$arItem["id"]?>">Изменить</button>
					<?//if($USER->isAdmin()):?>
					<?//endif?>
					<button class="btn btn-danger btn-brand-delete" data-id="<?=$arItem["id"]?>" onClick='confirmDeleteProfile(this);return false;'>Удалить</button>
				</td>
			</tr>
		<?endforeach?>
		</tbody>
	</table>
	<div class="row modal_rules">
		<div id="wrap-settings2"></div>
		<div class="col-lg-12" style="margin: 5px 0 0 0;">
			<button type="button" class="btn btn-success" id="profile-add-item" style="display: none;">+ Добавить правило</button>
		</div>
		<div class="col-lg-12" style="margin: 5px 0 0 0;">
			<button type="submit" class="btn btn-primary" id="profile-save" style="display: none;">Сохранить</button>
			<button type="button" class="btn btn-danger" id="profile-delete" style="display: none;">Удалить</button>
			<button type="button" class="btn btn-success" id="profile-new">+ Создать новый профиль</button>
		</div>
	</div>

	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить настройки</span></h2>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
