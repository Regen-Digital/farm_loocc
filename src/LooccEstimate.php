<?php

namespace Drupal\farm_loocc;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * A service for interacting with LOOC-C estimates.
 */
class LooccEstimate implements LooccEstimateInterface {

  /**
   * The cache ID for the ERF cobenefits.
   *
   * @var string
   */
  public static string $erfCobenefitCacheId = 'farm_loocc_erf_cobenefits';

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The geoPHP service.
   *
   * @var \Drupal\geoField\GeoPHP\GeoPHPInterface
   */
  protected $geoPHP;

  /**
   * The looc-c client service.
   *
   * @var \Drupal\farm_loocc\LooccClientInterface
   */
  protected $looccClient;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * THe cached ERF cobenefits.
   *
   * @var array
   */
  protected $erfCobenefits;

  /**
   * Constructs the LooccEstimate class.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geo_PHP
   *   The geoPHP service.
   * @param \Drupal\farm_loocc\LooccClientInterface $loocc_client
   *   The loocc client service.
   */
  public function __construct(Connection $connection, CacheBackendInterface $cache, TimeInterface $time, GeoPHPInterface $geo_PHP, LooccClientInterface $loocc_client) {
    $this->database = $connection;
    $this->cache = $cache;
    $this->time = $time;
    $this->geoPHP = $geo_PHP;
    $this->looccClient = $loocc_client;

    // Get the ERF Cobenefits.
    $this->cacheErfCobenfits();
  }

  /**
   * {@inheritdoc}
   */
  public function createEstimate(AssetInterface $asset, array $project_types, array $project_metadata = []) {

    // Get the project area.
    $project_area = $this->getProjectArea($asset);
    if (!$project_area) {
      return FALSE;
    }

    // Add default project metatdata values.
    $project_metadata += [
      'carbon_improvement' => 0,
      'new_irrigation' => FALSE,
    ];

    // ERF Estimates.
    $erf_estimates = $this->erfEstimates($project_area);

    // Soil estimates.
    $soil_estimates = $this->soilEstimates($project_area, $project_types, $project_metadata['new_irrigation']);

    // Carbon estimate.
    $carbon_estimates = $this->looccClient->carbonEstimate($project_area);

    // Bail if any of the requests failed.
    if (empty($erf_estimates) || empty($soil_estimates) || empty($carbon_estimates)) {
      return FALSE;
    }

    // Soil organic carbon estimate.
    $project_total_area = $carbon_estimates['polygonArea'];
    $bulk_density_estimate = round($carbon_estimates['polygonBDAverage'], 2);
    $carbon_estimate = round($carbon_estimates['polygonOCPercAverage'], 1);
    $carbon_improvement = round($project_metadata['carbon_improvement'], 1);
    $carbon_target = $carbon_estimate + $carbon_improvement;
    if ($soc_estimate = $this->looccClient->socEstimate($project_total_area, $carbon_estimate, $carbon_target, $bulk_density_estimate)) {
      $soil_estimates['soc-measure'] = [
        'annual' => $soc_estimate['totalCO2ePolyYr'],
        'project' => $soc_estimate['totalCO2ePolyProject'],
      ];

      // Get the method's LRF rating.
      $project_in_queensland = $this->looccClient->inQld($project_area);
      if ($project_in_queensland && $rating = $this->looccClient->lrfRating($project_area, 'soc-measure')) {
        $mapping = [
          'greatBarrierReef'     => 'great_barrier_reef',
          'coastalEcosystems'    => 'coastal_ecosystems',
          'wetlands'             => 'wetlands',
          'threatenedEcosystems' => 'threatened_ecosystems',
          'threatenedWildlife'   => 'threatened_wildlife',
          'nativeVegetation'     => 'native_vegetation',
          'summary'              => 'summary',
        ];
        foreach ($rating as $rating_id => $rating_value) {
          if (!empty($mapping[$rating_id])) {
            $soil_estimates['soc-measure'][$mapping[$rating_id]] = $rating_value;
          }
        }
      }

    }

    // Add the base estimate to the DB.
    $warning_message = implode(PHP_EOL, $carbon_estimates['warningMessages'] ?? []);
    $row = [
      'asset_id' => $asset->id(),
      'timestamp' => $this->time->getCurrentTime(),
      'project_length' => 25,
      'new_irrigation' => $project_metadata['new_irrigation'],
      'polygon_area' => $project_total_area,
      'bd_average' => $bulk_density_estimate,
      'carbon_average' => $carbon_estimate,
      'carbon_target' => $carbon_target,
      'warning_message' => $warning_message,
    ];

    // Create the base estimate.
    // Use merge to insert or update an existing base estimate.
    $this->database->merge('farm_loocc_estimate')
      ->key('asset_id', $asset->id())
      ->fields($row)
      ->execute();

    // Get the estimate_id.
    $estimate_id = $this->database->select('farm_loocc_estimate', 'fle')
      ->fields('fle', ['id'])
      ->condition('fle.asset_id', $asset->id())
      ->execute()
      ->fetchField();

    // Build an array of accu estimates to relate with the base estimate.
    $final_accu_estimates = $erf_estimates + $soil_estimates;

    // Bail if there are no accu_estimates to insert.
    if (empty($final_accu_estimates)) {
      return $estimate_id;
    }

    // Save each estimate in the database.
    foreach ($final_accu_estimates as $method_id => $estimate_values) {
      $this->updateAccuEstimate($estimate_id, $method_id, $estimate_values);
    }

    return $estimate_id;
  }

