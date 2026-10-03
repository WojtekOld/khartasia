<?php

namespace Drupal\Tests\external_entities\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Plugin\ExternalEntities\DataProcessor\StringCase;

/**
 * The class to test string alteration data processor.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
class DataProcessorStringCaseUnitTest extends UnitTestCase {

  /**
   * Data provider for testStringCaseProcessor().
   *
   * Structure:
   * - data
   * - expected_result
   * - config
   * - test name.
   */
  public static function provideTestStringCaseProcessor() {
    return [
      // No change.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        [
          'format' => '',
          'non_word' => '',
        ],
        'No change test',
      ],
      // To upper case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['TEXT1', 'TEXT2', 'TEXT3', 'TEXT4', 'SOME OTHER TEXT', '1 MORE "TE_XT".'],
        [
          'format' => 'upper',
          'non_word' => '',
        ],
        'To upper case test',
      ],
      // To lower case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'text2', 'text3', 'text4', 'some other text', '1 more "te_xt".'],
        [
          'format' => 'lower',
          'non_word' => '',
        ],
        'To lower case test',
      ],
      // To lower case no non-word.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'text2', 'text3', 'text4', 'someothertext', '1moretext'],
        [
          'format' => 'lower',
          'non_word' => 'remove',
        ],
        'To lower case no non-word test',
      ],
      // To lower case underscores.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'text2', 'text3', 'text4', 'some_other_text', '1_more_te_xt'],
        [
          'format' => 'lower',
          'non_word' => 'underscore',
        ],
        'To lower case underscores test',
      ],
      // Escaped characters on source test.
      [
        ["Test\\tsp\\xC3\\xA9cial & other$.\\n+Blah\\\\bla/bli #1;\\r\\n"],
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        [
          'format' => '',
          'non_word' => 'escaped',
        ],
        'Escaped characters on source test',
      ],
      // Escape characters from source test.
      [
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        ["Test\\tsp\\303\\251cial & other$.\\n+Blah\\\\bla/bli #1;\\r\\n"],
        [
          'format' => '',
          'non_word' => 'to_escape',
        ],
        'Escape characters from source test',
      ],
      // URL-decode source test.
      [
        ["Test%09sp%C3%A9cial%20%26%20other%24.%0A%2BBlah%5Cbla%2Fbli%20%231%3B%0D%0A"],
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        [
          'format' => '',
          'non_word' => 'url_encoded',
        ],
        'URL-decode source test',
      ],
      // URL-encode source test.
      [
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        ["Test%09sp%C3%A9cial%20%26%20other%24.%0A%2BBlah%5Cbla%2Fbli%20%231%3B%0D%0A"],
        [
          'format' => '',
          'non_word' => 'to_url_encode',
        ],
        'URL-encode source test',
      ],
      // Decode HTML entities from source test.
      [
        ["Test\tsp&eacute;cial &amp; other$.\n+Blah\bla/bli #1;\r\n"],
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        [
          'format' => '',
          'non_word' => 'with_html_entities',
        ],
        'Decode HTML entities from source test',
      ],
      // Encode source into HTML entities test.
      [
        ["Test\tspécial & other\$.\n+Blah\\bla/bli #1;\r\n"],
        ["Test\tsp&eacute;cial &amp; other$.\n+Blah\bla/bli #1;\r\n"],
        [
          'format' => '',
          'non_word' => 'to_html_entities',
        ],
        'Encode source into HTML entities test',
      ],
      // Title case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['Text1', 'Text2', 'Text3', 'Text4', 'Some Other Text', '1 More "Te_Xt".'],
        ['format' => 'title'],
        'Title test',
      ],
      // To lower camelCase case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'text2', 'text3', 'text4', 'someOtherText', '1MoreTeXt'],
        [
          'format' => 'lower_camel_case',
          'non_word' => '',
        ],
        'To lower camelCase test',
      ],
      // To upper camelCase case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['Text1', 'Text2', 'Text3', 'Text4', 'SomeOtherText', '1MoreTeXt'],
        [
          'format' => 'upper_camel_case',
          'non_word' => '',
        ],
        'To upper camelCase test',
      ],
      // To snake_case.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        ['text1', 'text2', 'text3', 't_ext4', 'some_other_text', '1_more_te_xt'],
        [
          'format' => 'snake_case',
          'non_word' => '',
          'prefix' => '',
          'suffix' => '',
        ],
        'To snake_case test',
      ],
      // Prefix and suffix.
      [
        ['text1', 'Text2', 'TEXT3', 'tEXT4', 'Some other text', '1 more "Te_xt".'],
        [
          'BeForetext1AfteR',
          'BeForetext2AfteR',
          'BeForetext3AfteR',
          'BeForet_ext4AfteR',
          'BeForesome_other_textAfteR',
          'BeFore1_more_te_xtAfteR',
        ],
        [
          'format' => 'snake_case',
          'non_word' => '',
          'prefix' => 'BeFore',
          'suffix' => 'AfteR',
        ],
        'To snake_case test',
      ],
    ];
  }

  /**
   * Tests numeric unit data processor.
   *
   * @dataProvider provideTestStringCaseProcessor
   */
  #[DataProvider('provideTestStringCaseProcessor')]
  public function testStringCaseProcessor(
    array $data,
    array $expected_result,
    array $config,
    string $test_name,
  ) {
    $string_translation = $this->createStub(TranslationInterface::class);

    $logger = $this->createStub(LoggerInterface::class);

    $field_def = $this->createStub(FieldDefinitionInterface::class);

    $data_processor = new StringCase($config, 'stringcase', [], $string_translation, $logger);

    $result = $data_processor->processData($data, $field_def, 'value');
    $this->assertEquals($expected_result, $result, $test_name);
  }

}
