<?php

namespace Drupal\entity_reference_views_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings form for entity reference views search configuration.
 */
class EntityFormViewsSearchSettingsForm extends ConfigFormBase {

  const SETTING_FIELD_TYPES = 'allowed_field_types';

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'entity_reference_views_search.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_reference_views_search_settings_form';
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
    $config = $this->config(static::SETTINGS);

    $form[self::SETTING_FIELD_TYPES] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed field types'),
      '#required' => TRUE,
      '#default_value' => $config->get(self::SETTING_FIELD_TYPES),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)->set(self::SETTING_FIELD_TYPES, $form_state->getValue(self::SETTING_FIELD_TYPES))->save();
    parent::submitForm($form, $form_state);
  }

}
