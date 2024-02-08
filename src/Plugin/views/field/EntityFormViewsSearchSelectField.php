<?php

namespace Drupal\entity_reference_views_search\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Presenting select row from results.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_reference_views_search_select")
 */
class EntityFormViewsSearchSelectField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['select_label'] = ['default' => 'Select'];
    $options['form_mode'] = ['default' => 'default'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['select_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Select label'),
      '#default_value' => $this->options['select_label'],
      '#required' => TRUE,
    ];
    $form['form_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->options['form_mode'],
      '#required' => TRUE,
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getEntity($row);

    // Build the URL with required parameters.
    $url = Url::fromRoute('entity_reference_views_search.ajax', [
      'display' => $this->view->current_display,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'form_mode' => $this->options['form_mode'],
      'view' => $this->view->id(),
    ]);

    // Set URL attributes.
    $url->setOptions([
      'attributes' => [
        'class' => ['use-ajax'],
        'data-processed' => 'true',
      ],
    ]);

    // Create a link.
    $link = Link::fromTextAndUrl($this->options['select_label'], $url);

    // Render the link.
    $build = $link->toRenderable();

    // Attach core's drupal AJAX library.
    $build['#attached']['library'][] = 'core/drupal.ajax';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return $this->options['select_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

}
