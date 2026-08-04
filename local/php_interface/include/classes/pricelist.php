<?
    class CPriceLists
    {
        var $timeout = 30;
        var $curTime = 0;
        var $startTime = 0;

        static function update()
        {
            set_time_limit(0);

            self::nUpdate();
        }
        static function nUpdate(){
            if( !CModule::IncludeModule('iblock') )
                return 'CPriceLists::update()';
            if( !CModule::IncludeModule('catalog') )
                return 'CPriceLists::update()';

            $data = self::getPrices();
            //print_r( $data );

            if( empty( $data ) )
                return 'CPriceLists::update()';

            $priceRes = array();

            foreach( $data['prices'] as $priceList ) {

                if( empty( $priceList['FILE'] ) || empty( $priceList['RULE'] ) )
                    continue;

                $parseRes = self::parseCsvFile(
                    $priceList['FILE'],
                    $data['rules'][ $priceList['RULE'] ]['AN_ROW'],
                    $data['rules'][ $priceList['RULE'] ]['PRICE_ROW']
                );
                if( !empty( $parseRes ) )
                    $priceRes = array_merge( $priceRes, $parseRes );
            }

            //todo: models (keys) overriding here

            self::setAvail(  $priceRes  );

            return 'CPriceList::update()';
        }

        static public function getPrices() {

            $select = array('ID', 'PROPERTY_FILE', 'PROPERTY_RULE');

            $dbl = CIBlockElement::GetList( array(), array('IBLOCK_ID' => PRICE_LIST_IBLOCK_ID), false, false, $select );

            while( $arRes = $dbl->Fetch() ){
                $prices[ $arRes['ID'] ] = array(
                    'ID'    => $arRes['ID'],
                    'FILE'  => CFile::GetPath( $arRes['PROPERTY_FILE_VALUE'] ),
                    'RULE'  => $arRes['PROPERTY_RULE_VALUE']
                );
                $rulesIDs[] = $arRes['RULE'];
            }

            if(empty( $prices ) || empty( $rulesIDs ))
                return false;

            $select = array(
                'ID',
                'PROPERTY_ARTNUMBER_ROW',
                'PROPERTY_ARTNUMBER_FUNCTION',
                'PROPERTY_PRICE_ROW',
                'PROPERTY_PRICE_MULTIPLIER'
            );

            $dbl = CIBlockElement::GetList( array(), array( '=ID' => $rulesIDs, 'IBLOCK_ID' => PRICE_LIST_RULES_IBLOCK_ID ), false, false, $select );
            while( $arRes = $dbl->Fetch() ){
                $rules[ $arRes['ID'] ] = array(
                    'ID'            => $arRes['ID'],
                    'AN_ROW'        => $arRes['PROPERTY_ARTNUMBER_ROW_VALUE'],
                    'AN_FUNCTION'   => $arRes['PROPERTY_ARTNUMBER_FUNTION_VALUE'],
                    'PRICE_ROW'     => $arRes['PROPERTY_PRICE_ROW_VALUE'],
                    'PRICE_MULTIPLIER'  => $arRes['PROPERTY_PRICE_MULTIPLIER_VALUE']
                );
            }

            if( empty( $rules ) )
                return false;

            return array( 'prices' => $prices, 'rules' => $rules );
        }

        static private function parseCsvFile( $path, $anRow, $priceRow ){

            $path = $_SERVER['DOCUMENT_ROOT'] . $path;

            if( empty( $path ) || empty( $anRow ) || empty( $priceRow ) )
                return false;

            if( !file_exists( $path ) || !is_readable( $path ) )
                return false;

            $anRow--;
            $priceRow--;

            $file = fopen( $path, 'r' );
            while( $row = fgetcsv($file, 0, ';') ){
                if( isset( $row[ $anRow ] ) ){
                    $an = trim( $row[ $anRow ] );
                    $an = explode( ' ', $an )[0];
                }

                if( isset( $row[ $priceRow ] ) ){
                    $price = trim( $row[ $priceRow ] );
                    $price = str_replace( array(' ', 'р', '$'), '', $price );
                    $price = str_replace( ',', '.', $price );
                    $price = floatval( $price );
                }

                if( !empty( $price ) && !empty( $an ) )
                    $res[ $an ] = $price;
            }

            return empty( $res ) ? false : $res;
        }

        static public function setAvail( $list ) {

            $pricesActivated = 0;
            $pricesDeactivated = 0;

            $dbl = CIBLockElement::GetList(array(),
                                           array('IBLOCK_ID' => CATALOG_IBLOCK_ID),
                                           false,
                                           false,
                                           array('ID', 'PROPERTY_CML2_ARTICLE', 'CATALOG_QUANTITY'));

            while( $arRes = $dbl->Fetch() ){
                $id = $arRes['ID'];
                $an = trim( $arRes['PROPERTY_CML2_ARTICLE_VALUE'] );
                $quantityInBase = $arRes['CATALOG_QUANTITY'];

                if (isset($list[$an]))
                {
                    $quantity = 100;
                    $active = 'Y';
                } else {
                    $quantity = 0;
                    $active = 'N';
                }
                /*if( in_array( $an, $list, true ) ){
                    $quantity = 100;
                    $active = 'Y';
                } else {
                    $quantity = 0;
                    $active = 'N';
                }*/

                if($quantityInBase != $quantity) {
                    //echo $an.' - '.$quantity.PHP_EOL;
                    $arFields = array('QUANTITY' => $quantity);
                    CCatalogProduct::Update($id, $arFields);
                    if($quantity == 100)
                    {
                        $pricesActivated++;
                    } else {
                        $pricesDeactivated++;
                    }
                    //$el = new CIBlockElement;
                    //$el->Update( $id, array('ACTIVE' => $active) );
                }

            }

            print "Активировано: " . $pricesActivated . "; Деактивировано: " . $pricesDeactivated . ";";
        }
        public static function testMethod()
        {
            echo 'test';
        }
    }