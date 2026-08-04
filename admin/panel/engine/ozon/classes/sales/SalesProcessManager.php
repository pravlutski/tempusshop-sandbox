<?php
class SalesProcessManager
{
  private array $isInAction = [];
  private array $settings;
  private ?SalesCommunicationService $scs;

  public function __construct( array $settings, SalesCommunicationService $communicationService )
  {
    if ( empty($settings) ) throw new InvalidArgumentException("\$settings cannot be an empty array");
    $this->settings = $settings;
    $this->scs = $communicationService;
  }

  public function processSales( array $salesList, array $salesProducts, array $items ):array
  {
    // $salesList - список всех акций из базы
    // $salesData - информация по акционным товарам от озона
    // $items - информация о товарах из базы
    // $settings - массив с дополнительными параметрами

    $result = [];

    foreach ( $salesList as $sale_id => $saleInfo ){
      SalesCommunicationService::logTech( "Проверка товаров для акции {$sale_id}" );
      $result[ $sale_id ] = $this->processItems( $saleInfo, $salesProducts, $items );

      SalesCommunicationService::logTech( "Товаров, прошедших проверку: " . count($result[$sale_id]['good'] ?? []) );
      SalesCommunicationService::logTech( "Товаров, непрошедших проверку: " . count($result[$sale_id]['bad'] ?? []) );
    }

    return $result;
  }

  private function processItems( array $saleInfo, array $salesData, array $items ):array
  {
    $result = [];
    $log = [];

    foreach ( $items as $ozon_id => $item ){
      $ozonData = $salesData[ $saleInfo['sale_id'] ][ $ozon_id ] ?? false;

      if ( $ozonData == false ) continue;

      $max_action_price = $ozonData['max_action_price'];

      if ( $item['dp_status'] === true ){

        $checkInfo = $this->checkItemDP(
          item: $item,
          maxActionPrice: $max_action_price,
          sort: $saleInfo['sort'],
         );
      }
      else{
        if ( $saleInfo['perc_entry'] == 'Y' && !empty($saleInfo['perc']) ){
          // Логика при установленном чекбоксе для вхождения с фиксированной скидкой
          $checkInfo = $this->checkItemWithFixedDiscount(
            item: $this->applyPriceDiscount( $item, $saleInfo ),
            maxActionPrice: $max_action_price,
            sort: $saleInfo['sort'],
            discount: $saleInfo['perc'],
          );
        }else{
          // Самая обычная логика
          $checkInfo = $this->checkItemDefault(
            item: $item,
            maxActionPrice: $max_action_price,
            sort: $saleInfo['sort'],
            discount: $saleInfo['perc'],
          );
        }
      }

      $this->distributeItem(
        productId: $item['ozon_id'],
        checkInfo: $checkInfo,
        result: $result
      );

      $log[] = $this->enrichWithContext(
        data: $checkInfo,
        item: $item,
        saleInfo: $saleInfo,
        ozonProduct: $ozonData,
      );
    }

    $this->scs->log( data: $log );

    return $result;
  }

  private function checkItemDP( array $item, float $maxActionPrice, int $sort ):array
  {
    // $item - массив с данными о товаре
    // $maxActionPrice - максимальная цена для вхождения в акцию или цена для вхождения для получения заданного процента бустинга
    // $sort - приоритет акции
    // $isInAction - контрольный массив, который будет использоваться для быстрой проверки, куда товар вошел

    // Возвращает массив со статусов и информацией для аналитики

    $result = [ // По умолчанию товар не входит в акцию
      'price' => $item['price'],
      'maxActionPrice' => $maxActionPrice,
      'reason' => "Не прошел по цене {$item['price']} > {$maxActionPrice}",
      'isPriceDynamic' => 'Y',
      'status' => false,
    ];

    if ( isset( $this->isInAction[ $item['model'] ] ) ){
      $result['reason'] = "Товар вошел в другую акцию по приоритету";
      return $result;
    }

    if ( $item['price'] <= $maxActionPrice ){
      $result['status'] = true;
      $result['reason'] = "Товар прошел по всем условиям {$item['price']} <= {$maxActionPrice}";

      // Нулевое значение сортировки не исключает возможность добавления товара в еще одну акцию
      if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;
    }

    return $result;
  }

