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
$arOrder = $_REQUEST["order"];

$arStickerWB = getStickerWB($arOrder, "WR");

//prent($arOrder);prent($arStickerWB);die;
if(!$arOrder) die("нет кодов");

$pdf = new TCPDF('L', 'mm', array('58','43'), true, 'UTF-8', false);

// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(0, 0, 0, 0);

// set auto page breaks false
$pdf->SetAutoPageBreak(false, 0);

// set document information

$pdf->SetFont('dejavusans', '', 10);

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

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output();
//$pdf->Output('example_058.pdf', 'D');