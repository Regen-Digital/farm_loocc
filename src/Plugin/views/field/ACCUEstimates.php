<?php

namespace Drupal\farm_loocc\Plugin\views\field;

use Drupal\Component\Serialization\Json;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Custom views field that renders a list of ACCU estimates.
 *
 * @ViewsField("farm_loocc_accu_estimates")
 */
class ACCUEstimates extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->accuEstimates = $this->getAccuEstimates();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Overwrite the query method to do nothing.
  }

  /**
   * {@inheritdoc}
   */
  protected function allowAdvancedRender() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {

    // Get estimates for the row.
    $estimate_id = $values->farm_loocc_estimate_id;
    $estimates = $this->accuEstimates[$estimate_id] ?? [];

    // Get options.
    $method_ids = array_column($estimates, 'method_id');
    $method_names = array_column($estimates, 'method_name');

    $select = [
      '#type' => 'select',
      '#title' => NULL,
      '#options' => array_combine($method_ids, $method_names),
      '#attributes' => [
        'data-method-estimates' => Json::encode($estimates),
      ],
      '#attached' => [
        'library' => ['farm_loocc/estimate_table'],
      ],
    ];
    return \Drupal::service('renderer')->render($select);
  }

  /**
   * Helper function to get mapped ACCU estimates.
   *
   * @return array
   *   Arrays of accu estimates keyed by the base estimate id.
   */
  protected function getAccuEstimates(): array {

    /** @var \Drupal\farm_loocc\LooccEstimateInterface $looc_estimate */
    $looc_estimate = \Drupal::service('farm_loocc.estimate');

    // Query all accu estimates.
    $accu_estimates = \Drupal::database()->select('farm_loocc_accu_estimate', 'flae')
      ->fields('flae', [
        'estimate_id',
        'method_id',
        'annual',
        'warning_message',
        'great_barrier_reef',
        'coastal_ecosystems',
        'wetlands',
        'threatened_ecosystems',
        'threatened_wildlife',
        'native_vegetation',
        'summary',
      ])
      ->orderBy('flae.estimate_id')
      ->orderBy('flae.annual', 'DESC')
      ->execute();

    // Map each accu estimate to the estimate id.
    $all_estimates = [];
    foreach ($accu_estimates as $result) {

      // Estimate values.
      $estimate = (array) $result;
      $method_id = $estimate['method_id'];
      $annual = $estimate['annual'];

      // Render the estimate with an improved name, if possible.
      $method_name = $method_id;
      if ($erf_cobenefits = $looc_estimate->getErfCobenefits($method_id)) {
        $method_name = $erf_cobenefits['methodName'];
      }
      // Else add spaces between each word in the method ID.
      else {
        $method_name = preg_replace('/[A-Z]/', ' $0', $method_name);
      }

      // Prepend the method name with the annual ACCUs.
      $estimate['method_name'] = "$annual: $method_name";

      // Include the estimate.
      $estimate_id = $result->estimate_id;
      $all_estimates[$estimate_id][] = $estimate;
    }

    return $all_estimates;
  }

}
