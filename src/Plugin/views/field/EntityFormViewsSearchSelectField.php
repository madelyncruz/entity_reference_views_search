<?php

namespace Drupal\entity_reference_views_search\Plugin\views\field;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Presenting select row from results.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_reference_views_search_select")
 */
class EntityFormViewsSearchSelectField extends FieldPluginBase {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new EntityFormViewsSearchSelectField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_display.repository'),
    );
  }

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
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#options' => $this->getFormModes(),
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

  /**
   * Retrieve form mode options for a specific entity type.
   *
   * @return array
   *   An array of form mode options.
   */
  protected function getFormModes() {
    // Get the base tables from the view.
    $base_tables = $this->view->getBaseTables();

    // Extract the entity type ID from the first table name.
    $entity_type_id = str_replace('_field_data', '', array_key_first($base_tables));

    // Retrieve form mode options using the entity type ID.
    return $this->entityDisplayRepository->getFormModeOptions($entity_type_id);
  }

}
