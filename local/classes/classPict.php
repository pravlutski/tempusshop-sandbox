<?
//https://www.brekot.ru/blog/bitrix-webp/

//namespace BFPict;

class Pict {
	private static $isPng = true;
	private static function checkFormat($str)
	{
		if ($str === 'image/png')
		{
			self::$isPng = true;
			return true;
		}
		elseif ($str === 'image/jpeg')
		{
			self::$isPng = false;
			return true;
		}
		else return false;
	}

	private static function implodeSrc($arr)
	{
		$arr[count($arr) - 1] = '';
		return implode('/', $arr);
	}

	private static function generateSrc($str)
	{
		$arPath = explode('/', $str);

		if ($arPath[2] === 'resize_cache')
		{
			$arPath = self::implodeSrc($arPath);
			return str_replace('resize_cache/iblock', 'webp/resize_cache', $arPath);
		}
		else
		{
			$arPath = self::implodeSrc($arPath);
			return str_replace('upload/iblock', 'upload/webp/iblock', $arPath);
		}
	}

    public static function getWebp($array, $intQuality = 70)
	{
		if (self::checkFormat($array['CONTENT_TYPE']))
		{
			$array['WEBP_PATH'] = self::generateSrc($array['src']);
			if (self::$isPng)
			{
				$array['WEBP_FILE_NAME'] = str_replace('.png', '.webp', strtolower($array['FILE_NAME']));
			}
			else
			{
				$array['WEBP_FILE_NAME'] = str_replace('.jpg', '.webp', strtolower($array['FILE_NAME']));
				$array['WEBP_FILE_NAME'] = str_replace('.jpeg', '.webp', strtolower($array['WEBP_FILE_NAME']));
			}

			if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_PATH']))
			{
				mkdir($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_PATH'], 0777, true);
			}
    		$array['WEBP_SRC'] = $array['WEBP_PATH'] . $array['WEBP_FILE_NAME'];
			if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC']))
			{
				if (self::$isPng)
				{
					$im = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . $array['src']);
				}
				else
				{
					$im = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . $array['src']);
				}
				imagewebp($im, $_SERVER['DOCUMENT_ROOT'] . $array['WEBP_SRC'], $intQuality);
				imagedestroy($im);
			}
		}
		return $array;
    }

	public static function resizePict($file, $width, $height, $isProportional = true, $intQuality = 70)
	{
		$file = \CFile::ResizeImageGet($file, array('width'=>$width, 'height'=>$height), ($isProportional ? BX_RESIZE_IMAGE_PROPORTIONAL : BX_RESIZE_IMAGE_EXACT), false, false, false, $intQuality);

		return $file['src'];
	}

	public static function getResizeWebp($file, $width, $height, $isProportional = true, $intQuality = 70)
	{
		$file = CFile::GetByID($file)->Fetch();
		$file['src'] = self::resizePict($file["ID"], $width, $height, $isProportional, $intQuality);

		$file = self::getWebp($file, $intQuality);

		return $file;
	}

	public static function getResizeWebpSrc($file, $width, $height, $isProportional = true, $intQuality = 70)
	{
		$file = CFile::GetByID($file)->Fetch();
		$file['src'] = self::resizePict($file["ID"], $width, $height, $isProportional, $intQuality);

		$file = self::getWebp($file, $intQuality);

		return $file['WEBP_SRC'];
	}
	
	public static function getSliderForItemExt(&$item, $propertyCode, $addDetailToSlider, $encode = true){
        $encode = ($encode === true);
        $result = array();

        if (!empty($item) && is_array($item))
        {

            if (
                '' != $propertyCode &&
                isset($item['PROPERTIES'][$propertyCode]) &&
                'F' == $item['PROPERTIES'][$propertyCode]['PROPERTY_TYPE']
            )
            {
                if ('MORE_PHOTO' == $propertyCode && isset($item['MORE_PHOTO']) && !empty($item['MORE_PHOTO']))
                {

                    foreach ($item['MORE_PHOTO'] as &$onePhoto)
                    {
                    	$alt = ($onePhoto["DESCRIPTION"] ? $onePhoto["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]));
                    	$title = ($onePhoto["DESCRIPTION"] ? $onePhoto["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]));
                    	if($item['ALT_TITLE_GET'] == 'SEO')
                    	{
                    		$alt = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]);
                    		$title = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]);
                    	}
						
                        $result[] = array(
                            'ID' => (int)$onePhoto['ID'],
                            'SRC' => ($encode ? CHTTP::urnEncode($onePhoto['SRC'], 'utf-8') : $onePhoto['SRC']),
                            'WIDTH' => (int)$onePhoto['WIDTH'],
                            'HEIGHT' => (int)$onePhoto['HEIGHT'],
                            'ALT' => $alt,
                            'TITLE' => $title,
							'WEBP_SRC' => self::getResizeWebpSrc($onePhoto["ID"], $onePhoto['WIDTH'], $onePhoto['HEIGHT'], true, 100),
                        );
                    }
                    unset($onePhoto);
                }
                else
                {
                    if (
                        isset($item['DISPLAY_PROPERTIES'][$propertyCode]['FILE_VALUE']) &&
                        !empty($item['DISPLAY_PROPERTIES'][$propertyCode]['FILE_VALUE'])
                    )
                    {
                        $fileValues = (
                        isset($item['DISPLAY_PROPERTIES'][$propertyCode]['FILE_VALUE']['ID']) ?
                            array(0 => $item['DISPLAY_PROPERTIES'][$propertyCode]['FILE_VALUE']) :
                            $item['DISPLAY_PROPERTIES'][$propertyCode]['FILE_VALUE']
                        );
                        foreach ($fileValues as &$oneFileValue)
                        {
                        	$alt = ($oneFileValue["DESCRIPTION"] ? $oneFileValue["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]));
	                    	$title = ($oneFileValue["DESCRIPTION"] ? $oneFileValue["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]));
	                    	if($item['ALT_TITLE_GET'] == 'SEO')
	                    	{
	                    		$alt = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]);
	                    		$title = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]);
	                    	}
                            $result[] = array(
                                'ID' => (int)$oneFileValue['ID'],
                                'SRC' => ($encode ? CHTTP::urnEncode($oneFileValue['SRC'], 'utf-8') : $oneFileValue['SRC']),
                                'WIDTH' => (int)$oneFileValue['WIDTH'],
                                'HEIGHT' => (int)$oneFileValue['HEIGHT'],
                                'ALT' => $alt,
                          		'TITLE' => $title,
								'WEBP_SRC' => self::getResizeWebpSrc($oneFileValue["ID"], $oneFileValue['WIDTH'], $oneFileValue['HEIGHT'], true, 100),
                            );
                        } 
                        if (isset($oneFileValue))
                            unset($oneFileValue);
                    }
                    else
                    {

                        $propValues = $item['PROPERTIES'][$propertyCode]['VALUE'];
                        if (!is_array($propValues))
                            $propValues = array($propValues);
						
						//op
						$propValues = array_slice($propValues, 0, 3);
						
                        foreach ($propValues as &$oneValue)
                        {
                            $oneFileValue = CFile::GetFileArray($oneValue);
                            if (isset($oneFileValue['ID']))
                            {
                            	$alt = ($oneFileValue["DESCRIPTION"] ? $oneFileValue["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]));
		                    	$title = ($oneFileValue["DESCRIPTION"] ? $oneFileValue["DESCRIPTION"] : ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]));
		                    	if($item['ALT_TITLE_GET'] == 'SEO')
		                    	{
		                    		$alt = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_ALT'] : $item["NAME"]);
		                    		$title = ($item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] ? $item['IPROPERTY_VALUES']['ELEMENT_DETAIL_PICTURE_FILE_TITLE'] : $item["NAME"]);
		                    	}
                                $result[] = array(
                                    'ID' => (int)$oneFileValue['ID'],
                                    'SRC' => ($encode ? CHTTP::urnEncode($oneFileValue['SRC'], 'utf-8') : $oneFileValue['SRC']),
                                    'WIDTH' => (int)$oneFileValue['WIDTH'],
                                    'HEIGHT' => (int)$oneFileValue['HEIGHT'],
                                    'ALT' => $alt,
                          			'TITLE' => $title,
									'WEBP_SRC' => self::getResizeWebpSrc($oneFileValue["ID"], $oneFileValue['WIDTH'], $oneFileValue['HEIGHT'], true, 100),
                                );
                            }
                        }
                        if (isset($oneValue))
                            unset($oneValue);
                    }
                }
            }
            if(isset($item['OFFERS']) && $item['OFFERS'] && !$addDetailToSlider){
            	if(empty($result))
            		unset($item['DETAIL_PICTURE']);
            }
            if ($addDetailToSlider || empty($result))
            {

                if (!empty($item['DETAIL_PICTURE']))
                {
                    if (!is_array($item['DETAIL_PICTURE']))
                        $item['DETAIL_PICTURE'] = CFile::GetFileArray($item['DETAIL_PICTURE']);

                    if (isset($item['DETAIL_PICTURE']['ID']))
                    {
                    	$alt = ($item['DETAIL_PICTURE']['DESCRIPTION'] ? $item['DETAIL_PICTURE']['DESCRIPTION'] : ($item['DETAIL_PICTURE']['ALT'] ? $item['DETAIL_PICTURE']['ALT'] : $item['NAME'] ));
                    	$title = ($item['DETAIL_PICTURE']['DESCRIPTION'] ? $item['DETAIL_PICTURE']['DESCRIPTION'] : ($item['DETAIL_PICTURE']['TITLE'] ? $item['DETAIL_PICTURE']['TITLE'] : $item['NAME'] ));
                    	if($item['ALT_TITLE_GET'] == 'SEO')
                    	{
                    		$alt = ($item['DETAIL_PICTURE']['ALT'] ? $item['DETAIL_PICTURE']['ALT'] : $item['NAME'] );
                    		$title = ($item['DETAIL_PICTURE']['TITLE'] ? $item['DETAIL_PICTURE']['TITLE'] : $item['NAME'] );
                    	}
                    	$detailPictIds = array_column($result, 'ID');
                    	if(!in_array((int)$item['DETAIL_PICTURE']['ID'], $detailPictIds)){                    	
	                        array_unshift(
	                            $result,
	                            array(
	                                'ID' => (int)$item['DETAIL_PICTURE']['ID'],
	                                'SRC' => ($encode ? CHTTP::urnEncode($item['DETAIL_PICTURE']['SRC'], 'utf-8') : $item['DETAIL_PICTURE']['SRC']),
	                                'WIDTH' => (int)$item['DETAIL_PICTURE']['WIDTH'],
	                                'HEIGHT' => (int)$item['DETAIL_PICTURE']['HEIGHT'],
	                                'ALT' => $alt,
	                                'TITLE' => $title,
									'WEBP_SRC' => self::getResizeWebpSrc($item['DETAIL_PICTURE']['ID'], $item['DETAIL_PICTURE']['WIDTH'], $item['DETAIL_PICTURE']['HEIGHT'], true, 100),
	                            )
	                        );
                    	}
                    }
                    elseif($item['PICTURE'])
                    {
                    	array_unshift(
                            $result,
                            array(
                                'SRC' => $item['PICTURE'],
                                'ALT' => $item['NAME'],
                                'TITLE' => $item['NAME']
                            )
                        );
                    }
                }
            }
        }
        return $result;
    }
}?>