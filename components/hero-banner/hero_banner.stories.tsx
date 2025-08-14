import React from 'react';
import { Meta, StoryObj } from '@storybook/react';
import HeroBanner from './hero_banner';

const meta: Meta<typeof HeroBanner> = {
  title: 'Components/HeroBanner',
  component: HeroBanner,
  parameters: {
    layout: 'fullscreen',
  },
  argTypes: {
    background_color: {
      control: 'select',
      options: ['primary', 'secondary', 'dark', 'light', 'brand'],
    },
    alignment: {
      control: 'select',
      options: ['left', 'center', 'right'],
    },
    overlay_opacity: {
      control: { type: 'range', min: 0, max: 1, step: 0.1 },
    },
  },
};

export default meta;
type Story = StoryObj<typeof HeroBanner>;

export const Default: Story = {
  args: {
    title: 'Welcome to Our Platform',
    subtitle: 'Build amazing digital experiences with our powerful tools',
    cta_button: {
      text: 'Get Started',
      url: '/get-started',
      variant: 'primary',
    },
  },
};

export const WithBackgroundImage: Story = {
  args: {
    ...Default.args,
    background_image: {
      src: 'https://images.unsplash.com/photo-1557804506-669a67965ba0',
      alt: 'Office workspace',
    },
    overlay_opacity: 0.4,
  },
};

export const LeftAligned: Story = {
  args: {
    ...Default.args,
    alignment: 'left',
    background_color: 'brand',
  },
};

export const WithSlots: Story = {
  args: {
    ...Default.args,
    slots: {
      content: (
        <div>
          <p>Additional content can be placed here.</p>
          <ul>
            <li>Feature 1</li>
            <li>Feature 2</li>
            <li>Feature 3</li>
          </ul>
        </div>
      ),
      footer: (
        <div>
          <p>✓ No credit card required ✓ 14-day free trial ✓ Cancel anytime</p>
        </div>
      ),
    },
  },
};

export const Minimal: Story = {
  args: {
    title: 'Simple Hero',
    background_color: 'light',
  },
};