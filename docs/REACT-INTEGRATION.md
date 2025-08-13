# React Integration Guide

## Table of Contents

- [Overview](#overview)
- [Setup and Configuration](#setup-and-configuration)
- [Component Development](#component-development)
- [Hydration Strategies](#hydration-strategies)
- [State Management](#state-management)
- [API Integration](#api-integration)
- [Performance Optimization](#performance-optimization)
- [Testing React Components](#testing-react-components)
- [Troubleshooting](#troubleshooting)

## Overview

The Component Entity module provides seamless React integration with Drupal, allowing components to be rendered either server-side with Twig or client-side with React. This guide covers the React-specific aspects of the module.

### How It Works

1. **Build Time**: JSX/TSX files are compiled to JavaScript
2. **Registration**: Components register with Drupal's component registry
3. **Rendering**: Drupal provides data and mount points
4. **Hydration**: React takes over client-side interactivity

### Architecture

```
Drupal Backend                     React Frontend
┌─────────────┐                   ┌──────────────┐
│   Entity    │                   │  Component   │
│    Data     │ ──── JSON ───▶   │   Registry   │
└─────────────┘                   └──────────────┘
      │                                  │
      ▼                                  ▼
┌─────────────┐                   ┌──────────────┐
│   Render    │                   │   Hydrate/   │
│   Initial   │ ─── HTML+Props ─▶ │   Render     │
│    HTML     │                   │              │
└─────────────┘                   └──────────────┘
```

## Setup and Configuration

### Installing Dependencies

```bash
# Navigate to module directory
cd modules/contrib/component_entity

# Install React and build tools
npm install

# Install additional React libraries (optional)
npm install --save \
  @tanstack/react-query \
  react-hook-form \
  framer-motion \
  react-intersection-observer
```

### Webpack Configuration

```javascript
// webpack.config.js
const path = require('path');
const glob = require('glob');

// Auto-discover React components
const componentEntries = {};
const componentFiles = glob.sync('components/**/*.{jsx,tsx}');

componentFiles.forEach(file => {
  const name = path.basename(file, path.extname(file));
  componentEntries[name] = `./${file}`;
});

module.exports = {
  mode: process.env.NODE_ENV || 'development',
  entry: {
    'component-renderer': './js/component-renderer.js',
    ...componentEntries,
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: '[name].bundle.js',
    library: {
      name: 'ComponentEntity',
      type: 'umd',
    },
  },
  module: {
    rules: [
      {
        test: /\.(jsx?|tsx?)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              '@babel/preset-env',
              '@babel/preset-react',
              '@babel/preset-typescript',
            ],
          },
        },
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
    alias: {
      '@components': path.resolve(__dirname, 'components'),
      '@utils': path.resolve(__dirname, 'js/utils'),
    },
  },
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
    drupal: 'Drupal',
    drupalSettings: 'drupalSettings',
  },
  devtool: process.env.NODE_ENV === 'development' ? 'source-map' : false,
};
```

### TypeScript Configuration

```json
// tsconfig.json
{
  "compilerOptions": {
    "target": "ES2020",
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "jsx": "react-jsx",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "allowSyntheticDefaultImports": true,
    "baseUrl": ".",
    "paths": {
      "@components/*": ["components/*"],
      "@utils/*": ["js/utils/*"],
      "@types/*": ["types/*"]
    },
    "types": ["node", "react", "react-dom"]
  },
  "include": ["components/**/*", "js/**/*", "types/**/*"],
  "exclude": ["node_modules", "dist"]
}
```

### Drupal Type Definitions

```typescript
// types/drupal.d.ts
declare global {
  interface Window {
    Drupal: DrupalInterface;
    drupalSettings: DrupalSettings;
  }

  interface DrupalInterface {
    componentEntity: ComponentEntityRegistry;
    behaviors: Record<string, DrupalBehavior>;
    t: (str: string, args?: Record<string, string>) => string;
    ajax: (settings: any) => any;
  }

  interface ComponentEntityRegistry {
    register: (name: string, component: React.ComponentType<any>) => void;
    get: (name: string) => React.ComponentType<any> | undefined;
    has: (name: string) => boolean;
    renderAll: (context?: Element) => void;
    hydrate: (element: Element, props: any) => void;
  }

  interface DrupalSettings {
    path: {
      baseUrl: string;
      currentPath: string;
    };
    user: {
      uid: string;
      permissions?: string[];
    };
    componentEntity?: {
      components?: Record<string, ComponentConfig>;
    };
    csrfToken: string;
  }

  interface ComponentConfig {
    type: string;
    props: any;
    config: {
      hydration?: 'full' | 'partial' | 'none';
      progressive?: boolean;
    };
  }
}

export {};
```

## Component Development

### Basic React Component

```tsx
// components/hero_banner/hero_banner.tsx
import React, { FC } from 'react';
import './hero_banner.css';

interface HeroBannerProps {
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
  drupalContext?: DrupalContext;
}

interface DrupalContext {
  entityId: string;
  entityType: string;
  bundle: string;
  viewMode: string;
  canEdit?: boolean;
}

const HeroBanner: FC<HeroBannerProps> = ({
  title,
  subtitle,
  backgroundImage,
  ctaButton,
  theme = 'light',
  drupalContext,
}) => {
  const handleCtaClick = () => {
    // Track event
    if (window.gtag) {
      window.gtag('event', 'click', {
        event_category: 'CTA',
        event_label: ctaButton?.text,
        component_id: drupalContext?.entityId,
      });
    }
  };

  return (
    <section className={`hero-banner hero-banner--${theme}`}>
      {backgroundImage && (
        <div className="hero-banner__background">
          <img src={backgroundImage.src} alt={backgroundImage.alt} />
        </div>
      )}
      
      <div className="hero-banner__content">
        <h1>{title}</h1>
        {subtitle && <p>{subtitle}</p>}
        
        {ctaButton && (
          <a 
            href={ctaButton.url}
            className="hero-banner__cta"
            onClick={handleCtaClick}
          >
            {ctaButton.text}
          </a>
        )}
        
        {drupalContext?.canEdit && (
          <button className="hero-banner__edit">
            Edit Component
          </button>
        )}
      </div>
    </section>
  );
};

// Register with Drupal
if (typeof window !== 'undefined' && window.Drupal?.componentEntity) {
  window.Drupal.componentEntity.register('hero_banner', HeroBanner);
}

export default HeroBanner;
```

### Component with Hooks

```tsx
// components/interactive_gallery/interactive_gallery.tsx
import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useInView } from 'react-intersection-observer';

interface GalleryImage {
  id: string;
  src: string;
  thumbnail: string;
  alt: string;
  caption?: string;
}

interface InteractiveGalleryProps {
  images: GalleryImage[];
  columns?: number;
  lazyLoad?: boolean;
  lightbox?: boolean;
}

const InteractiveGallery: React.FC<InteractiveGalleryProps> = ({
  images,
  columns = 3,
  lazyLoad = true,
  lightbox = true,
}) => {
  const [selectedImage, setSelectedImage] = useState<GalleryImage | null>(null);
  const [loadedImages, setLoadedImages] = useState<Set<string>>(new Set());
  const [filter, setFilter] = useState('');
  
  // Filter images based on search
  const filteredImages = useMemo(() => {
    if (!filter) return images;
    
    return images.filter(img => 
      img.alt.toLowerCase().includes(filter.toLowerCase()) ||
      img.caption?.toLowerCase().includes(filter.toLowerCase())
    );
  }, [images, filter]);
  
  // Preload next/previous images
  const preloadAdjacentImages = useCallback((currentIndex: number) => {
    const indicesToPreload = [
      currentIndex - 1,
      currentIndex + 1,
    ].filter(i => i >= 0 && i < images.length);
    
    indicesToPreload.forEach(index => {
      const img = new Image();
      img.src = images[index].src;
    });
  }, [images]);
  
  // Keyboard navigation
  useEffect(() => {
    if (!selectedImage || !lightbox) return;
    
    const handleKeyPress = (e: KeyboardEvent) => {
      const currentIndex = images.findIndex(img => img.id === selectedImage.id);
      
      switch (e.key) {
        case 'Escape':
          setSelectedImage(null);
          break;
        case 'ArrowLeft':
          if (currentIndex > 0) {
            setSelectedImage(images[currentIndex - 1]);
          }
          break;
        case 'ArrowRight':
          if (currentIndex < images.length - 1) {
            setSelectedImage(images[currentIndex + 1]);
          }
          break;
      }
    };
    
    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, [selectedImage, images, lightbox]);
  
  return (
    <div className="interactive-gallery">
      <div className="gallery-controls">
        <input
          type="search"
          placeholder="Search images..."
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          className="gallery-search"
        />
        
        <select
          value={columns}
          onChange={(e) => {
            // Update columns via Drupal AJAX
            Drupal.ajax({
              url: '/api/component/gallery/columns',
              submit: { columns: e.target.value },
            }).execute();
          }}
        >
          <option value="2">2 Columns</option>
          <option value="3">3 Columns</option>
          <option value="4">4 Columns</option>
        </select>
      </div>
      
      <div 
        className="gallery-grid"
        style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}
      >
        {filteredImages.map((image, index) => (
          <GalleryItem
            key={image.id}
            image={image}
            lazyLoad={lazyLoad}
            onClick={() => {
              setSelectedImage(image);
              preloadAdjacentImages(index);
            }}
            onLoad={() => setLoadedImages(prev => new Set(prev).add(image.id))}
            isLoaded={loadedImages.has(image.id)}
          />
        ))}
      </div>
      
      {lightbox && selectedImage && (
        <Lightbox
          image={selectedImage}
          onClose={() => setSelectedImage(null)}
          onNext={() => {
            const currentIndex = images.findIndex(img => img.id === selectedImage.id);
            if (currentIndex < images.length - 1) {
              setSelectedImage(images[currentIndex + 1]);
            }
          }}
          onPrevious={() => {
            const currentIndex = images.findIndex(img => img.id === selectedImage.id);
            if (currentIndex > 0) {
              setSelectedImage(images[currentIndex - 1]);
            }
          }}
          hasNext={images.findIndex(img => img.id === selectedImage.id) < images.length - 1}
          hasPrevious={images.findIndex(img => img.id === selectedImage.id) > 0}
        />
      )}
    </div>
  );
};

const GalleryItem: React.FC<{
  image: GalleryImage;
  lazyLoad: boolean;
  onClick: () => void;
  onLoad: () => void;
  isLoaded: boolean;
}> = ({ image, lazyLoad, onClick, onLoad, isLoaded }) => {
  const { ref, inView } = useInView({
    threshold: 0.1,
    triggerOnce: true,
  });
  
  const shouldLoad = !lazyLoad || inView;
  
  return (
    <div ref={ref} className="gallery-item" onClick={onClick}>
      {shouldLoad ? (
        <img
          src={image.thumbnail}
          alt={image.alt}
          onLoad={onLoad}
          className={`gallery-image ${isLoaded ? 'loaded' : 'loading'}`}
        />
      ) : (
        <div className="gallery-placeholder">Loading...</div>
      )}
      
      {image.caption && (
        <div className="gallery-caption">{image.caption}</div>
      )}
    </div>
  );
};

const Lightbox: React.FC<{
  image: GalleryImage;
  onClose: () => void;
  onNext: () => void;
  onPrevious: () => void;
  hasNext: boolean;
  hasPrevious: boolean;
}> = ({ image, onClose, onNext, onPrevious, hasNext, hasPrevious }) => {
  return (
    <div className="lightbox" onClick={onClose}>
      <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
        <img src={image.src} alt={image.alt} />
        
        {image.caption && (
          <div className="lightbox-caption">{image.caption}</div>
        )}
        
        <button className="lightbox-close" onClick={onClose}>×</button>
        
        {hasPrevious && (
          <button className="lightbox-prev" onClick={onPrevious}>‹</button>
        )}
        
        {hasNext && (
          <button className="lightbox-next" onClick={onNext}>›</button>
        )}
      </div>
    </div>
  );
};

// Register component
if (typeof window !== 'undefined' && window.Drupal?.componentEntity) {
  window.Drupal.componentEntity.register('interactive_gallery', InteractiveGallery);
}

export default InteractiveGallery;
```

## Hydration Strategies

### Full Hydration

Complete React takeover of server-rendered HTML.

```javascript
// Full hydration implementation
Drupal.behaviors.fullHydration = {
  attach: function(context) {
    const components = context.querySelectorAll('[data-hydration="full"]');
    
    components.forEach(element => {
      const componentType = element.dataset.componentType;
      const props = JSON.parse(element.dataset.props);
      
      const Component = Drupal.componentEntity.get(componentType);
      
      if (Component) {
        // React 18 with createRoot
        const root = ReactDOM.createRoot(element);
        root.render(React.createElement(Component, props));
      }
    });
  }
};
```

### Partial Hydration

Selective hydration based on user interaction.

```javascript
// Partial hydration with IntersectionObserver
Drupal.behaviors.partialHydration = {
  attach: function(context) {
    const components = context.querySelectorAll('[data-hydration="partial"]');
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const element = entry.target;
          const componentType = element.dataset.componentType;
          const props = JSON.parse(element.dataset.props);
          
          const Component = Drupal.componentEntity.get(componentType);
          
          if (Component) {
            ReactDOM.render(React.createElement(Component, props), element);
            observer.unobserve(element);
          }
        }
      });
    }, {
      rootMargin: '50px',
    });
    
    components.forEach(component => observer.observe(component));
  }
};
```

### Progressive Enhancement

```javascript
// Progressive enhancement pattern
class ProgressiveComponent {
  constructor(element) {
    this.element = element;
    this.props = JSON.parse(element.dataset.props);
    this.enhanced = false;
    
    // Check if we should enhance
    if (this.shouldEnhance()) {
      this.enhance();
    } else {
      this.setupFallback();
    }
  }
  
  shouldEnhance() {
    // Check for required features
    return (
      'IntersectionObserver' in window &&
      'fetch' in window &&
      window.matchMedia('(min-width: 768px)').matches
    );
  }
  
  enhance() {
    const Component = Drupal.componentEntity.get(this.element.dataset.componentType);
    
    if (Component) {
      // Add loading state
      this.element.classList.add('enhancing');
      
      // Render React component
      ReactDOM.render(
        React.createElement(Component, {
          ...this.props,
          onReady: () => {
            this.element.classList.remove('enhancing');
            this.element.classList.add('enhanced');
            this.enhanced = true;
          }
        }),
        this.element
      );
    }
  }
  
  setupFallback() {
    // Keep server-rendered HTML
    // Add basic JavaScript interactions
    this.element.querySelectorAll('[data-action]').forEach(button => {
      button.addEventListener('click', (e) => {
        const action = e.target.dataset.action;
        this.handleFallbackAction(action);
      });
    });
  }
  
  handleFallbackAction(action) {
    // Handle actions without React
    switch (action) {
      case 'toggle':
        this.element.classList.toggle('expanded');
        break;
      case 'submit':
        this.submitForm();
        break;
    }
  }
}

// Initialize progressive components
Drupal.behaviors.progressiveEnhancement = {
  attach: function(context) {
    const components = context.querySelectorAll('[data-progressive]');
    components.forEach(el => new ProgressiveComponent(el));
  }
};
```

## State Management

### Local State with Context

```tsx
// contexts/ComponentContext.tsx
import React, { createContext, useContext, useReducer, ReactNode } from 'react';

interface ComponentState {
  isEditing: boolean;
  isDirty: boolean;
  data: Record<string, any>;
  errors: Record<string, string>;
}

type ComponentAction =
  | { type: 'SET_EDITING'; payload: boolean }
  | { type: 'UPDATE_DATA'; payload: Partial<ComponentState['data']> }
  | { type: 'SET_ERROR'; payload: { field: string; error: string } }
  | { type: 'CLEAR_ERRORS' }
  | { type: 'RESET' };

const ComponentContext = createContext<{
  state: ComponentState;
  dispatch: React.Dispatch<ComponentAction>;
} | null>(null);

const componentReducer = (
  state: ComponentState,
  action: ComponentAction
): ComponentState => {
  switch (action.type) {
    case 'SET_EDITING':
      return { ...state, isEditing: action.payload };
    
    case 'UPDATE_DATA':
      return {
        ...state,
        data: { ...state.data, ...action.payload },
        isDirty: true,
      };
    
    case 'SET_ERROR':
      return {
        ...state,
        errors: {
          ...state.errors,
          [action.payload.field]: action.payload.error,
        },
      };
    
    case 'CLEAR_ERRORS':
      return { ...state, errors: {} };
    
    case 'RESET':
      return {
        isEditing: false,
        isDirty: false,
        data: {},
        errors: {},
      };
    
    default:
      return state;
  }
};

export const ComponentProvider: React.FC<{
  children: ReactNode;
  initialData?: Record<string, any>;
}> = ({ children, initialData = {} }) => {
  const [state, dispatch] = useReducer(componentReducer, {
    isEditing: false,
    isDirty: false,
    data: initialData,
    errors: {},
  });
  
  return (
    <ComponentContext.Provider value={{ state, dispatch }}>
      {children}
    </ComponentContext.Provider>
  );
};

export const useComponent = () => {
  const context = useContext(ComponentContext);
  
  if (!context) {
    throw new Error('useComponent must be used within ComponentProvider');
  }
  
  return context;
};
```

### Global State with Redux

```typescript
// store/componentStore.ts
import { configureStore, createSlice, PayloadAction } from '@reduxjs/toolkit';

interface ComponentEntity {
  id: string;
  type: string;
  data: Record<string, any>;
  status: 'idle' | 'loading' | 'succeeded' | 'failed';
  error?: string;
}

interface ComponentState {
  entities: Record<string, ComponentEntity>;
  selectedId: string | null;
}

const componentSlice = createSlice({
  name: 'components',
  initialState: {
    entities: {},
    selectedId: null,
  } as ComponentState,
  reducers: {
    componentAdded(state, action: PayloadAction<ComponentEntity>) {
      state.entities[action.payload.id] = action.payload;
    },
    
    componentUpdated(state, action: PayloadAction<{
      id: string;
      changes: Partial<ComponentEntity>;
    }>) {
      const component = state.entities[action.payload.id];
      if (component) {
        Object.assign(component, action.payload.changes);
      }
    },
    
    componentRemoved(state, action: PayloadAction<string>) {
      delete state.entities[action.payload];
    },
    
    componentSelected(state, action: PayloadAction<string>) {
      state.selectedId = action.payload;
    },
  },
});

export const store = configureStore({
  reducer: {
    components: componentSlice.reducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

export const {
  componentAdded,
  componentUpdated,
  componentRemoved,
  componentSelected,
} = componentSlice.actions;
```

## API Integration

### Fetching Component Data

```typescript
// hooks/useComponentData.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

interface ComponentData {
  id: string;
  type: string;
  attributes: Record<string, any>;
}

export const useComponentData = (componentId: string) => {
  return useQuery<ComponentData>({
    queryKey: ['component', componentId],
    queryFn: async () => {
      const response = await fetch(`/jsonapi/component/${componentId}`, {
        headers: {
          'Accept': 'application/vnd.api+json',
        },
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch component');
      }
      
      const json = await response.json();
      return json.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
  });
};

export const useUpdateComponent = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({
      componentId,
      data,
    }: {
      componentId: string;
      data: Partial<ComponentData>;
    }) => {
      const response = await fetch(`/jsonapi/component/${componentId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/vnd.api+json',
          'X-CSRF-Token': window.drupalSettings.csrfToken,
        },
        body: JSON.stringify({ data }),
      });
      
      if (!response.ok) {
        throw new Error('Failed to update component');
      }
      
      return response.json();
    },
    onSuccess: (data, variables) => {
      // Invalidate and refetch
      queryClient.invalidateQueries(['component', variables.componentId]);
      
      // Show success message
      if (window.Drupal) {
        Drupal.message('Component updated successfully');
      }
    },
    onError: (error) => {
      console.error('Update failed:', error);
      
      if (window.Drupal) {
        Drupal.message('Failed to update component', 'error');
      }
    },
  });
};
```

### Real-time Updates with WebSockets

```typescript
// hooks/useComponentWebSocket.ts
import { useEffect, useState } from 'react';

interface WebSocketMessage {
  type: 'update' | 'delete' | 'create';
  componentId: string;
  data?: any;
  userId?: string;
}

export const useComponentWebSocket = (componentId: string) => {
  const [socket, setSocket] = useState<WebSocket | null>(null);
  const [isConnected, setIsConnected] = useState(false);
  const [lastMessage, setLastMessage] = useState<WebSocketMessage | null>(null);
  
  useEffect(() => {
    const ws = new WebSocket(`wss://${window.location.host}/component-updates`);
    
    ws.onopen = () => {
      setIsConnected(true);
      
      // Subscribe to component updates
      ws.send(JSON.stringify({
        action: 'subscribe',
        componentId,
      }));
    };
    
    ws.onmessage = (event) => {
      const message: WebSocketMessage = JSON.parse(event.data);
      
      if (message.componentId === componentId) {
        setLastMessage(message);
        
        // Trigger re-render or update local state
        handleComponentUpdate(message);
      }
    };
    
    ws.onclose = () => {
      setIsConnected(false);
    };
    
    ws.onerror = (error) => {
      console.error('WebSocket error:', error);
    };
    
    setSocket(ws);
    
    return () => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
          action: 'unsubscribe',
          componentId,
        }));
        ws.close();
      }
    };
  }, [componentId]);
  
  const sendMessage = (message: any) => {
    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify(message));
    }
  };
  
  const handleComponentUpdate = (message: WebSocketMessage) => {
    // Update local state or trigger refetch
    switch (message.type) {
      case 'update':
        // Update component data
        break;
      case 'delete':
        // Handle deletion
        break;
      case 'create':
        // Handle new component
        break;
    }
  };
  
  return {
    isConnected,
    lastMessage,
    sendMessage,
  };
};
```

## Performance Optimization

### Code Splitting

```javascript
// Lazy load components
const LazyGallery = React.lazy(() => import('./components/gallery/gallery'));
const LazyChart = React.lazy(() => import('./components/chart/chart'));

