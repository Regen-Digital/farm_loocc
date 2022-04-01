<?php

namespace Drupal\farm_loocc\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Custom views field that renders a number form element.
 *
 * @ViewsField("farm_loocc_carbon_percent")
 */
class CarbonPercent extends FieldPluginBase {

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
    $value = $this->getValue($values);
    $estimate_id = $values->farm_loocc_estimate_id;

    // Render a number field.
    $number = [
      '#type' => 'number',
      '#title' => NULL,
      // Use #value not #default_value since this is not a true form.
      '#value' => $value,
      '#size' => 4,
      '#min' => 0,
      '#step' => 0.1,
      '#attributes' => [
        'data-estimate-id' => $estimate_id,
      ],
      '#attached' => [
        'library' => ['farm_loocc/estimate_table'],
      ],
    ];
    return \Drupal::service('renderer')->render($number);
  }

}
