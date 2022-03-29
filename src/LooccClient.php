<?php

namespace Drupal\farm_loocc;

use Drupal\Component\Serialization\Json;
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
  public function carbonEstimate(array $project_area): array {
    $res = $this->request('POST', 'soil/direct', ['json' => $project_area]);
    return Json::decode($res->getBody());
  }

  /**
   * {@inheritdoc}
   */
  public function erfEstimates(array $project_area, int $project_length = 25): array {
    $post = [
      'projectArea' => $project_area,
      'projectLength' => $project_length,
    ];
    $res = $this->request('POST', 'veg/all', ['json' => $post]);
    return Json::decode($res->getBody());
  }

  /**
   * {@inheritdoc}
   */
  public function getSa2(array $project_area): array {
    $res = $this->request('POST', 'soil/sa2', ['json' => $project_area]);
    $json = Json::decode($res->getBody());
    return $json['areas'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    $res = $this->request('GET', 'lrf/ping');
    return $res->getStatusCode() === 200;
  }

  /**
   * {@inheritdoc}
   */
  public function soilEstimate(int $sa2, int $land_area, array $project_types, bool $new_irrigation = TRUE): array {
    $post = [
      'sa2' => $sa2,
      'landArea' => $land_area,
      'projectTypes' => $project_types,
      'newIrrigation' => $new_irrigation,
    ];
    $res = $this->request('POST', 'soil/estimate', ['json' => $post]);
    return Json::decode($res->getBody());
  }

}