  private function checkItemDefault( array $item, float $maxActionPrice, int $sort, int $discount = 0 ):array
  {
    // $item - массив с данными о товаре
    // $maxActionPrice - максимальная цена для вхождения в акцию или цена для вхождения для получения заданного процента бустинга
    // $sort - приоритет акции
    // $isInAction - контрольный массив, который будет использоваться для быстрой проверки, куда товар вошел
    // $settings - массив с настройками модуля по которым будут проводиться проверки

    // Возвращает массив со статусов и информацией для аналитики

    $result = [ // По умолчанию товар не входит в акцию
      'price' => $item['price'],
      'maxActionPrice' => $maxActionPrice,
      'reason' => "Причина не установлена",
      'isPriceDynamic' => 'N',
      'status' => false,
    ];

    if ( isset( $this->isInAction[ $item['model'] ] ) ){
      $result['reason'] = "Товар вошел в другую акцию по приоритету";
      return $result;
    }

    if ( $item['price'] <= $maxActionPrice ){
      $result['status'] = true;
      $result['reason'] = "Товар прошел по всем условиям {$item['price']} <= {$maxActionPrice} DEFAULT";

      if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;
    }

    if ( $item['price'] > $maxActionPrice ){

      $conditions = $this->checkPriceConditions(
        cost: $item['cost'],
        checkDiscount: true,
        maxDiscount: $discount,
        price1: $maxActionPrice,
        price2: $item['price']
      );

      if ( $conditions['status'] ){
        $result['price'] = $maxActionPrice;
        $result['reason'] = "Товар с установленным MAP прошел по всем условиям: маржинальность - {$conditions['margin']}%, маржа - {$conditions['profit']}руб., скидка - {$conditions['discount']}";
        $result['status'] = true;

        // Нулевое значение сортировки акции не исключает возможность добавления товара в еще одну акцию
        if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;
      }else{
        $result['reason'] = "Товар с установленным MAP не прошел по условию(ям): маржинальность - {$conditions['margin']}%, маржа - {$conditions['profit']}руб., скидка - {$conditions['discount']}";
      }
    }

    return $result;
  }

  private function checkItemWithFixedDiscount( array $item, float $maxActionPrice, int $sort, int $discount = 0 ):array
  {
    // $item - массив с данными о товаре
    // $maxActionPrice - максимальная цена для вхождения в акцию или цена для вхождения для получения заданного процента бустинга
    // $sort - приоритет акции
    // $isInAction - контрольный массив, который будет использоваться для быстрой проверки, куда товар вошел
    // $settings - массив с настройками модуля по которым будут проводиться проверки

    // Возвращает массив со статусов и информацией для аналитики

    $result = [ // По умолчанию товар не входит в акцию
      'price' => $item['price'],
      'maxActionPrice' => $maxActionPrice,
      'reason' => "Товар не прошел ни по основным, ни по альтернативным условиям",
      'isPriceDynamic' => 'N',
      'status' => false,
    ];

    if ( isset( $this->isInAction[ $item['model'] ] ) ){
      $result['reason'] = "Товар вошел в другую акцию по приоритету";
      return $result;
    }

    $conditionsBase = $this->checkPriceConditions(
      cost: $item['cost'],
      checkDiscount: false,
      maxDiscount: $discount,
      price1: $item['price'],
      price2: false
    );

    if ( $conditionsBase['status'] ){ // Товар ПРОШЕЛ проверки на маржу и маржинальность

      if ( $item['price'] > $maxActionPrice ){
        $result['reason'] = "Товар с фиксированной скидкой не прошел по условию {$item['price']} > {$maxActionPrice}";
      }

      if ( $item['price'] <= $maxActionPrice ){
        $result['status'] = true;
        $result['reason'] = "Товар с фиксированной скидкой прошел по всем условия {$item['price']} <= {$maxActionPrice}";
        if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;
      }

    }else{ // Товар с фикс скидкой НЕ ПРОШЕЛ проверки на маржу и маржинальность
      if ( $item['startPrice'] <= $maxActionPrice ){
        $result['status'] = true;
        $result['price'] = $item['startPrice'];
        $result['reason'] = "Товар не прошел. с фикс скидкой, но прошел по РРЦ {$item['startPrice']} <= {$maxActionPrice}";
        if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;

        return $result;
      }
      $conditionsMAP = $this->checkPriceConditions(
        cost: $item['cost'],
        checkDiscount: false,
        maxDiscount: $discount,
        price1: $maxActionPrice,
        price2: false
      );

      if ( $conditionsMAP['status'] ){
        $result['price'] = $maxActionPrice;
        $result['status'] = true;

        $result['reason'] = "Товар с установленным MAP прошел по всем условиям: маржинальность - {$conditionsMAP['margin']}%, маржа - {$conditionsMAP['profit']}руб., скидка - {$conditionsMAP['discount']}";

        // Нулевое значение сортировки акции не исключает возможность добавления товара в еще одну акцию
        if ( $sort != 0 ) $this->isInAction[ $item['model'] ] = 1;
      }else{
        $result['reason'] = "Товар с установленным MAP не прошел по условию(ям): маржинальность - {$conditionsMAP['margin']}%, маржа - {$conditionsMAP['profit']}руб., скидка - {$conditionsMAP['discount']}";
      }
    }

    return $result;
  }

