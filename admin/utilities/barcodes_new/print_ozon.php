<?
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
//if(!$_REQUEST["order_wb_submit"]) return;
?>
<?

error_reporting(E_ERROR);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (!class_exists('OrderPrintManager')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
}

global $USER;
// remove default header/footer
//$pdf->setPrintHeader(false);
//$pdf->setPrintFooter(false);

//$pdf->SetMargins(0, 0, 0, 0);

// set auto page breaks false
//$pdf->SetAutoPageBreak(false, 0);

//$pdf->SetFont('dejavusans', '', 10);

if($_REQUEST["group_orders"]){
	/*$arGroup = unserialize($_REQUEST["group_orders"]);
	if(!is_array($arGroup) || count($arGroup) == 0){
		die("Нет данных");
	}
	//prent($arGroup);

	foreach($arGroup as $key => $arItems){
		$pdf->AddPage();
		$pdf->SetMargins(0, 0, 0, 0);
		
		
		$html = '
		<table border="1" cellspacing="1" cellpadding="2">
			<tr>
				<td>' . $arItems["ARTICLE"] . '</td>
			</tr>
			<tr>
				<td>Кол-во - ' . $arItems["COUNT_PRODUCT"] . '</td>
			</tr>
		'; 
		$html2 = "
		<p>{$arItems["ARTICLE"]}</p>
		Количество артикулов - {$arItems["COUNT_PRODUCT"]}</br>
		";
		
		if(count($arItems["BARCODES"]) > 0){
			$cnt = count($arItems["BARCODES"]);
			$html .= '<tr><td style="padding: 0 0 5px 0;">';
			foreach($arItems["BARCODES"] as $k => $b){
				if($k > 0) $html .= ", ";
				$html .= substr($b, 0, -3) . ' <span style="font-size: 16px;">' . substr($b, -3) . '</span>';
			}
			$html .= '<br></td></tr>';
		}
		$html .= '</table>';
		
		$pdf->writeHTML($html, true, false, true, false, '');
		$pdf->SetMargins(0, 0, 0, 0);
		$pdf->AddPage();
		$cnt = count($arItems["STICKERS"]) - 1;
		foreach($arItems["STICKERS"] as $k => $order_number){
			if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.svg")){
				$pdf->ImageSVG("/upload/ozon/{$order_number}.svg", -11, -1, 80, 42);
			}
			if($k < $cnt)
				$pdf->AddPage();
		}
	}*/
	//die;
}else{
	
	
	$arOrder = explode(",", $_REQUEST["order_number"]);
	$arOrderPrint = [];
	if(!$arOrder) die("нет кодов");
	
	$outputPath = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/outputPath.pdf";

	$arFiles = [];
	foreach($arOrder as $k => $order_number){
		$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.pdf";
		if (!file_exists($fileSticker)) {
			//continue;
			$fileStickerTmp = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}_tmp.pdf";
			
			if (!file_exists($fileSticker)) {
				$pdf = new TCPDF('L', 'mm', array('75','120'), true, 'UTF-8', false);
				$pdf->AddPage();
				$pdf->SetMargins(0, 0, 0, 0);
				
				$html = '
				<table border="1" cellspacing="1" cellpadding="2">
					<tr>
						<td>' . $order_number . '</td>
					</tr>
				'; 
				$html .= '</table>';
				
				$pdf->writeHTML($html, true, false, true, false, '');
				$pdf->Output($fileStickerTmp, 'F');
			}

			$arFiles[] = $fileStickerTmp;

		} else {
			$arFiles[] = $fileSticker; 
			
			$arOrderPrint[] = $order_number;
		}
		
		
	}
	
	$typeScan = trim($_REQUEST["type_scan"]);
	if ($typeScan && in_array($typeScan, ['manual', 'scanner']) && count($arOrderPrint) > 0) {

		$property = \Bitrix\Sale\Internals\OrderPropsTable::getRow([
			'filter' => ['=CODE' => 'OZON_NUMBER'],
			'select' => ['ID']
		]);

		if ($property) {
			$propertyId = $property['ID'];

			// Ищем заказы через ORM
			$orders = \Bitrix\Sale\Order::getList([
				'select' => [
					'ID', 
				],
				'filter' => [
					'=PROPERTY.ORDER_PROPS_ID' => $propertyId,
					'=PROPERTY.VALUE' => $arOrderPrint
				],
				'runtime' => [
					new Bitrix\Main\Entity\ReferenceField(
						'PROPERTY',
						'\Bitrix\Sale\Internals\OrderPropsValueTable',
						['=this.ID' => 'ref.ORDER_ID'],
						['join_type' => 'INNER']
					)
				]
			]);
			
			$arOrderIDs = [];
			while ($order = $orders->fetch()) {
				$arOrderIDs[] = $order["ID"];
			}
			
			$userID = $USER->getID();
			foreach ($arOrderIDs as $orderID) {
				OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan);
				
				// устанавливаем Стикер печатался
				$order = Bitrix\Sale\Order::load($orderID);
				$propertyCollection = $order->getPropertyCollection();

				if ($prop = $propertyCollection->getItemByOrderPropertyCode('STICKER_PRINT')) {
					$r = $prop->setField('VALUE', 'Y');
					if ($r->isSuccess()) {
						$order->save();
					} else {
						$result = array(
							'status' => "error",
							'message' => $r->getErrorMessages(),
						);
					}
				} else {
					$result = array(
						'status' => "error",
						'message' => 'Ошибка получения свойства',
					);
				}
			}
		}
	}
	
	// Собираем команду для Ghostscript
	$cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputPath) . " ";
	foreach ($arFiles as $file) {
		$cmd .= escapeshellarg($file) . " ";
	}
	
	exec($cmd, $output, $returnCode);
	
	if ($returnCode === 0) {
		$fileContent = file_get_contents($outputPath);
		$filename = 'sticker_ozon.pdf';
		header('Content-Type: application/pdf');
		header('Content-Length: '.strlen( $fileContent ));
		header('Content-disposition: inline; filename="' . $filename . '"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $fileContent;
	} else {
		die("Ошибка при объединении PDF!");
	}
	
	prent($cmd);die;
	foreach($arOrder as $k => $order_number){
		$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.pdf";
		// gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=/var/www/bitrix/data/www/tempusshop.ru/upload/ozon/output.pdf /var/www/bitrix/data/www/tempusshop.ru/upload/ozon/62838623-0169-1.pdf
		if (!file_exists($fileSticker)) {
			continue;
		}
		prent($fileSticker);


		$merger->addFile($fileSticker);
		
        //$pdf->setSourceFile($fileSticker);
        //$tplIdx = $pdf->importPage(1); // только 1 страница
        //$pdf->AddPage();
        //$pdf->useTemplate($tplIdx, 0, 0, 75, 120, true);
/*
		// Получаем количество страниц в PDF-файле
		$pageCount = $pdf->setSourceFile($fileSticker);

		// Импортируем каждую страницу (в вашем случае всегда 1)
		for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			// Импортируем страницу
			$templateId = $pdf->importPage($pageNo);

			$pdf->AddPage();

			// Используем импортированную страницу
			$pdf->useTemplate($templateId);
		}*/
	}
	file_put_contents('/var/www/bitrix_data/tempusshop.ru/upload/ozon/output.pdf', $merger->merge());
	// add a page
	/*$pdf->AddPage();
	$cnt = count($arOrder) - 1;
	foreach($arOrder as $k => $order_number){
		if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.svg")){
			$pdf->ImageSVG("/upload/ozon/{$order_number}.svg", -11, -1, 80, 42);
		}
		if($k < $cnt)
			$pdf->AddPage();
	}*/
}

//Close and output PDF document
//$pdf->Output();
