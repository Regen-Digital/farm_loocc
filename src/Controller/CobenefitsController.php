<?php

namespace Drupal\farm_loocc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_loocc\LooccClient;
use Drupal\farm_loocc\LooccEstimateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for viewing the ERF cobenefits.
 */
class CobenefitsController extends ControllerBase {

  /**
   * The loocc estimate service.
   *
   * @var \Drupal\farm_loocc\LooccEstimateInterface
   */
  protected $looccEstimate;

  /**
   * Constructor for the estimate ajax controller.
   *
   * @param \Drupal\farm_loocc\LooccEstimateInterface $loocc_estimate
   *   The loocc estimate service.
   */
  public function __construct(LooccEstimateInterface $loocc_estimate) {
    $this->looccEstimate = $loocc_estimate;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_loocc.estimate'),
    );
  }

  /**
   * Returns a page of ERF method co-benefits.
   *
   * @return array
   *   Render array.
   */
  public function page() {
    $render = [];

    // Build a container for all methods.
    $render['methods'] = [
      '#type' => 'container',
    ];

    // Start with erf methods.
    $methods = LooccClient::$erfMethods;
    foreach ($methods as $method_id) {
      if ($erf_cobenefits = $this->looccEstimate->getErfCobenefits($method_id)) {

        // Each method gets a collapsible details. Start closed.
        $method = [
          '#type' => 'details',
          '#title' => $erf_cobenefits['methodName'],
        ];

        // Add a table for each type of co-benefit, eg: Farm Profitability.
        foreach ($erf_cobenefits['co-benefit'] as $type_label => $cobenefits) {

          // Change the header for "Disbenefits".
          $cobenefit_header_label = $type_label == 'Disbenefits' ? $this->t('Disbenefits') : $this->t('Co-benefits');

          // Build the table.
          $type_id = str_replace(' ', '_', strtolower($type_label));
          $method[$type_id] = [
            '#type' => 'table',
            '#caption' => $type_label,
            '#header' => [$cobenefit_header_label, $this->t('Description'), $this->t('Rating')],
            '#rows' => [],
            '#attributes' => [
              'class' => ['loocc-cobenefit-table'],
            ],
          ];

          // Add a row for each co-benefit.
          foreach ($cobenefits as $benefit_label => $cobenefit) {
            $method[$type_id]['#rows'][] = [$benefit_label, $cobenefit['Description'], $cobenefit['Rating']];
          }
        }

        // Include the method.
        $render[$method_id] = $method;
      }
    }

    // Add cobenefits library.
    $render['#attached'] = [
      'library' => ['farm_loocc/cobenefits'],
    ];
    return $render;
  }

}
