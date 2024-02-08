<?php

namespace Drupal\entity_reference_views_search\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller for handling AJAX requests related to entity form views.
 */
class EntityFormViewsSearchAjaxController extends ControllerBase {

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new EntityFormViewsSearchAjaxController object.
   *
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->entityFormBuilder = $entity_form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
    );
  }

  /**
   * Handles AJAX requests for displaying entity form or pre-populate input.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function ajaxView(Request $request = NULL) {
    // Get parameters from the request.
    $view_id = $request->query->get('view') ?? NULL;
    $display = $request->query->get('display') ?? NULL;
    $entity_id = $request->query->get('entity_id') ?? '';
    $entity_type = $request->query->get('entity_type') ?? '';
    $form_mode = $request->query->get('form_mode') ?? 'default';

    // Create an AJAX response.
    $response = new AjaxResponse();

    // If necessary parameters are missing, return empty response.
    if (!$entity_type || !$entity_id) {
      return $response;
    }

    // Load the entity.
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    // If the loaded entity is not an instance of EntityInterface,
    // return empty response.
    if (!$entity instanceof EntityInterface) {
      return $response;
    }

    // Get the rendered form for the entity.
    $rendered_form = $this->entityFormBuilder->getForm($entity, $form_mode);

    // Define the container for updating HTML content.
    $container = '.ervs-container[data-ervs-view-id="' . $view_id . '"][data-ervs-view-display="' . $display . '"]';

    // Replace the content of results with the rendered form.
    $response->addCommand(new HtmlCommand($container . ' .ervs-results > div', $this->renderer->render($rendered_form)));

    // Invoke JS command to set the value of the input.
    $response->addCommand(new InvokeCommand($container . ' input.ervs-input', 'val', [$entity_id]));

    return $response;
  }

}
