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
   * A very useful ERF to Project type mapping.
   *
   * @var array|string[]
   */
  public static array $erfToProjectMapping = [
    'avoidedclearing' => 'acnv',
    'envplantings' => 'emp',
    'new-irrigation-pasture-renovation' => 'NewIrrigationPastureRenovation',
    'acid-new-irrigation' => 'AcidityManagementNewIrrigation',
    'acid-pasture-renovation' => 'AcidityManagementPasturerenovation',
    'nutrient-acid' => 'NutrientManagementAcidityManagement',
    'nutrient-irrigation' => 'NutrientManagementNewIrrigation',
    'nutrient-pasture' => 'NutrientManagementPastureRenovation',
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
  public function inQld(array $project_area): bool {
    $post = [
      'projectArea' => $project_area,
    ];
    $res = $this->request('POST', 'lrf/in-qld', ['json' => $post]);
    return (bool) $this->parseJsonFromResponse($res, 'completelyInsideQld');
  }

  /**
   * {@inheritdoc}
   */
  public function lrfRating(array $project_area, string $method_id) {
    $path_mapping = [
      'soc-measure' => 'soil',
      'hir' => 'revegetation',
      'acnv' => 'avoided-deforestation',
      'emp' => 'environmental-plantings',
      // @todo Need to confirm the mapping for this method.
      'nfmr' => 'plantation',
    ];

    // Bail if the method ID is not supported.
    if (empty($path_mapping[$method_id])) {
      return FALSE;
    }

    $endpoint = "lrf/$path_mapping[$method_id]";
    $post = [
      'projectArea' => $project_area,
    ];
    $res = $this->request('POST', $endpoint, ['json' => $post]);
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
  public function socEstimate(float $land_area, float $current_soc, float $target_soc, float $bulk_density, int $depth = 30, int $project_length = 25) {
    $post = [
      'landArea' => $land_area,
      'currentSOC' => $current_soc,
      'targetSOC' => $target_soc,
      'bulkDensity' => $bulk_density,
      'depthcm' => $depth,
      'projectLength' => $project_length,
    ];
    $res = $this->request('POST', 'soil/measured-soc-change', ['json' => $post]);
    return $this->parseJsonFromResponse($res);
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
