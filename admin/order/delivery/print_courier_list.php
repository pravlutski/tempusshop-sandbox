<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager"))
	return false;

if((count($_REQUEST["order"]) <= 0 && count($_REQUEST["payment_card"]) <= 0) || count($_REQUEST["basket"]) <= 0)
	return false;
/*
формируем массив нал/БН
*/
$ar["ORDER"] = $_REQUEST["order"];
$ar["ORDER_BN"] = $_REQUEST["payment_card"];
if(is_array($ar["ORDER_BN"]) && count($ar["ORDER_BN"]) > 0){
	foreach($ar["ORDER_BN"] as $key => $order_id){
		if(($k = array_search($order_id, $ar["ORDER"])) !== FALSE){
			unset($ar["ORDER"][$k]);
		}
	}
}

$order = new OrderService; 
//$arOrder = $order->getOrder(array(), array("ID" => $_REQUEST["order"]));
$arOrder = $order->getOrder(array(), array("ID" => $ar["ORDER"]));
$arOrderBN = $order->getOrder(array(), array("ID" => $ar["ORDER_BN"]));
	//prent($_REQUEST["basket"]);
	
if(!$arOrder && !$arOrderBN) return;

define("PDF_FONT_NAME_MAIN", "dejavuserifcondensed");
define("PDF_FONT_NAME_DATA", "dejavuserifcondensed");
define("PDF_FONT_SIZE_MAIN", 8);
//prent($arOrder); 
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {
	public $USER_NAME;

    // Page footer
	public function Footer() {
		$cur_y = $this->y;
		$this->SetTextColorArray($this->footer_text_color);
		//set style for cell border
		$line_width = (0.85 / $this->k);
		$this->SetLineStyle(array('width' => $line_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $this->footer_line_color));
		//print document barcode
		$barcode = $this->getBarcode();
		if (!empty($barcode)) {
			$this->Ln($line_width);
			$barcode_width = round(($this->w - $this->original_lMargin - $this->original_rMargin) / 3);
			$style = array(
				'position' => $this->rtl?'R':'L',
				'align' => $this->rtl?'R':'L',
				'stretch' => false,
				'fitwidth' => true,
				'cellfitalign' => '',
				'border' => false,
				'padding' => 0,
				'fgcolor' => array(0,0,0),
				'bgcolor' => false,
				'text' => false
			);
			$this->write1DBarcode($barcode, 'C128', '', $cur_y + $line_width, '', (($this->footer_margin / 3) - $line_width), 0.3, $style, '');
		}
		$w_page = isset($this->l['w_page']) ? $this->l['w_page'].' ' : '';
		if (empty($this->pagegroups)) {
			$pagenumtxt = $w_page.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
		} else {
			$pagenumtxt = $w_page.$this->getPageNumGroupAlias().' / '.$this->getPageGroupAlias();
		}
		//$pagenumtxt = $this->USER_NAME." - ".$pagenumtxt;
		$this->SetY($cur_y);
		//Print page number
		if ($this->getRTL()) {
			$this->SetX($this->original_rMargin);
			$this->Cell(0, 0, $pagenumtxt, 'T', 0, 'L');
			
		} else {
			$this->SetX($this->original_lMargin);
			$this->Cell(0, 0, $this->getAliasRightShift().$pagenumtxt, 'T', 0, 'R');
			$this->SetX(10);
			$this->Cell(0, 0, $this->USER_NAME." ".date("d-m-Y H:i"), 'T', 0, 'L');
		}
	}
}

// create new PDF document
$pdf = new MYPDF("L", PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->USER_NAME = $USER->GetLogin();
$pdf->SetPrintHeader(false);
//$pdf->setHeaderData('',0,'','',array(0,0,0), array(255,255,255) );  
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Tempus');
$pdf->SetTitle('Информационный курьерский лист');
$pdf->SetSubject('Информационный курьерский лист');
$pdf->SetKeywords('Информационный курьерский лист');

$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 8, PDF_MARGIN_RIGHT);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/lang/rus.php')) {
    require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/lang/rus.php');
    $pdf->setLanguageArray($l);
}

// set font
//$pdf->SetFont('dejavuserifcondensedbi', '', 10);
$pdf->SetFont('dejavuserifcondensed', '', 10);

$pdf->setCellHeightRatio(1.6);
// add a page dejavusansbi	dejavuserifcondensedbi
$pdf->AddPage();

$pdf->setCellHeightRatio(1.1);
$html = "<span style=\"text-align:left;font-size: 10px;line-height:14px;\">".date("d.m.Y")."</span>";
$html = "<h1 style=\"text-align:center;font-size: 16px;\">Информационный курьерский лист</h1>";



