<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\image_auto_tag\AzureCognitiveServices;
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
   * @var \Drupal\image_auto_tag\AzureCognitiveServices
   */
  protected $azure;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Field Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\image_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field Manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AzureCognitiveServices $azureCognitiveServices, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->azure = $azureCognitiveServices;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;

    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('image_auto_tag.azure'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_auto_tag_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'image_auto_tag.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('image_auto_tag.settings');

    // Check the current saved connection details.
    try {
      $this->azure->serviceStatus();
      $this->messenger()->addMessage('Connection test successful.');
    }
    catch (TransferException $e) {
      $this->messenger()->addWarning("Received code {$e->getCode()} from Azure. Message: '{$e->getMessage()}'");
    }
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
      '#default_value' => $config->get('person_entity_bundle'),
    ];
    // Image field to use for training.
    // @todo Support Media fields.
    // @todo Filter the list by chosen entity bundle.
    // @todo Support crop API, or similarly allow users to draw a box around a face.
    $fieldMap = $this->entityFieldManager->getFieldMapByFieldType('image');
    $imageFieldOptions = [];
    foreach ($fieldMap as $entityType => $entityTypeFields) {
      foreach (array_keys($entityTypeFields) as $fieldName) {
        $imageFieldOptions["{$entityType}.{$fieldName}"] = "{$entityType}.{$fieldName}";
      }
    }
    $form['person_image_field'] = [
      '#title' => t('Person image field'),
      '#description' => t('The image field from which to get training images of the persons face. Values in this field should contain ONLY the head, as closely cropped as possible.'),
      '#type' => 'select',
      '#options' => $imageFieldOptions,
      '#default_value' => $config->get('person_image_field'),
    ];
    $form['synchronous'] = [
      '#title' => t('Synchronous mode'),
      '#type' => 'checkbox',
      '#description' => t('Submit images for processing as soon as they are saved in Drupal. This will potentially slow down the editor process, as the "save" button has to upload the image to another service and get a response back. When disabled, everything is done on cron instead. It is recommended to keep this checkbox DISABLED on production systems.'),
      '#default_value' => $config->get('synchronous'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('image_auto_tag.settings')
      ->set('azure_endpoint', $values['azure_endpoint'])
      ->set('azure_service_key', $values['azure_service_key'])
      ->set('person_entity_bundle', $values['person_entity_bundle'])
      ->set('person_image_field', $values['person_image_field'])
      ->set('synchronous', $values['synchronous'])
      ->save();
    
    $this->validateAzureResource();

    parent::submitForm($form, $form_state);
  }

  /**
   * Validate that the Azure cognitive service responds and has our group.
   *
   * @return bool
   *   TRUE if the Azure resource is responding and has the Person Group.
   */
  protected function validateAzureResource() : bool {
    try {
      $personGroups = $this->azure->listPersonGroups();
    }
    catch (TransferException $e) {
      $this->messenger()->addError('Could not connect with this endpoint and API key. Error: ' . $e->getMessage());
      return FALSE;
    }

    // If our personGroup doesn't exist yet.
    $personGroup = array_filter($personGroups, function ($group) {
      return $group->personGroupId === AzureCognitiveServices::PEOPLE_GROUP;
    });
    if ($personGroup === []) {
      try {
        $this->azure->createPersonGroup(AzureCognitiveServices::PEOPLE_GROUP, 'Automatically created group for Drupal Image Auto Tag module.');
      }
      catch (TransferException $e) {
        $this->messenger()->addError('Could not create the Drupal People group. Error: ' . $e->getMessage());
        return FALSE;
      }
    }
    return TRUE;
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
    // Make sure the field and bundle choice match.
    $entityBundleArray = explode('.', $form_state->getValue('person_entity_bundle'));
    list($entityTypeId, $entityBundleId) = $entityBundleArray;
    $imageField = $form_state->getValue('person_image_field');
    $imageFieldEntityInfo = explode('.', $imageField);
    list($imageFieldEntity, $imageFieldName) = $imageFieldEntityInfo;
    $fieldMap = $this->entityFieldManager->getFieldMapByFieldType('image');
    if (!isset($fieldMap[$entityTypeId][$imageFieldName]) ||
      !\in_array($entityBundleId, $fieldMap[$entityTypeId][$imageFieldName]['bundles'], TRUE)) {
      $form_state->setErrorByName('person_image_field', 'The image field must exist on the person entity bundle.');
    }

    parent::validateForm($form, $form_state);
  }

}
