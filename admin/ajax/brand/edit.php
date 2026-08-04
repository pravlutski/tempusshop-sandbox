<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager")){
	$objBrand = new CPanelBrand;
	
	$brand_id = intval($_POST["id"]);
	if($brand_id > 0){
		$arBrand = $objBrand->getDetail( $brand_id );
		$arResult = array(
			'id'			=> $brand_id,
			'new'			=> false,
			'brand'		=> $arBrand,
		);
	}else{
		$arResult = array(
			'id'			=> false,
			'new'			=> true,
			'brand'		=> array("name" => "Новый бренд", "sort" => 500),
		);
	}//prent($arResult);
	
	$obj = new CExchange();
	$arListB = $obj->getBrandName();
	asort($arListB);
	//prent($arListB);
	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title" id="myModalLabel23232">Бренд - <?=$arResult["brand"]["name"]?></h4>
	</div>
	<form class="form-horizontal" id="apply-brand">
		<input type="hidden" name="brand-id" value="<?if($arResult["new"] === false):?><?=$id?><?endif?>">
		<div class="modal-body">
			<div class="col-lg-12">
				<div class="col-lg-8" style="margin: 0 0 10px 10px;">
					<input required="" type="text" class="form-control input_fix_w2" name="name" placeholder="Введите имя" value="<?if($arResult["new"] === false):?><?=$arResult["brand"]["name"]?><?endif?>">
				</div>
				<div class="col-lg-4" style="margin: 0 0 10px 10px;">
					<input type="text" class="form-control" name="sort" placeholder="Сортировка" value="<?=$arResult["brand"]["sort"]?>">
				</div>
				<div class="col-lg-12" style="margin: 0 0 10px 10px;">
					<p>Бренд в BITRIX</p>
					<select name="bitrix_id" class="form-control">
						<option>-- выберите бренд ---</option>
						<?foreach($arListB as $bitrix_id => $bitrix_name):?>
							<option value="<?=$bitrix_id?>" <?if($bitrix_id == $arResult["brand"]["bitrix_id"]):?>selected<?endif?>><?=$bitrix_name?></option>
						<?endforeach?>
					</select>
				</div>
				
				<div class="col-lg-12" style="margin: 0 0 10px 10px;">
					<p>Альтернативные названия через |</p>
					<input type="text" class="form-control" name="alt_name" placeholder="Альтернативные названия через |" value="<?=$arResult["brand"]["alt_name"]?>">
				</div>
				<div class="col-lg-12" style="margin: 0 0 10px 10px;">
					<p>Регулярка для артикулов бренда (что оставить из артикула)</p>
					<input type="text" class="form-control" name="regular" placeholder="Регулярка для артикулов бренда (что оставить из артикула)" value="<?=$arResult["brand"]["regular"]?>">
				</div>
				<div class="col-lg-12 panel panel-default" style="margin: 0 0 10px 10px;">
					<p>Поиск и замена по регулярному выражению</p>
					<div class="col-lg-5 row" style="margin: 0 0 10px 10px;">
						<p>Поиск строки</p>
						<input type="text" class="form-control" name="regular_search" placeholder="Поиск" value="<?=$arResult["brand"]["regular_search"]?>">
					</div>
					<div class="col-lg-5 row" style="margin: 0 0 10px 10px;">
						<p>Замена</p>
						<input type="text" class="form-control" name="regular_replace" placeholder="Замена" value="<?=$arResult["brand"]["regular_replace"]?>">
					</div>
				</div>
			</div>
		</div>
		<?/*<div class="col-sm-12 panel panel-default" style="padding-bottom: 10px;">
			<p>Наценка</p>
			<div class="col-lg-2 row1" style="">
				<p>RU</p>
				<input type="number" class="form-control" name="margin_ru" value="<?=$arResult["brand"]["margin_ru"]?>">
			</div>
			<div class="col-lg-2 row1" style="">
				<p>BY</p>
				<input type="number" class="form-control" name="margin_by" value="<?=$arResult["brand"]["margin_by"]?>">
			</div>
			<div class="col-lg-2 row1" style="">
				<p>PL</p>
				<input type="number" class="form-control" name="margin_pl" value="<?=$arResult["brand"]["margin_pl"]?>">
			</div>
            <div class="col-lg-2 row1" style="">
                <p>YA</p>
                <input type="number" class="form-control" name="margin_ya" value="<?=$arResult["brand"]["margin_ya"]?>">
            </div>
            <div class="col-lg-2 row1" style="">
                <p>OS</p>
                <input type="number" class="form-control" name="margin_os" value="<?=$arResult["brand"]["margin_os"]?>">
            </div>
			<div class="col-lg-2 row1" style="">
				<p>WB</p>
				<input type="number" class="form-control" name="margin_wb" value="<?=$arResult["brand"]["margin_wb"]?>">
			</div>
		</div>*/?>
		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button disabled="" type="submit" class="btn btn-primary" id="brand_submit">Сохранить</button>
		</div>
	</form>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');