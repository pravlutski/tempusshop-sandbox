<?php
class DocumentProcessor
{
  public function __construct(
    private ?ApiManager $api = null,
    private ?DataProvider $data = null,
    private string $cabinet,
    private bool $isTestFlag = true,
  ){}

  private function buildBaseTemplate( array $dict, array $profile ):array
  {
    $template = [];

    foreach ( $profile as $key => $value ){

      $meta = [
        'meta' => [
          'href' => "https://api.moysklad.ru/api/remap/1.2/entity/{$dict[$key]}/{$value}",
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/{$dict[$key]}/metadata",
          "type" => $dict[$key],
          "mediaType" => "application/json"
        ]
      ];

      $template[ $key ] = in_array( $key, ConfigProvider::getOnlyValueFields() ) ? $value : $meta;
    }

    return $template;
  }

  public function buildProductsTemplate( array $dict, array $products ):array
  {
    if ( empty($products) ) throw new Exception("Cannot build products template: empty array");
    $result = [];
    $rate = $this->data->getCurrencyRate( $this->cabinet );
    $counter  = 0;
    foreach  ( $products as $item ){
      $productId = $dict[ $this->cabinet ][ $item['product_id'] ] ?? false;
      if ( !$productId ) {
        continue;
      }

      if ( isset($result[ $item['product_id'] ]) ){
        $result[ $item['product_id'] ]['quantity'] += 1;
        continue;
      }
      $counter++;
      $price = round( $item["price"] / $rate, 2 );

      $assortment = [
        "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$productId}",
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata",
          "type" => "product",
          "mediaType" => "application/json"
        ],
      ];

      $result[ $item['product_id'] ] = [
        "quantity" => 1,
        "price" => $price * 100,
        "assortment" => $assortment
      ];
    }

    return array_values($result);
  }

  public function create( array $products, array $productsDict, array $metaDict, array $profile, string $document ):void
  {
    $template = $this->buildBaseTemplate(
      dict: $metaDict,
      profile: $profile
    );

    $productsTemplate = $this->buildProductsTemplate(
      dict: $productsDict,
      products: $products
    );

    $template['positions'] = $productsTemplate;

    if ( $this->isTestFlag ){
      var_dump( $template );
    }

    $response = $this->api->request(
      url: "/entity/{$document}",
      method: "POST",
      headers: $this->api->getHeaders(),
      data: $template
    );

    var_dump($response);

    if ( empty($response['id']) ) throw new Exception("Не удалось создать документ");
  }
}
 ?>
