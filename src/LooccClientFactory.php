<?php

namespace Drupal\farm_loocc;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Factory class to get authenticated instance of the LooccClient.
 */
class LooccClientFactory {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor for the LooccClientFactory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Returns a LooccClient authenticated with the configured api_key.
   *
   * @return \Drupal\farm_loocc\LooccClientInterface
   *   The LooccClient.
   */
  public function getAuthenticatedClient() {
    $config = $this->configFactory->get('farm_loocc.settings');
    $api_key = $config->get('api_key');
    return new LooccClient($api_key);
  }

}