  /**
   * {@inheritDoc}
   */
  public function getErfCobenefits(string $method_id) {
    $mapping = [
      'acnv' => 'avoidedclearing',
      'emp' => 'envplantings',
    ];
    $method_id = $mapping[$method_id] ?? $method_id;
    return $this->erfCobenefits[$method_id] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectArea(AssetInterface $asset) {

    // Bail if the geometry is empty.
    if ($asset->get('geometry')->isEmpty()) {
      return FALSE;
    }

    /** @var \Polygon $geom */
    $geom = $asset->get('geometry')->value;
    $geom = $this->geoPHP->load($geom);

    // Bail if the geometry was not loaded.
    if (empty($geom)) {
      return FALSE;
    }

    // Map points to the lat/lng that the LOOC-C API expects.
    $lat_longs = array_map(function (\Point $point) {
      return ['lat' => $point->y(), 'lng' => $point->x()];
    }, $geom->getPoints());

    // Bail if the geometry had no points.
    if (empty($lat_longs)) {
      return FALSE;
    }

    return $lat_longs;
  }

  /**
   * {@inheritdoc}
   */
  public function erfEstimates(array $project_area): array {

    // Check if the project is in Queensland.
    $project_in_queensland = $this->looccClient->inQld($project_area);

    // Start with the erf estimates from veg/all.
    $final_estimates = [];
    $erf_estimates = $this->looccClient->erfEstimates($project_area);
    foreach ($erf_estimates as $key => $value) {

      // Add project warnings to the associated method_id.
      if ($key === 'projectWarnings') {
        foreach ($value as $warning_info) {
          $method_id = $warning_info['method'];
          $warning_message = implode(PHP_EOL, $warning_info['warnings']);
          $final_estimates[$method_id]['warning_message'] = $warning_message;
        }
        continue;
      }

      // Else extract the method id and estimate interval from the key.
      // This should be of the format: "acnvAnnual".
      $matches = [];
      preg_match('/(.*)(Annual|Project)/', $key, $matches);

      // Bail if not a valid accu estimate key.
      if (count($matches) != 3) {
        continue;
      }

      // Save the interval with the method id.
      $method_id = strtolower($matches[1]);
      $interval = strtolower($matches[2]);
      $final_estimates[$method_id][$interval] = $value;
    }

    // Add LRF ratings for each ERF method.
    if ($project_in_queensland) {
      foreach (array_keys($final_estimates) as $method_id) {

        // Get the method's LRF rating.
        if ($rating = $this->looccClient->lrfRating($project_area, $method_id)) {
          $mapping = [
            'greatBarrierReef'     => 'great_barrier_reef',
            'coastalEcosystems'    => 'coastal_ecosystems',
            'wetlands'             => 'wetlands',
            'threatenedEcosystems' => 'threatened_ecosystems',
            'threatenedWildlife'   => 'threatened_wildlife',
            'nativeVegetation'     => 'native_vegetation',
            'summary'              => 'summary',
          ];
          foreach ($rating as $rating_id => $rating_value) {
            if (!empty($mapping[$rating_id])) {
              $final_estimates[$method_id][$mapping[$rating_id]] = $rating_value;
            }
          }
        }
      }
    }

    return $final_estimates;
  }

  /**
   * {@inheritdoc}
   */
  public function soilEstimates(array $project_area, array $project_types, bool $new_irrigation = FALSE): array {

    // Get all sa2 areas.
    $sa2_areas = $this->looccClient->getSa2($project_area);

    // Get the estimates for each area.
    $estimates = [];
    foreach ($sa2_areas as $area) {
      $area_id = $area['sa2'];
      $estimate = $this->looccClient->soilEstimate($area_id, $area['area'], $project_types, $new_irrigation);

      // Save the annual and project ACCU values.
      $area_estimates = [];
      foreach ($estimate['estimates'] as $project_estimate) {
        $area_estimates[$project_estimate['projectType']] = [
          'annual' => $project_estimate['annualSequestrationRate'],
          'project' => $project_estimate['totalSequesteredOverProject'],
        ];
      }
      $estimates = array_merge_recursive($estimates, $area_estimates);
    }

    // Return the total annual and project total values for each project.
    return array_map(function ($project_estimates) {

      // Iterate on the annual and total values. If there were multiple SA2
      // values this will be an array, so return array_sum.
      return array_map(function ($estimate_values) {
        $total = $estimate_values;
        if (is_array($estimate_values)) {
          $total = array_sum($estimate_values);
        }
        return round($total, 1);
      }, $project_estimates);
    }, $estimates);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAccuEstimate(int $estimate_id, string $method_id, array $estimate_values) {

    // Ensure both an annual and project value are provided.
    if (isset($estimate_values['annual']) && isset($estimate_values['project'])) {
      $estimate_values += [
        'estimate_id' => $estimate_id,
        'method_id' => $method_id,
        'warning_message' => NULL,
      ];

      // Round ACCU values.
      $estimate_values['annual'] = round($estimate_values['annual']);
      $estimate_values['project'] = round($estimate_values['project']);

      // Use merge to insert or update existing estimates.
      $this->database->merge('farm_loocc_accu_estimate')
        ->keys(['estimate_id' => $estimate_id, 'method_id' => $method_id])
        ->fields($estimate_values)
        ->execute();
    }
  }

  /**
   * Helper function to cache all ERF cobenefit values.
   */
  protected function cacheErfCobenfits() {
    // First get the value from the cache.
    if ($data = $this->cache->get(LooccEstimate::$erfCobenefitCacheId)) {
      $this->erfCobenefits = (array) $data->data;
      return;
    }

    // Else request all values.
    $all_values = [];
    foreach (LooccClient::$erfMethods as $erf_method) {
      if ($cobenefits = $this->looccClient->getErfCobenefits($erf_method)) {
        $all_values[$erf_method] = $cobenefits;
      }
    }
    $this->erfCobenefits = $all_values;
    $this->cache->set(LooccEstimate::$erfCobenefitCacheId, $all_values, $this->time->getCurrentTime() + (86400 * 7));
  }

}
