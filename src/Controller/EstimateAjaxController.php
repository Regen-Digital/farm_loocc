<?php

namespace Drupal\farm_loocc\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\BaseCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\farm_loocc\LooccClientInterface;
use Drupal\farm_loocc\LooccEstimateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for updating estimates via AJAX requests.
 */
class EstimateAjaxController extends ControllerBase {

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The csrf_token service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The loocc client.
   *
   * @var \Drupal\farm_loocc\LooccClientInterface
   */
  protected $looccClient;

  /**
   * The loocc estimate service.
   *
   * @var \Drupal\farm_loocc\LooccEstimateInterface
   */
  protected $looccEstimate;

  /**
   * Constructor for the estimate ajax controller.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
   *   The csrf token generator service.
   * @param \Drupal\farm_loocc\LooccClientInterface $loocc_client
   *   The loocc client.
   * @param \Drupal\farm_loocc\LooccEstimateInterface $loocc_estimate
   *   The loocc estimate service.
   */
  public function __construct(Connection $connection, CsrfTokenGenerator $csrf_token_generator, LooccClientInterface $loocc_client, LooccEstimateInterface $loocc_estimate) {
    $this->database = $connection;
    $this->csrfToken = $csrf_token_generator;
    $this->looccClient = $loocc_client;
    $this->looccEstimate = $loocc_estimate;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('csrf_token'),
      $container->get('farm_loocc.loocc_client'),
      $container->get('farm_loocc.estimate'),
    );
  }

  /**
   * Callback to perform an ajax operation.
   *
   * @param int $estimate_id
   *   The estimate ID.
   * @param string $op
   *   The operation.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function ajaxOperation(int $estimate_id, string $op, Request $request) {

    // Validate the csrf token manually. The _csrf_token route option only
    // works for links generated in PHP, not JS.
    // See https://www.drupal.org/docs/8/api/routing-system/access-checking-on-routes/csrf-access-checking
    $this->csrfToken->validate($request->get('token'), $request->getPathInfo());

    // Only accept AJAX requests.
    if ($request->get('js')) {
      // Delegate based on the operator.
      switch ($op) {
        case 'update':
          return $this->updateEstimate($estimate_id, $request);

        case 'delete':
          return $this->deleteEstimate($estimate_id, $request);
      }
    }

    // Redirect other requests to the estimates view.
    return $this->redirect('view.farm_loocc_estimates.page');
  }

  /**
   * Update an estimate.
   *
   * @param int $estimate_id
   *   The estimate ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  protected function updateEstimate(int $estimate_id, Request $request) {

    // Start a response.
    $response = new AjaxResponse();

    // Collect values to update.
    $values = array_filter([
      'selected_method' => $request->get('method_id'),
      'carbon_average' => $request->get('carbon_average'),
      'carbon_target' => $request->get('carbon_target'),
    ]);

    // Run the soc estimate if carbon values are provided.
    if (isset($values['carbon_average']) && isset($values['carbon_target'])) {

      // Get the base estimate area and bd_average.
      $estimate = $this->database->select('farm_loocc_estimate', 'fle')
        ->fields('fle', ['polygon_area', 'bd_average'])
        ->condition('fle.id', $estimate_id)
        ->execute()
        ->fetchObject();

      // Run the estimate.
      if ($soc_estimate = $this->looccClient->socEstimate($estimate->polygon_area, $values['carbon_average'], $values['carbon_target'], $estimate->bd_average)) {
        $estimate_values = [
          'annual' => $soc_estimate['totalCO2ePolyYr'],
          'project' => $soc_estimate['totalCO2ePolyProject'],
        ];
        $this->looccEstimate->updateAccuEstimate($estimate_id, 'soc-measure', $estimate_values);
      }

      // Refresh the view.
      $response->addCommand(new BaseCommand('refresh_loocc_estimates', ''));
    }

    // Update the base estimate values.
    if (!empty($values)) {
      $this->database->update('farm_loocc_estimate')
        ->condition('id', $estimate_id)
        ->fields($values)
        ->execute();
    }
    return $response;
  }

  /**
   * Delete an estimate.
   *
   * @param int $estimate_id
   *   The estimate ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  protected function deleteEstimate(int $estimate_id, Request $request) {

    // Delete esitmates.
    $this->database->delete('farm_loocc_accu_estimate')
      ->condition('estimate_id', $estimate_id)
      ->execute();
    $this->database->delete('farm_loocc_estimate')
      ->condition('id', $estimate_id)
      ->execute();

    // Update the view.
    $response = new AjaxResponse();
    $response->addCommand(new BaseCommand('refresh_loocc_estimates', ''));
    return $response;
  }

}