// Component with lazy loading
const DynamicComponent = ({ componentType, ...props }) => {
  const components = {
    gallery: LazyGallery,
    chart: LazyChart,
  };
  
  const Component = components[componentType];
  
  if (!Component) {
    return <div>Component not found</div>;
  }
  
  return (
    <React.Suspense fallback={<div>Loading...</div>}>
      <Component {...props} />
    </React.Suspense>
  );
};
```

### Memoization

```tsx
// Optimize with memo and useMemo
import React, { memo, useMemo, useCallback } from 'react';

interface ExpensiveComponentProps {
  data: any[];
  filter: string;
  onItemClick: (item: any) => void;
}

const ExpensiveComponent = memo<ExpensiveComponentProps>(({
  data,
  filter,
  onItemClick,
}) => {
  // Memoize expensive computations
  const filteredData = useMemo(() => {
    console.log('Filtering data...');
    return data.filter(item => 
      item.name.toLowerCase().includes(filter.toLowerCase())
    );
  }, [data, filter]);
  
  const sortedData = useMemo(() => {
    console.log('Sorting data...');
    return [...filteredData].sort((a, b) => a.name.localeCompare(b.name));
  }, [filteredData]);
  
  // Memoize callbacks
  const handleClick = useCallback((item: any) => {
    console.log('Item clicked:', item);
    onItemClick(item);
  }, [onItemClick]);
  
  return (
    <div className="expensive-component">
      {sortedData.map(item => (
        <div key={item.id} onClick={() => handleClick(item)}>
          {item.name}
        </div>
      ))}
    </div>
  );
}, (prevProps, nextProps) => {
  // Custom comparison for memo
  return (
    prevProps.filter === nextProps.filter &&
    prevProps.data === nextProps.data
  );
});

