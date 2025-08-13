# Component Entity Module

A Drupal 11 module that creates a seamless bridge between SDC (Single Directory Components) and Drupal's entity system, making SDC components first-class Drupal citizens with support for both Twig and React rendering.

## Overview

The Component Entity module transforms SDC components into regular Drupal content entities that behave exactly like any other content entity bundle (nodes, taxonomy terms, etc.). This approach follows established Drupal patterns completely while adding powerful component management capabilities.

### Core Features

- **True Drupal-Native Components**: Components are regular content entities with bundles
- **Seamless SDC Integration**: Automatic synchronization between SDC definitions and entity bundles
- **Dual Rendering Power**: Choose between Twig (server-side) or React (client-side) rendering per component instance
- **Zero Learning Curve**: Uses standard Drupal patterns - Field UI, Views, permissions, etc.
- **Maximum Flexibility**: Mix SSR and CSR rendering methods as needed
- **Performance Optimized**: Lazy loading, progressive enhancement, and smart caching
- **Future-Proof**: Built on pure Drupal core APIs with modern frontend support

## What Does This Module Solve?

### The Challenge

Modern web development demands component-based architecture, but Drupal's traditional approach creates a disconnect between:

- **Developers** who want to use modern component patterns (SDC, React)
- **Site Builders** who need familiar Drupal tools (Field UI, Views)
- **Content Editors** who require simple, intuitive interfaces
- **End Users** who expect fast, interactive experiences

Existing solutions often:
- Require custom UI and workflows that don't follow Drupal patterns
- Force a choice between server-side OR client-side rendering
- Create proprietary systems that break on Drupal upgrades
- Add unnecessary complexity to simple component management

### The Solution

Component Entity bridges this gap by making components behave exactly like standard Drupal content:

```
SDC Component Definition ←→ [Sync Bridge] ←→ Component Entity Bundle
         ↓                                            ↓
   Defines props/slots                    Has fields managed via Field UI
         ↓                                            ↓
   Twig OR React rendering                    Choose render method
         ↓                                            ↓
              Both output the same component HTML
```

## Architecture

### SDC Discovery and Synchronization Process

The module implements a sophisticated discovery and synchronization system that creates a true bi-directional bridge between SDC components and Drupal entities:

#### 1. Component Discovery Process

The discovery happens through multiple triggers:

```php
// Automatic discovery on cache clear
hook_cache_flush() {
  ComponentSyncService::discoverComponents();
}

// Manual discovery via Drush
drush component-entity:sync

// Programmatic discovery
\Drupal::service('component_entity.sync')->syncComponents();
```

**Discovery Flow:**

1. **SDC Scanner**: Scans all enabled modules/themes for `components/` directories
2. **Component Parser**: Reads `.component.yml` files and extracts metadata
3. **Bundle Generator**: Creates entity bundles with sanitized machine names
4. **Field Mapper**: Maps SDC props to Drupal field types automatically
5. **Schema Validator**: Validates component definitions against JSON Schema

```yaml
# SDC Component: components/hero/hero.component.yml
name: Hero Banner
props:
  title:
    type: string
    required: true
  image:
    type: object
    properties:
      src: 
        type: string
      alt:
        type: string
  cta_buttons:
    type: array
    items:
      type: object

# Automatic Entity Bundle Creation:
# Bundle ID: hero_banner
# Fields created:
# - field_title (string, required)
# - field_image (json field with src/alt structure)
# - field_cta_buttons (json field, multiple values)
```

#### 2. Bi-Directional Save Process

The module maintains perfect synchronization between SDC definitions and entity configurations:

**Forward Sync (SDC → Entity):**

```php
class ComponentSyncService {
  
  public function syncComponent($sdc_id, $component_definition) {
    // 1. Generate bundle from SDC ID
    $bundle = $this->generateBundleName($sdc_id);
    
    // 2. Create/update component type config entity
    $component_type = ComponentType::load($bundle) ?? ComponentType::create(['id' => $bundle]);
    $component_type->setSdcId($sdc_id);
    $component_type->setLabel($component_definition['name']);
    
    // 3. Map props to fields
    foreach ($component_definition['props'] as $prop_name => $prop_schema) {
      $field_name = 'field_' . $prop_name;
      $field_type = $this->mapSchemaToFieldType($prop_schema);
      
      // Create field storage if needed
      if (!FieldStorageConfig::loadByName('component', $field_name)) {
        FieldStorageConfig::create([
          'entity_type' => 'component',
          'field_name' => $field_name,
          'type' => $field_type,
          'cardinality' => $prop_schema['type'] === 'array' ? -1 : 1,
        ])->save();
      }
      
      // Create field instance
      if (!FieldConfig::loadByName('component', $bundle, $field_name)) {
        FieldConfig::create([
          'entity_type' => 'component',
          'bundle' => $bundle,
          'field_name' => $field_name,
          'label' => $prop_schema['title'] ?? $prop_name,
          'required' => $prop_schema['required'] ?? FALSE,
        ])->save();
      }
    }
    
    // 4. Configure rendering support
    $component_type->setRenderingConfiguration([
      'twig_enabled' => TRUE,
      'react_enabled' => file_exists($this->getReactComponentPath($sdc_id)),
      'default_method' => 'twig',
    ]);
    
    $component_type->save();
  }
}
```

