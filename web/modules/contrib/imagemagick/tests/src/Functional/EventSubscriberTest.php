<?php

declare(strict_types=1);

namespace Drupal\Tests\imagemagick\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\file_mdm\FileMetadataInterface;
use Drupal\file_mdm\FileMetadataManagerInterface;
use Drupal\imagemagick\ArgumentMode;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\imagemagick\Kernel\ToolkitSetupTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for ImagemagickEventSubscriber.
 */
#[Group('imagemagick')]
#[RunTestsInSeparateProcesses]
class EventSubscriberTest extends BrowserTestBase {

  use ToolkitSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'imagemagick', 'file_mdm'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Provides a list of available modules.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->moduleList = \Drupal::service('extension.list.module');
  }

  /**
   * Test module's event subscriber.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testEventSubscriber(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $this->prepareImageFileHandling();

    $fmdm = \Drupal::service(FileMetadataManagerInterface::class);

    // Change the Advanced Colorspace setting, must be included in the command
    // line.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('advanced.colorspace', 'GRAY')
      ->save();

    $image_uri = "public://image-test.png";
    $image = $this->imageFactory->get($image_uri);
    if (!$image->isValid()) {
      $this->fail("Could not load image $image_uri.");
    }
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    // Check the source colorspace.
    if ($toolkit_settings['binaries'] === 'imagemagick') {
      $this->assertSame('SRGB', $toolkit->getColorspace());
    }
    else {
      $this->assertNull($toolkit->getColorspace());
    }

    // Setup a list of arguments.
    $toolkit->arguments()
      ->add(["-resize", "100x75!"])
      ->add(["-quality", "75"]);

    // Save the derived image.
    $image->save($image_uri . '.derived');

    // Check expected command line.
    $expected = "[-resize] [100x75!] [-quality] [75] [-colorspace] [GRAY]";
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));

    // Check that the colorspace has been actually changed in the file.
    Cache::InvalidateTags([
      'config:imagemagick.file_metadata_plugin.imagemagick_identify',
    ]);
    $fmdm->release($image_uri . '.derived');
    $image_md = $fmdm->uri($image_uri . '.derived');
    $image = $this->imageFactory->get($image_uri . '.derived');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $this->assertSame(FileMetadataInterface::LOADED_FROM_FILE, $image_md->isMetadataLoaded(ImagemagickToolkit::FILE_METADATA_PLUGIN_ID));
    if ($toolkit_settings['binaries'] === 'imagemagick') {
      $this->assertSame('GRAY', $toolkit->getColorspace());
    }
    else {
      $this->assertNull($toolkit->getColorspace());
    }

    // Change the Prepend settings, must be included in the command line.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('prepend', '-debug All')
      ->save();
    $image = $this->imageFactory->get($image_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()
      ->add(["-resize", "100x75!"])
      ->add(["-quality", "75"]);
    $image->save($image_uri . '.derived');
    $expected = "[-resize] [100x75!] [-quality] [75] [-colorspace] [GRAY]";
    $this->assertSame('[-debug] [All]', $toolkit->arguments()->toDebugString(ArgumentMode::PreSource));
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test coalescence of Animated GIFs.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testGifCoalesce(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $this->prepareImageFileHandling();

    $image_uri = $this->moduleList->getPath('imagemagick') . '/misc/test-multi-frame.gif';

    // By default, no coalesce of animated GIFs.
    $image = $this->imageFactory->get($image_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->add(["-resize", "100x75!"]);
    $image->save("public://imagetest/coalesced.gif");
    $expected = "[-resize] [100x75!] [-quality] [100]";
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));

    // Change the Advanced Coalesce setting, '-coalesce' must now be included
    // in the command line.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('advanced.coalesce', TRUE)
      ->save();
    $image = $this->imageFactory->get($image_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->add(["-resize", "100x75!"]);
    $image->save("public://imagetest/coalesced.gif");
    $expected = "[-coalesce] [-resize] [100x75!] [-quality] [100]";
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));

    // Single frame GIF should not be coalesceable.
    $image = $this->imageFactory->get("public://image-test.gif");
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->add(["-resize", "100x75!"]);
    $image->save("public://imagetest/coalesced.gif");
    $expected = "[-resize] [100x75!] [-quality] [100]";
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));

    // PNG should not be coalesceable.
    $image = $this->imageFactory->get("public://image-test.png");
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->add(["-resize", "100x75!"]);
    $image->save("public://imagetest/coalesced.png");
    $expected = "[-resize] [100x75!] [-quality] [100]";
    $this->assertSame($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
  }

  /**
   * Test the default source frame index functionality.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array<string, mixed> $toolkit_settings
   *   The settings of the toolkit to set up.
   */
  #[DataProvider('providerToolkitConfiguration')]
  public function testDefaultSourceFrames(string $toolkit_id, string $toolkit_config, array $toolkit_settings): void {
    $this->setUpToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
    $this->prepareImageFileHandling();
    $binary = $toolkit_settings['binaries'];

    /** @var \Drupal\file_mdm\FileMetadataManagerInterface $fmdm */
    $fmdm = \Drupal::service(FileMetadataManagerInterface::class);

    $this->fileSystem->copy($this->moduleList->getPath('imagemagick') . '/misc/test-multipage.pdf', 'public://', FileExists::Replace);
    $this->fileSystem->copy($this->moduleList->getPath('imagemagick') . '/misc/test-multi-frame.gif', 'public://', FileExists::Replace);

    $pdf_uri = 'public://test-multipage.pdf';

    // Ensure the 'identify' command will pick up at most the first two pages
    // of all PDFs, however, the conversion will still only convert to the first
    // frame by default when converting between formats.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('image_formats.PDF.identify_frames', '[0-1]')
      ->set('image_formats.PDF.enabled', FALSE)
      ->save();

    // Assert that the default identify frames are used even if the file type
    // is not enabled. It'll be identified based on its extension type.
    $image = $this->imageFactory->get($pdf_uri);
    // The image should be identified based on it, but not valid.
    static::assertFalse($image->isValid());
    // Although the PDF type is not allowed, the frame count identified was
    // still limited to 2 frames.
    $pdf_md = $fmdm->uri($pdf_uri);
    self::assertNotNull($pdf_md);
    static::assertEquals(2, $pdf_md->getMetadata(ImagemagickToolkit::FILE_METADATA_PLUGIN_ID, 'frames_count'));

    $fmdm->deleteCachedMetadata($pdf_uri);
    $fmdm->release($pdf_uri);

    // Enable support for PDFs.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('image_formats.PDF.enabled', TRUE)
      ->save();

    // Ensure that conversions from PDF to PNG will only make use of the first
    // frame by default.
    $image = $this->imageFactory->get($pdf_uri);
    static::assertTrue($image->save('public://imagetest/test-multipage-default.pdf.png'));
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    static::assertEquals('[0]', $toolkit->arguments()->getSourceFrames());

    $fmdm->deleteCachedMetadata($pdf_uri);
    $fmdm->release($pdf_uri);
    $image = $this->imageFactory->get($pdf_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    // Identify only picks up the two frames as configured.
    static::assertEquals(2, $toolkit->getFrames());
    static::assertTrue($image->save('public://imagetest/test-multipage-single-frame.pdf.png'));
    static::assertEquals('[0]', $toolkit->arguments()->getSourceFrames());

    // If the source image explicitly sets the source frame, then the default
    // behavior will no longer apply against it.
    $image = $this->imageFactory->get($pdf_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->setSourceFrames('[1-2]');
    if ($binary === 'imagemagick') {
      // ImageMagick will create multiple images if the multiple source frames
      // are specified and destination type doesn't support multi-images.
      // @see https://imagemagick.org/script/command-line-options.php#adjoin
      // @todo ensure this returns true when multiple files are created.
      static::assertFalse($image->save('public://imagetest/test-multipage.pdf.png'));
      static::assertFileExists('public://imagetest/test-multipage.pdf-1.png');
      static::assertFileExists('public://imagetest/test-multipage.pdf-2.png');
      static::assertFileDoesNotExist('public://imagetest/test-multipage.pdf.png');
    }
    else {
      // GraphicsMagick will only create the single file by default because it
      // relies on the user explicitly specifying the '+adjoin' option - which
      // is the default behavior in ImageMagick.
      static::assertTrue($image->save('public://imagetest/test-multipage.pdf.png'));
      static::assertFileDoesNotExist('public://imagetest/test-multipage.pdf-1.png');
      static::assertFileDoesNotExist('public://imagetest/test-multipage.pdf-2.png');
      static::assertFileExists('public://imagetest/test-multipage.pdf.png');
    }
    static::assertEquals('[1-2]', $toolkit->arguments()->getSourceFrames());

    // Test PDF to PDF conversions still work as expected despite the 'identify'
    // operation only picking up the first 2 pages.
    $image = $this->imageFactory->get($pdf_uri);
    static::assertTrue($image->save('public://imagetest/test-multipage-destination.pdf'));
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->clear('image_formats.PDF.identify_frames')
      ->save();
    $pdf_image = $this->imageFactory->get('public://imagetest/test-multipage-destination.pdf');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    // Identify only picks up the two frames as configured.
    static::assertEquals(2, $toolkit->getFrames());
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $pdf_toolkit */
    $pdf_toolkit = $pdf_image->getToolkit();
    static::assertEquals(10, $pdf_toolkit->getFrames());

    // Test PDF to PDF conversions will use the default 'convert' frames
    // settings.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('image_formats.PDF.identify_frames', '[0-1]')
      // Process at most 5 pages within the PDFs.
      ->set('image_formats.PDF.convert_frames', '[0-4]')
      ->save();
    $image = $this->imageFactory->get($pdf_uri);
    static::assertTrue($image->save('public://imagetest/test-multipage-converted.pdf'));
    $image = $this->imageFactory->get($pdf_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    // Ignore the default source frames to use for the 'convert' operation.
    $toolkit->arguments()->setSourceFrames('');
    static::assertTrue($image->save('public://imagetest/test-multipage-converted-ignores-default.pdf'));
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->clear('image_formats.PDF.identify_frames')
      ->save();
    // The default 'convert_frames' configuration is used.
    $pdf_image = $this->imageFactory->get('public://imagetest/test-multipage-converted.pdf');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $pdf_toolkit */
    $pdf_toolkit = $pdf_image->getToolkit();
    static::assertEquals(5, $pdf_toolkit->getFrames());
    // The default 'convert_frames' configuration is ignored.
    $pdf_image = $this->imageFactory->get('public://imagetest/test-multipage-converted-ignores-default.pdf');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $pdf_toolkit */
    $pdf_toolkit = $pdf_image->getToolkit();
    static::assertEquals(10, $pdf_toolkit->getFrames());

    // Test that an animated GIF is created from pages 2 and 3 of the PDF.
    $image = $this->imageFactory->get($pdf_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->setSourceFrames('[1-2]');
    static::assertTrue($image->save('public://imagetest/test-multipage.pdf.gif'));
    $gif_image = $this->imageFactory->get('public://imagetest/test-multipage.pdf.gif');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $gif_toolkit */
    $gif_toolkit = $gif_image->getToolkit();
    static::assertEquals(2, $gif_toolkit->getFrames());

    // Enable GIF coalesce and ensure that only the first frame of all GIFs
    // is picked up.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->set('advanced.coalesce', TRUE)
      ->set('image_formats.GIF.identify_frames', '[0]')
      ->save();

    // Multi frame GIF should no longer add the '-coalesce' option since only 1
    // frame will be detected by default.
    $gif_uri = 'public://test-multi-frame.gif';
    $image = $this->imageFactory->get($gif_uri);
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();
    $toolkit->arguments()->add(['-resize', '100x75!']);
    static::assertTrue($image->save('public://imagetest/uncoalesced-multi-frame.gif'));
    $expected = '[-resize] [100x75!] [-quality] [100]';
    static::assertEquals($expected, $toolkit->arguments()->toDebugString(ArgumentMode::PostSource));
    // The destination image is still a multi-frame GIF.
    \Drupal::configFactory()->getEditable('imagemagick.settings')
      ->clear('image_formats.GIF.identify_frames')
      ->save();
    $gif_image = $this->imageFactory->get('public://imagetest/uncoalesced-multi-frame.gif');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $gif_toolkit */
    $gif_toolkit = $gif_image->getToolkit();
    static::assertEquals(13, $gif_toolkit->getFrames());
  }

}
