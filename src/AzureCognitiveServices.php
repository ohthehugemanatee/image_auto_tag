<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\TransferException;

/**
 * Class AzureCognitiveServices.
 *
 * Service class for Azure Cognitive Services.
 *
 * @package Drupal\image_auto_tag
 */
class AzureCognitiveServices {

  const PEOPLE_GROUP = 'drupal_image_auto_tag_people';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The applied configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The service status using the current config.
   *
   * @var bool
   */
  protected $status;

  /**
   * AzureCognitiveServices constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Configuration factory.
   */
  public function __construct(ClientFactory $httpClientFactory, ConfigFactoryInterface $configFactory) {
    $this->clientFactory = $httpClientFactory;
    $config = $configFactory->get('image_auto_tag.settings');
    $this->httpClient = $httpClientFactory->fromOptions([
      'base_uri' => (string) $config->get('azure_endpoint'),
      'headers' => [
        'Content-Type' => 'application/json',
        'Ocp-Apim-Subscription-Key' => (string) $config->get('azure_service_key'),
      ],
    ]);
    $this->config = $config;
  }

  /**
   * Test given Azure credentials.
   *
   * @param string $endpoint
   *   The endpoint base URL to use for the test.
   * @param string $serviceKey
   *   The service key to use for the test.
   *
   * @return bool
   *   TRUE on success, FALSE on failure... usually failure throws an exception.
   *
   * @throws \GuzzleHttp\Exception\TransferException
   *   An HTTP exception, direct from Guzzle.
   */
  public static function testCredentials(string $endpoint, string $serviceKey) : bool {
    $response = \Drupal::httpClient()->get('persongroups',
      [
        'base_uri' => $endpoint,
        'headers' => [
          'Content-Type' => 'application/json',
          'Ocp-Apim-Subscription-Key' => $serviceKey,
        ],
      ]);
    return $response->getStatusCode() === 200;
  }

  /**
   * Check status of the current configuration.
   *
   * @return bool
   *   TRUE on valid, FALSE on invalid.
   */
  public function serviceStatus() : bool {
    if ($this->status === NULL) {
      try {
        $response = $this->httpClient->request('GET', 'persongroups');
        $statusCode = $response->getStatusCode();
      } catch (TransferException $e) {
        $statusCode = $e->getCode();
      }
      $this->status = ($statusCode === 200);
    }
    return $this->status;
  }

  /**
   * Run face detection.
   *
   * @param string $file
   *   The image file path.
   *
   * @return array
   *   The decoded JSON detection result.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong in the HTTP request to Azure.
   */
  public function detectFaces(string $file): array {
    $response = $this->httpClient->request('POST', 'detect', [
      'headers' => [
        'Content-Type' => 'application/octet-stream',
      ],
      'query' => [
        // Request parameters.
        'returnFaceId' => 'true',
        'returnFaceLandmarks' => 'false',
      ],
      'body' => fopen($file, 'rb'),
    ]);
    return json_decode((string) $response->getBody());
  }

