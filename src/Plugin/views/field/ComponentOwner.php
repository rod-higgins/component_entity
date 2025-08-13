<?php

namespace Drupal\component_entity\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Field handler for component owner/author.
 *
 * @ViewsField("component_owner")
 */
class ComponentOwner extends EntityField {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['display_format'] = ['default' => 'username'];
    $options['link_to_user'] = ['default' => TRUE];
    $options['show_avatar'] = ['default' => FALSE];
    $options['avatar_size'] = ['default' => 'small'];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['display_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#options' => [
        'username' => $this->t('Username'),
        'realname' => $this->t('Real name (if available)'),
        'email' => $this->t('Email address'),
        'uid' => $this->t('User ID'),
        'full' => $this->t('Full (Name <email>)'),
      ],
      '#default_value' => $this->options['display_format'],
    ];
    
    $form['link_to_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to user profile'),
      '#default_value' => $this->options['link_to_user'],
    ];
    
    $form['show_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show user avatar'),
      '#default_value' => $this->options['show_avatar'],
    ];
    
    $form['avatar_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Avatar size'),
      '#options' => [
        'small' => $this->t('Small (25x25)'),
        'medium' => $this->t('Medium (50x50)'),
        'large' => $this->t('Large (100x100)'),
      ],
      '#default_value' => $this->options['avatar_size'],
      '#states' => [
        'visible' => [
          ':input[name="options[show_avatar]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    
    if (!$entity instanceof EntityOwnerInterface) {
      return '';
    }
    
    $owner = $entity->getOwner();
    
    if (!$owner) {
      return $this->t('Anonymous');
    }
    
    // Build the display text based on format.
    $display_text = '';
    switch ($this->options['display_format']) {
      case 'username':
        $display_text = $owner->getDisplayName();
        break;
      
      case 'realname':
        // Check if real name field exists.
        if ($owner->hasField('field_real_name') && !$owner->get('field_real_name')->isEmpty()) {
          $display_text = $owner->get('field_real_name')->value;
        }
        else {
          $display_text = $owner->getDisplayName();
        }
        break;
      
      case 'email':
        $display_text = $owner->getEmail();
        break;
      
      case 'uid':
        $display_text = $owner->id();
        break;
      
      case 'full':
        $display_text = sprintf(
          '%s <%s>',
          $owner->getDisplayName(),
          $owner->getEmail()
        );
        break;
      
      default:
        $display_text = $owner->getDisplayName();
    }
    
    // Build the render array.
    $build = [];
    
    // Add avatar if requested.
    if ($this->options['show_avatar']) {
      $avatar_sizes = [
        'small' => 25,
        'medium' => 50,
        'large' => 100,
      ];
      
      $size = $avatar_sizes[$this->options['avatar_size']] ?? 25;
      
      $build['avatar'] = [
        '#theme' => 'user_picture',
        '#account' => $owner,
        '#style_name' => 'thumbnail',
        '#attributes' => [
          'width' => $size,
          'height' => $size,
          'class' => ['component-owner-avatar'],
        ],
      ];
    }
    
    // Add the text display.
    if ($this->options['link_to_user'] && $owner->access('view')) {
      $build['name'] = [
        '#type' => 'link',
        '#title' => $display_text,
        '#url' => $owner->toUrl(),
        '#attributes' => [
          'class' => ['component-owner-link'],
        ],
      ];
    }
    else {
      $build['name'] = [
        '#markup' => $display_text,
        '#prefix' => '<span class="component-owner-text">',
        '#suffix' => '</span>',
      ];
    }
    
    // Wrap in a container if we have both avatar and name.
    if ($this->options['show_avatar']) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['component-owner-wrapper'],
        ],
        'avatar' => $build['avatar'] ?? [],
        'name' => $build['name'],
      ];
    }
    
    return $build['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    $this->ensureMyTable();
    
    // Join to the users table if needed.
    $this->query->ensureTable('users_field_data', $this->relationship);
    
    // Sort by username.
    $this->query->addOrderBy('users_field_data', 'name', $order);
  }

}