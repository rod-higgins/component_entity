# Component Entity API Reference

## Table of Contents

- [PHP Services](#php-services)
- [JavaScript API](#javascript-api)
- [REST API Endpoints](#rest-api-endpoints)
- [GraphQL Support](#graphql-support)
- [Hooks](#hooks)
- [Events](#events)
- [Drush Commands](#drush-commands)

## PHP Services

### Component Synchronization Service

**Service ID:** `component_entity.sync`

Handles synchronization between SDC components and entity bundles.

```php
// Get the service
$syncService = \Drupal::service('component_entity.sync');

// Sync all components
$results = $syncService->syncAll();

// Sync specific component
$result = $syncService->syncComponent('hero_banner');

// Check if component needs sync
$needsSync = $syncService->needsSync('hero_banner');

// Get sync status
$status = $syncService->getSyncStatus();
```

#### Methods

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `syncAll()` | none | `array` | Synchronizes all discovered SDC components |
| `syncComponent()` | `string $component_id` | `bool` | Synchronizes a specific component |
| `needsSync()` | `string $component_id` | `bool` | Checks if component needs synchronization |
| `getSyncStatus()` | none | `array` | Returns sync status for all components |
| `getComponentDefinition()` | `string $component_id` | `array` | Gets SDC component definition |

### Component Renderer Service

**Service ID:** `component_entity.renderer`

Renders component entities using configured rendering method.

```php
$renderer = \Drupal::service('component_entity.renderer');

// Render component with default view mode
$build = $renderer->render($component_entity);

// Render with specific view mode
$build = $renderer->render($component_entity, 'teaser');

// Render with context
$build = $renderer->render($component_entity, 'full', [
  'language' => 'en',
  'user' => $current_user,
]);

// Get render method for component
$method = $renderer->getRenderMethod($component_entity);
```

#### Methods

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `render()` | `ComponentEntityInterface $entity, string $view_mode = 'full', array $context = []` | `array` | Renders component entity |
| `getRenderMethod()` | `ComponentEntityInterface $entity` | `string` | Returns 'twig' or 'react' |
| `preprocess()` | `array &$variables` | `void` | Preprocesses component variables |

### React Generator Service

**Service ID:** `component_entity.react_generator`

Generates React component scaffolds from entity definitions.

```php
$generator = \Drupal::service('component_entity.react_generator');

// Generate React component
$generator->generateComponent('hero_banner', [
  'typescript' => TRUE,
  'hooks' => TRUE,
  'css_modules' => FALSE,
]);

// Generate all components
$generator->generateAll();

// Get generation options
$options = $generator->getDefaultOptions();
```

#### Methods

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `generateComponent()` | `string $bundle, array $options = []` | `bool` | Generates React component files |
| `generateAll()` | `array $options = []` | `array` | Generates all component React files |
| `getDefaultOptions()` | none | `array` | Returns default generation options |
| `validateComponent()` | `string $bundle` | `bool` | Validates if component can be generated |

### Field Mapping Service

**Service ID:** `component_entity.field_mapper`

Maps SDC component props to entity fields.

```php
$mapper = \Drupal::service('component_entity.field_mapper');

// Map component props to fields
$fields = $mapper->mapPropsToFields($component_definition);

// Get field definition for prop
$field_def = $mapper->getFieldDefinition('title', 'string');

// Map entity values to props
$props = $mapper->mapEntityToProps($component_entity);
```

## JavaScript API

### Component Registry

Register and manage React components.

```javascript
// Register a component
Drupal.componentEntity.register('hero_banner', HeroBannerComponent);

// Register multiple components
Drupal.componentEntity.registerMultiple({
  'hero_banner': HeroBannerComponent,
  'card': CardComponent,
  'accordion': AccordionComponent,
});

// Check if component is registered
const isRegistered = Drupal.componentEntity.has('hero_banner');

// Get registered component
const Component = Drupal.componentEntity.get('hero_banner');

// Get all registered components
const allComponents = Drupal.componentEntity.getAll();
```

### Component Rendering

Render components on the page.

```javascript
// Render all components in context
Drupal.componentEntity.renderAll(context);

// Render specific element
const element = document.getElementById('my-component');
Drupal.componentEntity.render(element, props);

// Hydrate server-rendered component
Drupal.componentEntity.hydrate(element, props);

// Refresh component with new data
Drupal.componentEntity.refresh('component-id');

// Unmount component
Drupal.componentEntity.unmount('component-id');
```

### Component Data Management

```javascript
// Get component data from drupalSettings
const componentData = Drupal.componentEntity.getData('component-id');

// Update component data
Drupal.componentEntity.updateData('component-id', newData);

// Fetch fresh data from API
Drupal.componentEntity.fetchData('component-id').then(data => {
  console.log('Fresh data:', data);
});

// Subscribe to data changes
const unsubscribe = Drupal.componentEntity.subscribe('component-id', (data) => {
  console.log('Data updated:', data);
});
```

### Utility Functions

```javascript
// Convert HTML string to React elements
const reactElement = Drupal.componentEntity.htmlToReact(htmlString);

// Process slots for React components
const processedSlots = Drupal.componentEntity.processSlots(rawSlots);

// Get CSRF token for API calls
const token = Drupal.componentEntity.getCsrfToken();

// Check user permissions
const canEdit = Drupal.componentEntity.hasPermission('edit any component entities');
```

## REST API Endpoints

### Component Entities

#### Get Component
```http
GET /jsonapi/component/{bundle}/{uuid}
Accept: application/vnd.api+json
```

**Response:**
```json
{
  "data": {
    "type": "component--hero_banner",
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "attributes": {
      "field_title": "Welcome",
      "field_subtitle": "Hello World",
      "field_background_color": "blue",
      "render_method": "react",
      "react_config": {
        "hydration": "full",
        "progressive": true
      }
    }
  }
}
```

#### Create Component
```http
POST /jsonapi/component/{bundle}
Content-Type: application/vnd.api+json
X-CSRF-Token: {token}

{
  "data": {
    "type": "component--hero_banner",
    "attributes": {
      "field_title": "New Banner",
      "field_subtitle": "Description"
    }
  }
}
```

#### Update Component
```http
PATCH /jsonapi/component/{bundle}/{uuid}
Content-Type: application/vnd.api+json
X-CSRF-Token: {token}

{
  "data": {
    "type": "component--hero_banner",
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "attributes": {
      "field_title": "Updated Title"
    }
  }
}
```

#### Delete Component
```http
DELETE /jsonapi/component/{bundle}/{uuid}
X-CSRF-Token: {token}
```

### Component Types

#### List Component Types
```http
GET /api/component-types
Accept: application/json
```

**Response:**
```json
{
  "data": [
    {
      "id": "hero_banner",
      "label": "Hero Banner",
      "description": "A hero banner component",
      "sdc_id": "component_entity:hero_banner",
      "rendering": {
        "twig": true,
        "react": true,
        "default": "twig"
      }
    }
  ]
}
```

### Custom Endpoints

#### Component Preview
```http
POST /api/component/preview
Content-Type: application/json
X-CSRF-Token: {token}

{
  "bundle": "hero_banner",
  "props": {
    "title": "Preview Title",
    "subtitle": "Preview Subtitle"
  },
  "render_method": "react"
}
```

#### Bulk Operations
```http
POST /api/component/bulk
Content-Type: application/json
X-CSRF-Token: {token}

{
  "operation": "update",
  "components": [
    {
      "id": "uuid-1",
      "data": { "field_title": "New Title 1" }
    },
    {
      "id": "uuid-2",
      "data": { "field_title": "New Title 2" }
    }
  ]
}
```

## GraphQL Support

### Query Components

```graphql
query GetComponents {
  componentQuery(
    filter: {
      conditions: [
        { field: "bundle", value: "hero_banner" }
        { field: "status", value: "1" }
      ]
    }
    limit: 10
    offset: 0
    sort: [{ field: "created", direction: DESC }]
  ) {
    entities {
      ... on ComponentHeroBanner {
        id
        uuid
        title
        subtitle
        backgroundColor
        renderMethod
        reactConfig {
          hydration
          progressive
        }
      }
    }
    count
  }
}
```

### Mutations

```graphql
mutation CreateComponent {
  createComponent(
    input: {
      bundle: "hero_banner"
      title: "New Banner"
      subtitle: "Description"
      backgroundColor: "blue"
      renderMethod: "react"
    }
  ) {
    entity {
      id
      uuid
    }
  }
}

mutation UpdateComponent {
  updateComponent(
    id: "123e4567-e89b-12d3-a456-426614174000"
    input: {
      title: "Updated Title"
    }
  ) {
    entity {
      id
      title
    }
  }
}
```

## Hooks

### Alter Hooks

#### Field Mapping Alter
```php
/**
 * Alter component field mapping.
 */
function hook_component_entity_field_mapping_alter(&$mapping, $component_definition, $bundle) {
  // Add custom field
  $mapping['custom_field'] = [
    'type' => 'string',
    'label' => 'Custom Field',
    'required' => FALSE,
  ];
  
  // Modify existing mapping
  if (isset($mapping['title'])) {
    $mapping['title']['required'] = TRUE;
  }
}
```

#### React Build Alter
```php
/**
 * Alter React component build array.
 */
function hook_component_entity_react_build_alter(&$build, ComponentEntityInterface $entity, $context) {
  // Add custom props
  $build['#props']['customProp'] = 'value';
  
  // Add custom library
  $build['#attached']['library'][] = 'mymodule/custom-components';
  
  // Modify hydration settings
  $build['#config']['hydration'] = 'partial';
}
```

#### Component Sync Alter
```php
/**
 * Alter components before synchronization.
 */
function hook_component_entity_sync_alter(&$components) {
  // Skip certain components
  unset($components['internal_component']);
  
  // Modify component definition
  if (isset($components['hero_banner'])) {
    $components['hero_banner']['auto_sync'] = FALSE;
  }
}
```

### Info Hooks

#### Component Info
```php
/**
 * Provide component information.
 */
function hook_component_entity_info() {
  return [
    'custom_component' => [
      'label' => t('Custom Component'),
      'description' => t('A custom component type'),
      'render_methods' => ['twig', 'react'],
      'field_mapping' => [
        'title' => ['type' => 'string', 'required' => TRUE],
      ],
    ],
  ];
}

/**
 * Alter component info.
 */
function hook_component_entity_info_alter(&$info) {
  // Modify component info
  if (isset($info['hero_banner'])) {
    $info['hero_banner']['label'] = t('Custom Hero Banner');
  }
}
```

## Events

### Component Events

```php
use Drupal\component_entity\Event\ComponentEvents;
use Drupal\component_entity\Event\ComponentRenderedEvent;
use Drupal\component_entity\Event\ComponentSynchronizedEvent;

/**
 * Subscribe to component events.
 */
class ComponentEventSubscriber implements EventSubscriberInterface {
  
  public static function getSubscribedEvents() {
    return [
      ComponentEvents::COMPONENT_RENDERED => 'onComponentRendered',
      ComponentEvents::COMPONENT_SYNCHRONIZED => 'onComponentSynchronized',
      ComponentEvents::COMPONENT_CREATED => 'onComponentCreated',
      ComponentEvents::COMPONENT_UPDATED => 'onComponentUpdated',
      ComponentEvents::COMPONENT_DELETED => 'onComponentDeleted',
    ];
  }
  
  public function onComponentRendered(ComponentRenderedEvent $event) {
    $entity = $event->getEntity();
    $build = $event->getBuild();
    
    // Log rendering
    \Drupal::logger('component_entity')->info('Component @type rendered', [
      '@type' => $entity->bundle(),
    ]);
  }
  
  public function onComponentSynchronized(ComponentSynchronizedEvent $event) {
    $results = $event->getResults();
    
    // Clear specific caches after sync
    Cache::invalidateTags(['component_list']);
  }
}
```

### Dispatching Events

```php
// Dispatch custom event
$event = new ComponentCustomEvent($component_entity, $data);
\Drupal::service('event_dispatcher')->dispatch(ComponentEvents::CUSTOM, $event);
```

## Drush Commands

### Synchronization Commands

```bash
# Sync all SDC components with entity bundles
drush component-entity:sync

# Sync specific component
drush component-entity:sync hero_banner

# Force sync (ignore checksums)
drush component-entity:sync --force

# Dry run (show what would be synced)
drush component-entity:sync --dry-run
```

### Generation Commands

```bash
# Generate React component for bundle
drush component-entity:generate-react hero_banner

# Generate with options
drush component-entity:generate-react hero_banner --typescript --hooks --css-modules

# Generate all React components
drush component-entity:generate-react-all

# Generate Twig template
drush component-entity:generate-twig hero_banner
```

### Management Commands

```bash
# List all component types
drush component-entity:list

# Show component type details
drush component-entity:info hero_banner

# Clear component caches
drush component-entity:cache-clear

# Rebuild component registry
drush component-entity:rebuild

# Export component configuration
drush component-entity:export hero_banner

# Import component configuration
drush component-entity:import /path/to/config.yml
```

### Development Commands

```bash
# Watch for component changes
drush component-entity:watch

# Validate component schemas
drush component-entity:validate

# Run component tests
drush component-entity:test hero_banner

# Generate component documentation
drush component-entity:docs
```

## Error Handling

### PHP Exception Classes

```php
use Drupal\component_entity\Exception\ComponentNotFoundException;
use Drupal\component_entity\Exception\ComponentSyncException;
use Drupal\component_entity\Exception\ComponentRenderException;

try {
  $syncService->syncComponent('invalid_component');
} catch (ComponentNotFoundException $e) {
  // Handle missing component
  \Drupal::logger('component_entity')->error($e->getMessage());
} catch (ComponentSyncException $e) {
  // Handle sync failure
  \Drupal::messenger()->addError('Sync failed: ' . $e->getMessage());
}
```

### JavaScript Error Handling

```javascript
// Wrap component rendering in try-catch
try {
  Drupal.componentEntity.render(element, props);
} catch (error) {
  console.error('Component render failed:', error);
  
  // Show fallback content
  element.innerHTML = '<div class="component-error">Component could not be loaded</div>';
  
  // Report to backend
  Drupal.componentEntity.reportError({
    component: 'hero_banner',
    error: error.message,
    stack: error.stack,
  });
}

// Use error boundaries in React
class ComponentErrorBoundary extends React.Component {
  componentDidCatch(error, errorInfo) {
    // Log error to service
    Drupal.componentEntity.logError(error, errorInfo);
  }
}
```

## Performance Optimization

### Caching

```php
// Set cache tags for component
$build['#cache'] = [
  'tags' => [
    'component:' . $entity->bundle(),
    'component:' . $entity->id(),
  ],
  'contexts' => ['languages', 'theme', 'user.permissions'],
  'max-age' => Cache::PERMANENT,
];

// Invalidate component caches
Cache::invalidateTags(['component:hero_banner']);
```

### Lazy Loading

```javascript
// Lazy load component only when needed
const lazyLoadComponent = async (componentType) => {
  const module = await import(`./components/${componentType}.js`);
  Drupal.componentEntity.register(componentType, module.default);
  return module.default;
};

// Use IntersectionObserver for lazy loading
const observer = new IntersectionObserver((entries) => {
  entries.forEach(async (entry) => {
    if (entry.isIntersecting) {
      const element = entry.target;
      const componentType = element.dataset.componentType;
      const Component = await lazyLoadComponent(componentType);
      Drupal.componentEntity.render(element, JSON.parse(element.dataset.props));
      observer.unobserve(element);
    }
  });
});
```
