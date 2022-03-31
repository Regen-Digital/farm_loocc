<?php

namespace Drupal\farm_loocc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * LOOC-C settings form.
 */
class LooccSettingsForm extends ConfigFormBase {

  /**
   * Config settings key.
   *
   * @var string
   */
  const SETTINGS = 'farm_loocc.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_loocc_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Api key field.
    $config = $this->config(static::SETTINGS);
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LOOC-C API key'),
      '#description' => $this->t('Enter your LOOC-C API key.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
