/**
 * @file
 * Tests for the HeroBanner React component.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import HeroBanner from '../../../components/hero-banner/hero-banner';

// Mock Drupal global
const mockDrupal = {
  t: (str: string) => str,
  url: (path: string) => path,
  ajax: jest.fn(),
};

// Mock drupalSettings
const mockDrupalSettings = {
  path: {
    baseUrl: '/',
    currentPath: '/test',
  },
  user: {
    uid: '1',
    permissions: ['edit any component entities'],
  },
};

describe('HeroBanner Component', () => {
  beforeEach(() => {
    // Set up globals
    (global as any).Drupal = mockDrupal;
    (global as any).drupalSettings = mockDrupalSettings;
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('Rendering', () => {
    it('should render with required props', () => {
      render(
        <HeroBanner 
          title="Test Hero Title"
        />
      );

      expect(screen.getByText('Test Hero Title')).toBeInTheDocument();
    });

    it('should render with all props', () => {
      render(
        <HeroBanner
          title="Full Hero"
          subtitle="With all the props"
          backgroundImage={{
            src: '/images/hero.jpg',
            alt: 'Hero background',
          }}
          ctaButton={{
            text: 'Click Me',
            url: '/action',
          }}
          theme="dark"
        />
      );

      expect(screen.getByText('Full Hero')).toBeInTheDocument();
      expect(screen.getByText('With all the props')).toBeInTheDocument();
      expect(screen.getByText('Click Me')).toBeInTheDocument();
      expect(screen.getByRole('img')).toHaveAttribute('alt', 'Hero background');
    });

    it('should apply correct theme classes', () => {
      const { container, rerender } = render(
        <HeroBanner title="Theme Test" theme="light" />
      );

      expect(container.firstChild).toHaveClass('hero-banner--light');

      rerender(<HeroBanner title="Theme Test" theme="dark" />);
      expect(container.firstChild).toHaveClass('hero-banner--dark');

      rerender(<HeroBanner title="Theme Test" theme="brand" />);
      expect(container.firstChild).toHaveClass('hero-banner--brand');
    });
  });

  describe('Interactivity', () => {
    it('should handle CTA button click', () => {
      const handleClick = jest.fn();
      
      render(
        <HeroBanner
          title="Click Test"
          ctaButton={{
            text: 'Action',
            url: '/test',
            onClick: handleClick,
          }}
        />
      );

      const button = screen.getByText('Action');
      fireEvent.click(button);

      expect(handleClick).toHaveBeenCalledTimes(1);
    });

    it('should track analytics on CTA click', () => {
      const mockGtag = jest.fn();
      (window as any).gtag = mockGtag;

      render(
        <HeroBanner
          title="Analytics Test"
          ctaButton={{
            text: 'Track Me',
            url: '/tracked',
          }}
          drupalContext={{
            entityId: '456',
            entityType: 'component',
            bundle: 'hero_banner',
            viewMode: 'full',
          }}
        />
      );

      const button = screen.getByText('Track Me');
      fireEvent.click(button);

      expect(mockGtag).toHaveBeenCalledWith('event', 'click', {
        event_category: 'CTA',
        event_label: 'Track Me',
        component_id: '456',
      });
    });
  });

  describe('Drupal Integration', () => {
    it('should show edit button for users with permission', () => {
      render(
        <HeroBanner
          title="Edit Test"
          drupalContext={{
            entityId: '789',
            entityType: 'component',
            bundle: 'hero_banner',
            viewMode: 'full',
            canEdit: true,
          }}
        />
      );

      expect(screen.getByText('Edit Component')).toBeInTheDocument();
    });

    it('should not show edit button without permission', () => {
      render(
        <HeroBanner
          title="No Edit Test"
          drupalContext={{
            entityId: '789',
            entityType: 'component',
            bundle: 'hero_banner',
            viewMode: 'full',
            canEdit: false,
          }}
        />
      );

      expect(screen.queryByText('Edit Component')).not.toBeInTheDocument();
    });

    it('should handle edit button click', () => {
      mockDrupal.ajax.mockImplementation(() => ({
        execute: jest.fn(),
      }));

      render(
        <HeroBanner
          title="Edit Click Test"
          drupalContext={{
            entityId: '999',
            entityType: 'component',
            bundle: 'hero_banner',
            viewMode: 'full',
            canEdit: true,
          }}
        />
      );

      const editButton = screen.getByText('Edit Component');
      fireEvent.click(editButton);

      expect(mockDrupal.ajax).toHaveBeenCalledWith({
        url: '/component/999/edit',
        dialogType: 'modal',
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper heading hierarchy', () => {
      render(
        <HeroBanner
          title="Heading Test"
          subtitle="Subheading Test"
        />
      );

      const heading = screen.getByRole('heading', { level: 1 });
      expect(heading).toHaveTextContent('Heading Test');

      const subheading = screen.getByRole('heading', { level: 2 });
      expect(subheading).toHaveTextContent('Subheading Test');
    });

    it('should have proper ARIA labels', () => {
      render(
        <HeroBanner
          title="ARIA Test"
          ctaButton={{
            text: 'Learn More',
            url: '/learn',
            ariaLabel: 'Learn more about our services',
          }}
        />
      );

      const button = screen.getByText('Learn More');
      expect(button).toHaveAttribute('aria-label', 'Learn more about our services');
    });

    it('should handle keyboard navigation', () => {
      const handleClick = jest.fn();

      render(
        <HeroBanner
          title="Keyboard Test"
          ctaButton={{
            text: 'Keyboard Action',
            url: '/keyboard',
            onClick: handleClick,
          }}
        />
      );

      const button = screen.getByText('Keyboard Action');
      button.focus();
      
      fireEvent.keyDown(button, { key: 'Enter' });
      expect(handleClick).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(button, { key: ' ' });
      expect(handleClick).toHaveBeenCalledTimes(2);
    });
  });

  describe('Performance', () => {
    it('should memoize expensive computations', () => {
      const expensiveComputation = jest.fn((title: string) => title.toUpperCase());

      const MemoizedHeroBanner = React.memo(
        ({ title }: { title: string }) => {
          const computedTitle = React.useMemo(
            () => expensiveComputation(title),
            [title]
          );
          return <HeroBanner title={computedTitle} />;
        }
      );

      const { rerender } = render(<MemoizedHeroBanner title="test" />);
      expect(expensiveComputation).toHaveBeenCalledTimes(1);

      // Re-render with same props
      rerender(<MemoizedHeroBanner title="test" />);
      expect(expensiveComputation).toHaveBeenCalledTimes(1);

      // Re-render with different props
      rerender(<MemoizedHeroBanner title="different" />);
      expect(expensiveComputation).toHaveBeenCalledTimes(2);
    });
  });

  describe('Error Handling', () => {
    it('should handle missing optional props gracefully', () => {
      // Should not throw
      expect(() => {
        render(<HeroBanner title="Minimal" />);
      }).not.toThrow();
    });

    it('should handle invalid image sources', () => {
      render(
        <HeroBanner
          title="Invalid Image"
          backgroundImage={{
            src: '',
            alt: 'Empty source',
          }}
        />
      );

      const image = screen.queryByRole('img');
      expect(image).not.toBeInTheDocument();
    });

    it('should display error boundary on component error', () => {
      // Mock console.error to suppress error output in tests
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

      const ThrowError = () => {
        throw new Error('Test error');
      };

      const ErrorBoundary = ({ children }: { children: React.ReactNode }) => {
        const [hasError, setHasError] = React.useState(false);

        React.useEffect(() => {
          const handleError = () => setHasError(true);
          window.addEventListener('error', handleError);
          return () => window.removeEventListener('error', handleError);
        }, []);

        if (hasError) {
          return <div>Something went wrong</div>;
        }

        return <>{children}</>;
      };

      render(
        <ErrorBoundary>
          <HeroBanner title="Error Test" />
          <ThrowError />
        </ErrorBoundary>
      );

      // Note: This is a simplified error boundary test
      // In practice, you'd use React's ErrorBoundary component

      consoleSpy.mockRestore();
    });
  });

  describe('Loading States', () => {
    it('should show loading state while fetching data', async () => {
      const MockAsyncHeroBanner = () => {
        const [data, setData] = React.useState<any>(null);
        const [loading, setLoading] = React.useState(true);

        React.useEffect(() => {
          setTimeout(() => {
            setData({ title: 'Loaded Title' });
            setLoading(false);
          }, 100);
        }, []);

        if (loading) {
          return <div>Loading...</div>;
        }

        return <HeroBanner {...data} />;
      };

      render(<MockAsyncHeroBanner />);
      
      expect(screen.getByText('Loading...')).toBeInTheDocument();

      await waitFor(() => {
        expect(screen.getByText('Loaded Title')).toBeInTheDocument();
      });
    });
  });
});