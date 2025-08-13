/**
 * @file
 * Component Entity admin UI enhancements.
 * Provides preview functionality, inline editing, and library browser.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Component preview functionality.
   */
  Drupal.behaviors.componentEntityPreview = {
    attach: function (context, settings) {
      // Preview mode switcher
      const previewContainers = once('component-preview', '.component-preview', context);
      
      previewContainers.forEach(function(container) {
        const buttons = container.querySelectorAll('.component-preview__toggle');
        const frame = container.querySelector('.component-preview__container');
        
        buttons.forEach(function(button) {
          button.addEventListener('click', function() {
            const mode = this.dataset.previewMode;
            
            // Update button states
            buttons.forEach(btn => btn.setAttribute('aria-pressed', 'false'));
            this.setAttribute('aria-pressed', 'true');
            
            // Update frame mode
            if (frame) {
              frame.dataset.previewMode = mode;
            }
          });
        });
        
        // Refresh preview button
        const refreshBtn = container.querySelector('.component-preview__refresh');
        if (refreshBtn) {
          refreshBtn.addEventListener('click', function() {
            const url = this.dataset.refreshUrl;
            refreshPreview(container, url);
          });
        }
      });
    }
  };

  /**
   * Refresh preview content via AJAX.
   */
  function refreshPreview(container, url) {
    const frame = container.querySelector('.component-preview__frame');
    if (!frame || !url) return;
    
    // Add loading state
    frame.classList.add('is-loading');
    frame.innerHTML = '<div class="component-loading"><div class="component-loading__spinner"></div></div>';
    
    // Fetch updated preview
    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.text())
    .then(html => {
      frame.innerHTML = html;
      frame.classList.remove('is-loading');
      
      // Reattach behaviors to new content
      Drupal.attachBehaviors(frame);
    })
    .catch(error => {
      console.error('Preview refresh failed:', error);
      frame.innerHTML = '<div class="component-error">Failed to refresh preview</div>';
      frame.classList.remove('is-loading');
    });
  }

  /**
   * Inline editing for component reference fields.
   */
  Drupal.behaviors.componentEntityInlineEdit = {
    attach: function (context, settings) {
      const inlineEditors = once('component-inline-edit', '.component-reference-inline-edit', context);
      
      inlineEditors.forEach(function(editor) {
        const editBtn = editor.querySelector('.component-inline-edit__button--edit');
        const saveBtn = editor.querySelector('.component-inline-edit__button--save');
        const cancelBtn = editor.querySelector('.component-inline-edit__button--cancel');
        
        if (editBtn) {
          editBtn.addEventListener('click', function() {
            enterEditMode(editor);
          });
        }
        
        if (saveBtn) {
          saveBtn.addEventListener('click', function() {
            saveInlineEdit(editor);
          });
        }
        
        if (cancelBtn) {
          cancelBtn.addEventListener('click', function() {
            exitEditMode(editor);
          });
        }
      });
    }
  };

  /**
   * Enter inline edit mode.
   */
  function enterEditMode(editor) {
    editor.classList.add('is-editing');
    
    // Load edit form via AJAX
    const entityId = editor.dataset.entityId;
    const url = `/component/${entityId}/inline-edit`;
    
    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.text())
    .then(html => {
      const content = editor.querySelector('.component-reference-inline-edit__content');
      if (content) {
        content.innerHTML = html;
        Drupal.attachBehaviors(content);
      }
    })
    .catch(error => {
      console.error('Failed to load inline edit form:', error);
      Drupal.announce('Failed to load edit form', 'error');
    });
  }

  /**
   * Exit inline edit mode.
   */
  function exitEditMode(editor) {
    editor.classList.remove('is-editing');
    
    // Reload original content
    const entityId = editor.dataset.entityId;
    const viewMode = editor.dataset.viewMode || 'default';
    const url = `/component/${entityId}/view/${viewMode}`;
    
    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.text())
    .then(html => {
      const content = editor.querySelector('.component-reference-inline-edit__content');
      if (content) {
        content.innerHTML = html;
        Drupal.attachBehaviors(content);
      }
    });
  }

  /**
   * Save inline edit changes.
   */
  function saveInlineEdit(editor) {
    const form = editor.querySelector('form');
    if (!form) return;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        exitEditMode(editor);
        Drupal.announce('Component saved successfully', 'status');
      } else {
        Drupal.announce(data.error || 'Failed to save component', 'error');
      }
    })
    .catch(error => {
      console.error('Failed to save inline edit:', error);
      Drupal.announce('Failed to save changes', 'error');
    });
  }

  /**
   * Component library browser.
   */
  Drupal.behaviors.componentLibraryBrowser = {
    attach: function (context, settings) {
      const browsers = once('component-browser', '.component-library-browser', context);
      
      browsers.forEach(function(browser) {
        initializeLibraryBrowser(browser);
      });
    }
  };

  /**
   * Initialize component library browser.
   */
  function initializeLibraryBrowser(browser) {
    const searchInput = browser.querySelector('.component-browser__search');
    const filterButtons = browser.querySelectorAll('.component-browser__filter');
    const componentCards = browser.querySelectorAll('.component-browser__item');
    
    // Search functionality
    if (searchInput) {
      searchInput.addEventListener('input', debounce(function() {
        filterComponents(browser, this.value);
      }, 300));
    }
    
    // Filter buttons
    filterButtons.forEach(function(button) {
      button.addEventListener('click', function() {
        // Update active state
        filterButtons.forEach(btn => btn.classList.remove('is-active'));
        this.classList.add('is-active');
        
        // Apply filter
        const filter = this.dataset.filter;
        applyFilter(browser, filter);
      });
    });
    
    // Component selection
    componentCards.forEach(function(card) {
      card.addEventListener('click', function() {
        selectComponent(this);
      });
    });
  }

  /**
   * Filter components by search term.
   */
  function filterComponents(browser, searchTerm) {
    const items = browser.querySelectorAll('.component-browser__item');
    const term = searchTerm.toLowerCase();
    
    items.forEach(function(item) {
      const name = item.dataset.componentName || '';
      const description = item.dataset.componentDescription || '';
      const tags = item.dataset.componentTags || '';
      
      const matches = name.toLowerCase().includes(term) ||
                      description.toLowerCase().includes(term) ||
                      tags.toLowerCase().includes(term);
      
      item.style.display = matches ? '' : 'none';
    });
    
    updateResultsCount(browser);
  }

  /**
   * Apply category filter.
   */
  function applyFilter(browser, filter) {
    const items = browser.querySelectorAll('.component-browser__item');
    
    items.forEach(function(item) {
      if (filter === 'all') {
        item.style.display = '';
      } else {
        const category = item.dataset.componentCategory;
        item.style.display = category === filter ? '' : 'none';
      }
    });
    
    updateResultsCount(browser);
  }

  /**
   * Update results count display.
   */
  function updateResultsCount(browser) {
    const visibleItems = browser.querySelectorAll('.component-browser__item:not([style*="display: none"])');
    const countElement = browser.querySelector('.component-browser__count');
    
    if (countElement) {
      const count = visibleItems.length;
      countElement.textContent = count + ' component' + (count !== 1 ? 's' : '');
    }
  }

  /**
   * Handle component selection.
   */
  function selectComponent(card) {
    const componentType = card.dataset.componentType;
    const targetField = card.closest('.component-browser').dataset.targetField;
    
    if (!componentType || !targetField) return;
    
    // Create new component and add to field
    const url = `/component/add/${componentType}`;
    const params = new URLSearchParams({
      field: targetField,
      ajax: 1
    });
    
    fetch(`${url}?${params}`, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.text())
    .then(html => {
      // Open modal or inline form
      openComponentForm(html, targetField);
    })
    .catch(error => {
      console.error('Failed to create component:', error);
      Drupal.announce('Failed to create component', 'error');
    });
  }

  /**
   * Open component creation form.
   */
  function openComponentForm(html, targetField) {
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'component-modal';
    modal.innerHTML = `
      <div class="component-modal__backdrop"></div>
      <div class="component-modal__content">
        <div class="component-modal__header">
          <h2>Create Component</h2>
          <button class="component-modal__close" aria-label="Close">×</button>
        </div>
        <div class="component-modal__body">${html}</div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Attach behaviors
    Drupal.attachBehaviors(modal);
    
    // Close button
    const closeBtn = modal.querySelector('.component-modal__close');
    const backdrop = modal.querySelector('.component-modal__backdrop');
    
    [closeBtn, backdrop].forEach(element => {
      if (element) {
        element.addEventListener('click', function() {
          modal.remove();
        });
      }
    });
    
    // Focus trap
    trapFocus(modal);
  }

  /**
   * Trap focus within modal.
   */
  function trapFocus(modal) {
    const focusableElements = modal.querySelectorAll(
      'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
    );
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];
    
    firstFocusable.focus();
    
    modal.addEventListener('keydown', function(e) {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === firstFocusable) {
            lastFocusable.focus();
            e.preventDefault();
          }
        } else {
          if (document.activeElement === lastFocusable) {
            firstFocusable.focus();
            e.preventDefault();
          }
        }
      }
      
      if (e.key === 'Escape') {
        modal.remove();
      }
    });
  }

  /**
   * Render method switcher enhancement.
   */
  Drupal.behaviors.componentRenderMethodSwitcher = {
    attach: function (context, settings) {
      const switchers = once('render-method-switcher', '.component-form-render-method', context);
      
      switchers.forEach(function(switcher) {
        const radios = switcher.querySelectorAll('input[type="radio"][name="render_method"]');
        const reactSettings = document.querySelector('.react-settings-panel');
        
        radios.forEach(function(radio) {
          radio.addEventListener('change', function() {
            if (reactSettings) {
              reactSettings.style.display = this.value === 'react' ? 'block' : 'none';
            }
            
            // Update preview if available
            updateRenderMethodPreview(this.value);
          });
        });
      });
    }
  };

  /**
   * Update preview based on render method.
   */
  function updateRenderMethodPreview(method) {
    const preview = document.querySelector('.component-preview__frame');
    if (!preview) return;
    
    const entityId = preview.dataset.entityId;
    if (!entityId) return;
    
    const url = `/component/${entityId}/preview/${method}`;
    
    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.text())
    .then(html => {
      preview.innerHTML = html;
      Drupal.attachBehaviors(preview);
      
      // Show method indicator
      const indicator = preview.querySelector('.render-method-indicator');
      if (!indicator) {
        const badge = document.createElement('div');
        badge.className = `render-method-badge render-method-badge--${method}`;
        badge.textContent = method;
        preview.appendChild(badge);
      }
    })
    .catch(error => {
      console.error('Failed to update preview:', error);
    });
  }

  /**
   * Utility: Debounce function.
   */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Drag and drop support for component ordering.
   */
  Drupal.behaviors.componentDragDrop = {
    attach: function (context, settings) {
      if (typeof Sortable === 'undefined') {
        return; // Sortable.js not loaded
      }
      
      const sortableContainers = once('component-sortable', '.component-list--sortable', context);
      
      sortableContainers.forEach(function(container) {
        new Sortable(container, {
          animation: 150,
          handle: '.component-drag-handle',
          ghostClass: 'component--ghost',
          chosenClass: 'component--chosen',
          dragClass: 'component--drag',
          onEnd: function(evt) {
            updateComponentOrder(container);
          }
        });
      });
    }
  };

  /**
   * Update component order after drag and drop.
   */
  function updateComponentOrder(container) {
    const items = container.querySelectorAll('.component-list__item');
    const order = [];
    
    items.forEach(function(item, index) {
      const entityId = item.dataset.entityId;
      if (entityId) {
        order.push({
          id: entityId,
          weight: index
        });
      }
    });
    
    // Send updated order to server
    fetch('/component/reorder', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ order: order })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        Drupal.announce('Component order updated', 'status');
      }
    })
    .catch(error => {
      console.error('Failed to update order:', error);
      Drupal.announce('Failed to update order', 'error');
    });
  }

})(jQuery, Drupal, drupalSettings, once);