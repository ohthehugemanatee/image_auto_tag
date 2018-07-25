<?php
/**
 * Created by PhpStorm.
 * User: ohthehugemanatee
 * Date: 7/25/18
 * Time: 5:25 PM
 */

namespace Drupal\image_auto_tag\Service;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Class AzureCognitiveServices.
 *
 * Service class for Azure Cognitive Services.
 *
 * @package Drupal\image_auto_tag
 */
interface ServicePluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Check status of the current configuration.
   *
   * @return bool
   *   TRUE on valid, FALSE on invalid.
   */
  public function serviceStatus(): bool;

  /**
   * Create a person record.
   *
   * @param string $name
   *   The name to assign to the new person.
   *
   * @return string
   *   A unique Id for the remote person record.
   */
  public function createPerson(string $name): string;

  /**
   * Get information for a specific person.
   *
   * @param string $personId
   *   The desired person's unique Id on the remote service.
   *
   * @return array
   *   Data on an individual person. An array with at least these keys:
   *    - personId: (string) the unique Id of the person on the remote service.
   *    - faceIds: (array) unique Ids of registered Faces on the remote person
   *      record.
   *    - name: (string) The Person's name.
   */
  public function getPerson(string $personId) : array;

  /**
   * Update an existing person's name on the remote service.
   *
   * @param string $personId
   *   The unique Id of the Person to update on the remote service.
   * @param string $name
   *   The new name of the Person.
   */
  public function updatePerson(string $personId, string $name) : void;

  /**
   * Delete a person record on the remote service.
   *
   * @param string $personId
   *   The unique Id of the Person to delete on the remote service.
   */
  public function deletePerson(string $personId): void;

  /**
   * List people on the remote service.
   *
   * @return array
   *   The list of person Ids.
   */
  public function listPeople(): array;

  /**
   * Add a face image to a person record.
   *
   * @param string $personId
   *   The person's personId.
   * @param string $file
   *   URI to an image of the person's face. It should only include one face.
   *
   * @return string
   *   The unique face Id if successful, empty if not.
   */
  public function addFace(string $personId, string $file): string;

  /**
   * Delete a face from a person record.
   *
   * @param string $personId
   *   The person's unique Id on the remote service..
   * @param string $faceId
   *   The unique Id of the face to delete.
   */
  public function deleteFace(string $personId, string $faceId) : void;

  /**
   * Trigger training on the remote service.
   */
  public function runTraining(): void;

  /**
   * Check training status.
   *
   * @return \stdClass
   *   The status message.
   */
  public function getTrainingStatus(): \stdClass;

  /**
   * Detect faces in a given image file.
   *
   * @param string $file
   *   The image file uri.
   *
   * @return array
   *   The detection result. An array of detected faces. Each array member
   *   should have coordinates for a bounding box around the face.
   */
  public function detectFaces(string $file): array;

  /**
   * Detect and identify faces in a given image file.
   *
   * @param string $file
   *   The image file uri.
   *
   * @return array
   *   The face identification result. An array of personIds detected.
   */
  public function detectAndIdentifyFaces(string $file): array;
}