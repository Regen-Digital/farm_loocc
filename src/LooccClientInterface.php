<?php

namespace Drupal\farm_loocc;

use GuzzleHttp\ClientInterface;

/**
 * Interface for the LOOC-C client.
 */
interface LooccClientInterface extends ClientInterface {

  /**
   * Helper function to ping the LOOC-C server.
   *
   * @return bool
   *   Boolean indicating success.
   */
  public function ping(): bool;

}
