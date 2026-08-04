<?php
class ApiManager extends ApiManagerBase
{
  public function getOfferCardsContentStatus( array $query = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", query: $query );
  }

  public function getOfferMappings( array $data = [], array $query = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", query: $query );
  }

  public function getCampaigns( array $query = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "GET", query: $query );
  }

  public function getStocks( array $data = [], array $query = [], int $campaignId ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", id: $campaignId, query: $query );
  }

  public function updateStocks( array $data = [], int $campaignId ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "PUT", id: $campaignId, data: $data );
  }

  public function getPromos():Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST" );
  }

  public function getPromoOffers( array $data = [], array $query = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data, query: $query );
  }

  public function deletePromoOffers( array $data = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data );
  }

  public function updatePromoOffers( array $data = [] ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data );
  }

  public function updatePrices( array $data, int $campaignId ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", id: $campaignId, data: $data );
  }

  public function updateBusinessPrices( array $data ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data );
  }

  public function getBusinessOrders( array $data, array $query ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data, query: $query );
  }

  public function generateGoodsPricesReport( array $data, array $query ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data, query: $query );
  }

  public function generateUnitedOrdersReport( array $data, array $query ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "POST", data: $data, query: $query );
  }

  public function getReportInfo( string $reportId ):Response
  {
    return $this->callApi( method: __FUNCTION__, http: "GET", id: $reportId );
  }
}
 ?>
