<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Печать ценников (RU)");?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?
$db = \Bitrix\Main\Application::getConnection();
$arPrice = [];
$models = [];
$strSql = "SELECT * FROM offline_price_ru WHERE active = 'Y'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ( $row = $results->Fetch() )
{
  $models[] = $row['article'];
  if (intval($row['old_price']) - intval($row['price']) == 0) continue;

  $diff = intval($row['price']) - intval($row['old_price']);

  if ( $row['old_price'] == 0 ){
    $arResult[ $row['article'] ] = [
      'old_price' => 0,
      'price' => round( $row['price'], 0 ),
      "diff" => $diff,
      "diff_p" => 100
    ];
    continue;
  }

  $percentage = ( $diff > 0 ) ? round( ($diff / intval($row['price']) ) * 100,2 ) : round( ($diff / intval($row['old_price']) ) * 100,2);

  if (abs($percentage) < 10) continue;

  $arResult[ $row['article'] ] = [
    'old_price' => round( $row['old_price'], 0 ),
    'price' => round( $row['price'], 0 ),
    "diff" => $diff,
    "diff_p" => $percentage
  ];
}

$arFilter = [
  "IBLOCK_ID" => 16,
  "PROPERTY_CML2_ARTICLE" => $models,
];
$arSelect = ["ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"];
$result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
$elBrands = [];
while ( $row = $result->GetNext() ){
  $elBrands[] = $row['PROPERTY_BRAND_VALUE'];
}
$result = CIBlockElement::GetList(
  ["NAME" => "ASC"],
  ['IBLOCK_ID' => CProSet::IB_BRANDS, 'ID' => $elBrands],
  false,
  false,
  ['ID', 'IBLOCK_ID', 'NAME']
);
$brands = [];
while ( $row = $result->GetNext() ){
  $brands[ $row['ID'] ] = $row['NAME'];
}
?>
<a href="/admin/utilities/" class="btn btn-default">Назад</a>


<form method="post" action="/admin/print/ru/single_sale.php" class="form-model" target="_blank" style="display:flex; flex-direction: row">
  <textarea class="form-control search_input" name="model" placeholder="Артикулы, каждый с новой строки" rows="7" cols="10" style=""></textarea>
  <select class="form form-control" name="mode" style="width: 160px;">
    <option value="models">Артикулы</option>
    <option value="barcodes">Баркоды</option>
  </select>
</form>
<div class="buttons-block">
  <button class="btn btn-primary print-group">Печать</button>
  <button class="btn btn-primary print-modal">Печать группы</button>
  <a target="_blank" href="/admin/print/ru/table.php" class="btn btn-primary print-all">Печать всех ценников</a>
</div>
<hr>
<?if(count_($arResult) > 0):?>
<div class="custom_table custom_table_margin">
	<table class="table">
		<thead>
			<tr>
				<th>Артикул</th>
				<th>Старая цена</th>
				<th>Текущая цена</th>
				<th>Изменение</th>
			</tr>
		</thead>
		<tbody>
			<?foreach($arResult as $key => $arItem):?>
			<tr class="">
				<td><?=$key?></td>
				<td><?=$arItem["old_price"]?></td>
				<td><?=$arItem["price"]?></td>
				<td style="<?if (intval($arItem["diff"]) > 0) { echo "color:green;"; } else { echo "color:red;"; }?>"><?=$arItem["diff"]?> (<?=$arItem['diff_p']?>%)</td>
			</tr>
			<?endforeach?>
		</tbody>
	</table>
</div>
<?else:?>
<p>Нет изменений</p>
<?endif;?>
<div class="modal-background" style="display:none">
  <div class="modal-window">
    <div class="modal-head">
      <h3>Печать по брендам</h3>
    </div>
    <div class="modal-body">
      <form class="form-brand" action="/admin/print/ru/single_sale.php" method="post" target="_blank">
        <select class="brand-select" name="brands[]" multiple>
          <?foreach ( $brands as $id => $name ):?>
          <option value="<?=$id?>"><?=$name?></option>
          <?endforeach;?>
        </select>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary print-brand">Печать</button>
    </div>
  </div>
</div>
<style media="screen">
  .buttons-block {
    margin-top: 10px;
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  .form-model {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    max-width: 557px;
    gap: 20px;
  }
  .search_input {
    width: 400px;
    margin-left: 0px;
    resize: none;
  }
  .modal-background{
    width: 100%;
    height: 100%;
    z-index: 99999;
    position: absolute;
    top: 0;
    left: 0;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
  }
  .modal-window{
    background-color: #f5f5f5;
    display: flex;
    flex-direction: column;
    margin: 10% auto 0 auto;
    width: 600px;
    /* min-height: 600px; */
    border-radius: 6px;
    padding: 15px;
  }
  .brand-select{
    width: 100%;
    height: 400px !important;
    border-radius: 6px;
    border: none;
  }
</style>
<script type="text/javascript">
  $(document).on('click', '.print-group', function(e){
    e.preventDefault();
    $('.form-model').submit();
  });

  $(document).on('click', '.print-brand', function(e){
    e.preventDefault();
    $('.form-brand').submit();
  })

  $(document).on('click', '.print-modal', function(e){
    e.preventDefault();
    $('.modal-background').fadeIn();
  })
  $(document).on('click', '.modal-background', function(e){
    e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
    }
  })
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
