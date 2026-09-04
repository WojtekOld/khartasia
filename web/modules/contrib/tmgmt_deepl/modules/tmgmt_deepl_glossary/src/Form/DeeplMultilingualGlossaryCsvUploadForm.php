<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Form controller for CSV upload of glossary dictionary entries.
 *
 * @ingroup tmgmt_deepl_glossary
 *
 * @phpstan-consistent-constructor
 */
class DeeplMultilingualGlossaryCsvUploadForm extends FormBase {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface
   */
  protected DeeplMultilingualGlossaryApiInterface $glossaryApi;

  /**
   * The DeepL glossary helper service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface
   */
  protected DeeplMultilingualGlossaryHelperInterface $glossaryHelper;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The glossary entity.
   *
   * @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface|null
   */
  protected ?DeeplMultilingualGlossaryInterface $glossary = NULL;

  /**
   * The parsed CSV entries.
   *
   * @var array|null
   */
  protected ?array $csvEntries = NULL;

  /**
   * Constructs a DeeplMultilingualGlossaryCsvUploadForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface $glossary_api
   *   The DeepL glossary API service.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface $glossary_helper
   *   The DeepL glossary helper service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(
    protected EntityRepositoryInterface $entity_repository,
    protected EntityTypeBundleInfoInterface $entity_type_bundle_info,
    DeeplMultilingualGlossaryApiInterface $glossary_api,
    DeeplMultilingualGlossaryHelperInterface $glossary_helper,
    AccountInterface $account,
  ) {
    $this->glossaryApi = $glossary_api;
    $this->glossaryHelper = $glossary_helper;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('tmgmt_deepl_glossary.ml.api'),
      $container->get('tmgmt_deepl_glossary.ml.helper'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'deepl_multilingual_glossary_csv_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get the glossary from the route.
    $deepl_ml_glossary = $this->getRouteMatch()->getParameter('deepl_ml_glossary');
    if (!$deepl_ml_glossary instanceof DeeplMultilingualGlossaryInterface) {
      $this->messenger()->addError($this->t('Glossary not found.'));
      return $form;
    }

    $this->glossary = $deepl_ml_glossary;
    $translator = $deepl_ml_glossary->getTranslator();

    if (!$translator instanceof Translator) {
      $this->messenger()->addError($this->t('Translator not found for this glossary.'));
      return $form;
    }
    // Set the translator on the API service.
    $this->glossaryApi->setTranslator($translator);

    // Get available languages.
    $language_mappings = $translator->getRemoteLanguagesMappings();
    $source_languages = $this->glossaryHelper->getAllowedLanguages();
    $available_languages = [];
    foreach ($language_mappings as $language_mapping) {
      assert(is_string($language_mapping));
      $language_mapping = $this->glossaryHelper->fixLanguageMappings($language_mapping);
      if (isset($source_languages[$language_mapping])) {
        $available_languages[$language_mapping] = $source_languages[$language_mapping];
      }
    }
    asort($available_languages);

    // Heading.
    $form['heading'] = [
      '#markup' => '<h2>' . $this->t('Upload CSV Dictionary') . '</h2>',
    ];

    // Description.
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Upload a CSV file to create a glossary dictionary. The CSV file should contain two columns: source text and target text, separated by a comma. One entry per line.') . '</p>',
    ];

    // Source language.
    $form['source_lang'] = [
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#description' => $this->t('The language of the source texts in the CSV file.'),
      '#options' => $available_languages,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select source language -'),
    ];

    // Target language.
    $form['target_lang'] = [
      '#type' => 'select',
      '#title' => $this->t('Target language'),
      '#description' => $this->t('The language of the target texts in the CSV file.'),
      '#options' => $available_languages,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select target language -'),
    ];

    // Allow overwriting an existing dictionary for the same language pair.
    $form['allow_overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overwrite existing dictionary'),
      '#description' => $this->t('If checked, an existing dictionary for the selected language pair will be replaced with the uploaded CSV entries.'),
      '#default_value' => FALSE,
    ];

    // CSV file upload.
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV file'),
      '#description' => [
        '#theme' => 'file_upload_help',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'csv'],
          'FileSizeLimit' => ['fileLimit' => Environment::getUploadMaxSize()],
        ],
        '#cardinality' => 1,
      ],
      '#required' => TRUE,
    ];

    // Actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload CSV'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('entity.deepl_ml_glossary.edit_form', ['deepl_ml_glossary' => $deepl_ml_glossary->id()]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Get values.
    $source_lang_value = $form_state->getValue('source_lang');
    $target_lang_value = $form_state->getValue('target_lang');
    $source_lang = is_string($source_lang_value) ? $source_lang_value : '';
    $target_lang = is_string($target_lang_value) ? $target_lang_value : '';

    // Validate source and target language are different.
    if ($source_lang === $target_lang) {
      $form_state->setErrorByName('target_lang', $this->t('Source and target languages must be different.'));
    }

    // Validate language pair is valid for DeepL.
    $this->validateLanguagePair($form_state, $source_lang, $target_lang);

    // Validate no existing dictionary unless overwrite is explicitly allowed.
    $allow_overwrite = (bool) $form_state->getValue('allow_overwrite');
    if (!$allow_overwrite && $this->glossary instanceof DeeplMultilingualGlossaryInterface) {
      $this->validateNoExistingDictionary($form_state, $source_lang, $target_lang);
    }

    // Validate and parse CSV file.
    $this->validateAndParseCsvFile($form, $form_state);
  }

  /**
   * Validate language pair is valid for DeepL.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $source_lang
   *   The source language code.
   * @param string $target_lang
   *   The target language code.
   */
  protected function validateLanguagePair(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    $valid_language_pairs = $this->glossaryHelper->getValidSourceTargetLanguageCombinations();

    $match = FALSE;
    foreach ($valid_language_pairs as $valid_language_pair) {
      assert(is_array($valid_language_pair));
      if (isset($valid_language_pair[$source_lang]) && ($valid_language_pair[$source_lang] === $target_lang)) {
        $match = TRUE;
        break;
      }
    }

    if (!$match) {
      $form_state->setErrorByName('source_lang', $this->t('Select a valid source/ target language.'));
      $form_state->setErrorByName('target_lang', $this->t('Select a valid source/ target language.'));
    }
  }

