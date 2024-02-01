<?php

namespace Drupal\entity_reference_views_search\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_reference_views_search\Form\EntityFormViewsSearchSettingsForm;
use Drupal\field\FieldConfigInterface;
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
   *
   * Explicitly handle the multiple values.
   */
  protected function handlesMultipleValues() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'searchable_fields' => [],
    ] + parent::defaultSettings();
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

    // Build table header.
    $header = [
      'status' => $this->t('Allow search'),
      'field' => $this->t('Field'),
      'type' => $this->t('Type'),
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

      // Build table status element.
      $element['searchable_fields'][$name]['status'] = [
        '#type' => 'checkbox',
        '#option' => $name,
        '#title_display' => 'invisible',
        '#default_value' => $searchable_fields[$name]['status'] ?? NULL,
      ];

      // Use field label to set the table field data.
      $element['searchable_fields'][$name]['field'] = [
        '#markup' => $label,
      ];

      // Use field type to set the table type data.
      $element['searchable_fields'][$name]['type'] = [
        '#markup' => $field_type,
      ];

      // Build table placeholder element.
      $input_status_target = 'input[name="fields[' . $field_definition->getName() . '][settings_edit_form][settings][searchable_fields][' . $name . '][status]"]';
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
    $searchable_fields = $this->getSetting('searchable_fields');

    // Get the number of fields in use.
    $count = 0;
    foreach ($searchable_fields as $item) {
      if ($item['status'] == 1) {
        $count++;
      }
    }

    // Set summary.
    $summary[] = $this->t('No. of fields in use: @count', ['@count' => $count]);
    $summary[] = $this->t('No. of applicable fields: @count', ['@count' => count($searchable_fields)]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $element;
  }

}
