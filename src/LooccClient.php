<?php

namespace Drupal\farm_loocc;

use GuzzleHttp\Client;

/**
 * Extends the Guzzle HTTP client with helper methods for the LOOC-C API.
 *
 * See https://api.looc-c.farm/v1/index.html.
 */
class LooccClient extends Client implements LooccClientInterface {

  /**
   * The base URI of the LOOC-C API.
   *
   * @var string
   */
  public static string $looccApiBaseUri = 'https://api.looc-c.farm/v1/';

  /**
   * LooccClient constructor.
   *
   * @param string $api_key
   *   The LOOC-C API key.
   * @param array $config
   *   Guzzle client config.
   */
  public function __construct(string $api_key, array $config = []) {
    $default_config = [
      'base_uri' => self::$looccApiBaseUri,
      'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $api_key,
      ],
      'http_errors' => FALSE,
    ];
    $config = $default_config + $config;
    parent::__construct($config);
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    $res = $this->request('GET', 'lrf/ping');
    return $res->getStatusCode() === 200;
  }

}
