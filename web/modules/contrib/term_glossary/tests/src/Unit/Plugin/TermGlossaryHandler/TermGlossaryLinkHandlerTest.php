<?php

declare(strict_types=1);

namespace Drupal\Tests\term_glossary\Unit\Plugin\TermGlossaryHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\term_glossary\Plugin\TermGlossaryHandler\TermGlossaryLinkHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TermGlossaryLinkHandler plugin.
 *
 * @group term_glossary
 */
#[CoversClass(TermGlossaryLinkHandler::class)]
#[Group('term_glossary')]
class TermGlossaryLinkHandlerTest extends UnitTestCase {

  /**
   * The handler under test.
   *
   * @var \Drupal\term_glossary\Plugin\TermGlossaryHandler\TermGlossaryLinkHandler
   */
  protected TermGlossaryLinkHandler $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Link::createFromRoute needs the url_generator service.
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $generated_url = new GeneratedUrl();
    $generated_url->setGeneratedUrl('/taxonomy/term/42');
    $url_generator->method('generateFromRoute')
      ->willReturn($generated_url);

    $unrouted_url_assembler = $this->createMock(
      UnroutedUrlAssemblerInterface::class,
    );

    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    $container->set(
      'unrouted_url_assembler', $unrouted_url_assembler,
    );
    \Drupal::setContainer($container);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $this->handler = new TermGlossaryLinkHandler(
      [],
      'link',
      ['id' => 'link', 'title' => 'Link to term'],
      $config_factory,
    );
  }

  /**
   * Tests buildMatchTag creates a link render array.
   *
   * Calls buildMatchTag() with tid=42 and name='Drupal'.
   * Verifies the render array has #type='link', #title set
   * to the match text, #url pointing to the
   * entity.taxonomy_term.canonical route with the correct
   * tid, and the 'glos-term' CSS class in URL options.
   */
  public function testBuildMatchTag(): void {
    $match_tag = [];
    $term_data = [
      'tid' => 42,
      'name' => 'Drupal',
      'lang' => 'en',
    ];

    $this->handler->buildMatchTag(
      $match_tag, 'Drupal', $term_data,
    );

    // The link handler uses Link::createFromRoute which
    // produces a render array with #type = link.
    $this->assertEquals('link', $match_tag['#type']);
    $this->assertEquals('Drupal', $match_tag['#title']);
    $this->assertInstanceOf(
      '\Drupal\Core\Url', $match_tag['#url'],
    );
    $this->assertEquals(
      'entity.taxonomy_term.canonical',
      $match_tag['#url']->getRouteName(),
    );
    $route_params = $match_tag['#url']->getRouteParameters();
    $this->assertEquals(42, $route_params['taxonomy_term']);
    $this->assertContains(
      'glos-term',
      $match_tag['#url']->getOption('attributes')['class'],
    );
  }

}
