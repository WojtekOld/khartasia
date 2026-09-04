<?php

declare(strict_types=1);

namespace Drupal\Tests\imagemagick\Kernel;

use Drupal\imagemagick\ArgumentMode;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for ImageMagick toolkit operations.
 */
#[Group('imagemagick')]
#[RunTestsInSeparateProcesses]
class ToolkitOperationsTest extends KernelTestBase {

  use ToolkitSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'imagemagick',
    'image_effects',
    'system',
    'file_mdm',
    'user',
    'sophron',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'imagemagick', 'sophron', 'file_mdm']);
  }

  /**
   * Create a new image and inspect the arguments.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testCreateNewImageArguments(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $image = $this->imageFactory->get();
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $image->createNew(100, 200);
    $this->assertSame([0, 1, 2], array_keys($toolkit->arguments()->find('/^./', NULL, ['image_toolkit_operation' => 'create_new'])));
    $this->assertSame([0, 1, 2], array_keys($toolkit->arguments()->find('/^./', NULL, ['image_toolkit_operation_plugin_id' => 'imagemagick_create_new'])));
    $this->assertSame(['-size', '100x200', 'xc:transparent'], $toolkit->arguments()->toArray(ArgumentMode::PostSource));
    $this->assertSame("[-size] [100x200] [xc:transparent]", $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test failures of CreateNew.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testCreateNewImageFailures(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $image = $this->imageFactory->get();
    $image->createNew(-50, 20);
    $this->assertFalse($image->isValid(), 'CreateNew with negative width fails.');
    $image->createNew(50, 20, 'foo');
    $this->assertFalse($image->isValid(), 'CreateNew with invalid extension fails.');
    $image->createNew(50, 20, 'gif', '#foo');
    $this->assertFalse($image->isValid(), 'CreateNew with invalid color hex string fails.');
    $image->createNew(50, 20, 'gif', '#ff0000');
    $this->assertTrue($image->isValid(), 'CreateNew with valid arguments validates the Image.');
  }

  /**
   * Test operations on image with no dimensions.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testOperationsOnImageWithNoDimensions(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $image = $this->imageFactory->get();
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $image->createNew(100, 200);
    $this->assertSame(100, $image->getWidth());
    $this->assertsame(200, $image->getHeight());
    $toolkit->setWidth(NULL);
    $toolkit->setHeight(NULL);
    $this->assertNull($image->getWidth());
    $this->assertNull($image->getHeight());
    $this->assertFalse($image->crop(10, 10, 20, 20));
    $this->assertNull($image->getWidth());
    $this->assertNull($image->getHeight());
    $this->assertFalse($image->scaleAndCrop(10, 10));
    $this->assertNull($image->getWidth());
    $this->assertNull($image->getHeight());
    $this->assertFalse($image->scale(5));
    $this->assertNull($image->getWidth());
    $this->assertNull($image->getHeight());
    // Resize sets explicitly the new dimension, so it should not fail.
    $this->assertTrue($image->resize(50, 100));
    $this->assertSame(50, $image->getWidth());
    $this->assertsame(100, $image->getHeight());
    $this->assertSame("[-size] [100x200] [xc:transparent] [-resize] [50x100!]", $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test 'scale_and_crop' operation.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testScaleAndCropOperation(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $image = $this->imageFactory->get();
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $image->createNew(100, 200);
    $image->apply('scale_and_crop', [
      'x' => 1,
      'y' => 1,
      'width' => 5,
      'height' => 10,
    ]);
    $this->assertSame("[-size] [100x200] [xc:transparent] [-resize] [5x10!] [-crop] [5x10+1+1] [+repage]", $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test 'scale_and_crop' operation with no anchor passed in.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testScaleAndCropNoAnchorOperation(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $image = $this->imageFactory->get();
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $image->createNew(100, 200);
    $image->apply('scale_and_crop', ['width' => 5, 'height' => 10]);
    $this->assertSame("[-size] [100x200] [xc:transparent] [-resize] [5x10!] [-crop] [5x10+0+0] [+repage]", $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test the 'rotate' operation.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testRotateOperation(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $jpeg_uri = __DIR__ . '/../../../misc/test-exif.jpeg';
    $gif_uri = __DIR__ . '/../../../misc/test-multi-frame.gif';
    $sources = [
      $jpeg_uri => [
        'no_bg' => [
          'degrees' => 90,
          'background' => NULL,
          'expected' => '[-background] [#FFFFFF] [-rotate] [90] [+repage] [-resize] [75x100!]',
        ],
        'with_bg' => [
          'degrees' => 45,
          'background' => '#FF0000',
          'expected' => '@\[-background] \[#FF0000(FF)?] \[-rotate] \[45] \[\+repage] \[-resize] \[125x125!]@',
        ],
      ],
      $gif_uri => [
        'no_bg' => [
          'degrees' => 270,
          'background' => NULL,
          'expected' => '[-background] [transparent] [-rotate] [270] [+repage] [-resize] [29x60!]',
        ],
        'with_bg' => [
          'degrees' => 135,
          'background' => '#FF0000',
          'expected' => '@\[-background] \[#FF0000(FF)?] \[-rotate] \[135] \[\+repage] \[-resize] \[64x64!]@',
        ],
      ],
    ];
    foreach ($sources as $source_uri => $data_set) {
      foreach ($data_set as $data_label => $item) {
        $image = $this->imageFactory->get($source_uri);
        static::assertTrue($image->isValid());
        /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
        $toolkit = $image->getToolkit();
        $image->rotate($item['degrees'], $item['background']);
        if (str_starts_with($item['expected'], '@')) {
          // The GraphicsMagick-based effect may strip out the alpha part
          // of the color upstream, so support that.
          static::assertMatchesRegularExpression($item['expected'], $toolkit->arguments()->toDebugString(ArgumentMode::PostSource), sprintf('[%s::%s]', $source_uri, $data_label));
        }
        else {
          static::assertEquals($item['expected'], $toolkit->arguments()->toDebugString(ArgumentMode::PostSource), sprintf('[%s::%s]', $source_uri, $data_label));
        }
      }
    }
  }

}
