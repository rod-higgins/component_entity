/**
 * Jest test setup file
 */

import '@testing-library/jest-dom';

// Mock Drupal globals
global.Drupal = {
  behaviors: {},
  attachBehaviors: jest.fn(),
  detachBehaviors: jest.fn(),
  t: (str: string) => str,
  formatPlural: (count: number, singular: string, plural: string) => 
    count === 1 ? singular : plural,
  url: (path: string) => path,
  theme: jest.fn(),
  componentEntity: {
    register: jest.fn(),
    renderAll: jest.fn(),
    render: jest.fn(),
    hydrate: jest.fn(),
    components: new Map(),
  },
};

global.drupalSettings = {
  path: {
    baseUrl: '/',
    scriptPath: '',
    pathPrefix: '',
    currentPath: '/',
    currentPathIsAdmin: false,
    isFront: true,
    currentLanguage: 'en',
  },
  user: {
    uid: '0',
    permissionsHash: '',
  },
};

global.jQuery = jest.fn(() => ({
  length: 0,
  each: jest.fn(),
  find: jest.fn(),
  on: jest.fn(),
  off: jest.fn(),
  trigger: jest.fn(),
  addClass: jest.fn(),
  removeClass: jest.fn(),
  attr: jest.fn(),
  data: jest.fn(),
})) as any;

// Mock fetch
global.fetch = jest.fn();

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
});

// Mock IntersectionObserver
global.IntersectionObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn(),
})) as any;

// Mock ResizeObserver
global.ResizeObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn(),
})) as any;