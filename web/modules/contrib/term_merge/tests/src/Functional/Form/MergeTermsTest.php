<?php

namespace Drupal\Tests\term_merge\Functional\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\term_merge\Form\MergeTerms;
use Drupal\Tests\term_merge\Functional\MergeTermsTestBase;

/**
 * Tests the term merge form.
 *
 * @group term_merge
 */
class MergeTermsTest extends MergeTermsTestBase {

  /**
   * Tests the title callback for the term merge form.
   *
   * @test
   */
  public function hasTitleCallback(): void {
    $sut = $this->createSubjectUnderTest();
    $vocabulary = $this->createVocabulary();

    $expected = new TranslatableMarkup('Merge %vocabulary terms', ['%vocabulary' => $vocabulary->label()]);
    self::assertEquals($expected, $sut->titleCallback($vocabulary));
  }

  /**
   * Tests the term merge form structure.
   *
   * @test
   */
  public function buildsAutocompleteForm(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();

    $actual = $sut->buildForm([], new FormState(), $vocabulary);
    self::assertEquals($this->getEmptyFormExpectation($vocabulary), $actual);
  }

  /**
   * Tests the autocomplete is restricted to the vocabulary.
   *
   * @test
   */
  public function restrictsAutocompleteToVocabulary(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();

    $actual = $sut->buildForm([], new FormState(), $vocabulary);

    $expected = [$vocabulary->id() => $vocabulary->id()];
    self::assertEquals($expected, $actual['terms']['#selection_settings']['target_bundles']);
  }

  /**
   * Test data provider for validatesSelectedTerms.
   *
   * @return array
   *   An array of selections. Each selection has contains the following values:
   *   - selected_terms: the submitted autocomplete values.
   *   - expecting_errors: a boolean indicating the form is expected to generate
   *     an error.
   */
  public static function validatesSelectedTermsTestDataProvider(): array {
    $test_data['No terms selected'] = [
      'selected_terms' => [],
      'expecting_errors' => TRUE,
    ];

    $test_data['One term selected'] = [
      'selected_terms' => [['target_id' => 1]],
      'expecting_errors' => FALSE,
    ];

    $test_data['Two terms selected'] = [
      'selected_terms' => [['target_id' => 1], ['target_id' => 2]],
      'expecting_errors' => FALSE,
    ];

    $test_data['three terms selected'] = [
      'selected_terms' => [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ],
      'expecting_errors' => FALSE,
    ];

    return $test_data;
  }

  /**
   * Checks the form validation for the merge terms form.
   *
   * @param array $selected_terms
   *   The submitted autocomplete values.
   * @param bool $expecting_errors
   *   If a validation error is expected.
   *
   * @test
   *
   * @dataProvider validatesSelectedTermsTestDataProvider
   */
  public function validatesSelectedTerms(array $selected_terms, bool $expecting_errors) {
    $vocabulary = $this->createVocabulary();
    $this->createTerm($vocabulary);
    $this->createTerm($vocabulary);
    $this->createTerm($vocabulary);
    $sut = $this->createSubjectUnderTest();

    $form_state = new FormState();
    $form_state->setValue('terms', $selected_terms);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    $sut->validateForm($form, $form_state);

    self::assertSame($expecting_errors, !empty($form_state->getErrors()));
  }

  /**
   * Tests the form redirects to the confirm form.
   *
   * @test
   */
  public function redirectsToConfirmationForm(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();

    $form_state = new FormState();
    $form_state->setValue('terms', [['target_id' => 1], ['target_id' => 2]]);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    $sut->submitForm($form, $form_state);

    $route_name = 'entity.taxonomy_vocabulary.merge_target';
    $route_parameters['taxonomy_vocabulary'] = $vocabulary->id();
    $expected = new Url($route_name, $route_parameters);
    self::assertEquals($expected, $form_state->getRedirect());
  }

  /**
   * Tests merge terms are saved to the temp store.
   *
   * @test
   */
  public function setsLocalStorage(): void {
    $vocabulary = $this->createVocabulary();
    $sut = $this->createSubjectUnderTest();
    $form_state = new FormState();
    $expected_term_ids = [1, 2];
    $form_state->setValue('terms', [
      ['target_id' => 1],
      ['target_id' => 2],
      ['target_id' => 2],
    ]);
    $form = $sut->buildForm([], $form_state, $vocabulary);

    self::assertEmpty($this->privateTempStoreFactory->get('term_merge')->get('terms'));
    $sut->submitForm($form, $form_state);

    self::assertEquals($expected_term_ids, $this->privateTempStoreFactory->get('term_merge')->get('terms'));
  }

  /**
   * Returns the expected form structure.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary the form is built for.
   *
   * @return array
   *   A renderable array.
   */
  private function getEmptyFormExpectation(VocabularyInterface $vocabulary): array {
    return [
      'terms' => [
        '#type' => 'entity_autocomplete',
        '#title' => new TranslatableMarkup('Terms to merge'),
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
        '#selection_handler' => 'default:taxonomy_term',
        '#selection_settings' => [
          'target_bundles' => [$vocabulary->id() => $vocabulary->id()],
        ],
        '#maxlength' => NULL,
        '#description' => new TranslatableMarkup('Select two or more terms to merge together. Separate multiple terms with commas.'),
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#button_type' => 'primary',
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Merge'),
        ],
      ],
    ];
  }

  /**
   * Creates the form class used for rendering the merge terms form.
   *
   * @return \Drupal\term_merge\Form\MergeTerms
   *   The form class used for rendering the merge terms form.
   */
  private function createSubjectUnderTest(): MergeTerms {
    return new MergeTerms($this->privateTempStoreFactory, \Drupal::moduleHandler());
  }

  /**
   * {@inheritdoc}
   */
  protected function numberOfTermsToSetUp(): int {
    return 0;
  }

}