**Reverse Sync (Entity → SDC):**

The revolutionary feature - create SDC components from Drupal's Field UI:

```php
class ComponentEntityFieldUIController {
  
  public function onFieldAdded($entity_type, $bundle, $field_name) {
    if ($entity_type !== 'component') return;
    
    // Get component type
    $component_type = ComponentType::load($bundle);
    if (!$component_type->getSdcId()) {
      // This is a Field UI created component - generate SDC
      $this->generateSDCFromEntity($component_type);
    }
    
    // Update existing SDC component
    $this->updateSDCComponent($component_type, $field_name);
  }
  
  protected function generateSDCFromEntity($component_type) {
    $bundle = $component_type->id();
    $label = $component_type->label();
    
    // Generate SDC directory structure
    $path = "modules/custom/component_entity_generated/components/$bundle";
    mkdir($path, 0755, TRUE);
    
    // Create component.yml from entity fields
    $fields = \Drupal::entityFieldManager()->getFieldDefinitions('component', $bundle);
    $props = [];
    
    foreach ($fields as $field_name => $field_def) {
      if (strpos($field_name, 'field_') === 0) {
        $prop_name = substr($field_name, 6);
        $props[$prop_name] = [
          'type' => $this->mapFieldTypeToSchema($field_def->getType()),
          'title' => $field_def->getLabel(),
          'required' => $field_def->isRequired(),
        ];
      }
    }
    
    // Write component.yml
    $component_yml = [
      'name' => $label,
      'props' => $props,
      'rendering' => [
        'twig' => TRUE,
        'react' => FALSE,
      ],
    ];
    
    file_put_contents("$path/$bundle.component.yml", Yaml::dump($component_yml));
    
    // Generate default Twig template
    $this->generateTwigTemplate($path, $bundle, $props);
    
    // Trigger webpack build for React if enabled
    if ($component_type->isReactEnabled()) {
      $this->triggerReactBuild($bundle);
    }
  }
}
```

#### 3. Rendering Architecture

The dual rendering system uses a sophisticated ViewBuilder that determines rendering method at runtime:

```php
class ComponentViewBuilder extends EntityViewBuilder {
  
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    foreach ($entities as $id => $entity) {
      $render_method = $entity->getRenderMethod();
      
      if ($render_method === 'twig') {
        $build[$id] = $this->buildTwigComponent($entity, $view_mode);
      } 
      elseif ($render_method === 'react') {
        $build[$id] = $this->buildReactComponent($entity, $view_mode);
      }
    }
  }
  
  protected function buildTwigComponent($entity, $view_mode) {
    // Get SDC component
    $component_id = $entity->bundle();
    $component = $this->componentManager->find("component_entity:$component_id");
    
    // Map entity fields to component props
    $props = $this->mapEntityToProps($entity);
    
    // Render using SDC
    return [
      '#type' => 'component',
      '#component' => $component_id,
      '#props' => $props,
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => ['render_method'],
      ],
    ];
  }
  
  protected function buildReactComponent($entity, $view_mode) {
    $props = $this->mapEntityToProps($entity);
    $config = $entity->getReactConfig();
    
    return [
      '#theme' => 'component_react_wrapper',
      '#component_id' => $entity->uuid(),
      '#component_type' => $entity->bundle(),
      '#props' => $props,
      '#config' => [
        'hydration' => $config['hydration'] ?? 'full',
        'progressive' => $config['progressive'] ?? FALSE,
        'lazy' => $config['lazy'] ?? FALSE,
      ],
      '#attached' => [
        'library' => [
          'component_entity/react-renderer',
          "component_entity/component.{$entity->bundle()}",
        ],
        'drupalSettings' => [
          'componentEntity' => [
            'components' => [
              $entity->uuid() => [
                'type' => $entity->bundle(),
                'props' => $props,
                'config' => $config,
              ],
            ],
          ],
        ],
      ],
    ];
  }
}
```

