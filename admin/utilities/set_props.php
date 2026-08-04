<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<h1 class="page-header">Установка значения свойств товаров</h1>

<?
set_time_limit(3600);
$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;

$arBrand = $objBrand->getList();

foreach($arBrand as $k => $v) {

	$arClearStr[] = mb_strtoupper($v["name"]);
	//альтернативные бренды
	if(strlen($v["alt_name"]) > 0){
		$tmp = explode("|", $v["alt_name"]);
		foreach($tmp as $key => &$name){
			$name = trim($name);
			if(strlen($name) > 0){
				$arAltBrand[] = array(
					'id' => $v['id'],
					'name' => mb_strtoupper($name, "UTF-8"),
					'regular' => $v['regular'],
				);

				$arClearStr[] = mb_strtoupper($name, "UTF-8");
			}
		}
		unset($name);
	}
}
if(is_array($arAltBrand) && count($arAltBrand) > 0) $arBrand = array_merge($arAltBrand, $arBrand);
//prent($arBrand);
//$arResult["PROPS"] = $objContent->getProps();

$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem) $arResult["PROPS"][$arItem["id"]] = $arItem;

global $USER;
$arGroups = $USER->GetUserGroupArray();

// if (!$USER->IsAdmin() && !in_array(7, $arGroups))
// {
//     $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
//     return ;
// }
//prent($_POST);

