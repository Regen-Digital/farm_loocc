<?php

namespace Drupal\farm_loocc;

use Drupal\asset\Entity\AssetInterface;

/**
 * An interface for the LooccEstimate service.
 */
interface LooccEstimateInterface {

  /**
   * Create an estimate for the given Asset and specified project types.
   *
   * @param \Drupal\asset\Entity\AssetInterface $asset
   *   The asset to create the estimate for.
   * @param array $project_types
   *   An array of project types.
   * @param array $project_metadata
   *   Additional metadata for the project, like new_irrigation.
   *
   * @return int|bool
   *   The base estimate ID or FALSE if the estimate could not be created.
   */
  public function createEstimate(AssetInterface $asset, array $project_types, array $project_metadata = []);

  /**
   * Returns the co-benefits for the specified ERF method.
   *
   * @param string $method_id
   *   The ERF method ID.
   *
   * @return array|false
   *   Array of the ERF co-benefits or FALSE if failure.
   */
  public function getErfCobenefits(string $method_id);

  /**
   * Returns an Asset's geometry in the format expected by the LOOC-C API.
   *
   * @param \Drupal\asset\Entity\AssetInterface $asset
   *   The asset interface.
   *
   * @return array|bool
   *   Array of the project area coordinates or FALSE if could not be computed.
   */
  public function getProjectArea(AssetInterface $asset);

  /**
   * Helper function to return ERF method estimates with ERF ratings.
   *
   * @param array $project_area
   *   The project area coordinates.
   *
   * @return array
   *   Array of the available ERF method estimates.
   */
  public function erfEstimates(array $project_area): array;

  /**
   * Helper function to return the total project estimates across all SA2 areas.
   *
   * @param array $project_area
   *   The project area coordinates.
   * @param array $project_types
   *   An array of project types.
   * @param bool $new_irrigation
   *   A boolean indicating if the project will acquire new irrigation methods.
   *
   * @return array
   *   Array of the total ACCU estimates across all SA2 areas for each project.
   */
  public function soilEstimates(array $project_area, array $project_types, bool $new_irrigation = FALSE): array;

  /**
   * Helper function to save an ACCU estimate to the DB.
   *
   * @param int $estimate_id
   *   The estimate ID.
   * @param string $method_id
   *   The method ID.
   * @param array $estimate_values
   *   The estimate values.
   */
  public function updateAccuEstimate(int $estimate_id, string $method_id, array $estimate_values);

}