if(is_array($arOrder) && count($arOrder) > 0){
	$html .= "<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width: 100%;\">
	<tr style=\"font-size: 8px;\">
	<td style=\"width: 10%;\">№</td>
	<td style=\"text-align:center;width: 10%;\">Цена</td>
	<td style=\"text-align:center;width: 15%;\">Модель</td>
	<td style=\"text-align:center;width: 15%;\">Адрес</td>
	<td style=\"text-align:center;width: 15%;\">Имя</td>
	<td style=\"text-align:center;width: 15%;\">Телефон</td>
	<td style=\"text-align:center;width: 20%;\">Комментарий</td>
	</tr>";
	//prent($arOrder);
	foreach($arOrder as $arItem){
		foreach($arItem["BASKET"] as $key => $arBasket){
			if(in_array($arBasket["ID"], $_REQUEST["basket"])){
				$txt_coomment = "";
				if($arItem["USER_DESCRIPTION"])
					$txt_coomment = "Клиент: " . $arItem["USER_DESCRIPTION"] . "<br>";
				if($arItem["COMMENTS"])
					$txt_coomment .= "Менеджер: " . $arItem["COMMENTS"];
					
				//$fio = str_replace(array("В"),'',$arItem["FIO"]);
				$fio = $arItem["FIO"];
				//$fio = iconv("windows-1251",'utf-8',$arItem["FIO"]);
				//$fio = mb_convert_encoding($arItem["FIO"], 'utf-8', mb_detect_encoding($arItem["FIO"]));
				//prent($fio);die;
				$html .= "<tr style=\"font-size: 8px;\">
							<td style=\"width: 10%;\">{$arItem["ORDER_ID"]}</td>
							<td style=\"text-align:center;width: 10%;\">".number_format($arBasket["PRICE"], 2, ',', ' ')."</td>
							<td style=\"text-align:center;width: 15%;\">{$arBasket["NAME"]}</td>
							<td style=\"text-align:center;width: 15%;\">{$arItem["ADDRESS"]}</td>
							<td style=\"text-align:center;width: 15%;\">{$fio}</td>
							<td style=\"text-align:center;width: 15%;\">{$arItem["PHONE"]}</td>
							<td style=\"text-align:left;width: 20%;\">{$txt_coomment}</td>
						</tr>";
			}
		}
	}

	$html .= "</table>";
}
/* БН */
if(is_array($arOrderBN) && count($arOrderBN) > 0){
	$html .= "<p>Оплата картой</p><table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width: 100%;\">
	<tr style=\"font-size: 8px;\">
	<td style=\"width: 10%;\">№</td>
	<td style=\"text-align:center;width: 10%;\">Цена</td>
	<td style=\"text-align:center;width: 15%;\">Модель</td>
	<td style=\"text-align:center;width: 15%;\">Адрес</td>
	<td style=\"text-align:center;width: 15%;\">Имя</td>
	<td style=\"text-align:center;width: 15%;\">Телефон</td>
	<td style=\"text-align:center;width: 20%;\">Комментарий</td>
	</tr>";
	//prent($arOrder);
	foreach($arOrderBN as $arItem){
		foreach($arItem["BASKET"] as $key => $arBasket){
			if(in_array($arBasket["ID"], $_REQUEST["basket"])){
				$txt_coomment = "";
				if($arItem["USER_DESCRIPTION"])
					$txt_coomment = "Клиент: " . $arItem["USER_DESCRIPTION"] . "<br>";
				if($arItem["COMMENTS"])
					$txt_coomment .= "Менеджер: " . $arItem["COMMENTS"];
					

				$html .= "<tr style=\"font-size: 8px;\">
							<td style=\"width: 10%;\">{$arItem["ORDER_ID"]}</td>
							<td style=\"text-align:center;width: 10%;\">".number_format($arBasket["PRICE"], 2, ',', ' ')."</td>
							<td style=\"text-align:center;width: 15%;\">{$arBasket["NAME"]}</td>
							<td style=\"text-align:center;width: 15%;\">{$arItem["ADDRESS"]}</td>
							<td style=\"text-align:center;width: 15%;\">".str_replace(array("я","в","ч","с","а","В"),'',$arItem["FIO"])."</td>
							<td style=\"text-align:center;width: 15%;\">{$arItem["PHONE"]}</td>
							<td style=\"text-align:left;width: 20%;\">{$txt_coomment}</td>
						</tr>";
			}
		}
	}

	$html .= "</table>";
}

//$html = iconv("utf-8", "windows-1251",$html);
$pdf->writeHTML($html, true, 0, true, true);

//Close and output PDF document
$pdf->Output('example_006.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>