ExpensiveComponent.displayName = 'ExpensiveComponent';
```

### Virtual Scrolling

```tsx
// Virtual scrolling for large lists
import { FixedSizeList } from 'react-window';

const VirtualList = ({ items }) => {
  const Row = ({ index, style }) => (
    <div style={style}>
      {items[index].name}
    </div>
  );
  
  return (
    <FixedSizeList
      height={600}
      itemCount={items.length}
      itemSize={50}
      width="100%"
    >
      {Row}
    </FixedSizeList>
  );
};
```

## Testing React Components

### Unit Tests

```tsx
// __tests__/HeroBanner.test.tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import HeroBanner from '../components/hero_banner/hero_banner';

describe('HeroBanner', () => {
  const defaultProps = {
    title: 'Test Title',
    subtitle: 'Test Subtitle',
    theme: 'light' as const,
  };
  
  it('renders title and subtitle', () => {
    render(<HeroBanner {...defaultProps} />);
    
    expect(screen.getByText('Test Title')).toBeInTheDocument();
    expect(screen.getByText('Test Subtitle')).toBeInTheDocument();
  });
  
  it('applies correct theme class', () => {
    const { container } = render(
      <HeroBanner {...defaultProps} theme="dark" />
    );
    
    expect(container.firstChild).toHaveClass('hero-banner--dark');
  });
  
  it('renders CTA button when provided', () => {
    const props = {
      ...defaultProps,
      ctaButton: {
        text: 'Click Me',
        url: '/test',
      },
    };
    
    render(<HeroBanner {...props} />);
    
    const button = screen.getByText('Click Me');
    expect(button).toHaveAttribute('href', '/test');
  });
  
  it('shows edit button when user can edit', () => {
    const props = {
      ...defaultProps,
      drupalContext: {
        entityId: '123',
        entityType: 'component',
        bundle: 'hero_banner',
        viewMode: 'full',
        canEdit: true,
      },
    };
    
    render(<HeroBanner {...props} />);
    
    expect(screen.getByText('Edit Component')).toBeInTheDocument();
  });
});
```

### Integration Tests

```tsx
// __tests__/ComponentIntegration.test.tsx
import React from 'react';
import { render, waitFor, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ComponentWithData from '../components/ComponentWithData';

// Mock fetch
global.fetch = jest.fn();

describe('Component Integration', () => {
  let queryClient: QueryClient;
  
  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
      },
    });
    
    (global.fetch as jest.Mock).mockClear();
  });
  
  it('fetches and displays data', async () => {
    const mockData = {
      data: {
        id: '123',
        attributes: {
          title: 'Fetched Title',
        },
      },
    };
    
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => mockData,
    });
    
    render(
      <QueryClientProvider client={queryClient}>
        <ComponentWithData componentId="123" />
      </QueryClientProvider>
    );
    
    await waitFor(() => {
      expect(screen.getByText('Fetched Title')).toBeInTheDocument();
    });
    
    expect(global.fetch).toHaveBeenCalledWith(
      '/jsonapi/component/123',
      expect.objectContaining({
        headers: {
          'Accept': 'application/vnd.api+json',
        },
      })
    );
  });
});
```

## Troubleshooting

### Common Issues and Solutions

#### Component Not Rendering

```javascript
// Debug checklist
console.log('1. Is component registered?', 
  Drupal.componentEntity.has('my_component'));

