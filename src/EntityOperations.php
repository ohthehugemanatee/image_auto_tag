<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Operations used in entity hook implementations.
 *
 * Because we want to reuse the same code on entity hooks and on cron.
 *
 * @package Drupal\image_auto_tag
 */
class EntityOperations implements EntityOperationsInterface {

  /**
   * Image Auto Tag service.
   *
   * @var \Drupal\image_auto_tag\ImageAutoTagInterface
   */
  protected $imageAutoTag;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Configuration for this module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(ImageAutoTagInterface $imageAutoTag, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    $this->imageAutoTag = $imageAutoTag;
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $configFactory->get('image_auto_tag.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function findFacesAndTag(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) : void {
    $peopleEntities = $this->imageAutoTag->detectAndIdentifyFaces($entity, $fieldDefinition);
    if (!empty($peopleEntities)) {
      // Apply this as the target field value.
      $targetTagField = $fieldDefinition->getThirdPartySetting('image_auto_tag', 'tag_field');
      $entity->set($targetTagField, $peopleEntities);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function syncPerson(ContentEntityInterface $entity) : void {
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    // Do we have a person record yet?
    $personMapResult = $personMapStorage->getQuery()
      ->condition('local_id', $entity->id())
      ->condition('local_entity_type', $entity->getEntityTypeId())
      ->execute();
    // If we don't have a record, create one on Azure.
    if ($personMapResult === []) {
      $this->imageAutoTag->createPerson($entity);
      $this->imageAutoTag->createFaces($entity);
    }
    else {
      /** @var \Drupal\image_auto_tag\Entity\PersonMap[] $personMaps */
      $personMaps = $personMapStorage->loadMultiple($personMapResult);
      $personMap = reset($personMaps);
      // Otherwise, update the existing Person on Azure.
      if ($entity->label() !== $entity->original->label()) {
        // @todo: Do this through the generic ImageAutoTag service.
        $azure = \Drupal::service('image_auto_tag.azure');
        $azure->updatePerson(AzureCognitiveServices::PEOPLE_GROUP, $personMap->getForeignId(), $entity->label());
      }
      // If the value of the faces image field has changed.
      $targetImageField = explode('.', $this->config->get('person_image_field'))[1];
      $imageFilesArray = $entity->get($targetImageField)->getValue();
      if ($imageFilesArray !== $entity->original->get($targetImageField)->getValue()) {
        // Make sure all the local images exist on the remote.
        foreach ($imageFilesArray as $index => $value) {
          /** @var \Drupal\Core\Image\Image $image */
          $image = $entity->get($targetImageField)->get($index);
          $personMapResult = $personMapStorage->getQuery()
            ->condition('local_id', $image->entity->id())
            ->condition('local_entity_type', 'file')
            ->execute();
          // If this image doesn't have a personMap yet, upload it as a face.
          if ($personMapResult === []) {
            $imagePath = $image->entity->getFileUri();
            $faceId = $azure->addFace(AzureCognitiveServices::PEOPLE_GROUP, $personMap->getForeignId(), $imagePath);
            $personMapStorage->create([
              'foreign_id' => $faceId->persistedFaceId,
              'local_id' => $image->entity->id(),
              'local_entity_type' => 'file',
            ])->save();
          }
        }
        // Delete any remote images that aren't on local.
        // @todo: Move to generic ImageAutoTag service.
        $personRecord = $azure->getPerson(AzureCognitiveServices::PEOPLE_GROUP, $personMap->getForeignId());
        $faceIds = $personRecord->persistedFaceIds;
        $personMapResult = $personMapStorage->getQuery()
          ->condition('foreign_id', $faceIds, 'IN')
          ->condition('local_entity_type', 'file')
          ->execute();
        if (\count($personMapResult) !== \count($faceIds)) {
          $personMaps = $personMapStorage->loadMultiple($personMapResult);
          $foreignIds = [];
          foreach ($personMaps as $personMap) {
            $foreignIds[] = $personMap->getForeignId();
          }
          // Delete the faces that exist on foreign, but not local.
          $missingFaces = array_diff($foreignIds, $faceIds);
          foreach ($missingFaces as $missingFace) {
            // @todo: Move to generic ImageAutoTag service.
            $azure->deleteFace(AzureCognitiveServices::PEOPLE_GROUP, $personMap->getForeignId(), $missingFace);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntity(ContentEntityInterface $entity) : void {
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    $personMapResult = $personMapStorage->getQuery()
      ->condition('local_id', $entity->id())
      ->condition('local_entity_type', $entity->getEntityTypeId())
      ->execute();
    if ($personMapResult > 0) {
      /** @var \Drupal\image_auto_tag\Entity\PersonMap $personMaps */
      $personMaps = $personMapStorage->loadMultiple($personMapResult);
      foreach ($personMaps as $personMap) {
        $personMap->delete();
        // @todo: Delete remote person object, too.
      }
    }
  }

}