### Automatic Build Process

#### Webpack Auto-Discovery and Building

The module includes an intelligent webpack configuration that automatically discovers and builds components:

```javascript
// webpack.config.js - Auto-discovery system
const glob = require('glob');

// Auto-discover ALL component JSX/TSX files across the Drupal installation
const componentEntries = {};

// Scan module components
const moduleComponents = glob.sync('modules/*/components/**/*.{jsx,tsx}');
// Scan theme components  
const themeComponents = glob.sync('themes/*/components/**/*.{jsx,tsx}');
// Scan generated components
const generatedComponents = glob.sync('modules/custom/component_entity_generated/components/**/*.{jsx,tsx}');

[...moduleComponents, ...themeComponents, ...generatedComponents].forEach(file => {
  const matches = file.match(/components\/([^\/]+)\/([^\/]+)\.(jsx|tsx)$/);
  if (matches) {
    const [, componentDir, componentName] = matches;
    componentEntries[`${componentDir}.${componentName}`] = file;
  }
});

// Webpack watches for new components and rebuilds automatically
module.exports = {
  entry: componentEntries,
  watch: process.env.NODE_ENV === 'development',
  watchOptions: {
    ignored: /node_modules/,
    aggregateTimeout: 300,
    poll: 1000, // Check for changes every second
  },
  // ... rest of config
};
```

#### Automatic React Component Generation

When a component is created via Field UI, the module can generate a React component scaffold:

```php
class ReactComponentGenerator {
  
  public function generateFromEntity($component_type) {
    $bundle = $component_type->id();
    $fields = $this->getComponentFields($bundle);
    
    // Generate React component code
    $jsx_code = $this->generateJSXTemplate($bundle, $fields);
    
    // Write to filesystem
    $path = "modules/custom/component_entity_generated/components/$bundle";
    file_put_contents("$path/$bundle.jsx", $jsx_code);
    
    // Trigger webpack build
    $this->triggerWebpackBuild();
  }
  
  protected function generateJSXTemplate($bundle, $fields) {
    $componentName = $this->toPascalCase($bundle);
    $props = array_map(fn($f) => $f->getName(), $fields);
    
    return <<<JSX
import React from 'react';
import PropTypes from 'prop-types';

const $componentName = ({ " . implode(', ', $props) . " }) => {
  return (
    <div className="component-$bundle">
      {/* Auto-generated component - customize as needed */}
      " . $this->generateJSXFields($fields) . "
    </div>
  );
};

$componentName.propTypes = {
  " . $this->generatePropTypes($fields) . "
};

// Auto-register with Drupal
if (typeof Drupal !== 'undefined' && Drupal.componentEntity) {
  Drupal.componentEntity.register('$bundle', $componentName);
}

export default $componentName;
JSX;
  }
}
```

## For Site Creators: Building with Components

Site creators can leverage the full power of Drupal's site building tools with components:

### Creating Components via Field UI

The revolutionary approach - create SDC components directly from Drupal's admin interface:

1. **Navigate to Component Types**: `/admin/structure/component-types`
2. **Add Component Type**: Click "Add component type"
3. **Configure the Bundle**:
   ```
   Label: Hero Banner
   Machine name: hero_banner
   Description: A hero banner with title, image, and CTA
   Rendering: ☑ Twig ☑ React
   ```

4. **Add Fields via Field UI**: `/admin/structure/component-types/hero_banner/fields`
   - Add field → Text → "Title"
   - Add field → Image → "Background Image"  
   - Add field → Link → "Call to Action"
   - Add field → List (text) → "Background Color" (blue|green|red)

5. **Automatic SDC Generation**:
   The module automatically creates:
   ```yaml
   # Generated at: modules/custom/component_entity_generated/components/hero_banner/hero_banner.component.yml
   name: Hero Banner
   props:
     title:
       type: string
       required: true
     background_image:
       type: object
     call_to_action:
       type: object
     background_color:
       type: string
       enum: ['blue', 'green', 'red']
   ```

6. **Automatic Template Generation**:
   ```twig
   {# Auto-generated template - customize as needed #}
   <div class="hero-banner hero-banner--{{ background_color }}">
     {% if background_image %}
       <div class="hero-banner__image">
         {{ background_image }}
       </div>
     {% endif %}
     <h1 class="hero-banner__title">{{ title }}</h1>
     {% if call_to_action %}
       <a href="{{ call_to_action.url }}" class="hero-banner__cta">
         {{ call_to_action.title }}
       </a>
     {% endif %}
   </div>
   ```

