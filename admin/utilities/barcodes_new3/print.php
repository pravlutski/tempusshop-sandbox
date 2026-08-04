<?
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
?>
<?

error_reporting(E_ERROR);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (!class_exists('OrderPrintManager')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
}
$logger = new TsLogger("/utils/print_barcodes/");
global $USER;
$userID = $USER->getID();

$typeScan = trim($_REQUEST["type_scan"]);
$source = trim($_REQUEST['source']);
$orderID = intval($_REQUEST["order_id"]);
$productID = intval($_REQUEST["product_id"]);
$numberID = intval($_REQUEST["number_id"]);

$logger->log("LOG", "Запрос", ['userID' => $userID, '_REQUEST' => $_REQUEST]); 

if ($source == 'wb') {
	$pdf = new TCPDF('L', 'mm', array('58','43'), true, 'UTF-8', false);

	// remove default header/footer
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);

	$pdf->SetMargins(0, 0, 0, 0);

	// set auto page breaks false
	$pdf->SetAutoPageBreak(false, 0);

	// set document information

	$pdf->SetFont('dejavusans', '', 10);

	global $USER;
	$arOrderPrint = [];
	if($_REQUEST["group_orders"]){
		$arGroup = unserialize($_REQUEST["group_orders"]);
		if(!is_array($arGroup) || count($arGroup) == 0){
			die("Нет данных");
		}
		//prent($arGroup);

		foreach($arGroup as $key => $arItems){
			if (count($arItems["STICKERS"]) > 1){
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
			}

			$pdf->SetMargins(0, 0, 0, 0);
			$pdf->AddPage();
			$cnt = count($arItems["STICKERS"]) - 1;
			foreach($arItems["STICKERS"] as $k => $order_number){
				if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_number}.svg")){
					$pdf->ImageSVG("/upload/wb/{$order_number}.svg", -11, -1, 80, 42);
					$arOrderPrint[] = $order_number;
				}
				if($k < $cnt)
					$pdf->AddPage();
			}
		}
		//die;
	}else{
		$arOrder = explode(",", $_REQUEST["order_market_number"]);
		if(!$arOrder) die("нет кодов");
		
		// add a page
		$pdf->AddPage();
		$cnt = count($arOrder) - 1;
		foreach($arOrder as $k => $order_number){
			if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_number}.svg")){
				$pdf->ImageSVG("/upload/wb/{$order_number}.svg", -11, -1, 80, 42);
				$arOrderPrint[] = $order_number;
			}
			if($k < $cnt)
				$pdf->AddPage();
		}
	}

	
	/*if ($typeScan && in_array($typeScan, ['manual', 'scanner']) && count($arOrderPrint) > 0) {
		
		$property = \Bitrix\Sale\Internals\OrderPropsTable::getRow([
			'filter' => ['=CODE' => 'MAXYSS_WB_NUMBER'],
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
			
			foreach ($arOrderIDs as $order_id) {
				OrderPrintManager::addPrintRecord($order_id, $userID, $typeScan);
					
				// устанавливаем Стикер печатался
				$order = Bitrix\Sale\Order::load($order_id);
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
	}*/
	if ($typeScan && in_array($typeScan, ['manual', 'scanner'])) {
		OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan, $productID, $numberID);
	}
	//Close and output PDF document
	$pdf->Output();
} elseif($source == 'ozon') {
	$arOrder = explode(",", $_REQUEST["order_market_number"]);
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

	/*if ($typeScan && in_array($typeScan, ['manual', 'scanner']) && count($arOrderPrint) > 0) {

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
			
			
			foreach ($arOrderIDs as $order_id) {

				OrderPrintManager::addPrintRecord($order_id, $userID, $typeScan);
				
				// устанавливаем Стикер печатался
				$order = Bitrix\Sale\Order::load($order_id);
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
	}*/

	// Собираем команду для Ghostscript
	$cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputPath) . " ";
	foreach ($arFiles as $file) {
		$cmd .= escapeshellarg($file) . " ";
	}

	exec($cmd, $output, $returnCode);

	if ($returnCode === 0) {
		if ($typeScan && in_array($typeScan, ['manual', 'scanner'])) {
			OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan, $productID, $numberID);
		}
		
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
} elseif($source == 'fbo') {
	$barcode = trim(htmlspecialchars($_REQUEST["barcode"]));
	$cabinet = trim(htmlspecialchars($_REQUEST["cabinet"]));
	
	if (!$cabinet || !in_array($cabinet, ['WB_WR', 'WB_IP', 'OZON_IP', '21_VEK'])) {
		die("Ошибка кабинета");
	}
	
	$filename = $_SERVER['DOCUMENT_ROOT'] . "/upload/barcodes/{$barcode}.png";
	//$filename = "/var/www/bitrix/data/www/tempusshop.ru/upload/barcodes/{$barcode}.png";
	$redColor = [0, 0, 0];

	try {
		//$generator = new Picqer\Barcode\BarcodeGeneratorPNG();
		//$barcodeImage = $generator->getBarcode($barcode, $generator::TYPE_EAN_13, 2, 50, $redColor);
		$svgPath = $_SERVER['DOCUMENT_ROOT'] . "/upload/barcodes/{$barcode}.svg";
		
		$generator = new Picqer\Barcode\BarcodeGeneratorSVG();
		$svgContent = $generator->getBarcode($barcode, $generator::TYPE_EAN_13, 2, 50);
        
		if (file_put_contents($svgPath, $svgContent) === false) {
			$generator = new Picqer\Barcode\BarcodeGeneratorPNG();
			$barcodeImage = $generator->getBarcode($barcode, $generator::TYPE_EAN_13, 2, 50, $redColor);
			
			if (file_put_contents($filename, $barcodeImage) === false) {
				die("Ошибка сохранения файла штрихкода");
			} else {
				$fileBarcodePng = $filename;
			}
        } else {
			$fileBarcodeSvg = "/upload/barcodes/{$barcode}.svg";
		}
		
    /*try {

        
        // Сохраняем SVG
        if (file_put_contents($svgPath, $svgContent) !== false) {
            //$pdf->ImageSVG("/upload/barcodes/{$barcode}.svg", 5, 20, 30, 11);
        } else {
            $this->drawSimpleBarcode($pdf, $barcode, 5, 20, 30, 11);
        }
    } catch (Exception $e) {
        $this->drawSimpleBarcode($pdf, $barcode, 5, 20, 30, 11);
    }
	
		// Сохраняем изображение
		if (file_put_contents($filename, $barcodeImage) === false) {
			die("Ошибка сохранения файла штрихкода");
		}*/
		
		if (file_exists($fileBarcodePng) || file_exists($_SERVER['DOCUMENT_ROOT'] . $fileBarcodeSvg)) {
			
			if (!is_readable($fileBarcodePng) && !is_readable($_SERVER['DOCUMENT_ROOT'] . $fileBarcodeSvg)) {
				die("Нет прав на чтение файла: ");
			}
			
			$obj = CIBlockElement::GetList(
				[], 
				['IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $productID], 
				false, 
				false, 
				[
					'NAME',
					'PROPERTY_WBARTICLE2',
					'PROPERTY_AEN2',
					'PROPERTY_WBARTICLE',
					'PROPERTY_AEN',
					'PROPERTY_WBARTICLE3',
					'PROPERTY_barcodes',
					'PROPERTY_CML2_ARTICLE'
				]
			);
			$product = [];
			$is_21vek = false;
			if ($res = $obj->GetNext()){
				$product["NAME"] = $res["NAME"];
				
				if ($cabinet == 'WB_WR') {
					$product["ARTICLE"] = $res["PROPERTY_WBARTICLE2_VALUE"];
					$product["BARCODE"] = $res["PROPERTY_AEN2_VALUE"];
				} else if ($cabinet == 'WB_IP') {
					$product["ARTICLE"] = $res["PROPERTY_WBARTICLE3_VALUE"];
					$product["BARCODE"] = $res["PROPERTY_AEN2_VALUE"];
				} else if ($cabinet == 'OZON_IP') {
					$product["ARTICLE"] = $res["PROPERTY_WBARTICLE_VALUE"];
					$product["BARCODE"] = $res["PROPERTY_AEN_VALUE"];
				} else if ($cabinet == '21_VEK') {
					$product["BARCODE"] = trim(explode(",", $res["PROPERTY_BARCODES_VALUE"])[0]);
					$is_21vek = true;
				}
				$product["ARTICLE_BX"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
			} else {
				die('Товар не найден');
			}
			
			if ($product["BARCODE"] != $barcode) {
				die("Не соответсвие баркодов. {$product["BARCODE"]} - {$barcode}");
			}
			
			if ($cabinet == 'WB_WR') {
				$company = 'ООО "ВОТЧЕС-РИТЕЙЛ"';
			} else if ($cabinet == 'WB_IP') {
				$company = 'ИП Шевцова А.О.';
			} else if ($cabinet == 'OZON_IP') {
				$company = 'ИП Сподырева Е.С.';
			} else if ($cabinet == '21_VEK') {
			//	$company = 'ООО "ВОТЧЕС-РИТЕЙЛ"';
			}

			if ($is_21vek) {

				$pdf = new TCPDF('P', 'mm', array(40, 58), true, 'UTF-8', false);
				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);
				$pdf->SetMargins(0, 0, 0, 0);
				$pdf->SetAutoPageBreak(false, 0);

				$pdf->SetFont('dejavusans', '', 8, '', true);
				$pdf->AddPage();

				// Теперь координаты будут обычными, но страница повернута
				$pdf->SetFont('dejavusans', '', 8);
				$pdf->SetXY(0, 2);
				$pdf->Cell(40, 4, $product["NAME"], 0, 1, 'C');

				$pdf->SetFont('dejavusans', '', 7);
				$pdf->SetXY(2, 10);
				$pdf->Cell(40, 4, "Арт. ". $product["ARTICLE_BX"], 0, 1, 'L');

				if ($fileBarcodePng) {
					$pdf->Image($fileBarcodePng, 5, 20, 30, 11, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
				} elseif($fileBarcodeSvg) {
					$pdf->ImageSVG($fileBarcodeSvg, 5, 20, 30, 11);
				}
				//
				
    
				$pdf->SetFont('dejavusans', '', 7);
				$pdf->SetXY(0, 31);
				$pdf->Cell(40, 4, $barcode, 0, 1, 'C');
				
				$pdf->SetFont('dejavusans', '', 5);
				$pdf->SetXY(2, 40);
				$pdf->Cell(40, 4, 'Импортер в Республику Беларусь', 0, 1, 'L');

				$pdf->SetXY(2, 42);
				$pdf->Cell(40, 4, 'ООО "Вотч-Трейд"', 0, 1, 'L');
				
				$pdf->SetXY(2, 44);
				$pdf->Cell(40, 4, 'УНП 192848849', 0, 1, 'L');

				$pdf->SetXY(2, 46);
				$pdf->Cell(40, 4, '220012 г. Минск ул К.Чорного 5А,', 0, 1, 'L');

				$pdf->SetXY(2, 48);
				$pdf->Cell(40, 4, 'офис 7', 0, 1, 'L');
 
			} else {
				$pdf = new TCPDF('L', 'mm', array(58, 40), true, 'UTF-8', false);
				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);
				$pdf->SetMargins(0, 0, 0, 0);
				$pdf->SetAutoPageBreak(false, 0);
				
				$pdf->SetFont('dejavusans', '', 8, '', true);
				
				$pdf->AddPage();
				
				$pdf->SetFont('dejavusans', 'B', 8);
				$pdf->SetXY(0, 2);
				$pdf->Cell(58, 4, $product["ARTICLE"], 0, 1, 'C');
				
				$pdf->SetFont('dejavusans', '', 7);
				$pdf->SetXY(0, 6);
				$pdf->Cell(58, 4, $product["NAME"], 0, 1, 'C');
				
				$pdf->SetXY(0, 10);
				$pdf->Cell(58, 4, $company, 0, 1, 'C');
				
				$pdf->SetFont('dejavusans', 'B', 7);
				$pdf->SetXY(0, 15);
				$pdf->Cell(58, 4, 'Товар собран и упакован с', 0, 1, 'C');
				
				$pdf->SetFont('dejavusans', 'B', 7);
				$pdf->SetXY(0, 18);
				$pdf->Cell(58, 4, 'ВИДЕОФИКСАЦИЕЙ', 0, 1, 'C');
				
				//$pdf->Image($filename, 4, 22, 50, 11, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
				$pdf->ImageSVG("/upload/barcodes/{$barcode}.svg", 5, 20, 30, 11);
				
				$pdf->SetFont('dejavusans', '', 7);
				$pdf->SetXY(0, 33);
				$pdf->Cell(58, 4, $barcode, 0, 1, 'C');
			}
			

			
			ob_clean();
			$pdf->Output("barcode_{$barcode}.pdf", 'I');
				
			// unlink($filename);
			if ($typeScan && in_array($typeScan, ['manual', 'scanner'])) {
				OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan, $productID, $numberID);
			}
		} else {
			die("Файл штрихкода не был создан");
		}
	} catch (Exception $e) {
		die("Ошибка генерации штрихкода: " . $e->getMessage());
	}

	/*prent($fileContent);die;
	$filename = "fbo_{$barcode}.pdf";
	header('Content-Type: application/pdf');
	header('Content-Length: '.strlen( $fileContent ));
	header('Content-disposition: inline; filename="' . $filename . '"');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Pragma: public');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	echo $fileContent;*/
} elseif($source == 'yandex') {
	$order_market_number = trim($_REQUEST["order_market_number"]);

	if(!$order_market_number) die("нет кода");

	$outputPath = $_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/outputPath.pdf";

	$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$order_market_number}.pdf";

	if (file_exists($fileSticker)) {
		
		OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan, $productID, $numberID);
		
		$fileContent = file_get_contents($fileSticker);
		$filename = "sticker_ya_{$order_market_number}.pdf";
		header('Content-Type: application/pdf');
		header('Content-Length: '.strlen( $fileContent ));
		header('Content-disposition: inline; filename="' . $filename . '"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		echo $fileContent;
	} else {
		die('not file');
	}

	//OrderPrintManager::addPrintRecord($orderID, $userID, $typeScan, $productID, $numberID);
	/*if ($typeScan && in_array($typeScan, ['manual', 'scanner']) && count($arOrderPrint) > 0) {
		
		$property = \Bitrix\Sale\Internals\OrderPropsTable::getRow([
			'filter' => ['=CODE' => 'ORDER_NUMBER_YA'],
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
			
			
			foreach ($arOrderIDs as $order_id) {

				OrderPrintManager::addPrintRecord($order_id, $userID, $typeScan);
				
				
				// устанавливаем Стикер печатался
				$order = Bitrix\Sale\Order::load($order_id);
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
	}*/

	// Собираем команду для Ghostscript
	/*$cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputPath) . " ";
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
	}*/
}
