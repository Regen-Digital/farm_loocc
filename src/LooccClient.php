<?php

namespace Drupal\farm_loocc;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

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
   * Valid project type ids.
   *
   * @var string[]
   */
  public static array $projectTypes = [
    'NutrientManagementAcidityManagement',
    'NutrientManagementNewIrrigation',
    'NutrientManagementPastureRenovation',
    'AcidityManagementNewIrrigation',
    'AcidityManagementPasturerenovation',
    'NewIrrigationPastureRenovation',
    'StubbleRetention',
    'ConversionToPasture',
  ];

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
  public function carbonEstimate(array $project_area) {
    $res = $this->request('POST', 'soil/direct', ['json' => $project_area]);
    return $this->parseJsonFromResponse($res);
  }

  /**
   * {@inheritdoc}
   */
  public function erfEstimates(array $project_area, int $project_length = 25) {
    $post = [
      'projectArea' => $project_area,
      'projectLength' => $project_length,
    ];
    $res = $this->request('POST', 'veg/all', ['json' => $post]);
    return $this->parseJsonFromResponse($res);
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
  public function soilEstimate(int $sa2, int $land_area, array $project_types, bool $new_irrigation = TRUE) {
    $post = [
      'sa2' => $sa2,
      'landArea' => $land_area,
      'projectTypes' => $project_types,
      'newIrrigation' => $new_irrigation,
    ];
    $res = $this->request('POST', 'soil/estimate', ['json' => $post]);
    return $this->parseJsonFromResponse($res);
  }

  /**
   * Helper function to parse JSON from API responses.
   *
   * This function also serves some simple error handling.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   *
   * @return array|false
   *   Return the array or FALSE if an error ocurred.
   */
  protected function parseJsonFromResponse(ResponseInterface $response) {
    $json = Json::decode($response->getBody());
    if (isset($json['status'])) {
      return FALSE;
    }
    else {
      return $json;
    }
  }

}
