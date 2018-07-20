<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\image_auto_tag\Entity\PersonMap;

/**
 * ImageAutoTag service.
 *
 * Main entry point for image auto tag functionality.
 *
 * @package Drupal\image_auto_tag
 */
class ImageAutoTag {

  /**
   * Azure cognitive services service.
   *
   * @var \Drupal\image_auto_tag\AzureCognitiveServices
   */
  protected $azureCognitiveServices;

  /**
   * Configuration for this module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Azure cognitive services status.
   *
   * @var bool
   */
  protected $serviceStatus;

  /**
   * ImageAutoTag constructor.
   *
   * @param \Drupal\image_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(AzureCognitiveServices $azureCognitiveServices, ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->azureCognitiveServices = $azureCognitiveServices;
    $this->config = $configFactory->get('image_auto_tag.settings');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Check the status of the configured service.
   *
   * @return bool
   *   TRUE if the service is up, FALSE if not.
   */
  public function getServiceStatus() : bool {
    if ($this->serviceStatus === NULL) {
      $this->serviceStatus = $this->azureCognitiveServices->serviceStatus();
    }
    return $this->serviceStatus;
  }

  /**
   * Submit faces for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity containing faces to submit.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function createPerson(ContentEntityInterface $entity) : void {
    // Create the Person record.
    $personId = $this->azureCognitiveServices->createPerson(AzureCognitiveServices::PEOPLE_GROUP, $entity->label());
    // Create the personMap record.
    PersonMap::create([
      'foreign_id' => $personId,
      'local_id' => $entity->id(),
      'local_entity_type' => $entity->getEntityTypeId(),
    ])->save();
  }

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
  public function createFaces(ContentEntityInterface $entity) : void {
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    $personImageField = $this->config->get('person_image_field');
    // Submit each value on the image field as a "face".
    $targetImageField = explode('.', $personImageField)[1];
    // Look up the remote personId.
    $personMapResult = $personMapStorage->getQuery()
      ->condition('local_id', $entity->id())
      ->condition('local_entity_type', $entity->getEntityTypeId())
      ->execute();
    if ($personMapResult === []) {
      // @todo: Throw an exception?
      return;
    }
    $personMap = $personMapStorage->load(reset($personMapResult));
    $personId = $personMap->getForeignId();
    if ($this->getServiceStatus() === FALSE) {
      // @todo: Throw an exception?
      return;
    }
    foreach ($entity->get($targetImageField)->getValue() as $index => $value) {
      /** @var \Drupal\Core\Image\Image $image */
      $image = $entity->get($targetImageField)->get($index);
      // @todo: Shouldn't this be $image->getSource()?
      $imagePath = $image->entity->getFileUri();
      // @todo: Custom exception.
      $faceId = $this->azureCognitiveServices->addFace(AzureCognitiveServices::PEOPLE_GROUP, $personId, $imagePath);
      PersonMap::create([
        'foreign_id' => $faceId,
        'local_id' => $image->entity->id(),
        'local_entity_type' => 'file',
      ])->save();
    }
  }

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
  public function detectFaces(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) : array {
    $fileUri = $entity->{$fieldDefinition->getName()}->entity->getFileUri();
    $detectedFaces = $this->azureCognitiveServices->detectFaces($fileUri);
    // Get faceIds.
    $faceIds = [];
    foreach ($detectedFaces as $detectedFace) {
      $faceIds[] = $detectedFace->faceId;
    }
    return $faceIds;
  }

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
  public function identifyFaces(array $detectedFaces) : array {
    if ($detectedFaces === []) {
      return [];
    }
    $identifiedFaces = $this->azureCognitiveServices->identifyFaces(array_slice($detectedFaces, 0, 10), AzureCognitiveServices::PEOPLE_GROUP);
    if ($identifiedFaces === []) {
      return [];
    }
    // Loop over the identified faces and tag with their Drupal entities.
    $personIds = [];
    foreach ($identifiedFaces as $identifiedFace) {
      // Take the first candidate.
      if ($identifiedFace->candidates !== []) {
        $personIds[] = $identifiedFace->candidates[0]->personId;
      }
    }
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    $personMapResult = $personMapStorage->getQuery()
      ->condition('foreign_id', $personIds, 'IN')
      ->execute();
    /* @var PersonMap[] $personMaps */
    $personMaps = $personMapStorage->loadMultiple($personMapResult);
    $peopleEntities = [];
    foreach ($personMaps as $personMap) {
      $peopleEntities[] = $personMap->getLocalEntity();
    }
    return $peopleEntities;
  }

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
  public function detectAndIdentifyFaces(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) : array {
    $detectedFaces = $this->detectFaces($entity, $fieldDefinition);
    return $this->identifyFaces($detectedFaces);
  }

}
