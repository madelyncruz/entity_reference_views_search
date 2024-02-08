<?php

namespace Drupal\entity_reference_views_search\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_reference_views_search\Form\EntityFormViewsSearchSettingsForm;
use Drupal\field\FieldConfigInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity reference views search widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_views_search_default",
 *   label = @Translation("Entity form views search"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = false
 * )
 */
class EntityReferenceViewsSearch extends WidgetBase implements ContainerFactoryPluginInterface {

  const NONE_LABEL = '- None -';

  /**
   * The config factory object.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * The config settings name.
   *
   * @var string
   */
  const SETTINGS = 'entity_reference_views_search.settings';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'searchable_fields' => NULL,
      'searchable_view' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $field_definition = $this->fieldDefinition;
    $handler_settings = $field_definition->getSetting('handler_settings');
    $target_bundle = reset($handler_settings['target_bundles']);
    $target_type = $field_definition->getSetting('target_type');

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $entity_field_definitions */
    $entity_field_definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $target_bundle);

    // Get allowed field types from the configuration.
    $allowed_field_types = $this->configFactory->get(self::SETTINGS)->get(EntityFormViewsSearchSettingsForm::SETTING_FIELD_TYPES);
    $allowed_field_types = Tags::explode($allowed_field_types);

    $element['searchable_view'] = [
      '#type' => 'select',
      '#title' => $this->t('Views result renderer'),
      '#options' => $this->getViewsOptions(),
      '#default_value' => $this->getSetting('searchable_view') ?? NULL,
      '#required' => TRUE,
    ];

    // Build table header.
    $header = [
      'status' => NULL,
      'label' => $this->t('Label'),
      'name' => $this->t('Name'),
      'type' => $this->t('Type'),
      'arg' => $this->t('Filter identifier'),
      'placeholder' => $this->t('Placeholder'),
    ];

    // Build searchable fields element table.
    $element['searchable_fields'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No applicable fields available.'),
      '#tree' => TRUE,
    ];

    // Get the searchable fields values.
    $searchable_fields = $this->getSetting('searchable_fields');

    // Get all the entity fields.
    foreach ($entity_field_definitions as $entity_field_definition) {
      $field_type = $entity_field_definition->getType();

      // Skip if field type is not allowed or
      // if field in not an instance of \Drupal\field\FieldConfigInterface.
      if (!$entity_field_definition instanceof FieldConfigInterface || !in_array($field_type, $allowed_field_types)) {
        continue;
      }

      // Get the information.
      $name = $entity_field_definition->getName();
      $label = $entity_field_definition->getLabel();
      $input_status_target = 'input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][searchable_fields][' . $name . '][status]"]';

      // Build table status data.
      $element['searchable_fields'][$name]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Status'),
        '#title_display' => 'invisible',
        '#option' => $name,
        '#default_value' => $searchable_fields[$name]['status'] ?? NULL,
      ];

      // Build table label data.
      $element['searchable_fields'][$name]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#default_value' => $searchable_fields[$name]['label'] ?? $label,
        '#required' => TRUE,
      ];

      // Build table name data.
      $element['searchable_fields'][$name]['name'] = [
        '#type' => 'textfield',
        '#value' => $name,
        '#disabled' => TRUE,
      ];

      switch ($field_type) {
        case 'entity_reference':
          $element_type = 'autocomplete';
          break;

        case 'phone_international':
          $element_type = 'phone_international';
          break;

        default:
          $element_type = 'textfield';
          break;
      }

      // Build table type data.
      $element['searchable_fields'][$name]['type'] = [
        '#type' => 'textfield',
        '#value' => $element_type,
        '#disabled' => TRUE,
      ];

      // Build table argument data.
      $element['searchable_fields'][$name]['arg'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Argument'),
        '#title_display' => 'invisible',
        '#default_value' => $searchable_fields[$name]['arg'] ?? NULL,
        '#states' => [
          'disabled' => [
            $input_status_target => ['checked' => FALSE],
          ],
          'required' => [
            $input_status_target => ['checked' => TRUE],
          ],
        ],
      ];

      // Build table placeholder data.
      $element['searchable_fields'][$name]['placeholder'] = [
        '#type' => 'textfield',
        '#title_display' => 'invisible',
        '#default_value' => $searchable_fields[$name]['placeholder'] ?? NULL,
        '#states' => [
          'disabled' => [
            $input_status_target => ['checked' => FALSE],
          ],
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    // Get the searchable fields values.
    $searchable_fields = $this->getSetting('searchable_fields') ?? [];
    $searchable_view = $this->getSetting('searchable_view') ?? NULL;

    // Get the number of fields in use.
    $count = count($this->getEnabledSearchableFields());

    // Set summary.
    $summary[] = $this->t('No. of fields in use: @count', ['@count' => $count]);
    $summary[] = $this->t('No. of applicable fields: @count', ['@count' => count($searchable_fields)]);
    $summary[] = $this->t('Views result renderer: @view', ['@view' => $searchable_view ? $this->getViewsOptions($searchable_view) : $this->t(self::NONE_LABEL)]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Get the searchable fields values.
    $enabled_fields = $this->getEnabledSearchableFields();
    $ajax_wrapper_id = $this->generateAjaxWrapper($items->getFieldDefinition()->id() . '_form');

    // Build container element.
    $element = [
      '#type' => 'container',
      '#id' => str_replace('js-', '', $ajax_wrapper_id),
      '#attributes' => [
        'class' => ['ervs-container'],
        'data-ervs-ajax' => $ajax_wrapper_id,
        'data-ervs-type' => 'entity_reference_views_search',
      ],
      '#tree' => TRUE,
      '#weight' => -20,
    ];

    // Build element for each enabled fields.
    foreach ($enabled_fields as $field_name => $field) {
      $element[$field_name] = [
        '#type' => $field['type'],
        '#title' => $field['label'],
      ];
      if ($field['type'] === 'phone_international') {
        $element[$field_name]['#geolocation'] = TRUE;
      }
      if ($field['placeholder']) {
        $element[$field_name]['#placeholder'] = $field['placeholder'];
      }
    }

    $element['results'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $ajax_wrapper_id . '" class="ervs-results">',
      '#suffix' => '</div>',
      '#weight' => 10,
    ];

    $element['target_id'] = [
      '#type' => 'hidden',
      '#type' => 'textfield',
      '#default_value' => !$items->isEmpty() ? $items->getValue()[0]['target_id'] : NULL,
      '#attributes' => [
        'class' => ['ervs-input'],
      ],
    ];
    $element['actions'] = [
      '#type' => 'actions',
      '#weight' => -10,
    ];
    $element['actions']['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search for existing records'),
      '#ajax' => [
        'wrapper' => $ajax_wrapper_id,
        'callback' => [$this, 'entityReferenceViewsSearchAjax'],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Explicitly handle the multiple values.
   */
  protected function handlesMultipleValues() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Check if the field is applicable to use the widget.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Get the handler settings.
    $handler_settings = $field_definition->getSetting('handler_settings');

    // Check if field definition is an instance of
    // \Drupal\field\FieldConfigInterface
    // and has target bundles.
    if ($field_definition instanceof FieldConfigInterface && isset($handler_settings['target_bundles'])) {
      // Multiple target bundles is not allowed to use the widget
      // and if disabled from the form field config settings.
      $is_applicable = count($handler_settings['target_bundles']) === 1 && $field_definition->getThirdPartySetting('entity_reference_views_search', 'status');

      // Return TRUE if applicable.
      // Otherwise, return FALSE.
      return $is_applicable;
    }

    // No option.
    return parent::isApplicable($field_definition);
  }

  /**
   * {@inheritdoc}
   *
   * Entity reference views search AJAX callback function.
   */
  public function entityReferenceViewsSearchAjax(array &$form, FormStateInterface $form_state) {
    $field_definition = $this->fieldDefinition;
    $field_name = $field_definition->getName();
    $ajax_wrapper_id = $form[$field_name]['widget']['#attributes']['data-ervs-ajax'];
    $enabled_fields = $this->getEnabledSearchableFields();
    $exposed_filters = [];

    // Create an AJAX response.
    $response = new AjaxResponse();

    // Get all the values.
    $values = $form_state->getValue([
      $field_name,
    ]);

    // Loop through enabled fields to map the values for exposed filter format.
    foreach ($enabled_fields as $key => $enabled_field) {
      $arg = $enabled_field['arg'];
      $exposed_filters[$arg] = $values[$key] ?? NULL;
    }

    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->viewsRenderer(FALSE);

    // Set exposed filter values.
    $view->setExposedInput($exposed_filters);

    // Run attachments.
    $view->preExecute();

    // Execute views query.
    $view->execute();

    // Invoke JS command to set attributes in the container.
    $response->addCommand(new InvokeCommand('.ervs-container[data-ervs-ajax="' . $ajax_wrapper_id . '"]', 'attr', [
      [
        'data-ervs-view-id' => $view->id(),
        'data-ervs-view-display' => $view->current_display,
      ],
    ]));

    // Replace the content of results with the rendered views.
    $response->addCommand(new ReplaceCommand('#' . $ajax_wrapper_id . ' > div', $view->render()));

    return $response;
  }

  /**
   * Generates AJAX wrapper by field ID.
   *
   * @param string $field_id
   *   The field ID to be appended to the wrapper.
   *
   * @return string
   *   Returns the generated AJAX wrapper ID.
   */
  protected function generateAjaxWrapper(string $field_id) : string {
    $ajax_wrapper_id = 'js-entity_reference_views_search_';
    $ajax_wrapper_id .= str_replace('.', '_', $field_id);
    return Html::getId($ajax_wrapper_id);
  }

  /**
   * Get the enabled searchable fields.
   *
   * @return array
   *   An array of enabled searchable fields.
   */
  protected function getEnabledSearchableFields() : array {
    $searchable_fields = $this->getSetting('searchable_fields');
    $enabled_fields = [];
    if (!$searchable_fields) {
      return $enabled_fields;
    }
    foreach ($searchable_fields as $field_name => $field) {
      if ($field['status'] == 1) {
        $enabled_fields[$field_name] = $field;
      }
    }
    return $enabled_fields;
  }

  /**
   * Get the views list.
   *
   * @param string $option
   *   The option key from the options.
   *
   * @return mixed
   *   An array of views options or the individual view option.
   */
  protected function getViewsOptions($option = NULL) {
    $views_options = Views::getViewsAsOptions();
    if (isset($views_options[$option])) {
      return $views_options[$option];
    }
    return $views_options;
  }

  /**
   * Views renderer.
   *
   * @param bool $return_renderable
   *   Identifier for returning the views output or the views object.
   * @param array $arguments
   *   An array of views arguments for filters.
   *
   * @return mixed
   *   An array of views options or the individual view option.
   */
  protected function viewsRenderer(bool $return_renderable = TRUE, array $arguments = []) {
    $view = $this->getSetting('searchable_view');
    $view_parts = explode(':', $this->getSetting('searchable_view'));
    $view_id = $view_parts[0] ?? NULL;
    $display_id = $view_parts[1] ?? NULL;

    /** @var \Drupal\views\ViewExecutable $view */
    $view = Views::getView($view_id);

    // Set views display.
    $view->setDisplay($display_id);

    // Check if views exists.
    if (!$view instanceof ViewExecutable || !$view->storage->getDisplay($display_id)) {
      return;
    }

    // Set views arguments.
    if ($arguments) {
      $view->setArguments($arguments);
    }

    // Get renderable views.
    if ($return_renderable) {
      $view->preExecute();
      $view->execute();
      return $view->buildRenderable();
    }

    return $view;
  }

}
