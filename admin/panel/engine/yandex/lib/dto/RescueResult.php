<?php
class RescueResult
{
  private function __construct(
      private bool $status,
      private mixed $data,
      private ?string $errorContext = null,
      private ?string $errorMessage = null,
      private ?int $errorLine = null
  ) {}

  public static function success( mixed $data ):self
  {
      return new self(true, $data);
  }

  public static function error( string $context, Throwable $e ):self
  {
      return new self( false, null, $context, $e->getMessage(), $e->getLine() );
  }

  public function isSuccess():bool
  {
      return $this->status;
  }

  public function isFailure():bool
  {
      return !$this->status;
  }

  public function getData():mixed
  {
    return $this->data;
  }

  public function getErrorContext():?string
  {
    return $this->errorContext;
  }

  public function getErrorMessage():?string
  {
    return $this->errorMessage;
  }

  public function getErrorLine():?int
  {
    return $this->errorLine;
  }
}
 ?>
