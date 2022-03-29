<?php

namespace Drupal\farm_loocc;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * A service for interacting with LOOC-C estimates.
 */
class LooccEstimate implements LooccEstimateInterface {

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
   * Constructs the LooccEstimate class.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geo_PHP
   *   The geoPHP service.
   * @param \Drupal\farm_loocc\LooccClientInterface $loocc_client
   *   The loocc client service.
   */
  public function __construct(Connection $connection, TimeInterface $time, GeoPHPInterface $geo_PHP, LooccClientInterface $loocc_client) {
    $this->database = $connection;
    $this->time = $time;
    $this->geoPHP = $geo_PHP;
    $this->looccClient = $loocc_client;
  }

  /**
   * {@inheritdoc}
   */
  public function createEstimate(AssetInterface $asset, array $project_types) {

    // Get the project area.
    $project_area = $this->getProjectArea($asset);
    if (!$project_area) {
      return FALSE;
    }

    // ERF Estimates.
    $erf_estimates = $this->looccClient->erfEstimates($project_area);

    // Soil estimates.
    $soil_estimates = $this->soilEstimates($project_area, $project_types, TRUE);

    // Carbon estimate.
    $carbon_estimates = $this->looccClient->carbonEstimate($project_area);

    // Bail if any of the requests failed.
    if (empty($erf_estimates) || empty($soil_estimates) || empty($carbon_estimates)) {
      return FALSE;
    }

    // Add the base estimate to the DB.
    $warning_message = implode(PHP_EOL, $carbon_estimates['warningMessages'] ?? []);
    $row = [
      'asset_id' => $asset->id(),
      'timestamp' => $this->time->getCurrentTime(),
      'project_length' => 25,
      'polygon_area' => $carbon_estimates['polygonArea'],
      'bd_average' => $carbon_estimates['polygonBDAverage'],
      'carbon_average' => $carbon_estimates['polygonOCPercAverage'],
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
    $final_accu_estimates = [];

    // Start with the erf estimates from veg/all.
    foreach ($erf_estimates as $key => $value) {

      // Add project warnings to the associated method_id.
      if ($key === 'projectWarnings') {
        foreach ($value as $warning_info) {
          $method_id = $warning_info['method'];
          $warning_message = implode(PHP_EOL, $warning_info['warnings']);
          $final_accu_estimates[$method_id]['warning_message'] = $warning_message;
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
      $final_accu_estimates[$method_id][$interval] = $value;
    }

    // Include soil estimates.
    $final_accu_estimates += $soil_estimates;

    // Bail if there are no accu_estimates to insert.
    if (empty($final_accu_estimates)) {
      return $estimate_id;
    }

    // Save each estimate in the database.
    foreach ($final_accu_estimates as $method_id => $estimate_values) {

      // Ensure both an annual and project value are provided.
      if (isset($estimate_values['annual']) && isset($estimate_values['project'])) {
        $estimate_values += [
          'estimate_id' => $estimate_id,
          'method_id' => $method_id,
          'warning_message' => NULL,
        ];

        // Use merge to insert or update existing estimates.
        $this->database->merge('farm_loocc_accu_estimate')
          ->keys(['estimate_id' => $estimate_id, 'method_id' => $method_id])
          ->fields($estimate_values)
          ->execute();
      }
    }

    return $estimate_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectArea(AssetInterface $asset): array {

    /** @var \Polygon $geom */
    $geom = $asset->get('geometry')->value;
    $geom = $this->geoPHP->load($geom);

    // Map points to the lat/lng that the LOOC-C API expects.
    $lat_longs = array_map(function (\Point $point) {
      return ['lat' => $point->y(), 'lng' => $point->x()];
    }, $geom->getPoints());
    return $lat_longs;
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

}
