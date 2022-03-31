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
   * @return array|false
   *   Array of the soil estimates or FALSE if failure.
   */
  public function carbonEstimate(array $project_area);

  /**
   * Returns ACCU estimates for available ERF methods in a given project area.
   *
   * @param array $project_area
   *   The project area coordinates.
   * @param int $project_length
   *   The project length in years, defaults to 25.
   *
   * @return array|false
   *   ACCU estimates for all available ERF methods or FALSE if failure.
   */
  public function erfEstimates(array $project_area, int $project_length = 25);

  /**
   * Returns if the project area is in Queensland.
   *
   * @param array $project_area
   *   The project area coordinates.
   *
   * @return bool
   *   Boolean indicating if the project area is in Queensland.
   */
  public function inQld(array $project_area): bool;

  /**
   * Returns the LRF co-benefit alignment ratings for LRF methods.
   *
   * @param array $project_area
   *   The project area coordinates.
   * @param string $method_id
   *   The method ID.
   *
   * @return array|false
   *   Array of the co-benefit alignment ratings or FALSE if not supported.
   */
  public function lrfRating(array $project_area, string $method_id);

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
   * Returns the SA2 areas the given project area is located in.
   *
   * @param array $project_area
   *   The project area coordinates.
   *
   * @return array|false
   *   Array of SA2 areas containing the SA2 ID and area or FALSE if failure.
   */
  public function getSa2(array $project_area);

  /**
   * Helper function to ping the LOOC-C server.
   *
   * @return bool
   *   Boolean indicating success.
   */
  public function ping(): bool;

  /**
   * Returns the measured soil organic carbon change estimate.
   *
   * @param float $land_area
   *   The total project area.
   * @param float $current_soc
   *   The current soil organic carbon %.
   * @param float $target_soc
   *   The target soil organic carbon %.
   * @param float $bulk_density
   *   The average bulk density.
   * @param int $depth
   *   The measurement depth in cm, defaults to 30.
   * @param int $project_length
   *   The project length in years, defaults to 25.
   *
   * @return array|false
   *   Array of the measured soc estimate values.
   */
  public function socEstimate(float $land_area, float $current_soc, float $target_soc, float $bulk_density, int $depth = 30, int $project_length = 25);

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
   * @return array|false
   *   Array of the ACCU estimates for the project types in the SA2 area.
   */
  public function soilEstimate(int $sa2, int $land_area, array $project_types, bool $new_irrigation = TRUE);

}
