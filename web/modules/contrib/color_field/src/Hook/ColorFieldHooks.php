<?php

namespace Drupal\color_field\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\color_field\ColorHex;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations for color_field.
 */
class ColorFieldHooks implements ContainerInjectionInterface {
  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('module_handler'),
    );
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.color_field':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Color Field is simple field that use a hexadecimal notation (HEX) for the combination of Red, Green, and Blue color values (RGB). See the <a href=":field">Field module help</a> and the <a href=":field_ui">Field UI help</a> pages for general information on fields and how to create and manage them. For more information, see the <a href=":link_documentation">online documentation for the Link module</a>.', [
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':field_ui' => $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString() : '#',
          ':link_documentation' => 'https://drupal.org/documentation/modules/link',
        ]) . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing and displaying color fields') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>settings</em> and the <em>display</em> of the link field can be configured separately. See the <a href=":field_ui">Field UI help</a> for more information on how to manage fields and their display.', [
          ':field_ui' => $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Adding link text') . '</dt>';
        $output .= '<dd>' . $this->t('In the field settings you can define additional link text to be <em>optional</em> or <em>required</em> in any link field.') . '</dd>';
        $output .= '<dt>' . $this->t('Displaying link text') . '</dt>';
        $output .= '<dd>' . $this->t('If link text has been submitted for a URL, then by default this link text is displayed as a link to the URL. If you want to display both the link text <em>and</em> the URL, choose the appropriate link format from the drop-down menu in the <em>Manage display</em> page. If you only want to display the URL even if link text has been submitted, choose <em>Link</em> as the format, and then change its <em>Format settings</em> to display <em>URL only</em>.') . '</dd>';
        $output .= '<dt>' . $this->t('Adding attributes to links') . '</dt>';
        $output .= '<dd>' . $this->t('You can add attributes to links, by changing the <em>Format settings</em> in the <em>Manage display</em> page. Adding <em>rel="nofollow"</em> notifies search engines that links should not be followed.') . '</dd>';
        $output .= '<dt>' . $this->t('Validating URLs') . '</dt>';
        $output .= '<dd>' . $this->t('All links are validated after a link field is filled in. They can include anchors or query strings.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme(): array {
    $theme = [];
    $theme['color_field_formatter_swatch'] = [
      'variables' => [
        'shape' => NULL,
        'color' => NULL,
        'width' => NULL,
        'height' => NULL,
        'attributes' => NULL,
      ],
    ];
    $theme['color_field_formatter_swatch_option'] = [
      'variables' => [
        'id' => NULL,
        'name' => NULL,
        'input_type' => NULL,
        'value' => NULL,
        'shape' => NULL,
        'color' => NULL,
        'width' => NULL,
        'height' => NULL,
        'attributes' => NULL,
      ],
    ];
    return $theme;
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $tokens = [];
    $tokens['color_field']['hex'] = [
      'name' => $this->t('HEX'),
      'description' => $this->t("Hex style color values."),
      'type' => 'hex',
    ];
    $tokens['hex']['with_opacity'] = [
      'name' => $this->t('With Opacity'),
      'description' => $this->t('HEX style color with opacity'),
    ];
    $tokens['color_field']['rgb'] = [
      'name' => $this->t('RGB'),
      'description' => $this->t("RGB style color values."),
      'type' => 'rgb',
    ];
    $tokens['rgb']['rgba'] = [
      'name' => $this->t('RGBA'),
      'description' => $this->t("Color value expressed as rgba(xx,xx,xx,x.x)."),
    ];
    $tokens['rgb']['red'] = [
      'name' => $this->t('Red'),
      'description' => $this->t("RGB Color value for red only expressed as an int."),
    ];
    $tokens['rgb']['blue'] = [
      'name' => $this->t('Blue'),
      'description' => $this->t("RGB Color value for blue only expressed as an int."),
    ];
    $tokens['rgb']['green'] = [
      'name' => $this->t('Green'),
      'description' => $this->t("RGB Color value for green only expressed as an int."),
    ];
    $tokens['rgb']['opacity'] = [
      'name' => $this->t('Opacity'),
      'description' => $this->t("RGB Color value for opacity only expressed as a float."),
    ];
    return [
      'types' => [
        'color_field' => [
          'name' => $this->t('Color Field'),
          'description' => $this->t('Values from a specific color field item.'),
          'needs-data' => 'color_field',
        ],
        'rgb' => [
          'name' => $this->t('RGB'),
          'description' => $this->t('RGB Values from a specific color field item.'),
          'needs-data' => 'color_field',
        ],
        'hex' => [
          'name' => $this->t('HEX'),
          'description' => $this->t('HEX values from a specific color field item'),
          'needs-data' => 'color_field',
        ],
      ],
      'tokens' => $tokens,
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public static function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if ($type === 'color_field' && !empty($data['color_field'])) {
      /** @var \Drupal\color_field\Plugin\Field\FieldType\ColorFieldType $color_field */
      $color_field = $data['color_field'];
      try {
        $color_hex = new ColorHex($color_field->color, is_null($color_field->opacity) ? NULL : (float) $color_field->opacity);
      }
      catch (\Exception $e) {
        // If the color value is invalid, return empty strings for all tokens.
        foreach ($tokens as $original) {
          $replacements[$original] = '';
        }
        return $replacements;
      }
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'hex':
            $replacements[$original] = $color_hex->toString(FALSE);
            break;

          case 'hex:with_opacity':
            $replacements[$original] = $color_hex->toString(TRUE);
            break;

          case 'rgb':
            $replacements[$original] = $color_hex->toRgb()->toString(FALSE);
            break;

          case 'rgba':
          case 'rgb:rgba':
            $replacements[$original] = $color_hex->toRgb()->toString(TRUE);
            break;

          case 'rgb:red':
            $replacements[$original] = $color_hex->toRgb()->getRed();
            break;

          case 'rgb:blue':
            $replacements[$original] = $color_hex->toRgb()->getBlue();
            break;

          case 'rgb:green':
            $replacements[$original] = $color_hex->toRgb()->getGreen();
            break;

          case 'rgb:opacity':
            $replacements[$original] = $color_hex->toRgb()->getOpacity();
            break;
        }
      }
    }
    return $replacements;
  }

  /**
   * Implements hook_content_export_field_value_alter().
   *
   * Include the color_field in exports by the single_content_sync module.
   */
  #[Hook('content_export_field_value_alter')]
  public static function contentExportFieldValueAlter(mixed &$value, FieldItemListInterface $field): void {
    switch ($field->getFieldDefinition()->getType()) {
      case 'color_field_type':
        $value[] = [
          'color' => $field->color,
        ];
        break;
    }
  }

}
