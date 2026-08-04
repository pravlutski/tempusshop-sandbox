<?php
class Response
{
  public function __construct(
    private ?ResponseData $data = null,
    private array $headers = [],
    private string $method = '',
    private string $curlError = '',
    private int $httpCode,
    private int $executionTime
  )
  {}

  public function getData():ResponseData
  {
    return $this->data;
  }

  public function getHeaders():array
  {
    return $this->headers;
  }

  public function getMethod():string
  {
    return $this->method;
  }

  public function getExecutionTime():int
  {
    return $this->executionTime;
  }

  public function getHttpCode():int
  {
    return $this->httpCode;
  }

  public function getCurlError():string
  {
    return $this->curlError;
  }
}
 ?>
