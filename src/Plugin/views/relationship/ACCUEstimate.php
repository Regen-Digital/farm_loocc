<?php

namespace Drupal\farm_loocc\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Custom views relationship to include specific accu estimates.
 *
 * @ViewsRelationship("accu_estimate")
 */
class ACCUEstimate extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if (isset($this->options['method_id'])) {
      $extra = [
        'field' => 'method_id',
        'value' => $this->options['method_id'],
      ];
      $this->definition['extra'][] = $extra;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Add a method_id option.
    $options['method_id'] = [
      'default' => 'soc-measure',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);

    // Add method_id field.
    $form['method_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Method ID'),
      '#description' => $this->t('The method ID of the ACCU estimate.'),
      '#default_value' => 'soc-measure',
      '#required' => TRUE,
    ];
  }

}