### Using Components in Site Building

#### Adding to Content Types

1. **Add Component Reference Field**:
   - Go to `/admin/structure/types/manage/article/fields`
   - Add field → Reference → Component
   - Select which component types to allow

2. **Configure Display**:
   - Manage display → Choose formatter
   - Options: Default, Inline editable, Preview mode

3. **Layout Builder Integration**:
   ```php
   // Components automatically available as Layout Builder blocks
   - Each component type becomes a block
   - Inline configuration supported
   - Preview in Layout Builder
   ```

#### Views Integration

Create dynamic component listings:

1. **Create a View**: `/admin/structure/views/add`
2. **Show**: Components
3. **Filter by**: Component type, render method, author
4. **Sort by**: Created date, title, custom fields
5. **Display formats**: Grid, List, Table, REST export

Example Views configuration:
```yaml
# Featured Components View
Show: Components of type: Hero Banner, Card, CTA
Filter: Published = Yes, Render method = React
Sort: Sticky first, then Created date DESC
Display: Grid of 3 columns
```

#### Permissions and Workflows

Configure granular permissions per component type:

```
Component: Hero Banner
☑ Create new Hero Banner components
☑ Edit own Hero Banner components
☐ Edit any Hero Banner components
☑ Delete own Hero Banner components
☐ Delete any Hero Banner components
☑ View published Hero Banner components
```

Enable workflows:
```php
// Enable Content Moderation for components
$workflow = Workflow::load('editorial');
$workflow->getTypePlugin()->addEntityTypeAndBundle('component', 'hero_banner');
$workflow->save();
```

## For Developers: Advanced Component Development

Developers get a modern, flexible development environment with full control:

### Component Development Workflow

#### 1. SDC-First Development

Create sophisticated SDC components with full schema:

```yaml
# components/dynamic_form/dynamic_form.component.yml
name: Dynamic Form
description: A form that adapts based on configuration
props:
  form_config:
    type: object
    properties:
      method:
        type: string
        enum: ['GET', 'POST']
      action:
        type: string
        format: uri
      fields:
        type: array
        items:
          type: object
          properties:
            type:
              type: string
              enum: ['text', 'email', 'select', 'textarea']
            name:
              type: string
            label:
              type: string
            required:
              type: boolean
            options:
              type: array
  submission_handler:
    type: string
    enum: ['ajax', 'traditional', 'custom']
slots:
  form_header:
    title: Form Header
  form_footer:
    title: Form Footer
rendering:
  twig: true
  react: true
  vue: false  # Future support
```

#### 2. React Component with TypeScript

```typescript
// components/dynamic_form/dynamic_form.tsx
import React, { useState, FormEvent } from 'react';
import { ComponentProps, FormField } from '@types/component_entity';

interface DynamicFormProps extends ComponentProps {
  form_config: {
    method: 'GET' | 'POST';
    action: string;
    fields: FormField[];
  };
  submission_handler: 'ajax' | 'traditional' | 'custom';
  slots: {
    form_header?: string;
    form_footer?: string;
  };
}

const DynamicForm: React.FC<DynamicFormProps> = ({ 
  form_config, 
  submission_handler,
  slots 
}) => {
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [submitting, setSubmitting] = useState(false);
  
  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    
    if (submission_handler === 'ajax') {
      setSubmitting(true);
      try {
        const response = await fetch(form_config.action, {
          method: form_config.method,
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(formData),
        });
        
        const result = await response.json();
        
        // Trigger Drupal behaviors
        if (window.Drupal) {
          window.Drupal.announce('Form submitted successfully');
          window.Drupal.behaviors.componentEntity.formSubmitted(result);
        }
      } catch (error) {
        console.error('Form submission error:', error);
      } finally {
        setSubmitting(false);
      }
    }
  };
  
  return (
    <form 
      onSubmit={handleSubmit}
      className="dynamic-form"
      data-component="dynamic-form"
    >
      {slots.form_header && (
        <div dangerouslySetInnerHTML={{ __html: slots.form_header }} />
      )}
      
      {form_config.fields.map((field) => (
        <FormField
          key={field.name}
          field={field}
          value={formData[field.name]}
          onChange={(value) => setFormData({
            ...formData,
            [field.name]: value
          })}
        />
      ))}
      
      <button type="submit" disabled={submitting}>
        {submitting ? 'Submitting...' : 'Submit'}
      </button>
      
      {slots.form_footer && (
        <div dangerouslySetInnerHTML={{ __html: slots.form_footer }} />
      )}
    </form>
  );
};

// Auto-register with Drupal
if (typeof window !== 'undefined' && window.Drupal?.componentEntity) {
  window.Drupal.componentEntity.register('dynamic_form', DynamicForm);
}

export default DynamicForm;
```

