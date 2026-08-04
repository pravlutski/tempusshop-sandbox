<?php
class PageFetcherBase
{
  public function __construct(
    protected ?ApiManager $api,
    protected ?ConfigProviderInterface $config
  ){}

  public function paginate( int $limit, callable $requestFn, callable $responseFn ):array
  {
    $query = [
      'limit' => $limit,
      'pageToken' => '',
    ];

    $flag = true;
    $result = [];

    while( $flag ){
      $response = $requestFn( $query );

      if ( $response->getHttpCode() != $this->config->getSuccessHttpCode() ){
        $this->displayAndMarkError( $response->getData()->decode() ?? [] );
        throw new Exception("Something went wrong with request");
      }
      $resultData = $response->getData()->decode();
      $data = $this->normalizeResponse( $resultData );

      if ( empty($data['paging']['nextPageToken']) ) $flag = false;

      $result = $result + $responseFn( $data );
      $query['pageToken'] = $data['paging']['nextPageToken'];
    }

    return $result;
  }

  private function normalizeResponse( array $data ):array
  {
    if ( !empty($data['result']) && is_array($data['result']) ){
      return $data['result'];
    }

    return $data;
  }

  private function displayAndMarkError( array $data ):void
  {
    CommunicationService::log("Fetcher fatal error!");
    CommunicationService::log( $data );
    CommunicationService::log("-----------------------------");

    CommunicationService::updateStatus( text: "Ошибка при получении данных", percent: 100, status: "ABORTED" );
  }
}
 ?>
