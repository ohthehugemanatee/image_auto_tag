<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * ImageAutoTag service definition.
 *
 * Main entry point for image auto tag functionality.
 *
 * @package Drupal\image_auto_tag
 */
interface ImageAutoTagInterface {

  /**
   * Check the status of the configured service.
   *
   * @return bool
   *   TRUE if the service is up, FALSE if not.
   */
  public function getServiceStatus(): bool;

  /**
   * Submit faces for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity containing faces to submit.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function createPerson(ContentEntityInterface $entity): void;

  /**
   * Create faces for a given "Person" entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal entity signifying a Person, with Face images in a field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function createFaces(ContentEntityInterface $entity): void;

  /**
   * Run face detection (not identification!) on a given entity and image field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The target entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The target image field.
   *
   * @return array
   *   An array of unique identifiers for detected faces.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function detectFaces(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition): array;

  /**
   * Identify faces.
   *
   * @param array $detectedFaces
   *   An array of unique identifiers for detected faces. Unique identifiers
   *   are specific to the service being used.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   An array of "people" Entities whose faces were detected in the target
   *   field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function identifyFaces(array $detectedFaces): array;

  /**
   * Detect and Identify Faces.
   *
   * One-stop shop method for detecting and identifying faces all in one.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity with images on which to perform detection/identification.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Definition of the field containing the target image.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   An array of "people" Entities whose faces were detected in the target
   *   field.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function detectAndIdentifyFaces(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition): array;
}