#### 3. Progressive Enhancement Pattern

Create components that work without JavaScript and enhance when React loads:

```twig
{# components/accordion/accordion.html.twig #}
<div class="accordion" data-component="accordion" data-props='{{ props|json_encode }}'>
  <details class="accordion__item">
    <summary class="accordion__trigger">{{ title }}</summary>
    <div class="accordion__content">
      {% block content %}{% endblock %}
    </div>
  </details>
</div>

{# This works without JS, React enhances it when loaded #}
```

```javascript
// components/accordion/accordion.jsx
import React, { useState, useEffect } from 'react';

const Accordion = ({ title, content, enhanced = true }) => {
  const [isOpen, setIsOpen] = useState(false);
  
  if (!enhanced) {
    // Fallback to native HTML details element
    return (
      <details className="accordion__item">
        <summary>{title}</summary>
        <div dangerouslySetInnerHTML={{ __html: content }} />
      </details>
    );
  }
  
  // Enhanced React version with animations
  return (
    <div className="accordion accordion--enhanced">
      <button
        className="accordion__trigger"
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
      >
        {title}
        <span className="accordion__icon">{isOpen ? '−' : '+'}</span>
      </button>
      <div 
        className={`accordion__content ${isOpen ? 'is-open' : ''}`}
        style={{
          maxHeight: isOpen ? '1000px' : '0',
          transition: 'max-height 0.3s ease'
        }}
      >
        <div dangerouslySetInnerHTML={{ __html: content }} />
      </div>
    </div>
  );
};

// Progressive enhancement handler
Drupal.behaviors.accordionEnhancement = {
  attach: function(context) {
    const accordions = context.querySelectorAll('.accordion:not(.enhanced)');
    accordions.forEach(el => {
      const props = JSON.parse(el.dataset.props || '{}');
      Drupal.componentEntity.hydrate('accordion', props, el, 'progressive');
      el.classList.add('enhanced');
    });
  }
};
```

### API and Integration

#### Programmatic Component Management

```php
use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\ComponentManager;

class ComponentApiExample {
  
  /**
   * Create a component programmatically.
   */
  public function createComponent() {
    $component = ComponentEntity::create([
      'type' => 'hero_banner',
      'name' => 'Dynamic Hero',
      'field_title' => 'Welcome, ' . \Drupal::currentUser()->getDisplayName(),
      'field_subtitle' => $this->getDynamicSubtitle(),
      'render_method' => $this->shouldUseReact() ? 'react' : 'twig',
      'react_config' => [
        'hydration' => 'partial',
        'lazy' => TRUE,
      ],
    ]);
    
    $component->save();
    return $component;
  }
  
  /**
   * Render a component with context.
   */
  public function renderComponent($component_id, array $context = []) {
    $component = ComponentEntity::load($component_id);
    
    // Add context for rendering
    $build = \Drupal::entityTypeManager()
      ->getViewBuilder('component')
      ->view($component, 'default');
    
    // Modify based on context
    if ($context['ajax']) {
      $build['#ajax'] = TRUE;
      $build['#attached']['library'][] = 'component_entity/ajax-handler';
    }
    
    return $build;
  }
}
```

#### Custom Field Formatters

Create specialized formatters for component reference fields:

```php
namespace Drupal\mymodule\Plugin\Field\FieldFormatter;

/**
 * @FieldFormatter(
 *   id = "component_carousel",
 *   label = @Translation("Component Carousel"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ComponentCarouselFormatter extends EntityReferenceEntityFormatter {
  
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    
    // Wrap in carousel
    return [
      '#theme' => 'component_carousel',
      '#items' => $elements,
      '#settings' => $this->getSettings(),
      '#attached' => [
        'library' => ['mymodule/carousel'],
      ],
    ];
  }
}
```

## For Content Editors: Intuitive Component Management

Content editors get a familiar, user-friendly interface for managing components:

### Creating and Editing Components

#### Quick Edit Interface

1. **Inline Editing**: Click the pencil icon on any component to edit in place
2. **Contextual Links**: Right-click for quick actions (Edit, Delete, Clone)
3. **Drag-and-Drop**: Reorder components with visual feedback

#### Component Creation Workflow

