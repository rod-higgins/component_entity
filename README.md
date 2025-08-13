# Component Entity Module

> **Transform Drupal into a modern component-driven CMS while staying 100% Drupal-native**

This module bridges Single Directory Components (SDC) with Drupal's entity system, providing a seamless way to manage components as content entities with dual Twig/React rendering capabilities.

## Key Features

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

2. **Sync with entity system**:
```bash
drush component-entity:sync
```

3. **Add fields via Field UI**:
- Navigate to `/admin/structure/component-types/card/fields`
- Add corresponding fields
- Configure field widgets and formatters

4. **Create React component** (optional):
```jsx
// components/card/card.jsx
const Card = ({ title, description, image, slots }) => (
  <div className="card">
    {image && <img src={image.src} alt={image.alt} />}
    <h3>{title}</h3>
    <p>{description}</p>
    {slots?.footer && <div className="card__footer">{slots.footer}</div>}
  </div>
);

// Auto-register with Drupal
if (typeof Drupal !== 'undefined' && Drupal.componentEntity) {
  Drupal.componentEntity.register('card', Card);
}

export default Card;
```

### Using Components

#### In Content Types
```php
// Add a component reference field to article content type
$fields['field_components'] = BaseFieldDefinition::create('entity_reference')
  ->setLabel(t('Components'))
  ->setSetting('target_type', 'component')
  ->setSetting('handler_settings', ['target_bundles' => ['hero_banner', 'card']])
  ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
```

#### In Twig Templates
```twig
{# node--article.html.twig #}
{% for component in content.field_components %}
  {{ component }}
{% endfor %}
```

#### Via Views
Create dynamic component listings with Views UI:
- Create new view of "Component" entities
- Filter by component type
- Add contextual filters
- Configure display modes

## Scenarios

### Scenario 1: Content Editor Workflow

When a content editor creates a component and chooses React rendering, here's the complete flow:

#### Step 1: Component Creation
The editor creates a new "Hero Banner" component via the UI and selects "React" for rendering:

```yaml
# The system auto-generates (optional):
# modules/custom/component_entity_generated/components/hero_banner/hero_banner.jsx
```

```javascript
// Auto-generated React component scaffold
const HeroBanner = ({ title, subtitle, backgroundColor }) => {
  return (
    <div className={`hero-banner hero-banner--${backgroundColor}`}>
      <h1>{title}</h1>
      <p>{subtitle}</p>
    </div>
  );
};

// Auto-register with Drupal
if (typeof Drupal !== 'undefined' && Drupal.componentEntity) {
  Drupal.componentEntity.register('hero_banner', HeroBanner);
}
```

#### Step 2: Build Process
The webpack build tools:
1. **Compile JSX → JavaScript** that browsers understand
2. **Bundle** with dependencies
3. **Register as Drupal Library** automatically:

```yaml
# Auto-generated in component_entity.libraries.yml
component.hero_banner:
  js:
    dist/components/hero_banner.js: {}
  dependencies:
    - core/react
    - component_entity/react-renderer
```

#### Step 3: Runtime Serving
When a visitor views the page:

1. **Drupal renders initial HTML**:
```html
<div data-component-entity="hero_banner" 
     data-props='{"title":"Welcome","subtitle":"Hello World","backgroundColor":"blue"}'>
  <!-- Optional pre-rendered HTML for SEO -->
  <div class="hero-banner hero-banner--blue">
    <h1>Welcome</h1>
    <p>Hello World</p>
  </div>
</div>
```

2. **React takes over** based on hydration strategy
3. **Component becomes interactive**

### Scenario 2: Hydration Strategies in Action

The module provides three hydration methods that content editors can choose per component instance:

#### Full Hydration (Maximum Interactivity)
Best for: Interactive dashboards, forms, real-time updates

```javascript
// Component is immediately interactive
ReactDOM.hydrate(<HeroBanner {...props} />, element);

// Use case: Live stock ticker
const StockTicker = ({ symbols }) => {
  const [prices, setPrices] = useState({});
  
  useEffect(() => {
    const ws = new WebSocket('wss://stocks.example.com');
    ws.onmessage = (e) => setPrices(JSON.parse(e.data));
    return () => ws.close();
  }, []);
  
  return (
    <div className="stock-ticker">
      {symbols.map(symbol => (
        <span key={symbol}>{symbol}: ${prices[symbol] || '...'}</span>
      ))}
    </div>
  );
};
```

#### Partial Hydration (Performance Optimized)
Best for: Content with some interactive elements

