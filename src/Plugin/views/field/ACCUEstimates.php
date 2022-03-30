<?php

namespace Drupal\farm_loocc\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\PrerenderList;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Custom views field that renders a list of ACCU estimates.
 *
 * @ViewsField("farm_loocc_accu_estimates")
 */
class ACCUEstimates extends PrerenderList {

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
  public function renderItems($items) {
    $render = [
      '#type' => 'select',
      '#title' => NULL,
      '#options' => $items,
    ];
    return \Drupal::service('renderer')->render($render);
  }

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item) { // phpcs:ignore
    /** @var \Drupal\farm_loocc\LooccEstimateInterface $looc_estimate */
    $looc_estimate = \Drupal::service('farm_loocc.estimate');

    // Estimate values.
    $method_id = $item['method_id'];
    $annual = $item['annual'];
    $project = $item['project'];

    // Render the estimate with an improved name, if possible.
    $method_name = $method_id;
    if ($erf_cobenefits = $looc_estimate->getErfCobenefits($method_id)) {
      $method_name = $erf_cobenefits['methodName'];
    }
    return "$method_name : $annual ($project)";
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(ResultRow $values) {
    $id = $values->farm_loocc_estimate_id;
    $estimates = $this->accuEstimates[$id] ?? [];
    return $estimates;
  }

  /**
   * Helper function to get mapped ACCU estimates.
   *
   * @return array
   *   Arrays of accu estimates keyed by the base estimate id.
   */
  protected function getAccuEstimates(): array {

    // Query all accu estimates.
    $accu_estimates = \Drupal::database()->select('farm_loocc_accu_estimate', 'flae')
      ->fields('flae', ['estimate_id', 'method_id', 'annual', 'project', 'warning_message'])
      ->orderBy('flae.estimate_id')
      ->execute();

    // Map each accu estimate to the estimate id.
    $all_estimates = [];
    foreach ($accu_estimates as $result) {
      $id = $result->estimate_id;
      $all_estimates[$id][] = (array) $result;
    }

    return $all_estimates;
  }

}
