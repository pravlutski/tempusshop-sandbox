<?php
class RescueService
{
  public static function rescue( callable $action, string $context ):RescueResult
  {
    try{
      return RescueResult::success( $action() );
    }catch( Throwable $e ){
      self::log( $context, $e );
      return RescueResult::error( $context, $e );
    }
  }

  private static function log( string $context, Throwable $e ):void
  {
    CommunicationService::log( $context );
    CommunicationService::log( "Message: " . $e->getMessage() );
    CommunicationService::log( "Trace: " . $e->getTraceAsString() );
    CommunicationService::log( "Line: " . $e->getLine() );
  }
}
 ?>
