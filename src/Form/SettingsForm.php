<?php

declare(strict_types = 1);

namespace Drupal\media_auto_tag\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\media_auto_tag\AzureCognitiveServices;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for configuring azure services.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * CogSer service.
   *
   * @var \Drupal\media_auto_tag\AzureCognitiveServices
   */
  protected $azure;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\media_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AzureCognitiveServices $azureCognitiveServices, EntityTypeManagerInterface $entityTypeManager) {
    $this->azure = $azureCognitiveServices;
    $this->entityTypeManager = $entityTypeManager;

    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('media_auto_tag.azure'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_auto_tag_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'media_auto_tag.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('media_auto_tag.settings');

    $form['azure_endpoint'] = [
      '#title' => t('Azure endpoint'),
      '#description' => t('The Media Services endpoint you want to use.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('azure_endpoint'),
    ];
    $form['azure_service_key'] = [
      '#title' => t('Azure service key'),
      '#description' => t('The Media Services API key to send to the endpoint.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('azure_service_key'),
    ];

    // Choose the entity type and bundle to represent people.
    $contentEntityTypes = $this->entityTypeManager->getDefinitions();
    $contentEntityOptions = [];
    foreach ($contentEntityTypes as $key => $entityType) {
      $bundleTypeId = NULL;
      if ($entityType instanceof ContentEntityType) {
        $bundleTypeId = $entityType->getBundleEntityType();
        // If this is an unbundled entity type.
        if ($bundleTypeId === NULL) {
          $contentEntityOptions[$key] = $entityType->getLabel();
          continue;
        }
        // If bundled, then add all the bundles.
        $bundles = $this->entityTypeManager->getStorage($bundleTypeId)->loadMultiple();
        foreach ($bundles as $bundle) {
          $contentEntityOptions["{$key}.{$bundle->id()}"] = "{$entityType->getLabel()}: {$bundle->label()}";
        }
      }
    }
    $form['person_entity_bundle'] = [
      '#title' => t('People Entity Type'),
      '#description' => t('The Drupal Entity Type which contains people to be recognized. Entities of this type will be "tags" on other content.'),
      '#type' => 'select',
      '#options' => $contentEntityOptions,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('media_auto_tag.settings')
      ->set('azure_endpoint', $values['azure_endpoint'])
      ->set('azure_service_key', $values['azure_service_key'])
      ->set('person_entity_bundle', $values['person_entity_bundle'])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('azure_endpoint');
    if ($endpoint === NULL || !UrlHelper::isValid($endpoint)) {
      $form_state->setErrorByName('azure_endpoint', 'The Azure endpoint must be a valid URL.');
    }
    // Make sure the endpoint ends in a slash.
    if (substr($endpoint, -1, 1) !== '/') {
      $form_state->setValue('azure_endpoint', $endpoint . '/');
    }
    if ($form_state->getValue('azure_service_key') === NULL) {
      $form_state->setErrorByName('azure_service_key', 'The Azure service key must not be empty');
    }

    // @TODO: test the connection here?
    parent::validateForm($form, $form_state);
  }

}
