<?php

declare(strict_types = 1);

namespace Drupal\media_auto_tag\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\media_auto_tag\AzureCognitiveServices;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for configuring azure services.
 */
class TrainingForm extends FormBase {

  const PEOPLE_GROUP = 'drupal_media_auto_tag_people';

  /**
   * CogSer service.
   *
   * @var \Drupal\media_auto_tag\AzureCognitiveServices
   */
  protected $azure;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\media_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AzureCognitiveServices $azureCognitiveServices) {
    $this->azure = $azureCognitiveServices;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $static = new static(
      $container->get('config.factory'),
      $container->get('media_auto_tag.azure')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_auto_tag_training';
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
    // Build a table of existing People.
    // @TODO: caching!
    $form['people'] = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Name'),
        'images' => $this->t('Images'),
      ],
      '#title' => $this->t('Known people'),
      '#rows' => [],
      '#empty' => $this->t('There are no known people yet'),
    ];
    // Validate the API resource is accessible and setup correctly.
    if ($this->validateAzureResource()) {
      try {
        foreach ($this->azure->listPeople(self::PEOPLE_GROUP) as $person) {
          $form['people']['#rows'][$person['id']] = [
            'name' => $person['name'] ?? '',
            'images' => \count($person['faces']),
          ];
        }
      }
      catch (TransferException $e) {
        $this->messenger()
          ->addError('Error listing People: ' . $e->getMessage());
      }
    }
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
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('azure_endpoint') === NULL) {
      $form_state->setErrorByName('azure_endpoint', 'The Azure endpoint must not be empty');
    }
    if ($form_state->getValue('azure_service_key') === NULL) {
      $form_state->setErrorByName('azure_service_key', 'The Azure service key must not be empty');
    }
    // Test the connection.
    try {
      $this->azure->listPersonGroups();
    } catch (TransferException $e) {
      $this->messenger()
        ->addError('Could not connect with this endpoint and API key. Error: ' . $e->getMessage());
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * @return bool
   */
  protected function validateAzureResource() {

    try {
      $personGroups = $this->azure->listPersonGroups();
      // Create our personGroup, if it doesn't already exist.
      if (empty($personGroups)) {
        $this->azure->createPersonGroup(self::PEOPLE_GROUP, 'Drupal media auto tag group');
      }
    }
    catch (TransferException $e) {
      $this->messenger()->addError('Could not connect with this endpoint and API key. Error: ' . $e->getMessage());
      return FALSE;
    }
    return TRUE;
  }
}
