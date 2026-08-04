<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager"))
	die("Непредвиденная ошибка");

$arResult = array();
foreach($_POST["PrintAdress"]["to"] as $arItem){
	$arResult["ITEMS"][] = array(
		"to_name" => $arItem["to_name"],
		"to_adress" => $arItem["to_adress"],
		"to_zip" => $arItem["to_zip"],
		"sum" => $arItem["sum"],
		"sum_int" => $arItem["sum_int"],
	);
}
//prent($arResult["ITEMS"]);die;

if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) <= 0)
	die("Нет данных для вывода");
//prent($arResult);
define("PDF_FONT_NAME_MAIN", "dejavuserifcondensed");
define("PDF_FONT_NAME_DATA", "dejavuserifcondensed");
define("PDF_FONT_SIZE_MAIN", 8);

//define("K_PATH_IMAGES", $_SERVER["DOCUMENT_ROOT"] . "/images/");
//define("PDF_HEADER_LOGO", "pdf_logo.png");
//define("PDF_HEADER_LOGO_WIDTH", 50);
//define("PDF_HEADER_TITLE", "");
//define("PDF_HEADER_STRING", "ИП Рудак Г.Ю. 2013-2017. тел. 8 (029) 344-99-66, 8 (033) 354-99-66 Пн-Вс 10:00 до 20:00");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {
    public function Header() {
        // get the current page break margin
        $bMargin = $this->getBreakMargin();
        // get current auto-page-break mode
        $auto_page_break = $this->AutoPageBreak;
        // disable auto-page-break
        $this->SetAutoPageBreak(false, 0);
        // set bacground image
		
        $img_file = '/upload/belposhta_blank.jpg';

		if(($this->page % 2) == 1){
			$this->Image('/upload/belposhta_blank_.jpg', 0, 0, 210, 97, 'jpg', '', '', true, 150, '', false, false, 0, false, false, false);
		}else{
			$this->Image('/upload/belposhta_blank_2.jpg', 0, 0, 210, 107, 'jpg', '', '', true, 150, '', false, false, 0, false, false, false);
		}
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        // set the starting point for the page content
        $this->setPageMark();
    }
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

		$this->SetY($cur_y);
		//Print page number
		/*
		if ($this->getRTL()) {
			$this->SetX($this->original_rMargin);
			$this->Cell(0, 0, $pagenumtxt, 'T', 0, 'L');
			
		} else {
			$this->SetX($this->original_lMargin);
			$this->Cell(0, 0, $this->getAliasRightShift().$pagenumtxt, 'T', 0, 'R');
			$this->SetX(10);
		}*/
	}
}

// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
//$pdf->SetPrintHeader(false);
//$pdf->setHeaderData('',0,'','',array(0,0,0), array(255,255,255) );  
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Tempus');
$pdf->SetTitle('Белпочта');
$pdf->SetSubject('Белпочта');
$pdf->SetKeywords('Белпочта');

// set default header data
//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
//$pdf->setCellPaddings(0,0,10,0);
// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));


