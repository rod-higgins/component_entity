/**
 * @file
 * React component renderer for the Component Entity module.
 * Handles registration, rendering, and hydration of React components.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Component registry for all React components.
   */
  const componentRegistry = {};
  
  /**
   * Component instances for cleanup.
   */
  const componentInstances = new Map();

  /**
   * Initialize the component entity namespace.
   */
  Drupal.componentEntity = Drupal.componentEntity || {};

  /**
   * Register a React component.
   * 
   * @param {string} name - Component name
   * @param {React.Component|Function} component - React component
   */
  Drupal.componentEntity.register = function(name, component) {
    componentRegistry[name] = component;
    console.log(`Registered component: ${name}`);
  };

  /**
   * Get a registered component.
   * 
   * @param {string} name - Component name
   * @returns {React.Component|Function|null}
   */
  Drupal.componentEntity.getComponent = function(name) {
    return componentRegistry[name] || null;
  };

  /**
   * Load a component dynamically.
   * 
   * @param {string} componentName - Component name to load
   * @returns {Promise}
   */
  const loadComponent = async (componentName) => {
    try {
      // Try to load from the compiled components directory
      const module = await import(`/modules/custom/component_entity/dist/js/${componentName}.component.js`);
      return module.default || module[componentName];
    } catch (error) {
      console.error(`Failed to load component ${componentName}:`, error);
      return null;
    }
  };

  /**
   * Render a component with error boundary.
   * 
   * @param {Object} Component - React component
   * @param {Object} props - Component props
   * @param {HTMLElement} element - DOM element to render into
   * @param {string} method - Render method (hydrate/render)
   */
  const renderComponent = (Component, props, element, method = 'render') => {
    if (!window.React || !window.ReactDOM) {
      console.error('React or ReactDOM not loaded');
      return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;

    // Error boundary wrapper
    class ErrorBoundary extends React.Component {
      constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
      }

      static getDerivedStateFromError(error) {
        return { hasError: true, error };
      }

      componentDidCatch(error, errorInfo) {
        console.error('Component Error:', error, errorInfo);
      }

      render() {
        if (this.state.hasError) {
          return React.createElement('div', {
            className: 'component-error',
            role: 'alert'
          }, 
            React.createElement('p', null, 'This component encountered an error and cannot be displayed.'),
            React.createElement('details', null,
              React.createElement('summary', null, 'Error details'),
              React.createElement('pre', null, this.state.error?.toString())
            )
          );
        }
        return this.props.children;
      }
    }

    // Wrap component in error boundary
    const componentWithBoundary = React.createElement(
      ErrorBoundary,
      null,
      React.createElement(Component, props)
    );

    // Choose render method
    if (method === 'hydrate' && ReactDOM.hydrate) {
      ReactDOM.hydrate(componentWithBoundary, element);
    } else {
      // For React 18+
      if (ReactDOM.createRoot) {
        const root = ReactDOM.createRoot(element);
        root.render(componentWithBoundary);
        componentInstances.set(element.id, root);
      } else {
        // Fallback for older React
        ReactDOM.render(componentWithBoundary, element);
      }
    }
  };

  /**
   * Process slots for React components.
   * 
   * @param {Object} slots - Raw slot HTML
   * @returns {Object} Processed slots
   */
  const processSlots = (slots) => {
    const processedSlots = {};
    
    for (const [key, value] of Object.entries(slots || {})) {
      // If slot is already rendered HTML, keep it
      if (typeof value === 'string') {
        processedSlots[key] = value;
      } else if (value && typeof value === 'object') {
        // If it's a render array or object, try to extract HTML
        processedSlots[key] = value.html || value.markup || '';
      }
    }
    
    return processedSlots;
  };

  /**
   * Render React components on the page.
   */
  Drupal.behaviors.componentEntityReact = {
    attach: function (context, settings) {
      // Check if we have components to render
      if (!settings.componentEntity || !settings.componentEntity.components) {
        return;
      }

      // Find all component roots that haven't been processed
      const componentRoots = once('component-react', '.component-react-root', context);
      
      componentRoots.forEach(function(element) {
        const componentId = element.id;
        const config = settings.componentEntity.components[componentId];
        
        if (!config) {
          console.warn(`No configuration found for component ${componentId}`);
          return;
        }

        // Check if component is registered
        let Component = componentRegistry[config.type];
        
        if (!Component) {
          // Try to load dynamically if lazy loading is enabled
          if (config.config && config.config.lazy) {
            loadComponent(config.type).then((LoadedComponent) => {
              if (LoadedComponent) {
                componentRegistry[config.type] = LoadedComponent;
                renderComponentInstance(element, LoadedComponent, config);
              }
            });
            return;
          } else {
            console.error(`Component not registered: ${config.type}`);
            return;
          }
        }

        renderComponentInstance(element, Component, config);
      });
    },

    detach: function (context, settings, trigger) {
      // Clean up React components on detach
      if (trigger === 'unload') {
        const componentRoots = context.querySelectorAll('.component-react-root');
        componentRoots.forEach(function(element) {
          if (componentInstances.has(element.id)) {
            const root = componentInstances.get(element.id);
            if (root && root.unmount) {
              root.unmount();
            }
            componentInstances.delete(element.id);
          } else if (window.ReactDOM && window.ReactDOM.unmountComponentAtNode) {
            window.ReactDOM.unmountComponentAtNode(element);
          }
        });
      }
    }
  };

  /**
   * Render a single component instance.
   * 
   * @param {HTMLElement} element - DOM element
   * @param {Object} Component - React component
   * @param {Object} config - Component configuration
   */
  function renderComponentInstance(element, Component, config) {
    // Prepare props with processed slots
    const props = {
      ...config.props,
      slots: processSlots(config.slots),
      // Add Drupal-specific props
      drupalContext: {
        entityId: config.entityId,
        entityType: 'component',
        bundle: config.type,
        viewMode: config.viewMode || 'default'
      }
    };

    // Handle different hydration methods
    const hydration = config.config?.hydration || 'full';
    
    switch (hydration) {
      case 'full':
        // Full hydration - make component interactive
        renderComponent(Component, props, element, 'hydrate');
        break;
      
      case 'partial':
        // Partial hydration - selective interactivity
        // Check if element has pre-rendered content
        if (element.innerHTML.trim()) {
          // Wait for user interaction before hydrating
          const hydrateOnInteraction = () => {
            renderComponent(Component, props, element, 'render');
            element.removeEventListener('mouseenter', hydrateOnInteraction);
            element.removeEventListener('focus', hydrateOnInteraction);
          };
          element.addEventListener('mouseenter', hydrateOnInteraction, { once: true });
          element.addEventListener('focus', hydrateOnInteraction, { once: true });
        } else {
          renderComponent(Component, props, element, 'render');
        }
        break;
      
      case 'none':
        // No hydration - static render only
        if (!element.innerHTML.trim()) {
          renderComponent(Component, props, element, 'render');
        }
        break;
      
      default:
        renderComponent(Component, props, element, 'render');
    }

    // Mark as processed
    element.dataset.reactProcessed = 'true';
    
    // Dispatch custom event
    element.dispatchEvent(new CustomEvent('component:rendered', {
      detail: { type: config.type, props: props },
      bubbles: true
    }));
  }

  /**
   * Helper function to create React elements from HTML strings.
   * Use with caution - only for trusted content!
   */
  Drupal.componentEntity.htmlToReact = function(html) {
    if (!window.React) return null;
    
    return window.React.createElement('div', {
      dangerouslySetInnerHTML: { __html: html }
    });
  };

  /**
   * Utility to refresh a specific component.
   */
  Drupal.componentEntity.refresh = function(componentId) {
    const element = document.getElementById(componentId);
    if (!element) return;
    
    const settings = drupalSettings.componentEntity?.components?.[componentId];
    if (!settings) return;
    
    // Unmount existing component
    if (componentInstances.has(componentId)) {
      const root = componentInstances.get(componentId);
      if (root && root.unmount) {
        root.unmount();
      }
      componentInstances.delete(componentId);
    }
    
    // Re-render
    const Component = componentRegistry[settings.type];
    if (Component) {
      renderComponentInstance(element, Component, settings);
    }
  };

  /**
   * Debug utility to list all registered components.
   */
  Drupal.componentEntity.debug = function() {
    console.group('Component Entity Debug Info');
    console.log('Registered components:', Object.keys(componentRegistry));
    console.log('Active instances:', Array.from(componentInstances.keys()));
    console.log('Component settings:', drupalSettings.componentEntity);
    console.groupEnd();
  };

})(Drupal, drupalSettings, once);