1. **Choose Component Type**: Visual selector with previews
   ```
   [Hero Banner]    [Card Grid]    [Accordion]
   Preview image    Preview image   Preview image
   "Full-width      "Responsive     "Collapsible
    hero section"    card layout"    content blocks"
   ```

2. **Fill in Fields**: Smart form with:
   - Live preview as you type
   - Media library integration for images
   - Link autocomplete for internal content
   - Color swatchers for theme colors

3. **Choose Rendering Method**:
   ```
   How should this component behave?
   
   ○ Static (Better for SEO) 
     Great for content that doesn't change
     Loads faster, better for search engines
   
   ● Interactive (Better for user experience)
     Allows animations and user interactions
     Modern, dynamic experience
   
   [Advanced Options ▼]
   ```

#### Advanced Editor Features

##### Component Library Browser

Access a visual library of all available components:

```
Search: [___________] Filter by: [All Types ▼] [All Render Methods ▼]

┌─────────────┬─────────────┬─────────────┐
│ Hero Banner │ Card        │ Accordion   │
│ ━━━━━━━━━━━ │ ━━━━━━━━━━━ │ ━━━━━━━━━━━ │
│ [Preview]   │ [Preview]   │ [Preview]   │
│             │             │             │
│ 12 variants │ 8 variants  │ 4 variants  │
│ [Use this]  │ [Use this]  │ [Use this]  │
└─────────────┴─────────────┴─────────────┘
```

##### Smart Cloning

Clone existing components with intelligent defaults:

```php
// Clone detection prevents duplicate content
$clone = $component->createDuplicate();
$clone->set('name', $component->label() . ' (Copy)');
$clone->set('field_title', '[Draft] ' . $component->get('field_title')->value);
$clone->setUnpublished(); // Safety first
```

##### Version Comparison

Compare component versions side-by-side:

```
Version 3 (Current)          Version 2 (2 hours ago)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Title: "Summer Sale!"        Title: "Spring Sale!"
Color: Red                   Color: Green
CTA: "Shop Now"              CTA: "Learn More"
                            
[Revert to Version 2] [View Differences]
```

### Content Editor Settings

#### Personal Preferences

Each editor can customize their experience:

```php
// User preferences for component editing
$config['component_entity.editor_preferences'] = [
  'default_render_method' => 'twig',
  'show_advanced_options' => FALSE,
  'enable_live_preview' => TRUE,
  'preview_breakpoints' => ['mobile', 'tablet', 'desktop'],
  'favorite_components' => ['hero_banner', 'card', 'cta'],
];
```

#### Keyboard Shortcuts

Productivity shortcuts for power users:

```
Ctrl+Shift+C - Create new component
Ctrl+Shift+E - Edit current component  
Ctrl+Shift+D - Duplicate component
Ctrl+Shift+P - Toggle preview mode
Ctrl+Shift+R - Toggle render method
```

## How Does This Module Solve It?

### 1. Drupal-Native Architecture

Component entities follow ALL established Drupal patterns:

- **Content Entities with Bundles**: Just like nodes have article/page types, components have hero_banner/cta/card types
- **Field UI Management**: Add/edit fields through the familiar Field UI interface
- **Standard Entity Forms**: Regular entity edit forms, no custom UI needed
- **Views Integration**: List, filter, sort components like any other entity
- **Entity Reference Fields**: Reference components from nodes/taxonomy/users normally
- **Permissions System**: Standard create/edit/delete permissions per bundle
- **Cache System**: Standard entity cache tags and contexts
- **Translation System**: Regular content translation support
- **Revision System**: Standard revision tracking
- **REST/JSON:API**: Automatic API exposure

### 2. Automatic SDC Synchronization

The module provides intelligent synchronization between SDC components and entity bundles:

```php
// When an SDC component is discovered:
// components/hero/hero.component.yml
name: Hero Banner
props:
  title:
    type: string
  subtitle:
    type: string
  background_color:
    type: string
    enum: ['blue', 'green', 'red']

// Automatically creates:
// - Component bundle: "hero_banner"
// - Field: field_title (string)
// - Field: field_subtitle (string)
// - Field: field_background_color (list_string)
```

### 3. Dual Rendering System

Each component instance can choose its rendering method:

**Twig Rendering (Server-side)**
- Traditional SDC/Twig templates
- Best for SEO-critical content
- No JavaScript required
- Instant first paint

**React Rendering (Client-side)**
- Modern React components with hydration
- Best for interactive elements
- Progressive enhancement options
- Optimal for dynamic UX