if ( $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["set_props"]) ){
	/* массив брендов */

	//$arBrand = $objBrand->getList();
	//prent($arBrand);die;
	/*$arFilter = Array(
		"IBLOCK_ID" => CProSet::IB_BRANDS,
	);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
	while($arFields = $res->GetNext()){
		$arBrand[$arFields["ID"]] = $arFields["NAME"];
	}*/
	ob_start();
	if($_POST["prop"] && isset($arResult["PROPS"][$_POST["prop"]])){

		$arList = explode("\r\n", $_POST["list_articles"]);
		$arList = array_diff($arList, array(''));


		$arArticles = array();
		foreach($arList as $key => &$article){
			$article = mb_strtoupper($article);
			$art = $article;
			//$art = str_replace($arBrand, "", $article);
			//$art = trim($art);
			foreach($arBrand as $brand){
				if(strripos($article, $brand["name"]) !== false){
					$arClearStr = array();
					//$article = str_replace($brand["name"], '', $article);
					$arClearStr[] = mb_strtoupper($brand["name"]);
					//альтернативные бренды
					if(strlen($brand["alt_name"]) > 0){
						$tmp = explode("|", $brand["alt_name"]);
						foreach($tmp as $key => &$name){
							$name = trim($name);
							if(strlen($name) > 0){
								$arClearStr[] = mb_strtoupper($name, "UTF-8");
							}
						}
						unset($name);
					}
					$article = str_replace($arClearStr, '', $article);

					$article = trim($article);
					//prent($article);
					if(strlen($brand["regular"]) > 2){
						preg_match($brand["regular"], $article, $matches);
						$matches = array_diff($matches, array(''));
						$matches = array_unique($matches);

						if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
							$article = $matches[0];
					}

					$article = str_replace(array("  "), array(" "), $article);
					$article = trim($article);

					//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
					//if($brand_name == "Q&Q"){
					if($brand["id"] == 16){
						if($article[4] == "-") $article[4] = "J";
						if(strlen($article) == 8) $article[8] = "Y";
					}

					//для романсана 22 удаляем пробелы
					if($brand["id"] == 22){
						$article = str_replace(" ", "", $article);
					}

					//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)

					if($brand["id"] == 38){
						$tmp = trim(array_pop(explode(" ", $article)));
						//$tmp = intval($tmp);
						//if($tmp > 0){
						if(strlen($tmp) > 0){
							$article = $tmp;

						}
					}

					//RA-KV0006Y10B
					//if($brand_name == "Orient"){
					if($brand["id"] == 2){
						if($article[2] == "-"){
							$article = substr($article, 0, 10);
						}else{
							$article = substr($article, 0, 9);
						}
					}elseif($brand["id"] == 14)
						$article = $article;
					elseif(strpos($article, " "))
						$article = strstr($article, " ", true);

					if($brand["id"] == 26 && $article[2] == "/"){
						$article = substr($article, 3);
					}

					//если Tissot то удаляем точку после буквы T
					if($brand["id"] == 20 && $article[0] == "T" && $article[1] == "."){
						$article = substr_replace($article, '', 1, 1);
					}
					break;
				}
			}

			//$alt_art = $objUtils->getArtnumber($art);
			$alt_art = $objUtils->getArtnumber($article);

			if($alt_art) $arArticles[] = $alt_art; else $arArticles[] = $article;
		}
		unset($article);
		//prent($arArticles);die;
		$prop = $arResult["PROPS"][$_POST["prop"]];
		$PROPERTY_CODE = $_POST["prop"];

		$productIDs = [];
		foreach($arArticles as $key => $article){
			$ID = CPanelProduct::findArticle($article);
			if(!$ID){

			}
			if($ID){

				if($_POST["MULTIPLE"] == "Y" && $_POST["INSERT_TO"] == "Y"){
					$arFilterEl = Array("IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $ID);
					$resEl = CIBlockElement::GetList(Array(), $arFilterEl, false, false, array("ID", "PROPERTY_{$_POST["prop"]}"));

					$oldList = array();
					while($obEl = $resEl->getNext()){
						//$arEl = $obEl->GetFields();
						/*
						$tmp = unserialize("PROPERTY_" . mb_strtoupper($_POST["prop"]), true);
						$tmp = unserialize("PROPERTY_" . mb_strtoupper($_POST["prop"]), true);
						$tmp = unserialize($arEl["PROPERTY_" . mb_strtoupper($_POST["prop"]) . "_VALUE"]);

						$val = (string) $obEl["~PROPERTY_" . mb_strtoupper($_POST["prop"]) . "_VALUE"];

						$oldList = unserialize($val)["VALUE"];*/

						$oldList = array_keys($obEl["~PROPERTY_" . mb_strtoupper($_POST["prop"]) . "_VALUE"]);

					}
					foreach($_POST["PROPS"][$_POST["prop"]] as $k => $v)
						if(!in_array($v, $oldList))
							$oldList[] = $v;

					$arValues = $oldList;
				}else{
					$arValues = $_POST["PROPS"][$_POST["prop"]];
				}

				//prent($arValues);

				CIBlockElement::SetPropertyValueCode($ID, $PROPERTY_CODE, $arValues);
				//CIblockElement::SetPropertyValuesEx($ID, CProSet::IB_CATALOG, array($PROPERTY_CODE => $arValues));
				\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CProSet::IB_CATALOG, $ID);
				//prent($arValues);
				//$ar = array($_POST["prop"] => $_POST["PROPS"][$_POST["prop"]]);
				//$arrProp = Array();
				//$arrProp[$_POST["prop"]] = Array("VALUE" => $_POST["PROPS"][$_POST["prop"]]);
				//CIblockElement::SetPropertyValuesEx($ID, CProSet::IB_CATALOG, $arrProp);
				//
				echo "<p style='color:green'>{$ID} - {$article} установлен</p>";

				$productIDs[$ID] = $ID;
			}else{
				echo "<p style='color:red;'>{$article} - не найден ID товара</p>";
			}

		}

		// отправляем в темпус.
		if ($PROPERTY_CODE && count($productIDs) > 0) {
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
			$syncHelper = new SyncHelper();

			$syncHelper->sendPropProduct($productIDs, [$PROPERTY_CODE]);
		}
		//prent($arArticles);
		/*
		foreach($arList as $key => $order_id){
			if($order_id){
//				$res = $obj->setStatusOrder($order_id, $_POST["status"]);
				if($res === false){
					echo "<p style='color:red'>{$order_id} не удалось установить статус {$arResult["STATUS"][$_POST["status"]]["NAME"]}</p>";
				}
			}

		//prent($order_id);prent($_POST["status"]);
		}*/
	}else{
		echo "<p style='color:red'>Выберите свойство</p>";
	}
	$set_text = ob_get_clean();
}
//prent($_POST);
?>
<form action="/admin/utilities/set_props.php" method="post" >
	<div class="page_header_selects clearfix">
		<div class="page_header_select" style=" width: 45%;margin: 0;">
			<label style="display: block;">Список артикулов</label>
			<textarea class="form-control select_w" name="list_articles" style="width: 90%;height: 200px;"><?if($_POST["list_articles"]):?><?=addslashes($_POST["list_articles"])?><?endif?></textarea>
		</div>
		<div class="page_header_select" style="    width: 50%;">
			<label style="display: block;">Свойство</label>
			<select class="form-control select_w" name="prop" id="sel_prop">
				<option>--- Выберите сойство ---</option>
				<?foreach($arResult["PROPS"] as $key => $arItem):?>
				<option value="<?=$arItem["id"]?>"><?=$arItem["name"]?></option>
				<?endforeach?>
			</select>
			<div id="brand_defaults_newrow_values" style="    margin: 15px 0 0 0;"></div>
		</div>
	</div>

	<input type="submit" class="btn btn-primary btn_big_width" name="set_props" value="Установить">
