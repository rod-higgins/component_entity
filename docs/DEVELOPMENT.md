# Component Entity Development Guide

## Table of Contents

- [Development Setup](#development-setup)
- [Architecture Overview](#architecture-overview)
- [Creating Custom Components](#creating-custom-components)
- [Extending the Module](#extending-the-module)
- [Testing](#testing)
- [Debugging](#debugging)
- [Performance Considerations](#performance-considerations)
- [Best Practices](#best-practices)

## Development Setup

### Prerequisites

- **PHP 8.1+** with required extensions
- **Composer 2.x**
- **Node.js 16+** and npm 8+
- **Drupal 10.2+** or **Drupal 11**
- **Git** for version control
- **Docker** (optional, for containerized development)

### Initial Setup

#### 1. Clone and Install

```bash
# Clone the module repository
git clone https://git.drupalcode.org/project/component_entity.git
cd component_entity

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Build assets
npm run build
```

#### 2. Development Environment

##### Using DDEV (Recommended)

```bash
# Initialize DDEV project
ddev config --project-type=drupal10 --docroot=web
ddev start

# Install Drupal with Component Entity
ddev composer require drupal/component_entity
ddev drush en component_entity -y

# Import configuration
ddev drush config:import -y
```

##### Using Lando

```yaml
# .lando.yml
name: component-entity-dev
recipe: drupal10
config:
  webroot: web
  php: '8.1'
  database: mariadb
services:
  node:
    type: node:18
tooling:
  npm:
    service: node
  npx:
    service: node
```

#### 3. Enable Development Mode

```php
// settings.local.php
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Enable verbose error reporting
$config['system.logging']['error_level'] = 'verbose';

// Component Entity specific settings
$config['component_entity.settings']['debug'] = TRUE;
$config['component_entity.settings']['cache']['discovery_cache_ttl'] = 0;
```

### Development Workflow

#### Watch Mode for React Components

```bash
# Start webpack in watch mode
npm run watch

# Or with hot module replacement
npm run dev
```

#### Automatic SDC Sync

```bash
# Watch for SDC component changes
drush component-entity:watch

# In another terminal, watch for file changes
fswatch -o components/ | xargs -n1 -I{} drush cr
```

## Architecture Overview

### Directory Structure

```
component_entity/
├── src/
│   ├── Entity/              # Entity classes
│   ├── Controller/          # Controllers
│   ├── Form/               # Form classes
│   ├── Plugin/             # Plugin implementations
│   │   ├── Field/          # Field plugins
│   │   ├── views/          # Views plugins
│   │   └── ComponentRenderer/ # Renderer plugins
│   ├── Service/            # Service classes
│   └── EventSubscriber/    # Event subscribers
├── js/                     # JavaScript source
│   ├── components/         # React components
│   ├── utils/             # Utility functions
│   └── component-renderer.js # Main renderer
├── css/                    # Stylesheets
├── templates/              # Twig templates
├── config/                 # Configuration
│   ├── install/           # Default config
│   └── schema/            # Config schema
├── tests/                  # Test files
│   ├── src/
│   │   ├── Unit/          # Unit tests
│   │   ├── Kernel/        # Kernel tests
│   │   └── Functional/    # Functional tests
│   └── js/                # JavaScript tests
└── docs/                   # Documentation
```

### Core Classes and Services

#### Entity Classes

```php
namespace Drupal\component_entity\Entity;

/**
 * Main component entity class.
 */
class ComponentEntity extends ContentEntityBase implements ComponentEntityInterface {
  
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    // Render method field
    $fields['render_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Render method'))
      ->setDefaultValue('twig')
      ->setSetting('allowed_values', [
        'twig' => 'Twig (Server-side)',
        'react' => 'React (Client-side)',
      ]);
    
    // React configuration
    $fields['react_config'] = BaseFieldDefinition::create('map')
      ->setLabel(t('React configuration'))
      ->setDefaultValue([
        'hydration' => 'full',
        'progressive' => FALSE,
      ]);
    
    return $fields;
  }
}
```

#### Service Architecture

```php
// Synchronization Service
interface SynchronizationServiceInterface {
  public function syncAll(): array;
  public function syncComponent(string $component_id): bool;
  public function needsSync(string $component_id): bool;
}

// Renderer Service
interface RendererServiceInterface {
  public function render(ComponentEntityInterface $entity, string $view_mode): array;
  public function getRenderMethod(ComponentEntityInterface $entity): string;
}

// Field Mapper Service
interface FieldMapperInterface {
  public function mapPropsToFields(array $props): array;
  public function mapEntityToProps(ComponentEntityInterface $entity): array;
}
```

## Creating Custom Components

### Step 1: Define SDC Component

```yaml
# modules/custom/mymodule/components/custom_hero/custom_hero.component.yml
name: Custom Hero
description: A custom hero component
props:
  title:
    type: string
    required: true
  subtitle:
    type: string
  background_image:
    type: object
    properties:
      src:
        type: string
      alt:
        type: string
  cta_button:
    type: object
    properties:
      text:
        type: string
      url:
        type: string
  theme:
    type: string
    enum: ['light', 'dark', 'brand']
    default: 'light'
slots:
  content:
    title: Main content
  footer:
    title: Footer content
rendering:
  twig: true
  react: true
  default: twig
```

### Step 2: Create Twig Template

```twig
{# custom_hero.html.twig #}
{% set classes = [
  'custom-hero',
  'custom-hero--' ~ theme|default('light'),
  background_image ? 'custom-hero--has-background' : '',
] %}

<div{{ attributes.addClass(classes) }}>
  {% if background_image %}
    <div class="custom-hero__background">
      <img src="{{ background_image.src }}" 
           alt="{{ background_image.alt }}" 
           loading="lazy">
    </div>
  {% endif %}
  
  <div class="custom-hero__content">
    <h1 class="custom-hero__title">{{ title }}</h1>
    
    {% if subtitle %}
      <p class="custom-hero__subtitle">{{ subtitle }}</p>
    {% endif %}
    
    {% if slots.content %}
      <div class="custom-hero__body">
        {{ slots.content }}
      </div>
    {% endif %}
    
    {% if cta_button %}
      <a href="{{ cta_button.url }}" 
         class="custom-hero__cta button button--primary">
        {{ cta_button.text }}
      </a>
    {% endif %}
  </div>
  
  {% if slots.footer %}
    <div class="custom-hero__footer">
      {{ slots.footer }}
    </div>
  {% endif %}
</div>
```

### Step 3: Create React Component

```tsx
// custom_hero.tsx
import React, { useState, useEffect } from 'react';
import type { FC } from 'react';
import './custom_hero.css';

interface CustomHeroProps {
  title: string;
  subtitle?: string;
  backgroundImage?: {
    src: string;
    alt: string;
  };
  ctaButton?: {
    text: string;
    url: string;
  };
  theme?: 'light' | 'dark' | 'brand';
  slots?: {
    content?: React.ReactNode;
    footer?: React.ReactNode;
  };
  drupalContext?: {
    entityId: string;
    entityType: string;
    bundle: string;
    viewMode: string;
  };
}

const CustomHero: FC<CustomHeroProps> = ({
  title,
  subtitle,
  backgroundImage,
  ctaButton,
  theme = 'light',
  slots = {},
  drupalContext,
}) => {
  const [isVisible, setIsVisible] = useState(false);
  
  useEffect(() => {
    // Animate on mount
    const timer = setTimeout(() => setIsVisible(true), 100);
    return () => clearTimeout(timer);
  }, []);
  
  const handleCtaClick = (e: React.MouseEvent) => {
    // Track analytics
    if (window.gtag) {
      window.gtag('event', 'click', {
        event_category: 'CTA',
        event_label: ctaButton?.text,
        component_id: drupalContext?.entityId,
      });
    }
  };
  
  const classes = [
    'custom-hero',
    `custom-hero--${theme}`,
    backgroundImage ? 'custom-hero--has-background' : '',
    isVisible ? 'custom-hero--visible' : '',
  ].filter(Boolean).join(' ');
  
  return (
    <div className={classes}>
      {backgroundImage && (
        <div className="custom-hero__background">
          <img 
            src={backgroundImage.src} 
            alt={backgroundImage.alt}
            loading="lazy"
          />
        </div>
      )}
      
      <div className="custom-hero__content">
        <h1 className="custom-hero__title">{title}</h1>
        
        {subtitle && (
          <p className="custom-hero__subtitle">{subtitle}</p>
        )}
        
        {slots.content && (
          <div className="custom-hero__body">
            {slots.content}
          </div>
        )}
        
        {ctaButton && (
          <a 
            href={ctaButton.url}
            className="custom-hero__cta button button--primary"
            onClick={handleCtaClick}
          >
            {ctaButton.text}
          </a>
        )}
      </div>
      
      {slots.footer && (
        <div className="custom-hero__footer">
          {slots.footer}
        </div>
      )}
    </div>
  );
};

// Register with Drupal
if (typeof window !== 'undefined' && window.Drupal?.componentEntity) {
  window.Drupal.componentEntity.register('custom_hero', CustomHero);
}

export default CustomHero;
```

### Step 4: Add Styles

```scss
// custom_hero.scss
.custom-hero {
  position: relative;
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  overflow: hidden;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.6s ease, transform 0.6s ease;
  
  &--visible {
    opacity: 1;
    transform: translateY(0);
  }
  
  &--light {
    background-color: var(--color-white);
    color: var(--color-text-dark);
  }
  
  &--dark {
    background-color: var(--color-gray-900);
    color: var(--color-text-light);
  }
  
  &--brand {
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    color: var(--color-white);
  }
  
  &__background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
    
    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    &::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.4);
    }
  }
  
  &__content {
    position: relative;
    z-index: 1;
    max-width: 800px;
    text-align: center;
  }
  
  &__title {
    font-size: clamp(2rem, 5vw, 3.5rem);
    margin-bottom: 1rem;
    animation: fadeInUp 0.8s ease;
  }
  
  &__subtitle {
    font-size: clamp(1.1rem, 3vw, 1.5rem);
    margin-bottom: 2rem;
    opacity: 0.9;
    animation: fadeInUp 0.8s ease 0.2s both;
  }
  
  &__cta {
    animation: fadeInUp 0.8s ease 0.4s both;
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
```

## Extending the Module

### Creating Custom Renderer Plugin

```php
namespace Drupal\mymodule\Plugin\ComponentRenderer;

use Drupal\component_entity\Plugin\ComponentRendererBase;

/**
 * Provides a Vue.js renderer for components.
 *
 * @ComponentRenderer(
 *   id = "vue",
 *   label = @Translation("Vue.js Renderer"),
 *   description = @Translation("Renders components using Vue.js")
 * )
 */
class VueRenderer extends ComponentRendererBase {
  
  /**
   * {@inheritdoc}
   */
  public function render(ComponentEntityInterface $entity, $view_mode = 'full', array $context = []) {
    $build = [
      '#theme' => 'component_vue_wrapper',
      '#component_id' => $entity->uuid(),
      '#component_type' => $entity->bundle(),
      '#props' => $this->mapEntityToProps($entity),
      '#attached' => [
        'library' => [
          'mymodule/vue-renderer',
          "mymodule/component.{$entity->bundle()}",
        ],
      ],
    ];
    
    return $build;
  }
  
  /**
   * {@inheritdoc}
   */
  public function isApplicable(ComponentEntityInterface $entity) {
    $config = $entity->getComponentType()->getThirdPartySetting('mymodule', 'vue_enabled');
    return !empty($config);
  }
}
```

### Creating Custom Field Mapper

```php
namespace Drupal\mymodule\Service;

use Drupal\component_entity\Service\FieldMapperInterface;

/**
 * Custom field mapper for specific component types.
 */
class CustomFieldMapper implements FieldMapperInterface {
  
  /**
   * {@inheritdoc}
   */
  public function mapPropsToFields(array $props, string $bundle = NULL) {
    $fields = [];
    
    foreach ($props as $prop_name => $prop_definition) {
      // Custom mapping logic
      if ($prop_definition['type'] === 'custom_type') {
        $fields[$prop_name] = $this->createCustomField($prop_definition);
      } else {
        $fields[$prop_name] = $this->createStandardField($prop_definition);
      }
    }
    
    return $fields;
  }
  
  /**
   * Creates a custom field definition.
   */
  protected function createCustomField(array $prop_definition) {
    return [
      'type' => 'entity_reference',
      'label' => $prop_definition['title'] ?? $prop_name,
      'settings' => [
        'target_type' => 'custom_entity',
        'handler' => 'default',
      ],
      'required' => $prop_definition['required'] ?? FALSE,
    ];
  }
}
```

### Creating Custom Sync Plugin

```php
namespace Drupal\mymodule\Plugin\ComponentSync;

use Drupal\component_entity\Plugin\ComponentSyncBase;

/**
 * @ComponentSync(
 *   id = "pattern_lab",
 *   label = @Translation("Pattern Lab Sync"),
 *   description = @Translation("Syncs Pattern Lab patterns with components")
 * )
 */
class PatternLabSync extends ComponentSyncBase {
  
  /**
   * {@inheritdoc}
   */
  public function discover() {
    $patterns = [];
    
    // Scan Pattern Lab directory
    $pattern_dir = DRUPAL_ROOT . '/patterns';
    $files = glob($pattern_dir . '/**/*.json');
    
    foreach ($files as $file) {
      $data = json_decode(file_get_contents($file), TRUE);
      $patterns[$data['name']] = $this->convertPattern($data);
    }
    
    return $patterns;
  }
  
  /**
   * Converts Pattern Lab pattern to component definition.
   */
  protected function convertPattern(array $pattern) {
    return [
      'id' => $pattern['name'],
      'label' => $pattern['title'],
      'props' => $this->extractProps($pattern),
      'slots' => $this->extractSlots($pattern),
    ];
  }
}
```

## Testing

### Unit Tests

```php
namespace Drupal\Tests\component_entity\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\component_entity\Service\FieldMapper;

/**
 * Tests the field mapper service.
 *
 * @group component_entity
 */
class FieldMapperTest extends UnitTestCase {
  
  /**
   * Tests prop to field mapping.
   */
  public function testPropToFieldMapping() {
    $mapper = new FieldMapper();
    
    $props = [
      'title' => ['type' => 'string', 'required' => TRUE],
      'count' => ['type' => 'integer'],
      'published' => ['type' => 'boolean'],
    ];
    
    $fields = $mapper->mapPropsToFields($props);
    
    $this->assertEquals('string', $fields['title']['type']);
    $this->assertTrue($fields['title']['required']);
    $this->assertEquals('integer', $fields['count']['type']);
    $this->assertEquals('boolean', $fields['published']['type']);
  }
}
```

### Kernel Tests

```php
namespace Drupal\Tests\component_entity\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\component_entity\Entity\ComponentEntity;

/**
 * Tests component entity CRUD operations.
 *
 * @group component_entity
 */
class ComponentEntityCrudTest extends KernelTestBase {
  
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'component_entity',
    'field',
    'user',
    'system',
  ];
  
  /**
   * Tests creating a component entity.
   */
  public function testCreateComponent() {
    $this->installEntitySchema('component');
    
    $component = ComponentEntity::create([
      'bundle' => 'hero_banner',
      'field_title' => 'Test Hero',
      'field_subtitle' => 'Test Subtitle',
      'render_method' => 'react',
    ]);
    
    $component->save();
    
    $this->assertNotEmpty($component->id());
    $this->assertEquals('Test Hero', $component->get('field_title')->value);
    $this->assertEquals('react', $component->get('render_method')->value);
  }
}
```

### Functional Tests

```php
namespace Drupal\Tests\component_entity\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests component entity UI.
 *
 * @group component_entity
 */
class ComponentEntityUiTest extends BrowserTestBase {
  
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['component_entity'];
  
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';
  
  /**
   * Tests component creation form.
   */
  public function testComponentCreationForm() {
    $user = $this->drupalCreateUser([
      'create hero_banner component entities',
      'view published component entities',
    ]);
    
    $this->drupalLogin($user);
    
    // Visit creation form
    $this->drupalGet('/component/add/hero_banner');
    $this->assertSession()->statusCodeEquals(200);
    
    // Fill and submit form
    $edit = [
      'field_title[0][value]' => 'Test Hero',
      'field_subtitle[0][value]' => 'Test Subtitle',
      'render_method[0][value]' => 'react',
    ];
    
    $this->submitForm($edit, 'Save');
    
    // Verify creation
    $this->assertSession()->pageTextContains('Component Test Hero has been created.');
  }
}
```

### JavaScript Tests

```javascript
// __tests__/CustomHero.test.tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CustomHero from '../components/custom_hero/custom_hero';

describe('CustomHero Component', () => {
  const defaultProps = {
    title: 'Test Title',
    subtitle: 'Test Subtitle',
    theme: 'light' as const,
  };
  
  it('renders title and subtitle', () => {
    render(<CustomHero {...defaultProps} />);
    
    expect(screen.getByText('Test Title')).toBeInTheDocument();
    expect(screen.getByText('Test Subtitle')).toBeInTheDocument();
  });
  
  it('renders CTA button when provided', () => {
    const props = {
      ...defaultProps,
      ctaButton: {
        text: 'Click Me',
        url: '/test',
      },
    };
    
    render(<CustomHero {...props} />);
    
    const button = screen.getByText('Click Me');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('href', '/test');
  });
  
  it('applies correct theme class', () => {
    const { container } = render(<CustomHero {...defaultProps} theme="dark" />);
    
    expect(container.firstChild).toHaveClass('custom-hero--dark');
  });
  
  it('tracks analytics on CTA click', () => {
    window.gtag = jest.fn();
    
    const props = {
      ...defaultProps,
      ctaButton: {
        text: 'Track Me',
        url: '/track',
      },
      drupalContext: {
        entityId: '123',
        entityType: 'component',
        bundle: 'custom_hero',
        viewMode: 'full',
      },
    };
    
    render(<CustomHero {...props} />);
    
    const button = screen.getByText('Track Me');
    fireEvent.click(button);
    
    expect(window.gtag).toHaveBeenCalledWith('event', 'click', {
      event_category: 'CTA',
      event_label: 'Track Me',
      component_id: '123',
    });
  });
});
```

## Debugging

### Enable Debug Mode

```php
// settings.local.php
$config['component_entity.settings']['debug'] = TRUE;
$config['component_entity.settings']['verbose_logging'] = TRUE;
```

### Debug Output in Templates

```twig
{# Enable debug output #}
{% if debug %}
  <pre class="component-debug">
    Component: {{ _self.getTemplateName() }}
    Props: {{ props|json_encode(constant('JSON_PRETTY_PRINT')) }}
    Slots: {{ slots|keys|join(', ') }}
    Context: {{ _context|keys|join(', ') }}
  </pre>
{% endif %}
```

### React DevTools Integration

```javascript
// Enable React DevTools
if (process.env.NODE_ENV === 'development') {
  window.__REACT_DEVTOOLS_GLOBAL_HOOK__.inject = function() {
    console.log('React DevTools connected');
  };
}

// Add component display names for debugging
CustomHero.displayName = 'CustomHero';

// Add prop types for runtime validation
if (process.env.NODE_ENV === 'development') {
  CustomHero.propTypes = {
    title: PropTypes.string.isRequired,
    subtitle: PropTypes.string,
    theme: PropTypes.oneOf(['light', 'dark', 'brand']),
  };
}
```

### Logging and Monitoring

```php
// Service with debug logging
class ComponentSyncService {
  
  protected function debug($message, array $context = []) {
    if ($this->config->get('debug')) {
      $this->logger->debug($message, $context);
      
      // Also output to screen if verbose
      if ($this->config->get('verbose_logging')) {
        \Drupal::messenger()->addMessage(
          $this->t('@message: @context', [
            '@message' => $message,
            '@context' => print_r($context, TRUE),
          ]),
          'status'
        );
      }
    }
  }
}
```

## Performance Considerations

### Optimize Bundle Loading

```php
// Lazy load component libraries
function component_entity_page_attachments_alter(&$attachments) {
  // Only load React if React components are present
  $has_react = FALSE;
  
  foreach ($attachments['#attached']['library'] ?? [] as $library) {
    if (strpos($library, 'component_entity/component.') === 0) {
      $has_react = TRUE;
      break;
    }
  }
  
  if (!$has_react) {
    // Remove React core if no components need it
    $key = array_search('component_entity/react-core', $attachments['#attached']['library']);
    if ($key !== FALSE) {
      unset($attachments['#attached']['library'][$key]);
    }
  }
}
```

### Implement Code Splitting

```javascript
// webpack.config.js
module.exports = {
  optimization: {
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendors',
          priority: 10,
        },
        common: {
          minChunks: 2,
          priority: 5,
          reuseExistingChunk: true,
        },
        // Split each component into its own chunk
        components: {
          test: /[\\/]components[\\/]/,
          name(module) {
            const match = module.identifier().match(/[\\/]components[\\/](.*?)[\\/]/);
            return match ? `component-${match[1]}` : 'component';
          },
        },
      },
    },
  },
};
```

### Cache Optimization

```php
// Implement smart caching
class ComponentEntityViewBuilder extends EntityViewBuilder {
  
  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    
    // Add cache contexts
    $build['#cache']['contexts'][] = 'route';
    $build['#cache']['contexts'][] = 'user.permissions';
    
    // Add cache tags
    $build['#cache']['tags'][] = 'component_type:' . $entity->bundle();
    
    // Set cache max age based on content
    if ($entity->get('render_method')->value === 'react') {
      // React components can have shorter cache
      $build['#cache']['max-age'] = 300; // 5 minutes
    }
    
    return $build;
  }
}
```

## Best Practices

### Component Design Principles

1. **Single Responsibility**: Each component should have one clear purpose
2. **Prop Validation**: Always validate and document props
3. **Accessibility**: Ensure WCAG 2.1 AA compliance
4. **Performance**: Lazy load heavy components
5. **Documentation**: Document props, slots, and usage

### Code Organization

```typescript
// Organize component files consistently
components/
  custom_hero/
    custom_hero.component.yml    # SDC definition
    custom_hero.html.twig        # Twig template
    custom_hero.tsx              # React component
    custom_hero.css              # Styles
    custom_hero.test.tsx         # Tests
    custom_hero.stories.tsx      # Storybook stories
    README.md                    # Documentation
```

### Security Considerations

```php
// Always sanitize user input
$safe_value = Html::escape($user_input);

// Validate component data
if (!$this->validator->validate($component_data)) {
  throw new InvalidComponentException('Invalid component data');
}

// Check permissions
if (!$this->currentUser->hasPermission('create ' . $bundle . ' component entities')) {
  throw new AccessDeniedHttpException();
}
```

### Performance Monitoring

```javascript
// Add performance marks
performance.mark('component-render-start');

// Render component
Drupal.componentEntity.render(element, props);

performance.mark('component-render-end');
performance.measure(
  'component-render',
  'component-render-start',
  'component-render-end'
);

// Log slow renders
const measure = performance.getEntriesByName('component-render')[0];
if (measure.duration > 100) {
  console.warn(`Slow component render: ${measure.duration}ms`);
}
```

### Error Handling

```javascript
// Comprehensive error handling
class ComponentRenderer {
  render(element, props) {
    try {
      // Validate inputs
      if (!element) {
        throw new Error('Element is required');
      }
      
      if (!props || typeof props !== 'object') {
        throw new Error('Props must be an object');
      }
      
      // Render component
      const Component = this.getComponent(props.type);
      
      if (!Component) {
        throw new Error(`Component ${props.type} not found`);
      }
      
      ReactDOM.render(<Component {...props} />, element);
      
    } catch (error) {
      // Log error
      console.error('Component render failed:', error);
      
      // Report to monitoring service
      if (window.Sentry) {
        window.Sentry.captureException(error);
      }
      
      // Show user-friendly error
      element.innerHTML = `
        <div class="component-error">
          <p>This component could not be loaded.</p>
          <button onclick="location.reload()">Reload Page</button>
        </div>
      `;
    }
  }
}
```

## Troubleshooting

### Common Issues and Solutions

#### Components Not Syncing

```bash
# Clear all caches
drush cr

# Force resync
drush component-entity:sync --force

# Check permissions
ls -la modules/custom/mymodule/components/

# Check SDC discovery
drush ev "print_r(\Drupal::service('plugin.manager.sdc')->getDefinitions());"
```

#### React Components Not Rendering

```javascript
// Check if component is registered
console.log(Drupal.componentEntity.getAll());

// Check for JavaScript errors
window.addEventListener('error', (e) => {
  console.error('Global error:', e);
});

// Enable verbose logging
localStorage.setItem('component_entity_debug', 'true');
```

#### Performance Issues

```php
// Profile component rendering
$profiler = \Drupal::service('profiler');
$profiler->start('component_render');

$build = $this->renderer->render($entity);

$profiler->stop('component_render');
$profile = $profiler->get('component_render');
\Drupal::logger('performance')->info('Render time: @time ms', [
  '@time' => $profile['duration'],
]);
```