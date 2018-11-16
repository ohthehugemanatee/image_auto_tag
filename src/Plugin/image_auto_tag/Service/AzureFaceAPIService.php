<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Plugin\image_auto_tag\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\image_auto_tag\Service\ServicePluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class AzureFaceAPIService
 *
 * @ImageAutoTagService(
 *   id = "azure_face_api",
 *   label = @Translation("Azure Face API")
 * )
 *
 * @package Drupal\image_auto_tag\Plugin\image_auto_tag\Service
 */
class AzureFaceAPIService extends PluginBase implements ServicePluginInterface {

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
   * Our log channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * AzureFaceAPIService constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin Id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ClientFactory $httpClientFactory, ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $loggerChannelFactory) {
    // Module config.
    $this->config = $configFactory->get('image_auto_tag.settings');
    // Pre-configure an HTTP client with authentication headers.
    $this->httpClient = $httpClientFactory->fromOptions([
      'base_uri' => (string) $this->config->get('azure_endpoint'),
      'headers' => [
        'Content-Type' => 'application/json',
        'Ocp-Apim-Subscription-Key' => (string) $this->config->get('azure_service_key'),
      ],
    ]);
    // Our log channel.
    $this->logger = $loggerChannelFactory->get('image_auto_tag');
    // For PluginBase goodness.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client_factory'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function serviceStatus(): bool {
    // Make sure we can do a simple request with these creds.
    try {
      $response = $this->httpClient->request('GET', 'persongroups');
    }
    catch (GuzzleException $e) {
      $this->logger->error(
        'Could not authenticate to Azure with the given credentials. Error %code : %message',
        ['%code' => $e->getCode(), '%message' => $e->getMessage()]
      );
      return FALSE;
    }
    // Make sure our PersonGroup exists.
    $personGroups = json_decode((string) $response->getBody());
    $eureka = FALSE;
    foreach ($personGroups as $personGroup) {
      if ($personGroup['personGroupId'] === self::PEOPLE_GROUP) {
        $eureka = TRUE;
      }
    }
    // If our PersonGroup doesn't exist, create it.
    if ($eureka === FALSE) {
      try {
        $response2 = $this->httpClient->request('PUT', 'persongroups/' . self::PEOPLE_GROUP, [
          'body' => json_encode([
            'name' => self::PEOPLE_GROUP,
          ]),
        ]);
      }
      catch (GuzzleException $e) {
        $this->logger->error(
          'Could not create Azure PersonGroup. Error %code : %message',
          ['%code' => $e->getCode(), '%message' => $e->getMessage()]
        );
        return FALSE;
      }
    }
    // Return true if both checks successful.
    return $response->getCode() === 200 &&
      ($eureka === TRUE || $response2->getCode() === 200);
  }

  /**
   * {@inheritdoc}
   */
  public function createPerson(string $name) : string {
    $personGroupId = self::PEOPLE_GROUP;
    try {
      $response = $this->httpClient->request('POST', 'persongroups/' . $personGroupId . '/persons', [
        'body' => json_encode([
          'name' => $name,
        ]),
      ]);
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Could not create person %name on Azure. Error %code: %message . Fix the problem and re-save the entity.',
        ['%code' => $e->getCode(), '%message' => $e->getMessage()]
      );
      return '';
    }
    return (string) json_decode((string) $response->getBody())->personId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPerson(string $personId): array {
    try {
      $response = $this->httpClient->request('GET', 'persongroups/' . self::PEOPLE_GROUP . '/persons/' . $personId);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Could not get person with Id %personId from Azure. Error %code: %message',
        [
          '%personId' => $personId,
          '%code' => $e->getCode(),
          '%message' => $e->getMessage(),
        ]
      );
      return [];
    }
    $personResponse = json_decode((string) $response->getBody());
    return [
      'faceIds' => $personResponse->persistedFaceIds,
      'name' => $personResponse->name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updatePerson(string $personId, string $name): void {
    try {
      $response = $this->httpClient->request('PATCH', 'persongroups/' . self::PEOPLE_GROUP . '/persons/' . $personId, [
        'body' => json_encode([
          'name' => $name,
        ]),
      ]);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Could not update person %name with Id %personId from Azure. Error %code: %message . Fix the problem and re-save the entity.',
        [
          '%name' => $name,
          '%personId' => $personId,
          '%code' => $e->getCode(),
          '%message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deletePerson(string $personId): void {
    $personGroupId = self::PEOPLE_GROUP;
    try {
      $this->httpClient->request('DELETE',
        "persongroups/{$personGroupId}/persons/{$personId}");
    }
    catch (GuzzleException $e) {
      $this->logger->error('Could not delete person with Id %personId from Azure. Error %code: %message .',
        [
          '%personId' => $personId,
          '%code' => $e->getCode(),
          '%message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listPeople(): array {
    try {
      $response = $this->httpClient->request('GET',
        'persongroups/' . self::PEOPLE_GROUP . '/persons');
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Could not list people on Azure. Error %code: %message',
        [
          '%code' => $e->getCode(),
          '%message' => $e->getMessage(),
        ]
      );
      return [];
    }
    return json_decode((string) $response->getBody());
  }

  /**
   * {@inheritdoc}
   */
  public function addFace(string $personId, string $file): string {
    // TODO: Implement addFace() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFace(string $personId, string $faceId): void {
    // TODO: Implement deleteFace() method.
  }

  /**
   * {@inheritdoc}
   */
  public function runTraining(): void {
    // TODO: Implement runTraining() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getTrainingStatus(): \stdClass {
    // TODO: Implement getTrainingStatus() method.
  }

  /**
   * {@inheritdoc}
   */
  public function detectFaces(string $file): array {
    // TODO: Implement detectFaces() method.
  }

  /**
   * {@inheritdoc}
   */
  public function detectAndIdentifyFaces(string $file): array {
    // TODO: Implement detectAndIdentifyFaces() method.
  }

}