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
   * Get information for a specific person.
   *
   * @param string $personId
   *   The desired person's Person Id.
   *
   * @return \stdClass
   *   The returned data from Azure. An array with keys:
   *    - personId: (string) the personId of the retrieved person.
   *    - persistedFaceIds: (array) persistedFaceIds of registered Faces in the
   *      person.
   *    - name: (string) The Person's display name.
   *    - userData (string) Any user-provided data attached to the person.
   */
  public function getPerson(string $personId) : \stdClass;

  /**
   * Update an existing person.
   *
   * @param string $personId
   *   The Id of the Person to update.
   * @param string $name
   *   The new name of the Person.
   *
   * @return bool
   *   TRUE on success, throws an exception on failure.
   */
  public function updatePerson(string $personId, string $name) : bool;

  /**
   * Add a face to a person record.
   *
   * @param string $personId
   *   The person's personId.
   * @param string $file
   *   The image file of the person's face. It should only include one face.
   *
   * @return string
   *   The face Id if successful, empty if not.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function addFace(string $personId, string $file) : string;

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
   * Delete a face from a person record.
   *
   * @param string $personId
   *   The person's personId.
   * @param string $faceId
   *   The persisted Id of the face to delete.
   *
   * @return string
   *   The face Id if successful, empty if not.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function deleteFace(string $personId, string $faceId);

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