Content editors see a simple choice:
```
Render method:
○ Server-side (Twig) - Better for SEO
● Client-side (React) - Better for interactivity
```

### 4. Minimal Magic Approach

The module adds only FOUR special behaviors to standard entities:

1. **Auto-Bundle Creation**: SDC components automatically create entity bundles
2. **Props-to-Fields Mapping**: SDC props become entity fields
3. **Render Integration**: Field values pass to SDC templates or React components
4. **Dual Rendering**: Support for both Twig and React with per-instance selection

Everything else uses standard Drupal core functionality.

## Installation

### Requirements

- Drupal 10.2+ or Drupal 11
- PHP 8.1+
- SDC (Single Directory Components) module enabled
- JSON Field module (included in core)
- Node.js 16+ (optional, for React build process)

### Installation Steps

1. **Download and enable the module**:
```bash
composer require drupal/component_entity
drush en component_entity -y
```

2. **Initial setup**:
```bash
# Clear caches to discover SDC components
drush cr

# Run initial SDC synchronization
drush component-entity:sync
```

3. **Install React dependencies** (optional, for React support):
```bash
cd modules/contrib/component_entity
npm install
npm run build
```

4. **Verify installation**:
- Visit `/admin/structure/component-types` to see auto-created bundles
- Check `/admin/reports/status` for Component Entity status

## Configuration

### Basic Configuration

1. **Component Types Management**:
   - Navigate to `/admin/structure/component-types`
   - View all synchronized component bundles
   - Configure fields for each component type via Field UI

2. **Rendering Configuration**:
   - Edit any component type
   - Set default rendering method (Twig or React)
   - Configure React-specific settings if enabled

3. **Permissions**:
   - Visit `/admin/people/permissions#module-component_entity`
   - Set permissions per component bundle:
     - Create new components
     - Edit own/any components
     - Delete own/any components
     - View published/unpublished components

### Advanced Configuration

#### Enable React Rendering

1. **Build React components**:
```bash
cd modules/contrib/component_entity
npm run build:components
```

2. **Configure component type**:
```php
// In your component.component.yml
rendering:
  twig: true
  react: true
  default: twig
```

3. **Set hydration options**:
- Full hydration: Complete React interactivity
- Partial hydration: Selective component hydration
- No hydration: Static React rendering

#### Field Mapping Customization

Override automatic field mapping in settings:
```php
// settings.php
$config['component_entity.settings']['field_mapping'] = [
  'hero_banner' => [
    'title' => 'field_hero_title',
    'subtitle' => 'field_hero_subtitle',
  ],
];
```

#### Performance Optimization

Configure caching and loading strategies:
```php
// settings.php
$config['component_entity.settings']['performance'] = [
  'lazy_load' => TRUE,
  'progressive_enhancement' => TRUE,
  'react_code_splitting' => TRUE,
  'cache_max_age' => 3600,
];
```

## Development

### Creating a New Component

1. **Define the SDC component**:
```yaml
# modules/custom/mymodule/components/card/card.component.yml
name: Card
props:
  title:
    type: string
    required: true
  description:
    type: string
  image:
    type: object
    properties:
      src:
        type: string
      alt:
        type: string
slots:
  footer:
    title: Footer content
rendering:
  twig: true
  react: true
```

2. **Create Twig template**:
```twig
{# modules/custom/mymodule/components/card/card.html.twig #}
<div class="card">
  <img src="{{ image.src }}" alt="{{ image.alt }}">
  <h3>{{ title }}</h3>
  <p>{{ description }}</p>
  <div class="card__footer">
    {% block footer %}{% endblock %}
  </div>
</div>
```

3. **Create React component** (optional):
```javascript
// modules/custom/mymodule/components/card/card.jsx
import React from 'react';

const Card = ({ title, description, image, slots }) => {
  return (
    <div className="card">
      <img src={image.src} alt={image.alt} />
      <h3>{title}</h3>
      <p>{description}</p>
      <div className="card__footer" 
           dangerouslySetInnerHTML={{ __html: slots.footer }} />
    </div>
  );
};

export default Card;
```

4. **Sync and use**:
```bash
# Sync the new component
drush component-entity:sync

# Clear caches
drush cr
```

### Using Components in Code

#### Programmatic Creation
```php
use Drupal\component_entity\Entity\ComponentEntity;

$component = ComponentEntity::create([
  'type' => 'hero_banner',
  'name' => 'Homepage Hero',
  'field_title' => 'Welcome to Our Site',
  'field_subtitle' => 'Discover amazing content',
  'field_background_color' => 'blue',
  'render_method' => 'twig',
]);
$component->save();
```