  /**
   * Create a person group.
   *
   * @param string $id
   *   The Id of the proposed person group.
   * @param string $name
   *   The name of the proposed person group.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong in the HTTP request.
   */
  public function createPersonGroup(string $id, string $name): bool {
    $response = $this->httpClient->request('PUT', 'persongroups/' . $id, [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return $response->getBody() === NULL;
  }

  /**
   * Delete person group.
   *
   * @param string $id
   *   The PersonGroup Id to delete.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong in the HTTP request.
   */
  public function deletePersonGroup(string $id) : bool {
    $response = $this->httpClient->request('DELETE',
      'persongroups/' . $id);
    return empty($response->getBody());
  }

  /**
   * List person Groups.
   *
   * @return array
   *   The list of groups.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function listPersonGroups() : array {
    $response = $this->httpClient->request('GET',
      'persongroups');
    return json_decode((string) $response->getBody());
  }

  /**
   * Get an individual Person Group.
   *
   * @param string $id
   *   The person group Id.
   *
   * @return array
   *   Details about the Person Group.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function getPersonGroup(string $id) : array {
    $response = $this->httpClient->request('GET',
      'persongroup/' . $id);
    return json_decode((string) $response->getBody());
  }

  /**
   * Create a person in a group.
   *
   * @param string $personGroupId
   *   The Id of the personGroup in which to create the person.
   * @param string $name
   *   The name to assign to the new person.
   *
   * @return string
   *   The personId of the created person.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function createPerson(string $personGroupId, string $name) {
    $response = $this->httpClient->request('POST', 'persongroups/' . $personGroupId . '/persons', [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return json_decode((string) $response->getBody());
  }

  /**
   * Get information for a specific person.
   *
   * @param string $personGroupId
   *   The desired person's PersonGroup Id.
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
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function getPerson(string $personGroupId, string $personId) : \stdClass {
    $response = $this->httpClient->request('GET', 'persongroups/' . $personGroupId . '/persons/' . $personId);
    return json_decode((string) $response->getBody());
  }

  /**
   * Update an existing person.
   *
   * @param string $personGroupId
   *   The Id of the personGroup to which the Person belongs.
   * @param string $personId
   *   The Id of the Person to update.
   * @param string $name
   *   The new name of the Person.
   *
   * @return bool
   *   TRUE on success, throws an exception on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   All errors are reported as Guzzle exceptions.
   */
  public function updatePerson(string $personGroupId, string $personId, string $name) {
    $response = $this->httpClient->request('PATCH', 'persongroups/' . $personGroupId . '/persons/' . $personId, [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return $response->getStatusCode() === 200;
  }

  /**
   * List people in a personGroup.
   *
   * @param string $personGroupId
   *   The target person Group Id.
   *
   * @return array
   *   The list of person Ids.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function listPeople(string $personGroupId): array {
    $response = $this->httpClient->request('GET',
      'persongroups/' . $personGroupId . '/persons');

    return json_decode((string) $response->getBody());
  }

  /**
   * Add a face to a person record.
   *
   * @param string $personGroupId
   *   The person's personGroupId.
   * @param string $personId
   *   The person's personId.
   * @param string $file
   *   The image file of the person's face. It should only include one face.
   *
   * @return string
   *   The face Id if successful, empty if not.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function addFace(string $personGroupId, string $personId, string $file) {
    $response = $this->httpClient->request('POST',
      "persongroups/{$personGroupId}/persons/{$personId}/persistedFaces", [
        'headers' => [
          'Content-Type' => 'application/octet-stream',
        ],
        'body' => fopen($file, 'rb'),
      ]);

    return json_decode((string) $response->getBody());
  }

  /**
   * Delete a face from a person record.
   *
   * @param string $personGroupId
   *   The person's personGroupId.
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
  public function deleteFace(string $personGroupId, string $personId, string $faceId) {
    $response = $this->httpClient->request('POST',
      "persongroups/{$personGroupId}/persons/{$personId}/persistedFaces/{$faceId}");

    return empty($response->getBody());
  }

  /**
   * Train a person group.
   *
   * @param string $id
   *   The person group Id.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function trainPersonGroup(string $id) : bool {
    $response = $this->httpClient->request('POST',
      'persongroups/' . $id . '/train');
    return empty($response->getBody());
  }

  /**
   * Check training status.
   *
   * @param string $id
   *   The Person Group Id for which to check training status.
   *
   * @return \stdClass
   *   The status message.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function getPersonGroupTrainingStatus(string $id) : \stdClass {
    $response = $this->httpClient->request('GET',
      'persongroups/' . $id . '/training');
    return json_decode((string) $response->getBody());
  }

  /**
   * Identify Faces.
   *
   * @param array $faces
   *   An array of face IDs to be identified.
   * @param string $personGroup
   *   The personGroup whose persons will be matched to the face IDs.
   *
   * @return array
   *   The face identification result.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function identifyFaces(array $faces, string $personGroup) : array {
    $response = $this->httpClient->request('POST', 'identify', [
      'body' => json_encode([
        'faceIds' => $faces,
        'personGroupId' => $personGroup,
      ]),
    ]);

    return json_decode((string) $response->getBody());
  }

}
