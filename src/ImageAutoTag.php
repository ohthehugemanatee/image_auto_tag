<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\image_auto_tag\Entity\PersonMap;

/**
 * ImageAutoTag service definition.
 *
 * Main entry point for image auto tag functionality.
 *
 * @package Drupal\image_auto_tag
 */
class ImageAutoTag implements ImageAutoTagInterface {

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
   * {@inheritdoc}
   */
  public function getServiceStatus() : bool {
    if ($this->serviceStatus === NULL) {
      $this->serviceStatus = $this->azureCognitiveServices->serviceStatus();
    }
    return $this->serviceStatus;
  }

  /**
   * {@inheritdoc}
   */
  public function createPerson(ContentEntityInterface $entity) : void {
    // Create the Person record.
    $personId = $this->azureCognitiveServices->createPerson($entity->label());
    // Create the personMap record.
    PersonMap::create([
      'foreign_id' => $personId,
      'local_id' => $entity->id(),
      'local_entity_type' => $entity->getEntityTypeId(),
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getPerson(string $personId) : \stdClass {
    return $this->azureCognitiveServices->getPerson($personId);
  }

  /**
   * {@inheritdoc}
   */
  public function updatePerson(string $personId, string $name) : bool {
    return $this->azureCognitiveServices->updatePerson($personId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function addFace(string $personId, string $file) : string {
    return $this->azureCognitiveServices->addFace($personId, $file);
  }

  /**
   * {@inheritdoc}
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
    /** @var \Drupal\image_auto_tag\Entity\PersonMapInterface $personMap */
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
      $faceId = $this->azureCognitiveServices->addFace($personId, $imagePath);
      PersonMap::create([
        'foreign_id' => $faceId,
        'local_id' => $image->entity->id(),
        'local_entity_type' => 'file',
      ])->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFace(string $personId, string $faceId) {
    return $this->azureCognitiveServices->deleteFace($personId, $faceId);
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function identifyFaces(array $detectedFaces) : array {
    if ($detectedFaces === []) {
      return [];
    }
    $identifiedFaces = $this->azureCognitiveServices->identifyFaces(\array_slice($detectedFaces, 0, 10));
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
   * {@inheritdoc}
   */
  public function detectAndIdentifyFaces(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) : array {
    $detectedFaces = $this->detectFaces($entity, $fieldDefinition);
    return $this->identifyFaces($detectedFaces);
  }

}
