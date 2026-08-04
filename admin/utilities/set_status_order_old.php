<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Установить статус заказа</h1>

<?
$obj = new OrderService;
$arResult["STATUS"] = $obj->getStatusOrderList();

//prent($arResult["STATUS"]);

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(12, $arGroups) && !in_array(6, $arGroups)) 
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return;
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["set_status"])){
	if($_POST["status"] && isset($arResult["STATUS"][$_POST["status"]])){
		
		$arList = explode("\r\n", $_POST["list_order"]);
		$arList = array_diff($arList, array(''));
		
		
		if($_POST["filter-encoding"] == "Y"){
			$arFilter = array("PROPERTY_VAL_BY_CODE_MAXYSS_OP_STICKER" => $arList);
		}else{
			$arFilter = array("ACCOUNT_NUMBER" => $arList);
		}
		$obj->getPropOrderFlg = false;
		$order = $obj->getOrder(array(), $arFilter);
		//prent($order);die; 
		$cnt = 0;
		foreach($order as $key => $arOrder){

			$res = $obj->setStatusOrder($arOrder["ID"], $_POST["status"]);
			//$res = $obj->setStatusOrderD7($arOrder["ID"], $_POST["status"]);
			
			if($res === false){
				echo "<p style='color:red'>{$arOrder["ORDER_ID"]} не удалось установить статус {$arResult["STATUS"][$_POST["status"]]["NAME"]}</p>";
			}else{
				$cnt++;
			}
	
		//prent($order_id);prent($_POST["status"]);
		}
		$cnt_all = count($order);
		echo "<p style=''>Всех заказов {$cnt_all}. Статус изменен у {$cnt}</p>";
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
		echo "<p style='color:red'>Выберите нужный статус</p>";
	}
	
}
?>
<form action="/admin/utilities/set_status_order.php" method="post" >
	<div class="page_header_selects clearfix">
		<div class="page_header_select" style=" width: 45%;margin: 0;">
			<label style="display: block;">Список заказов (Номера заказов)</label>
			<textarea class="form-control select_w" name="list_order" style="width: 90%;height: 200px;"><?if($_POST["list_order"]):?><?=addslashes($_POST["list_order"])?><?endif?></textarea>
		</div>
		<div class="page_header_select" style="    width: 50%;">
			<label style="display: block;">Статус</label>
			<select class="form-control select_w" name="status">
				<option>--- Выберите статус ---</option>
				<?foreach($arResult["STATUS"] as $key => $arItem):?>
				<option value="<?=$arItem["ID"]?>"><?=$arItem["NAME"]?></option>
				<?endforeach?>
			</select>
			<label style="display: block;"><input type="checkbox" name="filter-encoding" value="Y">по баркоду</label>
			
		</div>
	</div>

	<input type="submit" class="btn btn-primary btn_big_width" name="set_status" value="Установить">
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>