<?php
class ApiManager extends ApiBase
{

  public function importPrices( string $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('importPrices'),
      body: $data
    );
  }

  public function importStocks( array $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('importStocks'),
      body: json_encode($data)
    );
  }

  public function importProducts( array $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('importProducts'),
      body: json_encode($data)
    );
  }

  public function getProductList( array $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('getProductList'),
      body: json_encode($data)
    );
  }

  public function getStockOnWarehouses( array $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('getStockOnWarehouses'),
      body: json_encode($data)
    );
  }

  public function getAnalyticsStock( array $data ):Response
  {
    return $this->request(
      headers: $this->getHeaders(),
      url: ConfigProvider::getApiMethod('getAnalyticsStock'),
      body: json_encode($data)
    );
  }
}
?>
