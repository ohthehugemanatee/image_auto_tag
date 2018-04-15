<?php

declare(strict_types = 1);

namespace Drupal\media_auto_tag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\TransferException;

/**
 * Class AzureCognitiveServices.
 *
 * Service class for Azure Cognitive Services.
 *
 * @package Drupal\media_auto_tag
 */
class AzureCognitiveServices {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * HTTP Client Factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * The applied configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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
    $config = $configFactory->get('media_auto_tag.settings');
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

    echo $response->getBody();
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

    echo $response->getBody();
    return json_decode((string) $response->getBody());
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