  /**
   * Validate no existing dictionary with same language pair.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $source_lang
   *   The source language code.
   * @param string $target_lang
   *   The target language code.
   */
  protected function validateNoExistingDictionary(FormStateInterface $form_state, string $source_lang, string $target_lang): void {
    if (!$this->glossary instanceof DeeplMultilingualGlossaryInterface) {
      return;
    }

    $glossary_id = $this->glossary->getGlossaryId();
    if (!is_string($glossary_id)) {
      return;
    }

    if ($this->glossaryHelper->hasMultilingualGlossaryDictionary($glossary_id, $source_lang, $target_lang)) {
      $form_state->setErrorByName('source_lang', $this->t('A dictionary with this source/ target language combination already exists in this glossary.'));
      $form_state->setErrorByName('target_lang', $this->t('A dictionary with this source/ target language combination already exists in this glossary.'));
    }
  }

  /**
   * Validate and parse CSV file.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateAndParseCsvFile(array $form, FormStateInterface $form_state): void {
    // Check if there are validation errors already.
    $errors = $form_state->getErrors();
    if (count($errors) > 0) {
      return;
    }

    // Drupal names file inputs as files[<element_name>], so uploaded files are
    // stored under the 'files' key in the request files bag.
    $all_files = $this->getRequest()->files->get('files', []);
    $files = is_array($all_files) ? ($all_files['csv_file'] ?? NULL) : NULL;

    if ($files === NULL) {
      $form_state->setErrorByName('csv_file', $this->t('Please upload a CSV file.'));
      return;
    }

    // Support both single and #multiple file uploads.
    $file = is_array($files) ? reset($files) : $files;

    if (!$file instanceof UploadedFile || !$file->isValid()) {
      $form_state->setErrorByName('csv_file', $this->t('The file could not be uploaded.'));
      return;
    }

    // Read the file content.
    try {
      $file_path = $file->getRealPath();
      if (!is_string($file_path)) {
        $form_state->setErrorByName('csv_file', $this->t('The file could not be uploaded.'));
        return;
      }
      $content = file_get_contents($file_path);
      if ($content === FALSE) {
        // @codeCoverageIgnoreStart
        // Defensive: file_get_contents only fails on a filesystem-level fault,
        // which cannot be triggered through a unit test once the path is valid.
        $form_state->setErrorByName('csv_file', $this->t('Could not read the uploaded file.'));
        return;
        // @codeCoverageIgnoreEnd
      }
    }
    catch (\Exception $e) {
      // @codeCoverageIgnoreStart
      // Defensive: the read calls above do not throw under normal operation.
      $form_state->setErrorByName('csv_file', $this->t('Could not read the uploaded file: @message', [
        '@message' => $e->getMessage(),
      ]));
      return;
      // @codeCoverageIgnoreEnd
    }

    // Parse CSV content.
    try {
      $entries = $this->glossaryHelper->parseCsvContent($content);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('csv_file', $this->t('Error parsing CSV file: @message', [
        '@message' => $e->getMessage(),
      ]));
      return;
    }

    // Validate entries.
    if (count($entries) === 0) {
      $form_state->setErrorByName('csv_file', $this->t('The CSV file contains no valid entries.'));
      return;
    }

    // Validate entries for uniqueness and whitespace.
    $this->glossaryHelper->validateCsvEntries($entries, $form_state, 'csv_file');

    // Store entries for submit handler.
    $this->csvEntries = $entries;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->glossary instanceof DeeplMultilingualGlossaryInterface) {
      $this->messenger()->addError($this->t('Glossary not found.'));
      return;
    }

    if ($this->csvEntries === NULL) {
      $this->messenger()->addError($this->t('No CSV entries to upload.'));
      return;
    }

    $source_lang_value = $form_state->getValue('source_lang');
    $target_lang_value = $form_state->getValue('target_lang');
    $source_lang = is_string($source_lang_value) ? $source_lang_value : '';
    $target_lang = is_string($target_lang_value) ? $target_lang_value : '';

    // @codeCoverageIgnoreStart
    // Create dictionary entity.
    $dictionary = $this->glossaryHelper->createOrUpdateDictionaryFromEntries(
      $this->glossary,
      $source_lang,
      $target_lang,
      $this->csvEntries ?? []
    );
    // @codeCoverageIgnoreEnd
    if ($dictionary instanceof DeeplMultilingualGlossaryDictionaryInterface) {
      $this->messenger()->addMessage($this->t('Successfully uploaded @count entries to dictionary.', [
        '@count' => count($this->csvEntries),
      ]));

      // Redirect to glossary edit form.
      $form_state->setRedirect('entity.deepl_ml_glossary.edit_form', [
        'deepl_ml_glossary' => $this->glossary->id(),
      ]);
    }
    else {
      $this->messenger()->addError($this->t('Failed to create dictionary. Please check the logs for more information.'));
    }
  }

}