// set margins
//$pdf->SetMargins(PDF_MARGIN_LEFT, 8, PDF_MARGIN_RIGHT);
$pdf->SetMargins(9, 8, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
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
$pdf->SetFont('dejavuserifcondensed', '', 10);
$pdf->setCellHeightRatio(1.6);
// add a page
//$pdf->AddPage();
// create some HTML content
$html = "";
$i = 0;
//prent($arResult["ITEMS"]);die;
foreach($arResult["ITEMS"] as $arItem){	
	$pdf->AddPage();
	/*if($i != 0 && ($i % 2) == 0){
		$pdf->lastPage();
		$pdf->AddPage();
	}*/
//	$pdf->Image('https://tempusshop.ru/upload/belposhta_blank.jpg', 0, 0, 200, 113, 'jpg', '', '', true, 150, '', false, false, 0, false, false, false);
	
//	$html = "<img src=\"https://tempusshop.ru/upload/belposhta_blank.jpg\" border=\"0\" /><br>";
//	$pdf->writeHTML($html, true, 0, true, true);
/*
            [to_name] => Юрий
            [to_adress] => Юрий
            [to_zip] => 
            [sum] => триста восемьдесят четыре белорусского рубля 00 копеек
*/
	//if($i == 0){
	$textSum1 = $textSum2 = $textAdd1 = $textAdd2 = "";
		//if(strlen($arItem["sum"]) < 33){
		if(iconv_strlen($arItem["sum"],'UTF-8') < 33){
			$pdf->SetXY(135, 5);
			$pdf->Cell(0, 0, $arItem["sum"], 5, 10, 'L', 0, '', 1);
			$textSum1 = $arItem["sum"];
		}else{
			//$word = explode(PHP_EOL, wordwrap($arItem["sum"], 32, "<br>\n"));
			$word = explode(" ", $arItem["sum"]);
			foreach($word as $k => $v){
				$s = iconv_strlen($v,'UTF-8');
				if(iconv_strlen($textSum1,'UTF-8') + $s < 32 && $textSum2 == "") 
					$textSum1 .= $v . " "; 
				else
					$textSum2 .= $v . " ";
			}
			$pdf->SetXY(138, 3);
			$pdf->Cell(0, 0, $textSum1, 5, 10, 'L', 0, '', 1);
			$pdf->SetXY(106, 10);
			$pdf->Cell(0, 0, $textSum2, 5, 10, 'L', 0, '', 1);
			
			$pdf->SetXY(138, 17);
			$pdf->Cell(0, 0, $textSum1, 5, 10, 'L', 0, '', 1);
			$pdf->SetXY(106, 23);
			$pdf->Cell(0, 0, $textSum2, 5, 10, 'L', 0, '', 1);
		}
		
		//$pdf->SetX(150);
		//$pdf->SetY(60);
		$pdf->SetXY(120, 51);
		//$pdf->Cell(160, 60, "vsvs", 0);
		$pdf->Cell(0, 0, $arItem["to_name"], 5, 10, 'L', 0, '', 1);
		
		if(iconv_strlen($arItem["to_adress"],'UTF-8') < 40){
			$pdf->SetXY(120, 65);
			$pdf->Cell(0, 0, $arItem["to_adress"], 5, 10, 'L', 0, '', 1);
			$textAdd1 = $arItem["to_adress"];
		}else{
			$word = explode(" ", $arItem["to_adress"]);
			foreach($word as $k => $v){
				$s = iconv_strlen($v,'UTF-8');
				if(iconv_strlen($textAdd1,'UTF-8') + $s < 40 && $textAdd2 == "") 
					$textAdd1 .= $v . " "; 
				else
					$textAdd2 .= $v . " ";
			}
			$pdf->SetXY(120, 65);
			$pdf->Cell(0, 0, $textAdd1, 5, 10, 'L', 0, '', 1);
			$pdf->SetXY(114, 71);
			$pdf->Cell(0, 0, $textAdd2, 5, 10, 'L', 0, '', 1);
		}
		if(strlen($arItem["to_zip"]) > 0){
		$pdf->SetXY(122, 77);
		$pdf->Cell(0, 0, $arItem["to_zip"], 5, 10, 'L', 0, '', 1);
		}


	//}//
	//$pdf->lastPage();
	$pdf->AddPage();
	//печатаем эл денежный платеж
	$pdf->SetXY(31, 61);
	$pdf->Cell(0, 0, $arItem["to_name"], 5, 10, 'L', 0, '', 1);

	$pdf->SetXY(22, 69);
	$pdf->Cell(0, 0, $textAdd1, 5, 10, 'L', 0, '', 1);
	$pdf->SetXY(7, 76);
	$pdf->Cell(0, 0, $textAdd2, 5, 10, 'L', 0, '', 1);
	//сумма цифрой
	$pdf->SetXY(19, 25);
	$pdf->Cell(0, 0, $arItem["sum_int"] . " руб.", 5, 10, 'L', 0, '', 1);
	//сумма прописью
	$textSumAll = $textSum1 . " " . $textSum2;
	$pdf->SetXY(45, 25);
	$pdf->Cell(0, 0, $textSumAll, 5, 10, 'L', 0, '', 1);
//	$pdf->lastPage();
	$i++;
}

// output the HTML content
//$pdf->writeHTML($html, true, false, true, false, '');
//$pdf->writeHTML($html, true, 0, true, true);
/*
$i = 0;
foreach($arResult["ITEMS"] as $arItem){
	if($i == 0){
		$pdf->SetX(10);
		$pdf->Cell(10, 10, "vsvs", 'T', 0, 'L');
	}
	$i++;
}*/
// reset pointer to the last page
$pdf->lastPage();

$pdf->setCellHeightRatio(1.1);

//Close and output PDF document
$pdf->Output('blank.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>