<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_library\Element;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\ui_patterns_library\StoriesSyntaxConverter;
use Drupal\ui_patterns_library\StoryPluginManager;

/**
 * Renders a component story.
 */
class ComponentElementAlter implements TrustedCallbackInterface {

  public function __construct(
    protected ComponentPluginManager $componentPluginManager,
    protected StoryPluginManager $storyPluginManager,
    protected StoriesSyntaxConverter $storiesConverter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['alter'];
  }

  /**
   * Alter SDC component element.
   */
  public function alter(array $element): array {
    return $this->loadStory($element);
  }

  /**
   * Load story from component definition.
   */
  protected function loadStory(array $element): array {
    if (!isset($element['#story'])) {
      return $element;
    }
    $story_id = $element['#story'];
    $component = $this->componentPluginManager->getDefinition($element['#component']);
    $component['stories'] = $this->storyPluginManager->getComponentStories($element['#component']);
    if (!isset($component['stories'])) {
      return $element;
    }
    if (!isset($component['stories'][$story_id])) {
      return $element;
    }
    $story = $component['stories'][$story_id];
    $slots = \array_merge($story['slots'] ?? [], $element['#slots'] ?? []);
    $element['#slots'] = $this->storiesConverter->convertSlots($slots);
    $element['#props'] = \array_merge($story['props'] ?? [], $element['#props'] ?? []);
    return $element;
  }

}
