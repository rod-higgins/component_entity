# Component Entity Examples

## Table of Contents

- [Basic Examples](#basic-examples)
- [Advanced Components](#advanced-components)
- [Integration Examples](#integration-examples)
- [Real-World Use Cases](#real-world-use-cases)
- [Migration Examples](#migration-examples)

## Basic Examples

### Simple Card Component

#### SDC Definition
```yaml
# components/card/card.component.yml
name: Card
description: Basic card component
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
  link:
    type: object
    properties:
      url:
        type: string
      text:
        type: string
rendering:
  twig: true
  react: true
```

#### Twig Implementation
```twig
{# card.html.twig #}
<article class="card">
  {% if image %}
    <div class="card__image">
      <img src="{{ image.src }}" alt="{{ image.alt }}" loading="lazy">
    </div>
  {% endif %}
  
  <div class="card__content">
    <h3 class="card__title">{{ title }}</h3>
    
    {% if description %}
      <p class="card__description">{{ description }}</p>
    {% endif %}
    
    {% if link %}
      <a href="{{ link.url }}" class="card__link">
        {{ link.text|default('Read more') }}
      </a>
    {% endif %}
  </div>
</article>
```

#### React Implementation
```jsx
// card.jsx
const Card = ({ title, description, image, link }) => {
  return (
    <article className="card">
      {image && (
        <div className="card__image">
          <img src={image.src} alt={image.alt} loading="lazy" />
        </div>
      )}
      
      <div className="card__content">
        <h3 className="card__title">{title}</h3>
        
        {description && (
          <p className="card__description">{description}</p>
        )}
        
        {link && (
          <a href={link.url} className="card__link">
            {link.text || 'Read more'}
          </a>
        )}
      </div>
    </article>
  );
};

// Register with Drupal
if (typeof Drupal !== 'undefined' && Drupal.componentEntity) {
  Drupal.componentEntity.register('card', Card);
}
```

#### Usage in Content
```php
// Creating a card component programmatically
$card = \Drupal\component_entity\Entity\ComponentEntity::create([
  'bundle' => 'card',
  'field_title' => 'Featured Product',
  'field_description' => 'Check out our latest offering',
  'field_image' => [
    'target_id' => $file->id(),
    'alt' => 'Product image',
  ],
  'field_link' => [
    'uri' => 'https://example.com/product',
    'title' => 'View Product',
  ],
  'render_method' => 'react',
]);

$card->save();
```

### Alert/Notification Component

#### SDC Definition
```yaml
# components/alert/alert.component.yml
name: Alert
description: Alert notification component
props:
  message:
    type: string
    required: true
  type:
    type: string
    enum: ['info', 'success', 'warning', 'error']
    default: 'info'
  dismissible:
    type: boolean
    default: true
  icon:
    type: boolean
    default: true
```

#### React Implementation with State
```jsx
// alert.jsx
import React, { useState } from 'react';

const Alert = ({ message, type = 'info', dismissible = true, icon = true }) => {
  const [isVisible, setIsVisible] = useState(true);
  
  if (!isVisible) return null;
  
  const icons = {
    info: '💡',
    success: '✅',
    warning: '⚠️',
    error: '❌',
  };
  
  const handleDismiss = () => {
    setIsVisible(false);
    
    // Report dismissal to Drupal
    if (window.Drupal) {
      Drupal.ajax({
        url: '/api/component/alert/dismissed',
        submit: { type, message },
      });
    }
  };
  
  return (
    <div className={`alert alert--${type}`} role="alert">
      {icon && <span className="alert__icon">{icons[type]}</span>}
      <div className="alert__message">{message}</div>
      {dismissible && (
        <button 
          className="alert__dismiss" 
          onClick={handleDismiss}
          aria-label="Dismiss alert"
        >
          ×
        </button>
      )}
    </div>
  );
};

Drupal.componentEntity.register('alert', Alert);
```

## Advanced Components

### Interactive Data Table

#### SDC Definition
```yaml
# components/data_table/data_table.component.yml
name: Data Table
description: Interactive sortable data table
props:
  headers:
    type: array
    items:
      type: object
      properties:
        key:
          type: string
        label:
          type: string
        sortable:
          type: boolean
  data:
    type: array
    items:
      type: object
  searchable:
    type: boolean
    default: true
  paginated:
    type: boolean
    default: true
  pageSize:
    type: integer
    default: 10
```

#### React Implementation with Features
```jsx
// data_table.jsx
import React, { useState, useMemo } from 'react';

const DataTable = ({ 
  headers, 
  data, 
  searchable = true, 
  paginated = true, 
  pageSize = 10 
}) => {
  const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  
  // Sorting logic
  const sortedData = useMemo(() => {
    if (!sortConfig.key) return data;
    
    return [...data].sort((a, b) => {
      const aVal = a[sortConfig.key];
      const bVal = b[sortConfig.key];
      
      if (aVal === bVal) return 0;
      
      const comparison = aVal > bVal ? 1 : -1;
      return sortConfig.direction === 'asc' ? comparison : -comparison;
    });
  }, [data, sortConfig]);
  
  // Filtering logic
  const filteredData = useMemo(() => {
    if (!searchTerm) return sortedData;
    
    return sortedData.filter(row => 
      Object.values(row).some(value => 
        String(value).toLowerCase().includes(searchTerm.toLowerCase())
      )
    );
  }, [sortedData, searchTerm]);
  
  // Pagination logic
  const paginatedData = useMemo(() => {
    if (!paginated) return filteredData;
    
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    return filteredData.slice(start, end);
  }, [filteredData, currentPage, pageSize, paginated]);
  
  const totalPages = Math.ceil(filteredData.length / pageSize);
  
  const handleSort = (key) => {
    setSortConfig(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
  };
  
  return (
    <div className="data-table">
      {searchable && (
        <div className="data-table__search">
          <input
            type="search"
            placeholder="Search..."
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value);
              setCurrentPage(1);
            }}
            className="data-table__search-input"
          />
        </div>
      )}
      
      <table className="data-table__table">
        <thead>
          <tr>
            {headers.map(header => (
              <th 
                key={header.key}
                onClick={() => header.sortable && handleSort(header.key)}
                className={header.sortable ? 'sortable' : ''}
              >
                {header.label}
                {sortConfig.key === header.key && (
                  <span className="sort-indicator">
                    {sortConfig.direction === 'asc' ? ' ↑' : ' ↓'}
                  </span>
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {paginatedData.map((row, index) => (
            <tr key={index}>
              {headers.map(header => (
                <td key={header.key}>{row[header.key]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      
      {paginated && totalPages > 1 && (
        <div className="data-table__pagination">
          <button
            onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
            disabled={currentPage === 1}
          >
            Previous
          </button>
          
          <span className="page-info">
            Page {currentPage} of {totalPages}
          </span>
          
          <button
            onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
};

Drupal.componentEntity.register('data_table', DataTable);
```

### Accordion Component with Animations

```jsx
// accordion.jsx
import React, { useState } from 'react';

const AccordionItem = ({ title, content, isOpen, onToggle }) => {
  return (
    <div className={`accordion__item ${isOpen ? 'is-open' : ''}`}>
      <button
        className="accordion__trigger"
        onClick={onToggle}
        aria-expanded={isOpen}
      >
        <span>{title}</span>
        <span className="accordion__icon">{isOpen ? '−' : '+'}</span>
      </button>
      
      <div 
        className="accordion__content"
        style={{
          maxHeight: isOpen ? '1000px' : '0',
          opacity: isOpen ? 1 : 0,
          transition: 'all 0.3s ease',
        }}
      >
        <div className="accordion__inner">
          {content}
        </div>
      </div>
    </div>
  );
};

const Accordion = ({ items, allowMultiple = false }) => {
  const [openItems, setOpenItems] = useState(new Set());
  
  const handleToggle = (index) => {
    setOpenItems(prev => {
      const newSet = new Set(prev);
      
      if (newSet.has(index)) {
        newSet.delete(index);
      } else {
        if (!allowMultiple) {
          newSet.clear();
        }
        newSet.add(index);
      }
      
      return newSet;
    });
  };
  
  return (
    <div className="accordion">
      {items.map((item, index) => (
        <AccordionItem
          key={index}
          title={item.title}
          content={item.content}
          isOpen={openItems.has(index)}
          onToggle={() => handleToggle(index)}
        />
      ))}
    </div>
  );
};

Drupal.componentEntity.register('accordion', Accordion);
```

## Integration Examples

### Component with Drupal Forms

```php
namespace Drupal\mymodule\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\component_entity\Entity\ComponentEntity;

/**
 * Form with component preview.
 */
class ComponentPreviewForm extends FormBase {
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'component_preview_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updatePreview',
        'wrapper' => 'component-preview',
        'event' => 'keyup',
        'progress' => ['type' => 'none'],
      ],
    ];
    
    $form['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtitle'),
      '#ajax' => [
        'callback' => '::updatePreview',
        'wrapper' => 'component-preview',
      ],
    ];
    
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => [
        'light' => $this->t('Light'),
        'dark' => $this->t('Dark'),
        'brand' => $this->t('Brand'),
      ],
      '#ajax' => [
        'callback' => '::updatePreview',
        'wrapper' => 'component-preview',
      ],
    ];
    
    // Live preview
    $form['preview'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'component-preview'],
    ];
    
    $form['preview']['component'] = $this->buildComponentPreview($form_state);
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Component'),
    ];
    
    return $form;
  }
  
  /**
   * Builds component preview.
   */
  protected function buildComponentPreview(FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    if (empty($values['title'])) {
      return ['#markup' => '<p>' . $this->t('Enter a title to see preview') . '</p>'];
    }
    
    // Create temporary component for preview
    $component = ComponentEntity::create([
      'bundle' => 'hero_banner',
      'field_title' => $values['title'] ?? '',
      'field_subtitle' => $values['subtitle'] ?? '',
      'field_theme' => $values['theme'] ?? 'light',
      'render_method' => 'react',
    ]);
    
    // Render preview
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('component');
    return $view_builder->view($component, 'preview');
  }
  
  /**
   * AJAX callback to update preview.
   */
  public function updatePreview(array $form, FormStateInterface $form_state) {
    return $form['preview'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    $component = ComponentEntity::create([
      'bundle' => 'hero_banner',
      'field_title' => $values['title'],
      'field_subtitle' => $values['subtitle'],
      'field_theme' => $values['theme'],
      'render_method' => 'react',
    ]);
    
    $component->save();
    
    $this->messenger()->addStatus($this->t('Component created successfully.'));
    $form_state->setRedirect('entity.component.canonical', ['component' => $component->id()]);
  }
}
```

### Component in Views

```php
// views.view.component_gallery.yml
langcode: en
status: true
id: component_gallery
label: 'Component Gallery'
module: views
description: 'Gallery of all components'
tag: ''
base_table: component_field_data
base_field: id
display:
  default:
    display_plugin: default
    id: default
    display_title: Master
    position: 0
    display_options:
      fields:
        rendered_entity:
          id: rendered_entity
          table: component
          field: rendered_entity
          relationship: none
          view_mode: card
      filters:
        status:
          id: status
          table: component_field_data
          field: status
          value: '1'
        bundle:
          id: bundle
          table: component_field_data
          field: bundle
          value:
            hero_banner: hero_banner
            card: card
      sorts:
        created:
          id: created
          table: component_field_data
          field: created
          order: DESC
      style:
        type: grid
        options:
          columns: 3
          automatic_width: true
      row:
        type: fields
```

### Component with AJAX Updates

```javascript
// live_chart.jsx
import React, { useState, useEffect } from 'react';

const LiveChart = ({ endpoint, refreshInterval = 5000, chartType = 'line' }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  const fetchData = async () => {
    try {
      const response = await fetch(endpoint, {
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      
      if (!response.ok) throw new Error('Failed to fetch data');
      
      const json = await response.json();
      setData(json.data);
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  
  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, refreshInterval);
    return () => clearInterval(interval);
  }, [endpoint, refreshInterval]);
  
  if (loading) return <div className="chart-loading">Loading chart...</div>;
  if (error) return <div className="chart-error">Error: {error}</div>;
  
  return (
    <div className="live-chart">
      <div className="chart-header">
        <span className="live-indicator">● Live</span>
        <span className="last-updated">
          Last updated: {new Date().toLocaleTimeString()}
        </span>
      </div>
      
      <div className="chart-container">
        {/* Render chart based on type */}
        {chartType === 'line' && <LineChart data={data} />}
        {chartType === 'bar' && <BarChart data={data} />}
        {chartType === 'pie' && <PieChart data={data} />}
      </div>
    </div>
  );
};

// Simple line chart implementation
const LineChart = ({ data }) => {
  const maxValue = Math.max(...data.map(d => d.value));
  const width = 100 / data.length;
  
  return (
    <svg className="line-chart" viewBox="0 0 100 50">
      {data.map((point, index) => {
        const height = (point.value / maxValue) * 50;
        const x = index * width + width / 2;
        const y = 50 - height;
        
        return (
          <g key={index}>
            <rect
              x={index * width}
              y={y}
              width={width - 1}
              height={height}
              fill="var(--color-primary)"
              opacity="0.7"
            />
            <text
              x={x}
              y={48}
              fontSize="3"
              textAnchor="middle"
            >
              {point.label}
            </text>
          </g>
        );
      })}
    </svg>
  );
};

Drupal.componentEntity.register('live_chart', LiveChart);
```

## Real-World Use Cases

### E-commerce Product Card

```jsx
// product_card.jsx
import React, { useState } from 'react';

const ProductCard = ({ 
  product, 
  currency = 'USD',
  onAddToCart,
  onQuickView,
  drupalContext 
}) => {
  const [isInCart, setIsInCart] = useState(false);
  const [selectedVariant, setSelectedVariant] = useState(product.variants?.[0]);
  const [imageIndex, setImageIndex] = useState(0);
  
  const formatPrice = (price) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(price);
  };
  
  const handleAddToCart = async () => {
    setIsInCart(true);
    
    // Call Drupal Commerce API
    const response = await fetch('/cart/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': drupalSettings.csrfToken,
      },
      body: JSON.stringify({
        product_id: product.id,
        variant_id: selectedVariant?.id,
        quantity: 1,
      }),
    });
    
    if (response.ok && onAddToCart) {
      onAddToCart(product, selectedVariant);
    }
    
    // Update cart count in Drupal
    Drupal.ajax({ url: '/cart/count' }).execute();
  };
  
  return (
    <article className="product-card">
      <div className="product-card__badges">
        {product.sale && (
          <span className="badge badge--sale">
            {product.salePercentage}% OFF
          </span>
        )}
        {product.new && (
          <span className="badge badge--new">New</span>
        )}
      </div>
      
      <div className="product-card__images">
        <img 
          src={product.images[imageIndex]} 
          alt={product.title}
          onClick={() => onQuickView && onQuickView(product)}
        />
        
        {product.images.length > 1 && (
          <div className="image-dots">
            {product.images.map((_, index) => (
              <button
                key={index}
                className={`dot ${index === imageIndex ? 'active' : ''}`}
                onClick={() => setImageIndex(index)}
                aria-label={`View image ${index + 1}`}
              />
            ))}
          </div>
        )}
      </div>
      
      <div className="product-card__content">
        <h3 className="product-card__title">
          <a href={product.url}>{product.title}</a>
        </h3>
        
        <div className="product-card__rating">
          {'★'.repeat(Math.floor(product.rating))}
          {'☆'.repeat(5 - Math.floor(product.rating))}
          <span className="rating-count">({product.reviewCount})</span>
        </div>
        
        <div className="product-card__price">
          {product.sale ? (
            <>
              <span className="price--original">
                {formatPrice(product.originalPrice)}
              </span>
              <span className="price--sale">
                {formatPrice(product.salePrice)}
              </span>
            </>
          ) : (
            <span className="price">
              {formatPrice(product.price)}
            </span>
          )}
        </div>
        
        {product.variants && product.variants.length > 1 && (
          <div className="product-card__variants">
            <select 
              value={selectedVariant?.id}
              onChange={(e) => {
                const variant = product.variants.find(v => v.id === e.target.value);
                setSelectedVariant(variant);
              }}
              className="variant-selector"
            >
              {product.variants.map(variant => (
                <option key={variant.id} value={variant.id}>
                  {variant.label} - {formatPrice(variant.price)}
                </option>
              ))}
            </select>
          </div>
        )}
        
        <div className="product-card__actions">
          <button
            className={`btn-add-to-cart ${isInCart ? 'in-cart' : ''}`}
            onClick={handleAddToCart}
            disabled={!product.inStock}
          >
            {!product.inStock ? 'Out of Stock' : 
             isInCart ? 'In Cart ✓' : 'Add to Cart'}
          </button>
          
          <button
            className="btn-wishlist"
            onClick={() => {
              // Add to wishlist
              fetch('/wishlist/add', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': drupalSettings.csrfToken,
                },
                body: JSON.stringify({ product_id: product.id }),
              });
            }}
            aria-label="Add to wishlist"
          >
            ♡
          </button>
        </div>
      </div>
    </article>
  );
};

Drupal.componentEntity.register('product_card', ProductCard);
```

### Content Listing with Filters

```jsx
// content_listing.jsx
import React, { useState, useEffect, useMemo } from 'react';

const ContentListing = ({ 
  contentType = 'article',
  itemsPerPage = 12,
  filters = [],
  sortOptions = []
}) => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [activeFilters, setActiveFilters] = useState({});
  const [sortBy, setSortBy] = useState('created');
  const [sortOrder, setSortOrder] = useState('desc');
  
  // Build API query
  const buildQuery = () => {
    const params = new URLSearchParams({
      'filter[status]': 1,
      'filter[type]': contentType,
      'page[limit]': itemsPerPage,
      'page[offset]': (currentPage - 1) * itemsPerPage,
      'sort': `${sortOrder === 'desc' ? '-' : ''}${sortBy}`,
    });
    
    // Add active filters
    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value) {
        params.append(`filter[${key}]`, value);
      }
    });
    
    return params.toString();
  };
  
  // Fetch content from JSON:API
  const fetchContent = async () => {
    setLoading(true);
    
    try {
      const response = await fetch(`/jsonapi/node/${contentType}?${buildQuery()}`);
      const data = await response.json();
      
      setItems(data.data);
      setTotalItems(data.meta.count);
    } catch (error) {
      console.error('Failed to fetch content:', error);
    } finally {
      setLoading(false);
    }
  };
  
  useEffect(() => {
    fetchContent();
  }, [currentPage, activeFilters, sortBy, sortOrder]);
  
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  
  const handleFilterChange = (filterKey, value) => {
    setActiveFilters(prev => ({
      ...prev,
      [filterKey]: value,
    }));
    setCurrentPage(1);
  };
  
  return (
    <div className="content-listing">
      <div className="content-listing__header">
        <div className="results-count">
          Showing {items.length} of {totalItems} results
        </div>
        
        <div className="sort-controls">
          <select
            value={`${sortBy}:${sortOrder}`}
            onChange={(e) => {
              const [field, order] = e.target.value.split(':');
              setSortBy(field);
              setSortOrder(order);
            }}
            className="sort-select"
          >
            {sortOptions.map(option => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>
      
      <div className="content-listing__body">
        <aside className="filters-sidebar">
          {filters.map(filter => (
            <div key={filter.key} className="filter-group">
              <h3>{filter.label}</h3>
              
              {filter.type === 'select' && (
                <select
                  value={activeFilters[filter.key] || ''}
                  onChange={(e) => handleFilterChange(filter.key, e.target.value)}
                >
                  <option value="">All</option>
                  {filter.options.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              )}
              
              {filter.type === 'checkbox' && (
                <div className="checkbox-group">
                  {filter.options.map(option => (
                    <label key={option.value}>
                      <input
                        type="checkbox"
                        checked={activeFilters[filter.key]?.includes(option.value)}
                        onChange={(e) => {
                          const current = activeFilters[filter.key] || [];
                          const updated = e.target.checked
                            ? [...current, option.value]
                            : current.filter(v => v !== option.value);
                          handleFilterChange(filter.key, updated);
                        }}
                      />
                      {option.label}
                    </label>
                  ))}
                </div>
              )}
            </div>
          ))}
          
          <button
            className="clear-filters"
            onClick={() => {
              setActiveFilters({});
              setCurrentPage(1);
            }}
          >
            Clear All Filters
          </button>
        </aside>
        
        <main className="content-grid">
          {loading ? (
            <div className="loading">Loading...</div>
          ) : items.length === 0 ? (
            <div className="no-results">No items found</div>
          ) : (
            <div className="grid">
              {items.map(item => (
                <ContentCard key={item.id} item={item} />
              ))}
            </div>
          )}
        </main>
      </div>
      
      {totalPages > 1 && (
        <div className="pagination">
          <button
            onClick={() => setCurrentPage(1)}
            disabled={currentPage === 1}
          >
            First
          </button>
          
          <button
            onClick={() => setCurrentPage(prev => prev - 1)}
            disabled={currentPage === 1}
          >
            Previous
          </button>
          
          <span className="page-numbers">
            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
              const pageNum = currentPage - 2 + i;
              if (pageNum < 1 || pageNum > totalPages) return null;
              
              return (
                <button
                  key={pageNum}
                  onClick={() => setCurrentPage(pageNum)}
                  className={pageNum === currentPage ? 'active' : ''}
                >
                  {pageNum}
                </button>
              );
            }).filter(Boolean)}
          </span>
          
          <button
            onClick={() => setCurrentPage(prev => prev + 1)}
            disabled={currentPage === totalPages}
          >
            Next
          </button>
          
          <button
            onClick={() => setCurrentPage(totalPages)}
            disabled={currentPage === totalPages}
          >
            Last
          </button>
        </div>
      )}
    </div>
  );
};

const ContentCard = ({ item }) => (
  <article className="content-card">
    {item.attributes.field_image && (
      <img 
        src={item.attributes.field_image.url} 
        alt={item.attributes.field_image.alt}
      />
    )}
    <h3>{item.attributes.title}</h3>
    <p>{item.attributes.field_summary}</p>
    <a href={`/node/${item.attributes.drupal_internal__nid}`}>
      Read more →
    </a>
  </article>
);

Drupal.componentEntity.register('content_listing', ContentListing);
```

## Migration Examples

### Migrating from Paragraphs to Components

```php
namespace Drupal\mymodule\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\component_entity\Entity\ComponentEntity;

/**
 * Migrates paragraph entities to component entities.
 *
 * @MigrateProcessPlugin(
 *   id = "paragraph_to_component"
 * )
 */
class ParagraphToComponent extends ProcessPluginBase {
  
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraph = $value;
    
    // Map paragraph bundle to component bundle
    $bundle_map = [
      'hero_paragraph' => 'hero_banner',
      'text_paragraph' => 'text_block',
      'card_paragraph' => 'card',
    ];
    
    $component_bundle = $bundle_map[$paragraph->bundle()] ?? NULL;
    
    if (!$component_bundle) {
      return NULL;
    }
    
    // Create component entity
    $component = ComponentEntity::create([
      'bundle' => $component_bundle,
    ]);
    
    // Map fields
    $this->mapFields($paragraph, $component);
    
    // Set render method based on paragraph settings
    $render_method = $paragraph->field_interactive->value ? 'react' : 'twig';
    $component->set('render_method', $render_method);
    
    $component->save();
    
    return ['target_id' => $component->id()];
  }
  
  /**
   * Maps fields from paragraph to component.
   */
  protected function mapFields($paragraph, $component) {
    $field_map = [
      'field_title' => 'field_title',
      'field_body' => 'field_description',
      'field_image' => 'field_image',
      'field_link' => 'field_cta',
    ];
    
    foreach ($field_map as $source => $destination) {
      if ($paragraph->hasField($source) && $component->hasField($destination)) {
        $component->set($destination, $paragraph->get($source)->getValue());
      }
    }
  }
}
```

### Migration Configuration

```yaml
# migrate_plus.migration.paragraphs_to_components.yml
id: paragraphs_to_components
label: 'Migrate Paragraphs to Component Entities'
migration_group: content
source:
  plugin: entity:paragraph
  bundle: hero_paragraph
process:
  bundle:
    plugin: static_map
    source: bundle
    map:
      hero_paragraph: hero_banner
      text_paragraph: text_block
      card_paragraph: card
  field_title: field_heading
  field_subtitle: field_subheading
  field_description: field_body
  field_image: field_media
  field_link: field_cta_link
  render_method:
    plugin: default_value
    default_value: twig
destination:
  plugin: entity:component
migration_dependencies:
  required:
    - files
    - media
```

### Batch Migration Script

```php
/**
 * Batch migrate all paragraphs to components.
 */
function mymodule_batch_migrate_paragraphs() {
  $batch = [
    'title' => t('Migrating Paragraphs to Components'),
    'operations' => [],
    'finished' => 'mymodule_batch_migrate_finished',
  ];
  
  // Get all nodes with paragraph fields
  $query = \Drupal::entityQuery('node')
    ->exists('field_paragraphs')
    ->accessCheck(FALSE);
  
  $nids = $query->execute();
  
  foreach ($nids as $nid) {
    $batch['operations'][] = ['mymodule_migrate_node_paragraphs', [$nid]];
  }
  
  batch_set($batch);
}

/**
 * Batch operation: Migrate paragraphs for a single node.
 */
function mymodule_migrate_node_paragraphs($nid, &$context) {
  $node = \Drupal\node\Entity\Node::load($nid);
  
  if (!$node->hasField('field_paragraphs')) {
    return;
  }
  
  $components = [];
  
  foreach ($node->field_paragraphs as $paragraph_ref) {
    $paragraph = $paragraph_ref->entity;
    
    if (!$paragraph) {
      continue;
    }
    
    // Create component from paragraph
    $component = \Drupal\component_entity\Entity\ComponentEntity::create([
      'bundle' => mymodule_map_paragraph_bundle($paragraph->bundle()),
    ]);
    
    // Map fields
    mymodule_map_paragraph_fields($paragraph, $component);
    
    $component->save();
    $components[] = ['target_id' => $component->id()];
  }
  
  // Replace paragraph field with component field
  if ($node->hasField('field_components')) {
    $node->field_components = $components;
    $node->save();
  }
  
  $context['message'] = t('Migrated components for node @title', [
    '@title' => $node->label(),
  ]);
  
  $context['results'][] = $nid;
}

/**
 * Batch finished callback.
 */
function mymodule_batch_migrate_finished($success, $results, $operations) {
  if ($success) {
    $message = t('@count nodes processed.', ['@count' => count($results)]);
    \Drupal::messenger()->addStatus($message);
  } else {
    \Drupal::messenger()->addError(t('Migration failed.'));
  }
}