```javascript
// Static until user interaction
element.addEventListener('mouseenter', () => {
  ReactDOM.render(<InteractiveGallery {...props} />, element);
}, { once: true });

// Use case: Product gallery that becomes interactive on hover
const ProductGallery = ({ images, lazy }) => {
  if (lazy) {
    return <img src={images[0].src} alt="Click to view gallery" />;
  }
  
  // Full interactive gallery after hydration
  return <InteractiveGallery images={images} />;
};
```

#### No Hydration (SEO Focused)
Best for: Static content, better SEO, fastest initial load

```html
<!-- Server renders only, no JavaScript execution -->
<div class="hero-banner hero-banner--blue">
  <h1>Welcome</h1>
  <p>Pure HTML, perfect for SEO</p>
</div>
```

### Scenario 3: API-Driven Interactive Components

Components automatically get REST/JSON:API endpoints as standard Drupal entities:

#### Fetching Component Data
```javascript
// React component with live data fetching
const DynamicHero = ({ drupalContext, title, subtitle }) => {
  const [data, setData] = useState({ title, subtitle });
  const [isEditing, setIsEditing] = useState(false);
  
  // Fetch fresh data
  const refresh = async () => {
    const response = await fetch(
      `/jsonapi/component/hero_banner/${drupalContext.entityId}`
    );
    const json = await response.json();
    setData({
      title: json.data.attributes.field_title,
      subtitle: json.data.attributes.field_subtitle
    });
  };
  
  // Auto-refresh every 30 seconds
  useEffect(() => {
    const interval = setInterval(refresh, 30000);
    return () => clearInterval(interval);
  }, []);
  
  return (
    <div className="hero-banner">
      <h1>{data.title}</h1>
      <p>{data.subtitle}</p>
      <button onClick={refresh}>Refresh</button>
    </div>
  );
};
```

#### Inline Editing with Permissions
```javascript
// Component with inline editing capabilities
const EditableHero = ({ drupalContext, title, subtitle }) => {
  const [data, setData] = useState({ title, subtitle });
  const [isEditing, setIsEditing] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  
  // Save changes via JSON:API
  const save = async (newData) => {
    setIsSaving(true);
    
    try {
      const response = await fetch(
        `/jsonapi/component/hero_banner/${drupalContext.entityId}`,
        {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/vnd.api+json',
            'X-CSRF-Token': drupalSettings.csrfToken
          },
          body: JSON.stringify({
            data: {
              type: 'component--hero_banner',
              id: drupalContext.entityId,
              attributes: {
                field_title: newData.title,
                field_subtitle: newData.subtitle
              }
            }
          })
        }
      );
      
      if (response.ok) {
        setData(newData);
        setIsEditing(false);
        
        // Show success message
        Drupal.message('Component updated successfully');
      }
    } catch (error) {
      Drupal.message('Error saving component', 'error');
    } finally {
      setIsSaving(false);
    }
  };
  
  // Check edit permissions
  const canEdit = drupalContext.permissions?.includes('edit any component entities');
  
  if (isEditing) {
    return (
      <EditForm 
        data={data} 
        onSave={save} 
        onCancel={() => setIsEditing(false)}
        isSaving={isSaving}
      />
    );
  }
  
  return (
    <div className="hero-banner">
      <h1>{data.title}</h1>
      <p>{data.subtitle}</p>
      {canEdit && (
        <button 
          onClick={() => setIsEditing(true)}
          className="hero-banner__edit"
        >
          Edit
        </button>
      )}
    </div>
  );
};
```

### Scenario 4: Real-time Collaborative Editing

For advanced use cases with WebSocket support:

