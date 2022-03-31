<?php

namespace Drupal\farm_loocc\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Custom views field that renders the average lrf rating.
 *
 * @ViewsField("farm_loocc_average_lrf_rating")
 */
class AverageLrfRating extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    $this->ensureMyTable();

    // Add a subquery for the AVG summary rating.
    $sub_query = \Drupal::database()->select('farm_loocc_accu_estimate', 'flae');
    $sub_query->addField('flae', 'estimate_id');
    $sub_query->addExpression("ROUND(AVG(flae.summary), 1)", 'average_summary');
    $sub_query->groupBy('flae.estimate_id');

    // Join in the subquery.
    $join = [
      'table formula' => $sub_query,
      'field' => 'estimate_id',
      'left_table' => 'farm_loocc_estimate',
      'left_field' => 'id',
      'adjust' => TRUE,
    ];
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $join);
    $this->query->addRelationship('farm_loocc_accu_estimate', $join, 'farm_loocc_estimate');

    // Add the average_summary subquery field as average_lrf_rating.
    $this->field_alias = $this->query->addField(NULL, 'average_summary', 'average_lrf_rating');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (isset($this->field_alias)) {
      $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
      $this->query->addOrderBy(NULL, 'average_summary', $order, $this->field_alias, $params);
    }
  }

}
