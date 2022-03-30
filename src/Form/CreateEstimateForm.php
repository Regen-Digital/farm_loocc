<?php

namespace Drupal\farm_loocc\Form;

use Drupal\asset\Entity\Asset;
use Drupal\asset\Entity\AssetInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Url;
use Drupal\farm_loocc\LooccClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form for creating LOOC-C estimates.
 */
class CreateEstimateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CreateEstimateForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_loocc_create_estimate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Fieldset for asset selection.
    $form['asset_selection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select land assets to include in the estimate.'),
    ];

    // Let the user choose assets individually or in bulk.
    $form['asset_selection']['bulk'] = [
      '#type' => 'radios',
      '#options' => [
        1 => $this->t('Bulk by land type'),
        0 => $this->t('Individual land assets'),
      ],
      '#default_value' => 1,
      '#ajax' => [
        'wrapper' => 'asset-selection-wrapper',
        'callback' => [$this, 'assetSelectionCallback'],
      ],
    ];

    // AJAX Wrapper for the asset selection.
    $form['asset_selection']['asset_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'asset-selection-wrapper',
      ],
    ];

    // Simple entity autocomplete for individual asset selection.
    $bulk_select = (boolean) $form_state->getValue('bulk', 1);
    if (!$bulk_select) {
      $form['asset_selection']['asset_wrapper']['asset'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Land asset'),
        '#description' => $this->t('Search for land assets by their name. Use commas to select multiple land assets.'),
        '#target_type' => 'asset',
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => ['land'],
        ],
        '#tags' => TRUE,
        '#required' => TRUE,
      ];
    }
    // Else bulk select by land type.
    else {
      $land_type_options = farm_land_type_options();
      $form['asset_selection']['asset_wrapper']['land_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Land type'),
        '#options' => $land_type_options,
        '#required' => TRUE,
        '#ajax' => [
          'wrapper' => 'asset-selection-wrapper',
          'callback' => [$this, 'assetSelectionCallback'],
        ],
      ];

      // Display asset options.
      if ($land_type = $form_state->getValue('land_type')) {
        $asset_storage = $this->entityTypeManager->getStorage('asset');
        $asset_ids = $asset_storage->getQuery()
          ->accessCheck()
          ->condition('status', 'active')
          ->condition('land_type', $land_type)
          ->condition('is_location', TRUE)
          ->condition('intrinsic_geometry', NULL, 'IS NOT NULL')
          ->execute();
        $assets = $asset_storage->loadMultiple($asset_ids);
        $asset_options = array_map(function (AssetInterface $asset) {
          return $asset->label();
        }, $assets);

        // Display checkboxes for each asset.
        $form_state->setValue('asset_bulk', []);
        $form['asset_selection']['asset_wrapper']['asset_bulk'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select assets'),
          '#options' => $asset_options,
          '#default_value' => array_keys($asset_options),
          '#required' => TRUE,
        ];

        // Display message is there are no options.
        if (empty($asset_options)) {
          $form['asset_selection']['asset_wrapper']['asset_bulk'] = [
            '#markup' => $this->t('No @land_type land assets found. Make sure these land assets are not archived and have a geometry.', ['@land_type' => $land_type_options[$land_type]]),
          ];
        }
      }
    }

    // Additional project metadata.
    $form['metadata'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Project metadata'),
      '#tree' => TRUE,
    ];

    // New irrigation flag.
    $form['metadata']['new_irrigation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Will this project use new irrigation methods?'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create estimate'),
    ];

    return $form;
  }

  /**
   * AJAX callback for the asset selection container.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The asset selection container.
   */
  public function assetSelectionCallback(array $form, FormStateInterface $form_state) {
    return $form['asset_selection']['asset_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the submitted assets.
    $bulk = (boolean) $form_state->getValue('bulk');
    $asset_ids = $bulk
      ? Checkboxes::getCheckedCheckboxes($form_state->getValue('asset_bulk', []))
      : array_column($form_state->getValue('asset', []), 'target_id');
    $assets = Asset::loadMultiple($asset_ids);
    if (empty($assets)) {
      $this->messenger()->addError($this->t('No assets selected.'));
      return;
    }

    // Get the submitted metadata.
    $project_metadata = $form_state->getValue('metadata', []);

    // Assemble the batch operation for creating estimates.
    $operations = [];
    foreach ($assets as $asset_id => $asset) {
      $operations[] = [
        [self::class, 'createLooccEstimateBatch'],
        [$asset_id, LooccClient::$projectTypes, $project_metadata],
      ];
    }
    $batch = [
      'operations' => $operations,
      'title' => $this->t('Creating LOOC-C estimates'),
      'init_message' => $this->t('Creating estimates... Each estimate may take 15-30 seconds to complete.'),
      'error_message' => $this->t('The operation has encountered an error.'),
      'progress_message' => $this->t('Completed @current of @total. Estimated @estimate remaining.'),
      'finished' => [self::class, 'createLooccEstimateBatchFinished'],
    ];
    batch_set($batch);
  }

  /**
   * Implements callback_batch_operation().
   *
   * Performs batch creation of LOOC-C estimates.
   */
  public static function createLooccEstimateBatch($asset_id, $project_types, $project_metadata, &$context) {

    /** @var \Drupal\farm_loocc\LooccEstimateInterface $loocc_estimate */
    $loocc_estimate = \Drupal::service('farm_loocc.estimate');

    // Create the estimate for the asset.
    $asset = Asset::load($asset_id);
    if ($estimate_id = $loocc_estimate->createEstimate($asset, $project_types, $project_metadata)) {
      $context['results'][] = ['asset' => $asset_id, 'estimate' => $estimate_id];
      $context['message'] = t('Created estimate for @asset.', ['@asset' => $asset->label()]);
    }
    else {
      $context['message'] = t('Failed to create estimate for @asset.', ['@asset' => $asset->label()]);
    }
  }

  /**
   * Implements callback_batch_finished().
   *
   * Redirects after the batch is finished.
   */
  public static function createLooccEstimateBatchfinished($success, $results) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'Created estimate for @count land asset.',
        'Created estimates for @count land assets.',
      );
      \Drupal::messenger()->addStatus($message);
    }

    // Redirect to looc_c estimates page.
    $redirect_url = Url::fromRoute('view.farm_loocc_estimates.page')->setAbsolute()->toString();
    return new RedirectResponse($redirect_url);
  }

}
