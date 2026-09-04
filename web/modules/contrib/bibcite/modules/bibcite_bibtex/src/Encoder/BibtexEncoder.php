<?php

namespace Drupal\bibcite_bibtex\Encoder;

use RenanBr\BibTexParser\Processor\TagNameCaseProcessor;
use RenanBr\BibTexParser\Processor\NamesProcessor;
use RenanBr\BibTexParser\Processor\KeywordsProcessor;
use RenanBr\BibTexParser\Processor\DateProcessor;
use RenanBr\BibTexParser\Processor\FillMissingProcessor;
use RenanBr\BibTexParser\Processor\TrimProcessor;
use RenanBr\BibTexParser\Processor\UrlFromDoiProcessor;
use RenanBr\BibTexParser\Listener;
use RenanBr\BibTexParser\Parser;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Drupal\bibcite_bibtex\BibciteLatexToUnicode;

/**
 * BibTeX format encoder.
 */
class BibtexEncoder implements EncoderInterface, DecoderInterface {

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'bibtex';

  /**
   * The listener object.
   *
   * @var \RenanBr\BibTexParser\Listener
   */
  protected Listener $listener;

  /**
   * The parser object.
   *
   * @var \RenanBr\BibTexParser\Parser
   */
  protected Parser $parser;

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format): bool {
    return $format == static::$format;
  }

  /**
   * Initializes the listener and parser objects.
   */
  public function setUpParser() {
    // Create and configure a Listener.
    $this->listener = new Listener();
    $this->listener->addProcessor(new TagNameCaseProcessor(CASE_LOWER));
    $this->listener->addProcessor(new NamesProcessor());
    $this->listener->addProcessor(new KeywordsProcessor());
    $this->listener->addProcessor(new DateProcessor());
    $this->listener->addProcessor(new FillMissingProcessor([/* ... */]));
    $this->listener->addProcessor(new TrimProcessor());
    $this->listener->addProcessor(new UrlFromDoiProcessor());
    // @todo Check whether the required package is installed.
    // $this->listener->addProcessor(new Processor\LatexToUnicodeProcessor());
    // Create a Parser and attach the listener.
    $this->parser = new Parser();
    $this->parser->addListener($this->listener);
  }

  /**
   * {@inheritdoc}
   */
  public function decode(string $data, string $format, array $context = []): mixed {
    $data = $this->lineEndingsReplace($data);
    $this->setUpParser();

    /*
     * Handle type as case-insensitive.
     * Tags should be handled as case-insensitive as well, but it's done by BibtexParser library.
     * @see https://www.drupal.org/node/2890060
     */
    $data = preg_replace_callback('/^@(\w+){/m', function ($word) {
      return '@' . strtolower($word[1]) . '{';
    }, $data);

    /*
     * Ignore "type" tag inside records.
     * Type in BibTeX must go before content.
     * @see https://en.wikipedia.org/wiki/BibTeX
     * @see https://www.drupal.org/node/2882855
     */
    $data = preg_replace('/^\s*type *= *{.*}.*$/m', '', $data);

    // Latex to Unicode conversion.
    $searchReplace = BibciteLatexToUnicode::getTranstabLatexUnicode();
    $searchStrings = array_keys($searchReplace);
    foreach ($searchStrings as $key => $value) {
      // Add search pattern delimiters.
      $searchStrings[$key] = "/" . $value . "/";
    }
    $replaceStrings = array_values($searchReplace);
    $data = preg_replace($searchStrings, $replaceStrings, $data);

    $this->parser->parseString($data);
    $parsed = $this->listener->export();

    foreach ($parsed as $i => $entry) {
      // Remove newlines from the abstract and title.
      foreach (['abstract', 'title'] as $field) {
        if (isset($entry[$field])) {
          $entry[$field] = preg_replace('/\n */', ' ', $entry[$field]);
        }
      }
      // Each author/editor should be a single string, not an array.
      if (isset($entry['author'])) {
        $entry['author'] = array_map([static::class, 'formatName'], $entry['author']);
      }
      if (isset($entry['editor'])) {
        $entry['editor'] = array_map([static::class, 'formatName'], $entry['editor']);
      }
      // By default, month is mapped to bibcite_date, with format mm/YYYY.
      if (isset($entry['month'])) {
        $entry['month'] = date('m/Y', \strtotime($entry['month'] . ' ' . ($entry['year'] ?? '')));
      }
      // The parser uses citation-key, but bibcite uses reference.
      if (isset($entry['citation-key'])) {
        $entry['reference'] = $entry['citation-key'];
      }
      unset($entry['_type'], $entry['citation-key'], $entry['_original']);
      // Strip curly braces from each entry.
      array_walk_recursive($entry, function (&$string) {
        $string = preg_replace('/[{}]/', '', $string);
      });

      $parsed[$i] = $entry;
    }

    $keys = array_keys($parsed);
    if (count($keys) === 0 || $keys[0] === -1) {
      $format_definition = \Drupal::service('plugin.manager.bibcite_format')->getDefinition($format);
      $format_label = $format_definition['label'];
      throw new UnexpectedValueException("Incorrect '{$format_label}' format or empty set.");
    }
    $this->processEntries($parsed);

    return $parsed;
  }

  /**
   * Format a name.
   *
   * @param string[] $parts
   *   A keyed array with parts of a name, using some or all of the keys
   *   - first
   *   - von
   *   - last
   *   - jr.
   *
   * @return string
   *   A name like "First von Last Jr" if all parts are present.
   */
  protected static function formatName(array $parts): string {
    $empty_name = ['first' => '', 'von' => '', 'last' => '', 'jr' => ''];
    return implode(' ', array_filter(array_replace(
      $empty_name,
      array_intersect_key($parts, $empty_name),
    )));
  }

  /**
   * Convert line endings function.
   *
   * Different sources uses different line endings in exports.
   * Convert all line endings to unix which is expected by BibtexParser.
   *
   * @param string $data
   *   Input string from file.
   *
   * @return string
   *   Unix formatted string
   */
  public function lineEndingsReplace($data) {
    /*
     * \R is escape sequence of newline, equivalent to the following: (\r\n|\n|\x0b|\f|\r|\x85)
     * @see http://www.pcre.org/original/doc/html/pcrepattern.html Newline sequences.
     */
    return preg_replace("/\R/", "\n", $data);
  }

  /**
   * Workaround about some things in BibtexParser library.
   *
   * @param array $parsed
   *   List of parsed entries.
   */
  protected function processEntries(array &$parsed) {
    foreach ($parsed as &$entry) {
      if (!empty($entry['pages']) && is_array($entry['pages'])) {
        $entry['pages'] = implode('-', $entry['pages']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format): bool {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   */
  public function encode(mixed $data, string $format, array $context = []): string {
    if (isset($data['type'])) {
      $data = [$data];
    }

    $data = array_map(function ($raw) {
      return $this->buildEntry($raw);
    }, $data);

    return implode("\n", $data);
  }

  /**
   * Build BibTeX entry string.
   *
   * @param array $data
   *   Array of BibTeX values.
   *
   * @return string
   *   Formatted BibTeX string.
   */
  protected function buildEntry(array $data) {
    if (empty($data['reference'])) {
      $data['reference'] = $data['type'];
    }
    // Hotfix "editor" field so it behaves the same as "author" field.
    if (isset($data['editor'])) {
      $data['editor'] = implode(' and ', $data['editor']);
    }

    $entry = $this->buildStart($data['type'], $data['reference']);

    unset($data['type']);
    unset($data['reference']);

    foreach ($data as $key => $value) {
      $entry .= $this->buildLine($key, $value);
    }

    $entry .= $this->buildEnd();

    // Unicode to Latex conversion.
    $searchReplace = BibciteLatexToUnicode::getTranstabUnicodeLatex();
    $searchStrings = array_keys($searchReplace);
    foreach ($searchStrings as $key => $value) {
      // Add search pattern delimiters.
      $searchStrings[$key] = "/" . $value . "/";
    }
    $replaceStrings = array_values($searchReplace);
    $entry = preg_replace($searchStrings, $replaceStrings, $entry);

    return $entry;
  }

  /**
   * Build first string for BibTeX entry.
   *
   * @param string $type
   *   Publication type in BibTeX format.
   * @param string $reference
   *   Reference key.
   *
   * @return string
   *   First entry string.
   */
  protected function buildStart($type, $reference) {
    return '@' . $type . '{' . $reference . ',' . "\n";
  }

  /**
   * Build entry line.
   *
   * @param string $key
   *   Line key.
   * @param string|array $value
   *   Line value.
   *
   * @return string
   *   Entry line.
   */
  protected function buildLine($key, $value) {
    switch ($key) {
      case 'author':
        $value = implode(' and ', $value);
        break;

      case 'keywords':
        $value = implode(', ', $value);
        break;
    }

    return '  ' . $key . ' = {' . $value . '},' . "\n";
  }

  /**
   * Build the end of BibTeX entry.
   *
   * @return string
   *   End line for the BibTeX entry.
   */
  protected function buildEnd() {
    return "}\n";
  }

}