#### Rendering in Templates
```twig
{# In a node template #}
{{ content.field_components }}

{# Or render specific component #}
{% set component = node.field_hero_component.entity %}
{{ component|view('full') }}
```

#### Entity Reference Usage
```php
// Add component reference field to node type
$field_storage = FieldStorageConfig::create([
  'field_name' => 'field_components',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => [
    'target_type' => 'component',
  ],
]);
$field_storage->save();
```

### Extending the Module

#### Custom Sync Handler
```php
namespace Drupal\mymodule\ComponentSync;

use Drupal\component_entity\ComponentSync\SyncHandlerInterface;

class CustomSyncHandler implements SyncHandlerInterface {
  
  public function shouldSync($component_definition) {
    // Custom logic for component sync
    return strpos($component_definition['name'], 'Custom') !== FALSE;
  }
  
  public function mapFields($component_definition) {
    // Custom field mapping logic
    return [
      'title' => [
        'type' => 'string',
        'label' => 'Custom Title',
      ],
    ];
  }
}
```

#### React Component Registry
```javascript
// Register custom React components
Drupal.componentEntity.register('hero_banner', HeroBannerComponent);
Drupal.componentEntity.register('call_to_action', CTAComponent);

// Use in theme
Drupal.behaviors.myThemeComponents = {
  attach: function(context, settings) {
    Drupal.componentEntity.renderAll(context);
  }
};
```

### Module Architecture Diagrams

#### System Architecture
[Space reserved for SVG diagram showing overall system architecture]

#### Data Flow Diagram
[Space reserved for SVG diagram showing data flow between SDC, Entity System, and Rendering]

#### Component Lifecycle
[Space reserved for SVG diagram showing component lifecycle from creation to rendering]

## Roadmap

### Phase 1: Core Implementation ✅ (Complete)
- Basic entity structure and bundle management
- SDC synchronization service
- Field mapping system
- Twig rendering integration
- Views and Field UI support

### Phase 2: React Integration ✅ (Complete)
- React renderer service
- Component registry system
- DrupalSettings integration
- Hydration options (full/partial/none)
- Build process setup

### Phase 3: UI Enhancement ✅ (Complete)
- Render method selection UI
- Inline editing with AJAX
- Component library browser
- Preview functionality
- Improved form displays

### Phase 4: Performance Optimization (In Progress)
- Lazy loading implementation
- Progressive enhancement support
- Server-side React rendering (SSR)
- Code splitting for React components
- Advanced caching strategies

### Phase 5: Developer Experience (Planned Q2 2025)
- CLI scaffolding commands
- Component generator
- TypeScript support
- Storybook integration
- Development mode with HMR

### Phase 6: Advanced Features (Planned Q3 2025)
- Vue.js rendering option
- Web Components output
- GraphQL integration
- AI-assisted component creation
- Visual component builder UI

### Future Considerations
- Svelte rendering support
- Component versioning system
- A/B testing integration
- Personalization support
- Component marketplace integration

## Contributing

We welcome contributions from the Drupal community! This module embraces Drupal's values of collaboration and innovation.

### How to Contribute

1. **Report Issues**: Use the issue queue at drupal.org/project/issues/component_entity
2. **Submit Patches**: Follow Drupal coding standards and include tests
3. **Documentation**: Help improve documentation and examples
4. **Testing**: Test with different SDC components and report findings
5. **Translations**: Provide translations for the module interface

### Development Setup

```bash
# Clone the repository
git clone https://git.drupalcode.org/project/component_entity.git

# Install dependencies
composer install
npm install

# Run tests
phpunit tests/
npm test

# Check coding standards
phpcs --standard=Drupal .
npm run lint
```

### Coding Standards

- Follow Drupal coding standards for PHP
- Use ESLint configuration for JavaScript
- Write tests for new features
- Document complex logic
- Keep commits atomic and well-described

## Support

- **Documentation**: drupal.org/docs/contributed-modules/component-entity
- **Issue Queue**: drupal.org/project/issues/component_entity
- **Security Issues**: Report via Drupal.org security team
- **Community**: #component-entity on Drupal Slack

## License

This module is licensed under the GNU General Public License v2.0 or later, consistent with Drupal core.

## Credits

Maintained by the Drupal community with special thanks to:
- SDC initiative contributors
- React and Twig rendering system developers
- All contributors and testers

---

*This module represents the bridge between traditional Drupal and modern frontend development, staying 100% true to the Drupal way while embracing contemporary web standards.*