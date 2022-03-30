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
   * Valid ERF method IDS.
   *
   * @var string[]
   */
  public static array $erfMethods = [
    'avoidedclearing',
    'acid-new-irrigation',
    'acid-pasture-renovation',
    'beefherd',
    'envplantings',
    'hir',
    'new-irrigation-pasture-renovation',
    'nfmr',
    'nutrient-acid',
    'nutrient-irrigation',
    'nutrient-pasture',
    'soc-measure',
  ];

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
  public function getErfCobenefits(string $method_id) {
    $post = ['method' => $method_id];
    $res = $this->request('POST', 'erf-cobenefits/erf-method', ['json' => $post]);
    return $this->parseJsonFromResponse($res, 'body');
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
   * @param string|null $body_key
   *   The key containing the body. Defaults to NULL.
   *
   * @return array|false
   *   Return the array or FALSE if an error ocurred.
   */
  protected function parseJsonFromResponse(ResponseInterface $response, string $body_key = NULL) {
    $json = Json::decode($response->getBody());
    if (isset($json['status'])) {
      return FALSE;
    }
    elseif (!empty($body_key) && !isset($json[$body_key])) {
      return FALSE;
    }
    else {
      return $body_key ? $json[$body_key] : $json;
    }
  }

}