console.log('2. Component registry:', 
  Drupal.componentEntity.getAll());

console.log('3. Props data:', 
  document.querySelector('[data-component-type="my_component"]')?.dataset.props);

console.log('4. React loaded?', 
  typeof React !== 'undefined');

console.log('5. Drupal behaviors attached?', 
  Drupal.behaviors);
```

#### Hydration Mismatch

```javascript
// Fix hydration mismatches
const SafeHydrate = ({ children }) => {
  const [hasMounted, setHasMounted] = useState(false);
  
  useEffect(() => {
    setHasMounted(true);
  }, []);
  
  if (!hasMounted) {
    // Return server-rendered content
    return <div suppressHydrationWarning>{children}</div>;
  }
  
  // Return client-rendered content
  return children;
};
```

#### Performance Issues

```javascript
// Performance profiling
const ProfiledComponent = () => {
  useEffect(() => {
    performance.mark('component-mount-start');
    
    return () => {
      performance.mark('component-mount-end');
      performance.measure(
        'component-mount',
        'component-mount-start',
        'component-mount-end'
      );
      
      const measure = performance.getEntriesByName('component-mount')[0];
      console.log(`Component mount time: ${measure.duration}ms`);
    };
  }, []);
  
  return <YourComponent />;
};
```

### Debug Mode

```javascript
// Enable debug mode
localStorage.setItem('component_entity_debug', 'true');

// Debug component
window.ComponentEntityDebug = {
  logProps: (componentType) => {
    const elements = document.querySelectorAll(`[data-component-type="${componentType}"]`);
    elements.forEach((el, index) => {
      console.log(`Component ${index}:`, JSON.parse(el.dataset.props));
    });
  },
  
  testRender: (componentType, props = {}) => {
    const Component = Drupal.componentEntity.get(componentType);
    const container = document.createElement('div');
    document.body.appendChild(container);
    
    ReactDOM.render(React.createElement(Component, props), container);
    console.log('Test render complete');
  },
  
  checkDependencies: () => {
    const deps = {
      React: typeof React !== 'undefined',
      ReactDOM: typeof ReactDOM !== 'undefined',
      Drupal: typeof Drupal !== 'undefined',
      ComponentRegistry: typeof Drupal?.componentEntity !== 'undefined',
    };
    
    console.table(deps);
    return deps;
  },
};
```