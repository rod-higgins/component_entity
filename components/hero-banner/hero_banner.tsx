import React, { FC, useState, useEffect, useCallback, useRef } from 'react';
import './hero_banner.css';

interface HeroBannerProps {
  title: string;
  subtitle?: string;
  background_image?: {
    src: string;
    alt: string;
    loading?: 'lazy' | 'eager';
  };
  background_color?: 'primary' | 'secondary' | 'dark' | 'light' | 'brand';
  cta_button?: {
    text: string;
    url: string;
    variant?: 'primary' | 'secondary' | 'outline';
    target?: '_self' | '_blank';
  };
  alignment?: 'left' | 'center' | 'right';
  overlay_opacity?: number;
  min_height?: string;
  drupal_context?: {
    entity_id: string;
    entity_type: string;
    bundle: string;
    view_mode: string;
    can_edit?: boolean;
  };
  slots?: {
    content?: React.ReactNode;
    footer?: React.ReactNode;
  };
}

const HeroBanner: FC<HeroBannerProps> = ({
  title,
  subtitle,
  background_image,
  background_color = 'dark',
  cta_button,
  alignment = 'center',
  overlay_opacity = 0.4,
  min_height = '500px',
  drupal_context,
  slots = {},
}) => {
  const [isLoaded, setIsLoaded] = useState(false);
  const [isVisible, setIsVisible] = useState(false);
  const heroRef = useRef<HTMLElement>(null);

  // Intersection Observer for animations
  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.1 }
    );

    if (heroRef.current) {
      observer.observe(heroRef.current);
    }

    return () => observer.disconnect();
  }, []);

  // Image load handler
  const handleImageLoad = useCallback(() => {
    setIsLoaded(true);
  }, []);

  // CTA click handler with analytics
  const handleCtaClick = useCallback((e: React.MouseEvent<HTMLAnchorElement>) => {
    // Track event if analytics available
    if (window.gtag && cta_button) {
      window.gtag('event', 'click', {
        event_category: 'CTA',
        event_label: cta_button.text,
        component_type: 'hero_banner',
        component_id: drupal_context?.entity_id,
      });
    }

    // Handle external links
    if (cta_button?.target === '_blank') {
      e.preventDefault();
      window.open(cta_button.url, '_blank', 'noopener,noreferrer');
    }
  }, [cta_button, drupal_context]);

  // Edit handler for Drupal integration
  const handleEdit = useCallback(() => {
    if (drupal_context && window.Drupal) {
      const editUrl = `/admin/structure/component/${drupal_context.bundle}/${drupal_context.entity_id}/edit`;
      window.location.href = editUrl;
    }
  }, [drupal_context]);

  // Build CSS classes
  const heroClasses = [
    'hero-banner',
    `hero-banner--${background_color}`,
    `hero-banner--align-${alignment}`,
    isVisible ? 'hero-banner--visible' : '',
    isLoaded ? 'hero-banner--loaded' : '',
  ].filter(Boolean).join(' ');

  const overlayStyle = {
    opacity: overlay_opacity,
  };

  const containerStyle = {
    minHeight: min_height,
  };

  return (
    <section 
      ref={heroRef}
      className={heroClasses}
      style={containerStyle}
      data-component="hero-banner"
      data-entity-id={drupal_context?.entity_id}
    >
      {/* Background Image */}
      {background_image && (
        <div className="hero-banner__background">
          <img
            src={background_image.src}
            alt={background_image.alt}
            loading={background_image.loading || 'lazy'}
            onLoad={handleImageLoad}
            className="hero-banner__image"
          />
          <div 
            className="hero-banner__overlay"
            style={overlayStyle}
          />
        </div>
      )}

      {/* Content Container */}
      <div className="hero-banner__container">
        <div className="hero-banner__content">
          {/* Title */}
          <h1 className="hero-banner__title">
            {title}
          </h1>

          {/* Subtitle */}
          {subtitle && (
            <p className="hero-banner__subtitle">
              {subtitle}
            </p>
          )}

          {/* Slot: Additional Content */}
          {slots.content && (
            <div className="hero-banner__body">
              {slots.content}
            </div>
          )}

          {/* CTA Button */}
          {cta_button && (
            <div className="hero-banner__actions">
              <a
                href={cta_button.url}
                className={`hero-banner__cta button button--${cta_button.variant || 'primary'}`}
                target={cta_button.target}
                rel={cta_button.target === '_blank' ? 'noopener noreferrer' : undefined}
                onClick={handleCtaClick}
              >
                {cta_button.text}
              </a>
            </div>
          )}

          {/* Edit Button (Admin Only) */}
          {drupal_context?.can_edit && (
            <button
              className="hero-banner__edit"
              onClick={handleEdit}
              aria-label="Edit hero banner"
            >
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9 9a.5.5 0 0 1-.253.143l-3 .75a.5.5 0 0 1-.606-.606l.75-3a.5.5 0 0 1 .143-.253l9-9z"/>
              </svg>
              Edit
            </button>
          )}
        </div>

        {/* Slot: Footer Content */}
        {slots.footer && (
          <div className="hero-banner__footer">
            {slots.footer}
          </div>
        )}
      </div>
    </section>
  );
};

// Register with Drupal Component Entity
if (typeof window !== 'undefined' && window.Drupal?.componentEntity) {
  window.Drupal.componentEntity.register('hero_banner', HeroBanner);
}

// Export for testing and external use
export default HeroBanner;