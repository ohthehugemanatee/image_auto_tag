<?php

declare(strict_types = 1);

namespace Drupal\media_auto_tag;

use Drupal\Core\Http\ClientFactory;

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
   * AzureCognitiveServices constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The guzzle HTTP client.
   */
  public function __construct(ClientFactory $httpClientFactory) {
    $this->httpClient = $httpClientFactory->fromOptions([
      'headers' => [
        'Content-Type' => 'application/json',
        'Ocp-Apim-Subscription-Key' => '30ebdb47a8cb4985b85c4772a476b7a9',
      ],
    ]);
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong in the HTTP request to Azure.
   */
  public function detectFaces(string $file): array {
    $response = $this->httpClient->request('POST', 'https://northeurope.api.cognitive.microsoft.com/face/v1.0/detect', [
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong in the HTTP request.
   */
  public function createPersonGroup(string $id, string $name): bool {
    $response = $this->httpClient->request('PUT', 'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $id, [
      'body' => json_encode([
        'name' => $name,
      ]),
    ]);
    return empty($response->getBody());
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong in the HTTP request.
   */
  public function deletePersonGroup(string $id) : bool {
    $response = $this->httpClient->request('DELETE',
      'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $id);
    return empty($response->getBody());
  }

  /**
   * List person Groups.
   *
   * @return array
   *   The list of groups.
   *
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function listPersonGroups() : array {
    $response = $this->httpClient->request('GET',
      'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups');
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function createPerson(string $personGroupId, string $name) {
    $response = $this->httpClient->request('POST', 'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $personGroupId . '/persons', [
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function listPeople(string $personGroupId): array {
    $response = $this->httpClient->request('GET',
      'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $personGroupId . '/persons');

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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function addFace(string $personGroupId, string $personId, string $file) {
    $response = $this->httpClient->request('POST',
      "https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/{$personGroupId}/persons/{$personId}/persistedFaces", [
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function trainPersonGroup(string $id) : bool {
    $response = $this->httpClient->request('POST',
      'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $id . '/train');
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function getPersonGroupTrainingStatus(string $id) : \stdClass {
    $response = $this->httpClient->request('GET',
      'https://northeurope.api.cognitive.microsoft.com/face/v1.0/persongroups/' . $id . '/training');
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
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If anything goes wrong with the HTTP request.
   */
  public function identifyFaces(array $faces, string $personGroup) : array {
    $response = $this->httpClient->request('POST', 'https://northeurope.api.cognitive.microsoft.com/face/v1.0/identify', [
      'body' => json_encode([
        'faceIds' => $faces,
        'personGroupId' => $personGroup,
      ]),
    ]);

    return json_decode((string) $response->getBody());
  }

}
