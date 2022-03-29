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
   *
   * @return int|bool
   *   The base estimate ID or FALSE if the estimate could not be created.
   */
  public function createEstimate(AssetInterface $asset, array $project_types);

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

}
