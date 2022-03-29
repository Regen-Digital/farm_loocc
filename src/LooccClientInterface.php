<?php

namespace Drupal\farm_loocc;

use GuzzleHttp\ClientInterface;

/**
 * Interface for the LOOC-C client.
 */
interface LooccClientInterface extends ClientInterface {

  /**
   * Returns the direct soil estimates in a given project area.
   *
   * Note that this API request can take 15-30 seconds per project area.
   *
   * @param array $project_area
   *   The project area coordinates.
   *
   * @return array
   *   Array of the bulk density and organic carbon estimates.
   */
  public function carbonEstimate(array $project_area): array;

  /**
   * Returns ACCU estimates for available ERF methods in a given project area.
   *
   * @param array $project_area
   *   The project area coordinates.
   * @param int $project_length
   *   The project length in years, defaults to 25.
   *
   * @return array
   *   ACCU estimates for all available ERF methods.
   */
  public function erfEstimates(array $project_area, int $project_length = 25): array;

  /**
   * Returns the SA2 areas the given project area is located in.
   *
   * @param array $project_area
   *   The project area coordinates.
   *
   * @return array
   *   Array of SA2 areas each containing the SA2 ID and area.
   */
  public function getSa2(array $project_area): array;

  /**
   * Helper function to ping the LOOC-C server.
   *
   * @return bool
   *   Boolean indicating success.
   */
  public function ping(): bool;

  /**
   * Returns the soil estimate for projects at the given SA2 area.
   *
   * @param int $sa2
   *   The SA2 area ID.
   * @param int $land_area
   *   The project area within the SA2 area.
   * @param array $project_types
   *   Array of project types.
   * @param bool $new_irrigation
   *   A boolean indicating if the project will acquire new irrigation methods.
   *
   * @return array
   *   Array of the ACCU estimates for the project types in the SA2 area.
   */
  public function soilEstimate(int $sa2, int $land_area, array $project_types, bool $new_irrigation = TRUE): array;

}
