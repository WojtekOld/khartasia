<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns_ckeditor5\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\ui_patterns_ckeditor5\Controller\PreviewController;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The preview route renders what the editor asks for, and only that.
 *
 * Pins the widget preview: rendered component and its label, the CSRF
 * header, the entity context for a saved and for an unsaved host.
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('ui_patterns')]
#[Group('ui_patterns_ckeditor5')]
#[RunTestsInSeparateProcesses]
final class PreviewControllerTest extends CKEditor5KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createPageType();
    \user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content', 'create page content']);
  }

  /**
   * The rendered component comes with its label in a header.
   */
  public function testPreview(): void {
    $response = $this->preview([
      'component_id' => self::COMPONENT,
      'component_settings' => Json::encode([
        'props' => ['string' => $this->propSource('textfield', ['value' => 'Hello'])],
      ]),
    ]);
    self::assertSame(200, $response->getStatusCode());
    self::assertMatchesRegularExpression('#ui-patterns-props-string">\s*Hello\s*<#', (string) $response->getContent());
    self::assertSame('UI Patterns Test component', $response->headers->get('Drupal-Component-Label'));
  }

  /**
   * Without the CSRF header the preview is denied.
   */
  public function testMissingCsrfHeader(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->preview(['component_id' => self::COMPONENT], FALSE);
  }

  /**
   * An unknown component is not found.
   */
  public function testUnknownComponent(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->preview(['component_id' => 'ui_patterns_test:missing']);
  }

  /**
   * A saved host entity gives the sources their entity context.
   */
  public function testSavedEntityContext(): void {
    $node = Node::create(['type' => 'page', 'title' => 'The host title']);
    $node->save();
    $response = $this->preview([
      'component_id' => self::COMPONENT,
      'component_settings' => ['props' => ['string' => $this->propSource('token', ['value' => '[node:title]'])]],
      'entity_type' => 'node',
      'entity_id' => $node->id(),
    ]);
    self::assertMatchesRegularExpression('#ui-patterns-props-string">\s*The host title\s*<#', (string) $response->getContent());
  }

  /**
   * A host not saved yet stands as a sample entity of its bundle.
   */
  public function testUnsavedEntityContext(): void {
    $response = $this->preview([
      'component_id' => self::COMPONENT,
      'component_settings' => ['props' => ['string' => $this->propSource('token', ['value' => '[node:type]'])]],
      'entity_type' => 'node',
      'entity_id' => '',
      'entity_bundle' => 'page',
    ]);
    self::assertMatchesRegularExpression('#ui-patterns-props-string">\s*page\s*<#', (string) $response->getContent());
  }

  /**
   * The routes are only for text formats using the filter.
   */
  public function testFormatUsesFilter(): void {
    $with = FilterFormat::load(self::FORMAT);
    self::assertTrue(PreviewController::formatUsesComponentEmbedFilter($with)->isAllowed());
    $without = FilterFormat::create(['format' => 'plain', 'name' => 'Plain']);
    $without->save();
    self::assertFalse(PreviewController::formatUsesComponentEmbedFilter($without)->isAllowed());
  }

  /**
   * Posts a preview request as the JavaScript plugin does.
   */
  private function preview(array $content, bool $with_header = TRUE): Response {
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($with_header) {
      // Anonymous requests only need the header to be there.
      $server['HTTP_X_DRUPAL_COMPONENTPREVIEW_CSRF_TOKEN'] = 'present';
    }
    $request = Request::create('/editor/preview/ui_patterns_ckeditor5/' . self::FORMAT, 'POST', [], [], [], $server, Json::encode($content));
    return PreviewController::create($this->container)->preview(FilterFormat::load(self::FORMAT), $request);
  }

}
