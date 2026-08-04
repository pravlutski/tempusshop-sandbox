<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
//if(!$_REQUEST["order_wb_submit"]) return;
?>
<?

error_reporting(E_ERROR);
    
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/classes/tcpdf/tcpdf.php');

class PDF_WB extends TCPDF {

    //Page header
    public function Header() {
    }

    // Page footer
    public function Footer() {
    }
	
	public function ImageSVG($file, $x='', $y='', $w=0, $h=0, $link='', $align='', $palign='', $border=0, $fitonpage=false) {
		if ($this->state != 2) {
			 return;
		}
		// reset SVG vars
		$this->svggradients = array();
		$this->svggradientid = 0;
		$this->svgdefsmode = false;
		$this->svgdefs = array();
		$this->svgclipmode = false;
		$this->svgclippaths = array();
		$this->svgcliptm = array();
		$this->svgclipid = 0;
		$this->svgtext = '';
		$this->svgtextmode = array();
		if ($this->rasterize_vector_images AND ($w > 0) AND ($h > 0)) {
			// convert SVG to raster image using GD or ImageMagick libraries
			return $this->Image($file, $x, $y, $w, $h, 'SVG', $link, $align, true, 300, $palign, false, false, $border, false, false, false);
		}
		if ($file[0] === '@') { // image from string
			$this->svgdir = '';
			$svgdata = substr($file, 1);
		} else { // SVG file
			$this->svgdir = dirname($file);
			$svgdata = TCPDF_STATIC::fileGetContents($file);
		}
		if ($svgdata === FALSE) {
			$this->Error('SVG file not found: '.$file);
		}
		if ($x === '') {
			$x = $this->x;
		}
		if ($y === '') {
			$y = $this->y;
		}
		// check page for no-write regions and adapt page margins if necessary
		list($x, $y) = $this->checkPageRegions($h, $x, $y);
		$k = $this->k;
		$ox = 0;
		$oy = 0;
		$ow = $w;
		$oh = $h;
		$aspect_ratio_align = 'xMidYMid';
		$aspect_ratio_ms = 'meet';
		$regs = array();
		// get original image width and height
		preg_match('/<svg([^\>]*)>/si', $svgdata, $regs);
		if (isset($regs[1]) AND !empty($regs[1])) {
			$tmp = array();
			if (preg_match('/[\s]+x[\s]*=[\s]*"([^"]*)"/si', $regs[1], $tmp)) {
				$ox = $this->getHTMLUnitToUnits($tmp[1], 0, $this->svgunit, false);
			}
			$tmp = array();
			if (preg_match('/[\s]+y[\s]*=[\s]*"([^"]*)"/si', $regs[1], $tmp)) {
				$oy = $this->getHTMLUnitToUnits($tmp[1], 0, $this->svgunit, false);
			}
			$tmp = array();
			if (preg_match('/[\s]+width[\s]*=[\s]*"([^"]*)"/si', $regs[1], $tmp)) {
				$ow = $this->getHTMLUnitToUnits($tmp[1], 1, $this->svgunit, false);
			}
			$tmp = array();
			if (preg_match('/[\s]+height[\s]*=[\s]*"([^"]*)"/si', $regs[1], $tmp)) {
				$oh = $this->getHTMLUnitToUnits($tmp[1], 1, $this->svgunit, false);
			}
			$tmp = array();
			$view_box = array();
			if (preg_match('/[\s]+viewBox[\s]*=[\s]*"[\s]*([0-9\.\-]+)[\s]+([0-9\.\-]+)[\s]+([0-9\.]+)[\s]+([0-9\.]+)[\s]*"/si', $regs[1], $tmp)) {
				if (count($tmp) == 5) {
					array_shift($tmp);
					foreach ($tmp as $key => $val) {
						$view_box[$key] = $this->getHTMLUnitToUnits($val, 0, $this->svgunit, false);
					}
					$ox = $view_box[0];
					$oy = $view_box[1];
				}
				// get aspect ratio
				$tmp = array();
				if (preg_match('/[\s]+preserveAspectRatio[\s]*=[\s]*"([^"]*)"/si', $regs[1], $tmp)) {
					$aspect_ratio = preg_split('/[\s]+/si', $tmp[1]);
					switch (count($aspect_ratio)) {
						case 3: {
							$aspect_ratio_align = $aspect_ratio[1];
							$aspect_ratio_ms = $aspect_ratio[2];
							break;
						}
						case 2: {
							$aspect_ratio_align = $aspect_ratio[0];
							$aspect_ratio_ms = $aspect_ratio[1];
							break;
						}
						case 1: {
							$aspect_ratio_align = $aspect_ratio[0];
							$aspect_ratio_ms = 'meet';
							break;
						}
					}
				}
			}
		}
		if ($ow <= 0) {
			$ow = 1;
		}
		if ($oh <= 0) {
			$oh = 1;
		}
		// calculate image width and height on document
		if (($w <= 0) AND ($h <= 0)) {
			// convert image size to document unit
			$w = $ow;
			$h = $oh;
		} elseif ($w <= 0) {
			$w = $h * $ow / $oh;
		} elseif ($h <= 0) {
			$h = $w * $oh / $ow;
		}
		// fit the image on available space
		list($w, $h, $x, $y) = $this->fitBlock($w, $h, $x, $y, $fitonpage);
		if ($this->rasterize_vector_images) {
			// convert SVG to raster image using GD or ImageMagick libraries
			return $this->Image($file, $x, $y, $w, $h, 'SVG', $link, $align, true, 300, $palign, false, false, $border, false, false, false);
		}
		// set alignment
		$this->img_rb_y = $y + $h;
		// set alignment
		if ($this->rtl) {
			if ($palign == 'L') {
				$ximg = $this->lMargin;
			} elseif ($palign == 'C') {
				$ximg = ($this->w + $this->lMargin - $this->rMargin - $w) / 2;
			} elseif ($palign == 'R') {
				$ximg = $this->w - $this->rMargin - $w;
			} else {
				$ximg = $x - $w;
			}
			$this->img_rb_x = $ximg;
		} else {
			if ($palign == 'L') {
				$ximg = $this->lMargin;
			} elseif ($palign == 'C') {
				$ximg = ($this->w + $this->lMargin - $this->rMargin - $w) / 2;
			} elseif ($palign == 'R') {
				$ximg = $this->w - $this->rMargin - $w;
			} else {
				$ximg = $x;
			}
			$this->img_rb_x = $ximg + $w;
		}
		// store current graphic vars
		$gvars = $this->getGraphicVars();
		// store SVG position and scale factors
		$svgoffset_x = ($ximg - $ox) * $this->k;
		$svgoffset_y = -($y - $oy) * $this->k;
		$svgoffset_x = $svgoffset_y = 0;
		if (isset($view_box[2]) AND ($view_box[2] > 0) AND ($view_box[3] > 0)) {
			$ow = $view_box[2];
			$oh = $view_box[3];
		} else {
			if ($ow <= 0) {
				$ow = $w;
			}
			if ($oh <= 0) {
				$oh = $h;
			}
		}
		$svgscale_x = $w / $ow;
		$svgscale_y = $h / $oh;
		$svgscale_x = 0.5;
		$svgscale_y = 0.588;
		//
		
		// scaling and alignment
		if ($aspect_ratio_align != 'none') {
			// store current scaling values
			$svgscale_old_x = $svgscale_x;
			$svgscale_old_y = $svgscale_y;
			// force uniform scaling
			if ($aspect_ratio_ms == 'slice') {
				// the entire viewport is covered by the viewBox
				if ($svgscale_x > $svgscale_y) {
					$svgscale_y = $svgscale_x;
				} elseif ($svgscale_x < $svgscale_y) {
					$svgscale_x = $svgscale_y;
				}
			} else { // meet
				// the entire viewBox is visible within the viewport
				if ($svgscale_x < $svgscale_y) {
					$svgscale_y = $svgscale_x;
				} elseif ($svgscale_x > $svgscale_y) {
					$svgscale_x = $svgscale_y;
				}
			}
			// correct X alignment
			switch (substr($aspect_ratio_align, 1, 3)) {
				case 'Min': {
					// do nothing
					break;
				}
				case 'Max': {
					$svgoffset_x += (($w * $this->k) - ($ow * $this->k * $svgscale_x));
					break;
				}
				default:
				case 'Mid': {
					$svgoffset_x += ((($w * $this->k) - ($ow * $this->k * $svgscale_x)) / 2);
					break;
				}
			}
			// correct Y alignment
			switch (substr($aspect_ratio_align, 5)) {
				case 'Min': {
					// do nothing
					break;
				}
				case 'Max': {
					$svgoffset_y -= (($h * $this->k) - ($oh * $this->k * $svgscale_y));
					break;
				}
				default:
				case 'Mid': {
					$svgoffset_y -= ((($h * $this->k) - ($oh * $this->k * $svgscale_y)) / 2);
					break;
				}
			}
		}
		$svgoffset_x = $svgoffset_y = 0;
		//prent($svgoffset_y);die;
		// store current page break mode
		//$page_break_mode = $this->AutoPageBreak;
		//$page_break_margin = $this->getBreakMargin();
		//$cell_padding = $this->cell_padding;
		$cell_padding = array(
    "T" => 0,
    "R" => 0,
    "B" => 0,
    "L" => 0,
		);
		$this->SetCellPadding(0);
		$this->SetAutoPageBreak(false);
		// save the current graphic state
		$this->_out('q'.$this->epsmarker);
		// set initial clipping mask
		$this->Rect($ximg, $y, $w, $h, 'CNZ', array(), array());
		// scale and translate
		$e = $ox * $this->k * (1 - $svgscale_x);
		$f = ($this->h - $oy) * $this->k * (1 - $svgscale_y);
		$this->_out(sprintf('%F %F %F %F %F %F cm', $svgscale_x, 0, 0, $svgscale_y, ($e + $svgoffset_x), ($f + $svgoffset_y)));
		// creates a new XML parser to be used by the other XML functions
		$this->parser = xml_parser_create('UTF-8');
		// the following function allows to use parser inside object
		xml_set_object($this->parser, $this);
		// disable case-folding for this XML parser
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
		// sets the element handler functions for the XML parser
		xml_set_element_handler($this->parser, 'startSVGElementHandler', 'endSVGElementHandler');
		// sets the character data handler function for the XML parser
		xml_set_character_data_handler($this->parser, 'segSVGContentHandler');
		// start parsing an XML document
		if (!xml_parse($this->parser, $svgdata)) {
			$error_message = sprintf('SVG Error: %s at line %d', xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser));
			$this->Error($error_message);
		}
		// free this XML parser
		xml_parser_free($this->parser);
		// restore previous graphic state
		$this->_out($this->epsmarker.'Q');
		// restore graphic vars
		//$this->setGraphicVars($gvars);
		$this->lasth = $gvars['lasth'];
		/*if (!empty($border)) {
			$bx = $this->x;
			$by = $this->y;
			$this->x = $ximg;
			if ($this->rtl) {
				$this->x += $w;
			}
			$this->y = $y;
			$this->Cell($w, $h, '', $border, 0, '', 0, '', 0, true);
			$this->x = $bx;
			$this->y = $by;
		}
		if ($link) {
			$this->Link($ximg, $y, $w, $h, $link, 0);
		}*/
		