</form>

<script>

    var JSONPROPS = '<?=json_encode($arResult["PROPS"], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS)?>';
    var PROPS = jQuery.parseJSON( JSONPROPS );

    function getVariants( obj ){

		var code = $(obj).find(":selected").val();
		console.log(code);
		console.log(PROPS);
		var prop_options = '';
                if( PROPS[code].values !== null )
                {
					if(PROPS[code].is_multiple == "Y"){

						prop_options = '<input type="hidden" name="MULTIPLE" value="Y">';
						prop_options += '<p style="margin: 0 0 0 0;padding: 3px 3px 3px 10px;border: 1px solid black;"><input type="checkbox" name="INSERT_TO" value="Y" checked><span>Добавить к списку без перезаписи</span></p>'
						for( var key in PROPS[code].values )
							prop_options += '<p style="margin: 0 0 0 0;"><input type="checkbox" name="PROPS[' + PROPS[code].id + '][]" value="' + key + '"><span>' + PROPS[code].values[key] + '</span></p>'
						$('#brand_defaults_newrow_values').html(prop_options);
					}else{
						for( var key in PROPS[code].values )
							prop_options += '<option value="'+ key +'">' + PROPS[code].values[key] + '</option>'
						$('#brand_defaults_newrow_values').html(
							'<select name="PROPS[' + PROPS[code].id + ']" class="form-control">' + prop_options + '</select>'
						);
					}

                }
                else
                {
                    $('#brand_defaults_newrow_values').html(
                        '<input type="text" name="PROPS[' + PROPS[code].id + ']" class="form-control">'
                    );
                }
	/*
        var clear_props = jQuery.parseJSON( JSONPROPS );
        var tr = $( button.parentNode );
        var code = tr.find( 'td:nth-child(2) .def_val').attr('name');
        PROPS[code] = clear_props[code];
        tr.remove();*/
    }

		$(document).on("change", "#sel_prop", function(e){
			getVariants(this);
		})

	<?if($_POST["PROPS"]):?>
		<?foreach($_POST["PROPS"] as $code => $ar):?>
		$("#sel_prop option[value=<?=$code?>]").prop('selected', true);
		//var ob = $('sel_prop[name=<?=$code?>]');
		//console.log(ob);
		//getVariants(ob);
		$("#sel_prop").change();
		<?endforeach?>
	<?endif?>
</script>

<?
echo $set_text;
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
