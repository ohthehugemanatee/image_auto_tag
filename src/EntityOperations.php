<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Operations used in entity hook implementations.
 *
 * Because we want to reuse the same code on entity hooks and on cron.
 *
 * @package Drupal\image_auto_tag
 */
class EntityOperations {

  /**
   * Image Auto Tag service.
   *
   * @var \Drupal\image_auto_tag\ImageAutoTagInterface
   */
  protected $imageAutoTag;

  public function __construct(ImageAutoTagInterface $imageAutoTag) {
    $this->imageAutoTag = $imageAutoTag;
  }

  /**
   * Do face detection on a given entity and image field, and tag appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The target entity to be tagged.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The image field to use for face detection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function findFacesAndTag(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) : void {
    $peopleEntities = $this->imageAutoTag->detectAndIdentifyFaces($entity, $fieldDefinition);
    if (!empty($peopleEntities)) {
      // Apply this as the target field value.
      $targetTagField = $fieldDefinition->getThirdPartySetting('image_auto_tag', 'tag_field');
      $entity->set($targetTagField, $peopleEntities);
    }
  }
}