		// set pointer to align the next text/objects
		switch($this->img_rb_x) {
			case 'T':{
				$this->y = $y;
				$this->x = $this->img_rb_x;
				break;
			}
			case 'M':{
				$this->y = $y + round($h/2);
				$this->x = $this->img_rb_x;
				break;
			}
			case 'B':{
				$this->y = $this->img_rb_y;
				$this->x = $this->img_rb_x;
				break;
			}
			case 'N':{
				$this->SetY($this->img_rb_y);
				break;
			}
			default:{
				// restore pointer to starting position
				$this->x = $gvars['x'];
				$this->y = $gvars['y'];
				$this->page = $gvars['page'];
				$this->current_column = $gvars['current_column'];
				$this->tMargin = $gvars['tMargin'];
				$this->bMargin = $gvars['bMargin'];
				$this->w = $gvars['w'];
				$this->h = $gvars['h'];
				$this->wPt = $gvars['wPt'];
				$this->hPt = $gvars['hPt'];
				$this->fwPt = $gvars['fwPt'];
				$this->fhPt = $gvars['fhPt'];
				break;
			}
		}
		//
		$this->endlinex = $this->img_rb_x;
		// restore page break
		$this->SetAutoPageBreak($page_break_mode, 0);
		$this->cell_padding = $cell_padding;
	}
}

$arOrder = explode(",", $_REQUEST["order_wb"]);
//prent($arOrder);die;
if(count($arOrder) <= 0) die("нет кодов");


$pdf = new TCPDF('L', 'mm', array('58','40'), true, 'UTF-8', false);
//$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(400, 300), true, 'UTF-8', false);
// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(0, 0, 0, true);

// set auto page breaks false
$pdf->SetAutoPageBreak(false, 0);

// set document information

//$pdf->SetMargins(0, 0, 0);
//$pdf->SetMargins (0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);
//$pdf->SetHeaderMargin(0);
//$pdf->SetFooterMargin(0);

// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, 0);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// add a page
$pdf->AddPage();
$cnt = count($arOrder) - 1;
foreach($arOrder as $k => $order_wb){
	$pdf->ImageSVG("/upload/wb/{$order_wb}.svg", -10, -8, 80, 50);

	if($k + 1 < $cnt)
		$pdf->AddPage();
}

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output();
//$pdf->Output('example_058.pdf', 'D');
//$file, $x='', $y='', $w=0, $h=0, $link='', $align='', $palign='', $border=0, $fitonpage=false