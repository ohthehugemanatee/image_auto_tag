<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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

  public static function submitPerson(ContentEntityInterface $entity) {

  }

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
   * Create the person group.
   *
   * @param string $name
   *   The name of the proposed person group.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong in the HTTP request.
   */
  public function createPersonGroup(string $name): bool {
    $response = $this->httpClient->request('PUT', 'persongroups/' . self::PEOPLE_GROUP, [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return $response->getBody() === NULL;
  }

  /**
   * Delete person group.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong in the HTTP request.
   */
  public function deletePersonGroup() : bool {
    $response = $this->httpClient->request('DELETE',
      'persongroups/' . self::PEOPLE_GROUP);
    return empty($response->getBody());
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
   * Create a person record.
   *
   * @param string $name
   *   The name to assign to the new person.
   *
   * @return string
   *   The personId of the created person.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function createPerson(string $name) : string {
    $personGroupId = self::PEOPLE_GROUP;
    $response = $this->httpClient->request('POST', 'persongroups/' . $personGroupId . '/persons', [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return json_decode((string) $response->getBody())->personId;
  }

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
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function getPerson(string $personId) : \stdClass {
    $response = $this->httpClient->request('GET', 'persongroups/' . self::PEOPLE_GROUP . '/persons/' . $personId);
    return json_decode((string) $response->getBody());
  }

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
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   All errors are reported as Guzzle exceptions.
   */
  public function updatePerson(string $personId, string $name) {
    $response = $this->httpClient->request('PATCH', 'persongroups/' . self::PEOPLE_GROUP . '/persons/' . $personId, [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return $response->getStatusCode() === 200;
  }

  public function deletePerson(string $personId) : void {
    $personGroupId = self::PEOPLE_GROUP;
    $this->httpClient->request('DELETE',
      "persongroups/{$personGroupId}/persons/{$personId}");

  }

  /**
   * List people in a personGroup.
   *
   * @return array
   *   The list of person Ids.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function listPeople(): array {
    $response = $this->httpClient->request('GET',
      'persongroups/' . self::PEOPLE_GROUP . '/persons');

    return json_decode((string) $response->getBody());
  }

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
   *   If anything goes wrong with the HTTP request.
   */
  public function addFace(string $personId, string $file) : string{
    $personGroupId = self::PEOPLE_GROUP;
    $response = $this->httpClient->request('POST',
      "persongroups/{$personGroupId}/persons/{$personId}/persistedFaces", [
        'headers' => [
          'Content-Type' => 'application/octet-stream',
        ],
        'body' => fopen($file, 'rb'),
      ]);

    return json_decode((string) $response->getBody())->persistedFaceId;
  }

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
  public function deleteFace(string $personId, string $faceId) {
    $personGroupId = self::PEOPLE_GROUP;
    $response = $this->httpClient->request('POST',
      "persongroups/{$personGroupId}/persons/{$personId}/persistedFaces/{$faceId}");

    return empty($response->getBody());
  }

  /**
   * Train people records.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function trainPersonGroup() : bool {
    $response = $this->httpClient->request('POST',
      'persongroups/' . self::PEOPLE_GROUP . '/train');
    return empty($response->getBody());
  }

  /**
   * Check training status.
   *
   * @return \stdClass
   *   The status message.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function getTrainingStatus() : \stdClass {
    $response = $this->httpClient->request('GET',
      'persongroups/' . self::PEOPLE_GROUP . '/training');
    return json_decode((string) $response->getBody());
  }

  /**
   * Identify Faces.
   *
   * @param array $faces
   *   An array of face IDs to be identified.
   *
   * @return array
   *   The face identification result.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If anything goes wrong with the HTTP request.
   */
  public function identifyFaces(array $faces) : array {
    $response = $this->httpClient->request('POST', 'identify', [
      'body' => json_encode([
        'faceIds' => $faces,
        'personGroupId' => self::PEOPLE_GROUP,
      ]),
    ]);

    return json_decode((string) $response->getBody());
  }

}
