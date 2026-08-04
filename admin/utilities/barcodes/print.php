<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
//if(!$_REQUEST["order_wb_submit"]) return;
?>
<?

error_reporting(E_ERROR);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

class PDF_WB extends TCPDF {

    //Page header
    public function Header() {
    }

    // Page footer
    public function Footer() {
    }
}

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
		foreach($arItems["STICKERS"] as $k => $order_wb){
			if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_wb}.svg")){
				$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -11, -1, 80, 42);
			}
			if($k < $cnt)
				$pdf->AddPage();
		}
	}
	//die;
}else{
	$arOrder = explode(",", $_REQUEST["order_wb"]);
	if(!$arOrder) die("нет кодов");
	
	// add a page
	$pdf->AddPage();
	$cnt = count($arOrder) - 1;
	foreach($arOrder as $k => $order_wb){
		if(file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_wb}.svg")){
			$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -11, -1, 80, 42);
		}
		if($k < $cnt)
			$pdf->AddPage();
	}
}

//Close and output PDF document
$pdf->Output();
