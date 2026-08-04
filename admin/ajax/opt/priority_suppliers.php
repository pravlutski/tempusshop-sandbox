<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager")){
	global $DB;
	$suuplier = new CPanelSupplier;
	$arSupplier = $suuplier->getList(["opt_supplier" => "Y"]);
	foreach($arSupplier as $key => &$arItem){
		$tmp = json_decode($arItem["settings"], true);
		$arPriority = [];
		foreach($tmp["brand"] as $br){
			$arPriority[$br["priority"]] = $br["priority"];
		}
		$arItem["priority_list"] = $arPriority;
	}
	unset($arItem);
	$arResult["PRICE_DEVIATION_FOREIGN"] = CProSet::getOption("PRICE_DEVIATION_FOREIGN");
	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title" id="myModalLabel23232">Приоритеты поставщиков</h4>
	</div>
	<div class="test"></div>
	<form class="form-horizontal" id="apply-priority-suppliers">
		<div class="modal-body">
			<table class="table" id="sup-list">
				<thead>
					<tr> 
						<th>Поставщик</th>
						<th>Приоритет</th>
					</tr>
				</thead>
				<tbody>
					<?foreach($arSupplier as $key => $arItem):?>
					<tr id="sup-tr-47">
						<td><?=$arItem["name"]?></td>
						<td><?=implode(" - ", $arItem["priority_list"]);?></td>
					</tr>
					<?endforeach?>
				</tbody>
			</table>

			<div class="col-sm-12 panel panel-default" style="padding-bottom: 10px;">
				<p style="font-size: 11px;">Максимальное отклонение</p>
				<input type="text" class="form-control input_fix_w" name="price-deviation-foreign" value="<?=$arResult["PRICE_DEVIATION_FOREIGN"]?>">
			</div>
		</div>

		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button type="submit" class="btn btn-primary" id="priority_suppliers_submit">Сохранить</button>
		</div>
	</form>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');