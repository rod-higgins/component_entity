/**
 * Drupal global type definitions
 */

declare global {
  interface Window {
    Drupal: DrupalInterface;
    drupalSettings: DrupalSettings;
    jQuery: JQueryStatic;
  }

  const Drupal: DrupalInterface;
  const drupalSettings: DrupalSettings;
  const jQuery: JQueryStatic;
}

interface DrupalInterface {
  behaviors: Record<string, DrupalBehavior>;
  attachBehaviors: (context?: Element, settings?: DrupalSettings) => void;
  detachBehaviors: (context?: Element, settings?: DrupalSettings, trigger?: string) => void;
  t: (str: string, args?: Record<string, string>, options?: { context?: string }) => string;
  formatPlural: (count: number, singular: string, plural: string, args?: Record<string, string>) => string;
  url: (path: string) => string;
  theme: (func: string, variables?: Record<string, any>) => string;
  componentEntity?: ComponentEntityRegistry;
}

interface DrupalBehavior {
  attach?: (context: Element, settings?: DrupalSettings) => void;
  detach?: (context: Element, settings?: DrupalSettings, trigger?: string) => void;
}

interface DrupalSettings {
  path: {
    baseUrl: string;
    scriptPath: string;
    pathPrefix: string;
    currentPath: string;
    currentPathIsAdmin: boolean;
    isFront: boolean;
    currentLanguage: string;
  };
  user: {
    uid: string;
    permissionsHash: string;
  };
  [key: string]: any;
}

interface ComponentEntityRegistry {
  register: (componentId: string, component: React.ComponentType<any>) => void;
  renderAll: (context?: Element) => void;
  render: (element: Element, props?: Record<string, any>) => void;
  hydrate: (element: Element, props?: Record<string, any>) => void;
  components: Map<string, React.ComponentType<any>>;
}

interface JQueryStatic {
  (selector: string | Element | Document): JQuery;
  ajax: (settings: any) => any;
  once: (id: string) => JQuery;
}

interface JQuery {
  length: number;
  [index: number]: Element;
  each: (callback: (index: number, element: Element) => void) => JQuery;
  find: (selector: string) => JQuery;
  on: (event: string, handler: (e: Event) => void) => JQuery;
  off: (event: string) => JQuery;
  trigger: (event: string) => JQuery;
  addClass: (className: string) => JQuery;
  removeClass: (className: string) => JQuery;
  attr: (name: string, value?: string) => string | JQuery;
  data: (key: string, value?: any) => any;
}

export {};