  private function distributeItem( int $productId, array $checkInfo, array &$result ):void
  {
    if ( $checkInfo['status'] ){
      $result['good'][] = [
        'action_price' => $checkInfo['price'],
        'product_id' => $productId
      ];
      return ;
    }

    $result['bad'][] = [
      'product_id' => $productId
    ];
  }

  private function applyPriceDiscount( array $item, array $saleInfo ):array
  {
    $discount = intval($saleInfo['perc'] ?? 0);
    $result = $item;
    $price = $item['price'];

    $result['price'] = $price * ( 1 - $discount / 100 );
    $result['startPrice'] = $price;

    return $result;
  }

  private function checkPriceConditions( float $cost, bool $checkDiscount, float $price1, float|bool $price2 = false, int $maxDiscount = 0 ):array
  {
    $com = intval($this->settings['com']);

    $margin = SalesCalculator::calculateMargin( $price1, $com, $cost );
    $approvedMargin = $margin >= $this->settings['min_profit_perc'];

    $profit = SalesCalculator::calculateProfit( $price1, $com, $cost );
    $approvedProfit = $profit >= $this->settings['min_profit'];

    if ( $checkDiscount ){
      $discount = SalesCalculator::calculateDiscount( $price1, $price2 );
      $approvedDiscount = $discount <= $maxDiscount;
    }else{
      $approvedDiscount = true;
    }

    $status = false;

    if ( $approvedMargin && $approvedProfit && $approvedDiscount ){
      $status = true;
    }

    return [
      'margin' => $margin,
      'profit' => $profit,
      'discount' => $discount,
      'status' => $status,
    ];
  }

  private function enrichWithContext( array $data, array $item, array $saleInfo, array $ozonProduct ):array
  {
    $result = $data;

    $result['saleId'] = $saleInfo['sale_id'];
    $result['saleName'] = $saleInfo['name'];
    $result['model'] = $item['model'];
    $result['startPrice'] = $result['startPrice'] ?? $item['price'];
    $result['cost'] = $item['cost'];
    $result['status'] = $data['status'] ? 'Y' : 'N';
    $result['priceMinElastic'] = $ozonProduct['price_min_elastic'];
    $result['priceMaxElastic'] = $ozonProduct['price_max_elastic'];
    $result['date'] = date('Y-m-d G:i:s');

    return $result;
  }

  public function mergeSourceData( array $itemsActive, array $itemsCandidates ):array
  {
    $result = [];

    foreach ( $itemsActive as $saleId => $products ){
      if ( isset($itemsCandidates[$saleId]) ){
        $result[ $saleId ] =  $itemsCandidates[$saleId] + $products;
        continue;
      }
      $result[ $saleId ] = $products;
    }

    return $result;
  }

  public function filterFailedCandidates( array $checkedItems, array $activeItems ):array
  {
    $result = [];
    foreach ( $checkedItems as $saleId => $categories ){
      $itemsGood = $categories['good'];
      $itemsBad = $categories['bad'];

      foreach ( $itemsBad as $item ){
        if ( empty($activeItems[$saleId][$item['product_id']]) ) continue;
        $result[$saleId]['bad'][] = [
          'product_id' => $item['product_id'],
        ];
      }
      $result[$saleId]['good'] = $itemsGood;
    }

    return $result;
  }

}

 ?>
