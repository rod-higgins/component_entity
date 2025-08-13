/**
 * @file
 * Tests for the Component Entity renderer.
 */

import { ComponentRenderer } from '../js/component-renderer';

// Mock DOM elements
const createMockElement = (type = 'hero_banner', props = {}) => {
  const element = document.createElement('div');
  element.dataset.componentType = type;
  element.dataset.componentId = '123';
  element.dataset.props = JSON.stringify(props);
  element.dataset.renderMethod = 'react';
  return element;
};

describe('ComponentRenderer', () => {
  let renderer;

  beforeEach(() => {
    // Clear the DOM
    document.body.innerHTML = '';
    
    // Create new renderer instance
    renderer = new ComponentRenderer();
    
    // Mock React and ReactDOM
    global.React = {
      createElement: jest.fn(),
    };
    
    global.ReactDOM = {
      render: jest.fn(),
      hydrate: jest.fn(),
      createRoot: jest.fn(() => ({
        render: jest.fn(),
      })),
    };
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('Component Registration', () => {
    it('should register a component', () => {
      const TestComponent = () => null;
      
      renderer.register('test_component', TestComponent);
      
      expect(renderer.has('test_component')).toBe(true);
      expect(renderer.get('test_component')).toBe(TestComponent);
    });

    it('should throw error when registering duplicate component', () => {
      const TestComponent = () => null;
      
      renderer.register('test_component', TestComponent);
      
      expect(() => {
        renderer.register('test_component', TestComponent);
      }).toThrow('Component test_component is already registered');
    });

    it('should list all registered components', () => {
      const Component1 = () => null;
      const Component2 = () => null;
      
      renderer.register('component_1', Component1);
      renderer.register('component_2', Component2);
      
      const components = renderer.getAll();
      
      expect(components).toHaveProperty('component_1');
      expect(components).toHaveProperty('component_2');
    });
  });

  describe('Component Rendering', () => {
    it('should render a single component', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component', { title: 'Test' });
      document.body.appendChild(element);
      
      renderer.render(element);
      
      expect(React.createElement).toHaveBeenCalledWith(
        TestComponent,
        expect.objectContaining({
          title: 'Test',
          drupalContext: expect.objectContaining({
            componentId: '123',
            componentType: 'test_component',
          }),
        })
      );
    });

    it('should render all components in context', () => {
      const Component1 = jest.fn(() => null);
      const Component2 = jest.fn(() => null);
      
      renderer.register('component_1', Component1);
      renderer.register('component_2', Component2);
      
      const element1 = createMockElement('component_1', { title: 'One' });
      const element2 = createMockElement('component_2', { title: 'Two' });
      
      document.body.appendChild(element1);
      document.body.appendChild(element2);
      
      renderer.renderAll(document.body);
      
      expect(React.createElement).toHaveBeenCalledTimes(2);
    });

    it('should skip unregistered components', () => {
      const element = createMockElement('unregistered_component');
      document.body.appendChild(element);
      
      // Should not throw
      expect(() => {
        renderer.renderAll(document.body);
      }).not.toThrow();
      
      expect(React.createElement).not.toHaveBeenCalled();
    });
  });

  describe('Hydration', () => {
    it('should hydrate component with full hydration', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      element.dataset.hydration = 'full';
      
      renderer.hydrate(element, { title: 'Hydrated' });
      
      expect(ReactDOM.hydrate).toHaveBeenCalled();
    });

    it('should use partial hydration when specified', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      element.dataset.hydration = 'partial';
      
      renderer.hydrate(element, { title: 'Partial' });
      
      // Implementation would depend on partial hydration strategy
      expect(React.createElement).toHaveBeenCalled();
    });

    it('should skip hydration when set to none', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      element.dataset.hydration = 'none';
      
      renderer.hydrate(element, { title: 'Static' });
      
      expect(ReactDOM.hydrate).not.toHaveBeenCalled();
      expect(ReactDOM.render).toHaveBeenCalled();
    });
  });

  describe('Error Handling', () => {
    it('should handle render errors gracefully', () => {
      const ErrorComponent = () => {
        throw new Error('Render error');
      };
      
      renderer.register('error_component', ErrorComponent);
      
      const element = createMockElement('error_component');
      document.body.appendChild(element);
      
      // Mock console.error to prevent test output noise
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      
      renderer.render(element);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        expect.stringContaining('Failed to render component'),
        expect.any(Error)
      );
      
      consoleSpy.mockRestore();
    });

    it('should handle invalid props gracefully', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      element.dataset.props = 'invalid json';
      
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      
      renderer.render(element);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        expect.stringContaining('Failed to parse props'),
        expect.any(Error)
      );
      
      consoleSpy.mockRestore();
    });
  });

  describe('Drupal Integration', () => {
    it('should attach to Drupal behaviors', () => {
      global.Drupal = {
        behaviors: {},
        componentEntity: renderer,
      };
      
      renderer.attachBehaviors();
      
      expect(Drupal.behaviors).toHaveProperty('componentEntityRenderer');
      expect(typeof Drupal.behaviors.componentEntityRenderer.attach).toBe('function');
    });

    it('should respond to Drupal AJAX events', () => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      
      // Simulate Drupal AJAX content insertion
      const ajaxContent = document.createElement('div');
      ajaxContent.appendChild(element);
      
      // Trigger Drupal behaviors
      renderer.renderAll(ajaxContent);
      
      expect(React.createElement).toHaveBeenCalled();
    });

    it('should preserve Drupal settings', () => {
      global.drupalSettings = {
        componentEntity: {
          components: {
            test_component: {
              library: 'component_entity/test_component',
            },
          },
        },
      };
      
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const element = createMockElement('test_component');
      renderer.render(element);
      
      expect(React.createElement).toHaveBeenCalledWith(
        TestComponent,
        expect.objectContaining({
          drupalContext: expect.objectContaining({
            settings: expect.objectContaining({
              library: 'component_entity/test_component',
            }),
          }),
        })
      );
    });
  });

  describe('Performance', () => {
    it('should batch multiple render calls', (done) => {
      const TestComponent = jest.fn(() => null);
      renderer.register('test_component', TestComponent);
      
      const elements = [];
      for (let i = 0; i < 10; i++) {
        const element = createMockElement('test_component', { id: i });
        elements.push(element);
        document.body.appendChild(element);
      }
      
      // Queue multiple renders
      elements.forEach(el => renderer.render(el));
      
      // Renders should be batched
      setTimeout(() => {
        expect(React.createElement).toHaveBeenCalledTimes(10);
        done();
      }, 0);
    });

    it('should use lazy loading when configured', () => {
      const element = createMockElement('lazy_component');
      element.dataset.lazy = 'true';
      
      // Mock IntersectionObserver
      const observerCallback = jest.fn();
      global.IntersectionObserver = jest.fn((callback) => ({
        observe: jest.fn(),
        unobserve: jest.fn(),
        disconnect: jest.fn(),
      }));
      
      renderer.observeLazyComponents(document.body);
      
      expect(IntersectionObserver).toHaveBeenCalled();
    });
  });
});