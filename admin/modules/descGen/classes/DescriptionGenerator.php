<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class DescriptionGenerator
{
  private $debug;
  private $db;
  private $element;
  private $logPath;

  private $excludeProp;

  private $props;
  private $propsBX;

  private $items = [];
  private $propNames;
  private $templates;
  private $options;

  private $demo;

  public function __construct( bool $debug = true )
  {
    global $DB;
    $this->db = $DB;
    $this->excludeProp = ["NAME_MARKETPLACE", "MECHANISM", "ID", "IBLOCK_ID", "DETAIL_TEXT", "DESC_RICH_OZON", "FACE", "OZON_ACTIVE"];
    $this->logPath = $_SERVER["DOCUMENT_ROOT"] . '/admin/modules/descGen/logs/' . date('Y-m-d') . '_genLog.txt';
    $this->debug = $debug;

    $this->templates = [
      'COLOR' => "Цвет корпуса: %s. ",
      'DIAL_COLOR' => "Цвет циферблата: %s. ",
      'DIAMETER' => "Ширина (с заводной головкой): %s мм. ",
      'THICKNESS' => "Толщина: %s мм. ",
      'HEIGHT' => "Высота (с ушками): %s мм. ",
      'WARRANTY' => "Гарантия: %s. ",
      'VENDORCODES' => "Другие наименования модели: %s.",
    ];

    $this->propNames = [
      "NAME_MARKETPLACE", "MECHANISM", "FACE", "BACKLIGHT", "MATERIAL",
      "FEATURES", "CASE", "GLASS", "CALENDAR",
      "WR", "COLOR", "DIAL_COLOR",
      "HEIGHT", "THICKNESS", "WARRANTY", "VENDORCODES"
    ];

    $this->element = new CIBlockElement;

    $this->demo = false;
  }

  public function cron():void
  {
    $this->writeLog('START');
    $this->getItems();
    $this->getPropertyText();
    $this->processItemsCron();
    $this->writeLog('END');
  }

  public function ajax( array|bool $optionsAJAX = false ):void
  {
    $this->options = $optionsAJAX;
    if ( ! $this->options ) return;
    if ( ! self::validateOptionsArray($this->options) ) die;

    $this->getItems();
    $this->getPropertyText();
    $this->processItemsAjax();
  }

  public function getRichDescription( int $card_id, bool $defaultMode = true ):string|bool
  {
    if ( empty( $card_id ) ){
      if ( $this->debug ) throw new \Exception("No empty array allowed. Method: generateDescription");
      return false;
    }
    $this->getPropertyText();
    $this->getItems( $card_id );
    $card = $this->items[ $card_id ];

    if ( !$defaultMode ) {
      $description = $this->generateDescription($card, 'rich');
      return $description;
    }

    if ( !empty($card['DETAIL_TEXT']) ){
      $description = self::modifyDetailDescription( $card );
    }else{
      $description = $this->generateDescription($card, 'rich');
    }

    return $description;
  }

  private function getItems( int|bool $card_id = false ):void
  {
    $arSelect = ["IBLOCK_ID", "ID", "DETAIL_TEXT", "PROPERTY_DESC_RICH_OZON","PROPERTY_OZON_ACTIVE", "PROPERTY_MATERIAL", "PROPERTY_FEATURES", "PROPERTY_CASE", "PROPERTY_GLASS", "PROPERTY_CALENDAR", "PROPERTY_WR", "PROPERTY_BACKLIGHT", "PROPERTY_FACE", "PROPERTY_vendorcodes", "PROPERTY_COLOR", "PROPERTY_DIAL_COLOR", "PROPERTY_DIAMETER", "PROPERTY_HEIGHT", "PROPERTY_THICKNESS", "PROPERTY_NAME_MARKETPLACE", "PROPERTY_MECHANISM", "PROPERTY_WARRANTY"];
    $arFilter = [
      "IBLOCK_ID" => 16,
      "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
    ];

    if ( !empty( $card_id ) ) $arFilter['ID'] = $card_id;
    if ( $this->options ) $arFilter['PROPERTY_CML2_ARTICLE'] = $this->options['vendor_codes'];

    $result = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
    while ( $card = $result->Fetch() ){

      $this->items[ $card["ID"] ] = [
          "ID" => $card['ID'],
          "DETAIL_TEXT" => $card["DETAIL_TEXT"],
          "DESC_RICH_OZON" => $card["PROPERTY_DESC_RICH_OZON_VALUE"]["TEXT"],
        ];

      foreach ( $this->propNames as $name ){
        $this->items[ $card['ID'] ][ $name ] = self::getOptionCode( $card, $name );
      }

    }
    $this->writeLog( 'GOT ITEMS: ' . count($this->items) );
  }

  private function generateDescription( array $item, string $mode ):string|bool // (rich / detail)
  {
    if ( empty($item) ){
      if ( $this->debug ){
        $this->writeLog('Method generateDescription returned an exception (empty array).');
        throw new \Exception("No empty array allowed. Method: generateDescription");
      }
      return false;
    }

    $desc = '';
    $mechTmp = is_array($item['MECHANISM']) ? $item['MECHANISM'][0] : $item['MECHANISM'];

    if ( $mechTmp == 42 || $mechTmp == 'Кварцевые' ){
      $faceTmp = empty( $this->props[ $item['FACE'] ]['text'] ) ? '. ' : $this->props[ $item['FACE'] ]['text'];
      $mechBlock = sprintf($this->props[ $mechTmp ]['text'], $faceTmp);
    }else{
      $mechBlock = $this->props[ $mechTmp ]['text'];
    }


    $desc = "{$item['NAME_MARKETPLACE']}. {$mechBlock}";
    foreach ($item as $propCode => $prop) {
      if ( empty($prop) ) continue;

      if ( is_array($prop) ){
        foreach ($prop as $value) {
          if ( in_array($propCode, $this->excludeProp) ) continue;
          if ( isset($this->templates[$propCode]) ){

            $desc .= sprintf( $this->templates[$propCode], $this->props[$value]['name'] );
          }else{
            $desc .= $this->props[$value]['text'];
          }
        }
      }else{
        if ( in_array($propCode, $this->excludeProp) ) continue;
        if ( isset($this->templates[$propCode]) ){
          $desc .= sprintf( $this->templates[$propCode], $prop );
        }else{
          $desc .= $this->props[$prop]['text'];
        }
      }
    }

    return $desc;
  }

  private static function modifyDetailDescription( array $item ):string
  {
    // Чистим от тегов и декодируем мнемоники
    $detail = str_replace('&nbsp', ' ', $item['DETAIL_TEXT']);
    $detail = str_replace('<br>', '%BR%', $detail);
    $detail = preg_replace('/&([a-z]+);/', '', $detail);
    $detail = preg_replace('/&#([0-9]+);/', '', $detail);
    $formatted = htmlspecialchars_decode( strip_tags($detail) );
    $formatted =  strip_tags($detail) ;
    // Удаляем все непечатные символы
    $text = preg_replace('/\p{Cc}/u', '', $formatted);
    //Достаем из свойства текст по шаблону
    $regex = "/[A-яA-z\s()\/0-9\:\.\,\=\+\-\—\%]+/u";
    preg_match_all($regex, $text, $matches);
    $extractedText = implode("", $matches[0]);
    // Избавляемся от двойных пробелов
    $extractedText = str_replace('  ', ' ', $extractedText);
    // Обрезаем текст по последнюю точку
    if ( $last_dot_index = strrpos($extractedText, '.') ){
      $extractedText = mb_substr($extractedText, 0, $last_dot_index + 1);
    }
    // Добавляем альтернативные артикулы, если свойство заполнено
    if ( !empty($item['VENDORCODES']) ){
      $extractedText .= ' Другие наименования модели: ' . $item['VENDORCODES'];
    }
    return $extractedText;
   }

  private static function modifyRichDescription( string $rich ):string
  {
   return strrpos( $rich, 'Другие наименования модели:' ) ? explode('Другие наименования модели:', $rich)[0] : $rich;
  }

  private function processItemsCron():void
  {
    if ( empty($this->items) ) {
      throw new Exception("Empty \$items");
      return;
    }
    foreach ( $this->items as $item ){
      if ( !empty($item['DETAIL_TEXT']) && !empty($item["DESC_RICH_OZON"]) ){
        $this->writeLog( $item['ID'] . ' has both DESC_RICH_OZON and DETAIL_TEXT. Nothing will be generated' );
        continue;
      }

      if ( !empty($item['DETAIL_TEXT']) && empty($item["DESC_RICH_OZON"]) ){
        $this->writeLog( $item['ID'] . ' has no DESC_RICH_OZON but has DETAIL_TEXT. RICH will be generated from DETAIL_TEXT' );
        $descRich = self::modifyDetailDescription( $item );

        $this->updateDescriptionProp( $item['ID'], "DESC_RICH_OZON", $descRich );
        continue;
      }

      if ( empty($item['DETAIL_TEXT']) ){
        $this->writeLog( $item['ID'] . ' has no DETAIL_TEXT or DESC_RICH_OZON. Both will be generated' );
        $descRich = $this->generateDescription($item, 'rich');
        $descDetail = self::modifyRichDescription( $descRich );

        $this->updateDescriptionProp( $item['ID'], "DESC_RICH_OZON", $descRich );
        $this->updateDescriptionProp( $item['ID'], "DETAIL_TEXT", $descDetail );
        continue;
      }
    }
  }

  private function processItemsAjax() : void
  {
    if ( empty($this->items) ) {
      throw new Exception("Empty \$items");
      return;
    }

    foreach ( $this->items as $item ){
      if ( $this->options['detail_flag'] && $this->options['rich_flag'] ){

        $descDetail = $this->generateDescription( $item, 'detail' );
        $descRich = $this->generateDescription( $item, 'rich' );

        // var_dump($descRich);
        // var_dump($descDetail);
        $this->updateDescriptionProp( $item['ID'], "DESC_RICH_OZON", $descRich );
        $this->updateDescriptionProp( $item['ID'], "DETAIL_TEXT", $descDetail );
        continue;

      } elseif ( $this->options['detail_flag'] ){

        $descDetail = $this->generateDescription( $item, 'detail' );
        // var_dump($descDetail);
        $this->updateDescriptionProp( $item['ID'], "DETAIL_TEXT", $descDetail );
        continue;

      } elseif ( $this->options['rich_flag'] ){
        $descRich = $this->generateDescription( $item, 'rich' );
        // var_dump($descRich);
        // var_dump( mb_strlen($descRich) );
        $this->updateDescriptionProp( $item['ID'], "DESC_RICH_OZON", $descRich );
        continue;
      }
      $this->writeLog( $item['ID'] . ' has both DEATIL_TEXT and DESC_RICH_OZON' );
    }
  }

  private function updateDescriptionProp( int $id, string $property, string $text ):bool
  {
    // Этот метод нужен не столько для группировки, сколько для удобного дебага без изменений свойств
    if ( !in_array($property, ['DETAIL_TEXT', 'DESC_RICH_OZON']) ) return false;

    if ( $this->demo ) {
      var_dump( ['id' => $id, 'property' => $property, 'text' => $text] );
      return true;
    }

    if ( $property == 'DETAIL_TEXT'){
      $res = $this->element->Update( $id, [$property => $text] );
      return $res;
    }

    if ( $property == 'DESC_RICH_OZON' ){
      $res = CIBlockElement::SetPropertyValueCode( $id, $property, ['VALUE' => $text] );
      return $res;
    }

    return false;
  }

  private static function getOptionCode( array $card, string $prop ):mixed
  {
    if ( in_array($prop, ["WARRANTY"]) ){
      return $card["PROPERTY_{$prop}_VALUE"];
    }
    if ( is_array($card["PROPERTY_{$prop}_VALUE"]) ){
      return array_keys($card["PROPERTY_{$prop}_VALUE"]);
    }
    if ( isset($card["PROPERTY_{$prop}_ENUM_ID"]) ){
      return $card["PROPERTY_{$prop}_ENUM_ID"];
    }
    return $card["PROPERTY_{$prop}_VALUE"];
  }

  private function getPropertyText():void
  {
    $strSql = "SELECT * FROM sds_property_text_match";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $result->Fetch() ){
      $this->props[ $row['option_id'] ] = [
        'name' => $row['option_name'],
        'text' => $row['text']
      ];
    }
  }

  private static function validateOptionsArray( array $array ):bool
  {
    if ( empty( $array ) ) return false;

    $allowedKeys = [
      'detail_flag' => 'is_bool',
      'rich_flag' => 'is_bool',
      'vendor_codes' => 'is_array'
    ];

    foreach ( $allowedKeys as $key => $method ){
      if ( !isset( $array[$key] ) ){
        throw new InvalidArgumentException("Option array has no required key: {$key}");
        return false;
      }
      if ( ! $method($array[$key]) ){
        throw new InvalidArgumentException( "Option array key {$key} cannot be " . gettype( $array[$key] ) );
        return false;
      }
    }

    return true;
  }

  private function writeLog( string $message ):bool
  {
    if ( ! $this->debug ) return false;

    file_put_contents( $this->logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );

    return true;
  }
}

 ?>
