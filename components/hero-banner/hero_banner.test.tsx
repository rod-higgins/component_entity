import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import HeroBanner from './hero_banner';

describe('HeroBanner Component', () => {
  const defaultProps = {
    title: 'Test Hero Title',
  };

  beforeEach(() => {
    // Mock window.gtag
    window.gtag = jest.fn();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('Rendering', () => {
    it('renders with required props', () => {
      render(<HeroBanner {...defaultProps} />);
      expect(screen.getByText('Test Hero Title')).toBeInTheDocument();
    });

    it('renders subtitle when provided', () => {
      render(
        <HeroBanner 
          {...defaultProps} 
          subtitle="Test Subtitle" 
        />
      );
      expect(screen.getByText('Test Subtitle')).toBeInTheDocument();
    });

    it('renders background image when provided', () => {
      render(
        <HeroBanner
          {...defaultProps}
          background_image={{
            src: '/test-image.jpg',
            alt: 'Test background',
          }}
        />
      );
      const image = screen.getByAltText('Test background');
      expect(image).toHaveAttribute('src', '/test-image.jpg');
      expect(image).toHaveAttribute('loading', 'lazy');
    });

    it('renders CTA button when provided', () => {
      render(
        <HeroBanner
          {...defaultProps}
          cta_button={{
            text: 'Click Me',
            url: '/test-url',
            variant: 'primary',
          }}
        />
      );
      const button = screen.getByText('Click Me');
      expect(button).toHaveAttribute('href', '/test-url');
      expect(button).toHaveClass('button--primary');
    });

    it('renders slots content when provided', () => {
      render(
        <HeroBanner
          {...defaultProps}
          slots={{
            content: <div>Additional content</div>,
            footer: <div>Footer content</div>,
          }}
        />
      );
      expect(screen.getByText('Additional content')).toBeInTheDocument();
      expect(screen.getByText('Footer content')).toBeInTheDocument();
    });
  });

  describe('Interactions', () => {
    it('tracks CTA click events', () => {
      render(
        <HeroBanner
          {...defaultProps}
          cta_button={{
            text: 'Track Me',
            url: '/tracked',
          }}
          drupal_context={{
            entity_id: 'test-123',
            entity_type: 'component',
            bundle: 'hero_banner',
            view_mode: 'full',
          }}
        />
      );

      const button = screen.getByText('Track Me');
      fireEvent.click(button);

      expect(window.gtag).toHaveBeenCalledWith('event', 'click', {
        event_category: 'CTA',
        event_label: 'Track Me',
        component_type: 'hero_banner',
        component_id: 'test-123',
      });
    });

    it('opens external links in new tab', () => {
      const openSpy = jest.spyOn(window, 'open').mockImplementation();
      
      render(
        <HeroBanner
          {...defaultProps}
          cta_button={{
            text: 'External',
            url: 'https://external.com',
            target: '_blank',
          }}
        />
      );

      const button = screen.getByText('External');
      fireEvent.click(button);

      expect(openSpy).toHaveBeenCalledWith(
        'https://external.com',
        '_blank',
        'noopener,noreferrer'
      );

      openSpy.mockRestore();
    });

    it('shows edit button for authorized users', () => {
      render(
        <HeroBanner
          {...defaultProps}
          drupal_context={{
            entity_id: 'test-123',
            entity_type: 'component',
            bundle: 'hero_banner',
            view_mode: 'full',
            can_edit: true,
          }}
        />
      );

      expect(screen.getByLabelText('Edit hero banner')).toBeInTheDocument();
    });
  });

  describe('Styling', () => {
    it('applies correct alignment classes', () => {
      const { container } = render(
        <HeroBanner
          {...defaultProps}
          alignment="left"
        />
      );
      expect(container.firstChild).toHaveClass('hero-banner--align-left');
    });

    it('applies correct color theme classes', () => {
      const { container } = render(
        <HeroBanner
          {...defaultProps}
          background_color="primary"
        />
      );
      expect(container.firstChild).toHaveClass('hero-banner--primary');
    });

    it('applies custom min-height', () => {
      const { container } = render(
        <HeroBanner
          {...defaultProps}
          min_height="600px"
        />
      );
      expect(container.firstChild).toHaveStyle('min-height: 600px');
    });

    it('applies overlay opacity', () => {
      const { container } = render(
        <HeroBanner
          {...defaultProps}
          background_image={{
            src: '/test.jpg',
            alt: 'Test',
          }}
          overlay_opacity={0.6}
        />
      );
      const overlay = container.querySelector('.hero-banner__overlay');
      expect(overlay).toHaveStyle('opacity: 0.6');
    });
  });

  describe('Accessibility', () => {
    it('has proper heading hierarchy', () => {
      render(<HeroBanner {...defaultProps} />);
      const heading = screen.getByRole('heading', { level: 1 });
      expect(heading).toHaveTextContent('Test Hero Title');
    });

    it('includes proper alt text for images', () => {
      render(
        <HeroBanner
          {...defaultProps}
          background_image={{
            src: '/test.jpg',
            alt: 'Descriptive alt text',
          }}
        />
      );
      expect(screen.getByAltText('Descriptive alt text')).toBeInTheDocument();
    });

    it('marks external links with rel attributes', () => {
      render(
        <HeroBanner
          {...defaultProps}
          cta_button={{
            text: 'External',
            url: 'https://external.com',
            target: '_blank',
          }}
        />
      );
      const link = screen.getByText('External');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });
  });
});