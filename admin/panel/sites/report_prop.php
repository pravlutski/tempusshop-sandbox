<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>

<?
$APPLICATION->SetPageProperty("page_h1", "Утилита проверки заполненности свойств");
$APPLICATION->SetTitle('Утилита проверки заполненности свойств');

global $DB;

opcache_reset();
CModule::IncludeModule("iblock");
$dbPanel = new DBPanel();
$arResult = [];
$objPricelist = new CPanelPricelist;
/*$filter = [
	"website" => "os",
	"!bitrix_id" => "0",
];
$arModel = $objPricelist->getPriceByFilterNew($filter, "bitrix_id", ["bitrix_id", "model"], false, $priceType);
*/
// список свойств которые анализируем
/*$checkProp = [
	"TYPE", "CASE", "FACE"
];*/

$checkProp = json_decode(CProSet::getOption("PROP_LOG_ANALYSIS"), true);

foreach($checkProp as &$prop){
	$prop = mb_strtoupper($prop);
}
unset($prop);
$arSkip = [
	"AVAILABILITY_BY", "AVAILABILITY_PL", "AVAILABILITY_RU",
	"AVAILABILITY_KZ", "IN_STOCK", "ACTIVE_YA",
	"OZON_ACTIVE", "DP_DISCOUNT", "EX_YA",
	"MAXIMUM_PRICE_PL", "MARKETPLACE_WB_TAGS", 
];
$properties = CIBlockProperty::GetList(["sort"=>"asc", "name"=>"asc"], Array(
	"ACTIVE" => "Y", 
	"PROPERTY_TYPE" => "L",
	"IBLOCK_ID" => 16
));
while ($prop_fields = $properties->GetNext())
{
	$prop_fields["CODE"] = mb_strtoupper($prop_fields["CODE"]);
	if(!in_array($prop_fields["CODE"], $arSkip)){
		$arResult["PROPERTY"][$prop_fields["CODE"]] = [
			"ID" => $prop_fields["ID"],
			"CODE" => $prop_fields["CODE"],
			"NAME" => $prop_fields["NAME"],
		];
	}

}

// в GetList не группирую. пройду по массиву
$arSelect = ["ID"];
foreach($checkProp as $prop){
	$arSelect[] = "PROPERTY_{$prop}";
}
$arFilter = [
	"PROPERTY_OZON_ACTIVE" => 1943,
	//"ID" => 209384
];
//"ID" => array_keys($arModel)
$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
$cntEl = 0;
while($ar = $rs->GetNext()){
	foreach($checkProp as $prop){
		$col_prop = "PROPERTY_{$prop}_VALUE";
		if(is_array($ar[$col_prop])){
			if(count($ar[$col_prop]) > 0){
				$arResult["ITEMS"][$prop]["FILLED"] += 1;
			}else{
				$arResult["ITEMS"][$prop]["NO_FILLED"] += 1;
			}
		}else{
			if(strlen($ar[$col_prop]) > 0){
				$arResult["ITEMS"][$prop]["FILLED"] += 1;
			}else{
				$arResult["ITEMS"][$prop]["NO_FILLED"] += 1;
			}
		}
	}
	$cntEl++;
}

?>
<div class="row">
	<div class="col-12">
		<div class="row">
			<div class="col-6">
				<a id="prop-log-btn" class="btn btn-primary"/>Свойства</a>
				<p>Активных товаров: <?=$cntEl?></p>
				<table class="table table-striped">
					<thead>
						<tr>
							<th scope="col">Свойство</th>
							<th scope="col">Заполнено свойств</th>
						</tr>
					</thead>
					<tbody>
						<?foreach($arResult["ITEMS"] as $prop => $arItem):?>
							<tr>
								<th scope="row"><span data-code="<?=$prop?>" class="check-prop"><?=$arResult["PROPERTY"][$prop]["NAME"]?></span></th>
								<th scope="row"><?=$arItem["FILLED"]?></th>
							</tr>
						<?endforeach?>
					</tbody>
				</table>
			</div>
			<div class="col-6">
				<label for="only_empty">Только пустые</label>
				<input type="checkbox" id="only_empty" name="only_empty" value="Y">
				<div id="detail-log"></div>
			</div>
		</div>
	</div>
</div>

<div id="prop-log-modal" class="modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Свойства для анализа</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<select name="property-setting[]" id="property-setting" multiple style="width: 100%;">
				<?foreach($arResult["PROPERTY"] as $arItem):?>
				<option value="<?=$arItem["CODE"]?>" <?if(is_array($checkProp) && in_array($arItem["CODE"], $checkProp)):?>selected<?endif?>><?=$arItem["NAME"]?></option>
				<?endforeach?>
				</select>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
				<button type="button" id="prop-log-save" class="btn btn-primary">Сохранить</button>
			</div>
		</div>
	</div>
</div>
<script>
$('#prop-log-btn').click(function(e) {
	e.preventDefault();
	$('#prop-log-modal').modal('show');
});

$('#prop-log-save').click(function() {
  var data = $('#property-setting').val();
  $.ajax({
    url: '/admin/panel/sites/ajax/report_prop/save.php',
    method: 'POST',
    data: { property: data},
    success: function(response) {
		alert('Данные успешно сохранены!');
		$('#prop-log-modal').modal('hide');
    },
    error: function(xhr, status, error) {
      alert('Произошла ошибка при сохранении данных: ' + error);
    }
  });
});

function getLog(){
	dataString = "";
	if($("#only_empty").is(':checked')){
		dataString = "only_empty=Y";
	}else
		dataString = "only_empty=N";
	
	dataString += "&property_code=" + $(".check-prop.active").attr("data-code");
	$.ajax({
		url: '/admin/panel/sites/ajax/report_prop/detail.php',
		method: 'POST',
		data: dataString,
		success: function(data) {
			$('#detail-log').html(data);
		},
		error: function(data) {
			alert('Произошла ошибка при запросе данных');
		}
	});
}

$('.check-prop').click(function() {
	$(".check-prop").removeClass("active");
	$(this).addClass("active");
	getLog();
});
$(document).on("change", "#only_empty", function (e) {
	getLog();
});
</script>
<style>
.textarea {
  max-width: 800px;
  width: 100%;
  height: 70vh;
  border: 1px solid #ccc;
  padding: 5px;
  overflow-y: scroll;
  resize: none;
  font-family: inherit;
  font-size: 14px;
  background-color: #fff;
}
span.check-prop{
    color: #0d6efd;
    text-decoration: underline;
	cursor: pointer;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