```javascript
// Component with real-time updates across users
const CollaborativeComponent = ({ drupalContext, initialData }) => {
  const [data, setData] = useState(initialData);
  const [activeUsers, setActiveUsers] = useState([]);
  
  useEffect(() => {
    // Connect to WebSocket for real-time updates
    const ws = new WebSocket(`wss://yoursite.com/component-updates`);
    
    // Join component room
    ws.onopen = () => {
      ws.send(JSON.stringify({
        action: 'join',
        componentId: drupalContext.entityId
      }));
    };
    
    // Handle incoming updates
    ws.onmessage = (event) => {
      const message = JSON.parse(event.data);
      
      switch (message.type) {
        case 'update':
          if (message.componentId === drupalContext.entityId) {
            setData(message.data);
            // Show who made the change
            Drupal.message(`Updated by ${message.user}`);
          }
          break;
          
        case 'user-joined':
          setActiveUsers(prev => [...prev, message.user]);
          break;
          
        case 'user-left':
          setActiveUsers(prev => prev.filter(u => u !== message.user));
          break;
      }
    };
    
    return () => ws.close();
  }, [drupalContext.entityId]);
  
  return (
    <div className="collaborative-component">
      <div className="active-users">
        {activeUsers.map(user => (
          <span key={user} className="user-avatar" title={user}>
            {user[0]}
          </span>
        ))}
      </div>
      <div className="content">
        {/* Component content */}
      </div>
    </div>
  );
};
```

### Scenario 5: Progressive Enhancement Pattern

Start with server-rendered HTML, enhance with React when needed:

```javascript
// Progressive enhancement component
const ProgressiveForm = ({ drupalContext }) => {
  const [enhanced, setEnhanced] = useState(false);
  const [formData, setFormData] = useState({});
  
  useEffect(() => {
    // Check if we should enhance
    if ('IntersectionObserver' in window && window.matchMedia('(min-width: 768px)').matches) {
      setEnhanced(true);
    }
  }, []);
  
  if (!enhanced) {
    // Return null to keep server-rendered HTML
    return null;
  }
  
  // Enhanced version with React
  return (
    <form className="enhanced-form">
      {/* Rich interactive form */}
    </form>
  );
};

// Register with progressive enhancement
Drupal.behaviors.progressiveComponents = {
  attach: function(context) {
    // Only enhance if JavaScript is enabled and conditions are met
    const components = context.querySelectorAll('[data-progressive-enhance]');
    
    components.forEach(element => {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            // Enhance when component comes into view
            const props = JSON.parse(element.dataset.props);
            ReactDOM.render(<ProgressiveForm {...props} />, element);
            observer.unobserve(element);
          }
        });
      });
      
      observer.observe(element);
    });
  }
};
```

### Complete Architecture Flow

1. **Build Time**:
   - JSX components are discovered by webpack
   - Compiled to browser-compatible JavaScript
   - Registered as Drupal libraries
   - No separate React server needed

2. **Request Time**:
   - Drupal serves the page with embedded component data
   - Initial HTML is rendered server-side (SEO-friendly)
   - React library and component files load as standard Drupal assets
   - CSRF tokens and permissions passed via drupalSettings

3. **Runtime**:
   - Components hydrate based on their configuration
   - React components can fetch/update via JSON:API
   - All Drupal permissions and access controls apply
   - Cache invalidation happens automatically

4. **Benefits**:
   - ✅ SEO-friendly with server-side rendering
   - ✅ Interactive when needed with React
   - ✅ No separate Node.js server required
   - ✅ Full Drupal permission system integration
   - ✅ Standard Drupal caching and performance
   - ✅ Familiar Drupal development patterns

## API Reference

### PHP Services

```php
// Component synchronization service
$sync_service = \Drupal::service('component_entity.sync');
$sync_service->syncAll();

// React generator service
$generator = \Drupal::service('component_entity.react_generator');
$generator->generateComponent($component_type);

// Component renderer
$renderer = \Drupal::service('component_entity.renderer');
$build = $renderer->render($component_entity, 'full');
```

### JavaScript API

```javascript
// Register React component
Drupal.componentEntity.register('hero_banner', HeroBannerComponent);

// Render all components on page
Drupal.componentEntity.renderAll(context);

// Render specific component
Drupal.componentEntity.render(element, props);

// Refresh component data
Drupal.componentEntity.refresh('component-id');
```

### Drush Commands

```bash
# Sync all SDC components
drush component-entity:sync

# Sync specific component
drush component-entity:sync hero_banner

# Generate React component
drush component-entity:generate-react hero_banner

# List all component types
drush component-entity:list

# Clear component caches
drush component-entity:cache-clear
```

### Hooks

```php
/**
 * Alter component field mapping.
 */
function hook_component_entity_field_mapping_alter(&$mapping, $component_definition) {
  // Custom field mapping logic
}

/**
 * React component build alter.
 */
function hook_component_entity_react_build_alter(&$build, $entity, $context) {
  // Modify React component build array
}

/**
 * Component sync alter.
 */
function hook_component_entity_sync_alter(&$components) {
  // Modify components before sync
}
```

### Events

```php
// Component rendered event
class ComponentRenderedEvent extends Event {
  const NAME = 'component_entity.rendered';
}

// Component synchronized event  
class ComponentSynchronizedEvent extends Event {
  const NAME = 'component_entity.synchronized';
}
```

### Custom Component Sync

```php
namespace Drupal\mymodule\Plugin\ComponentSync;

/**
 * @ComponentSync(
 *   id = "custom_sync",
 *   label = @Translation("Custom synchronization")
 * )
 */
class CustomSync extends ComponentSyncBase {
  
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

### React Component Registry

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