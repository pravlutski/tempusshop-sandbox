<?php
class ResponseData
{
  public function __construct( private string $data = '' ){}

    public function decode():array
    {
      if ( empty($this->data) ) return [];

      $data = json_decode($this->data, true);

      if ( $data === false ){
        throw new InvalidResponseException("Cannot decode json string: Invalid json structure");
      }
      return $data;
    }

    public function raw():string
    {
      if ( empty($this->data) ) return '';
      return $this->data;
    }
}
 ?>
