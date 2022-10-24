<?php

namespace Drupal\content_block\Plugin\Block;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;

/**
 * Provides a 'Content' block
 *
 * @Block(
 *   id = "content_block",
 *   admin_label = @Translation("Content"),
 * )
 */
class ContentBlock extends ContentBlockBase {
  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return [
      'entity_type' => [
        '#type' => 'textfield',
        '#title' => $this->t('Entity type'),
        '#description' => $this->t('Enter the machine name of the entity type to render, or leave blank to infer from the current page.'),
        '#default_value' => '',
      ],
      'entity_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Entity ID'),
        '#description' => $this->t('Enter the entity ID you wish to render, or leave blank to infer from the current page.'),
        '#default_value' => '',
      ],
      'view_mode' => [
        '#type' => 'textfield',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('Enter the machine name of the view mode to render, or leave blank to use the default view mode.'),
        '#default_value' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#cache' => [
        'max-age' => 0,
      ]
    ];

    $content = $this->getEntityView();

    if (!empty($content)) {
      $build['content'] = $content;
    }

    return $build;
  }

  /**
   * Build a view using a view builder for the configured entity and view mode
   *
   * @return array
   */
  protected function getEntityView() {
    $entity_view = [];
    $entity = $this->loadEntity();

    if (!is_null($entity)) {
      $view_mode = $this->getOptionValue('view_mode') ?: 'full';

      if ($this->hasContent($entity, $view_mode)) {
        $entity_view = \Drupal::entityTypeManager()
          ->getViewBuilder($entity->getEntityTypeId())
          ->view($entity, $view_mode);
      }
    }

    return $entity_view;
  }

  /**
   * Checks of the entity has content in any of the fields displayed on the
   * provided view mode.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param $view_mode
   * @return bool
   */
  protected function hasContent(FieldableEntityInterface $entity, $view_mode) {
    $has_content = FALSE;

    foreach ($this->getDisplayFields($entity, $view_mode) as $field_name => $field_settings) {
      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        $has_content = TRUE;
        break;
      }
    }

    return $has_content;
  }

  /**
   * Gets the fields that are configured on the provided view mode.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param $view_mode
   * @return array
   */
  protected function getDisplayFields(FieldableEntityInterface $entity, $view_mode) {
    $fields = [];

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode);

    if (!is_null($display)) {
      $fields = $display
        ->removeComponent('title')
        ->removeComponent('uid')
        ->removeComponent('created')
        ->getComponents();
    }

    return $fields;
  }

  /**
   * Load the entity configured by the block
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   The loaded entity, or NULL
   */
  protected function loadEntity() {
    $entity = NULL;

    $entityType = $this->getOptionValue('entity_type');
    $entityId = $this->getOptionValue('entity_id');

    if (empty($entityType) || empty($entityId)) {
      $entity = $this->loadCurrentEntity($entityType);
    } else {
      $entityId = $this->getOptionValue('entity_id');

      if (!empty($entityId)) {
        /** @var FieldableEntityInterface $entity */
        $entity = \Drupal::entityTypeManager()->getStorage($entityType)->load($entityId);
      }
    }

    return $entity;
  }

  /**
   * Loads the current entity from the current request URI, and returns it if available.
   *
   * @param null $entityType
   *   The entity type to load, or empty to try and determine the current entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object from the current request URI, or NULL
   */
  protected function loadCurrentEntity($entityType = NULL) {
    $path = \Drupal::service('path.current')->getPath();

    $url = Url::fromUri('internal:' . $path);

    $params = $url->getRouteParameters();

    $entity = null;

    if (!empty($params)) {
      if (empty($entityType)) {
        if (isset($params['entity_type'])) {
          $entityType = $params['entity_type'];
        } else {
          $entityType = key($params);
        }

      }

      if (!empty($entityType)) {
        $param = isset($params['entity']) ? $params['entity'] : $params[$entityType];
        $entity = \Drupal::entityTypeManager()->getStorage($entityType)->load($param);
      }
    }

    return $entity;
  }
}
