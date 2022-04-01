<?php

namespace Drupal\farm_loocc\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\BaseCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for updating estimates via AJAX requests.
 */
class EstimateAjaxController extends ControllerBase {

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
    \Drupal::getContainer()->get('csrf_token')->validate($request->get('token'), $request->getPathInfo());

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
    $method_id = $request->get('method_id');
    \Drupal::database()->update('farm_loocc_estimate')
      ->condition('id', $estimate_id)
      ->fields(['selected_method' => $method_id])
      ->execute();

    return new AjaxResponse();
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
    \Drupal::database()->delete('farm_loocc_accu_estimate')
      ->condition('estimate_id', $estimate_id)
      ->execute();
    \Drupal::database()->delete('farm_loocc_estimate')
      ->condition('id', $estimate_id)
      ->execute();

    // Update the view.
    $response = new AjaxResponse();
    $response->addCommand(new BaseCommand('refresh_loocc_estimates', ''));
    return $response;
  }

}
