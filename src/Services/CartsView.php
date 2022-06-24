<?php

namespace Drupal\commerceformatage\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Permet d'afficher et de gerer un panier.
 *
 * @author stephane
 *        
 */
class CartsView {
  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;
  
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  
  function __construct(CartProviderInterface $cart_provider, EntityTypeManagerInterface $entity_type_manager) {
    $this->cartProvider = $cart_provider;
    $this->entityTypeManager = $entity_type_manager;
  }
  
  function getCartRender() {
    $cachable_metadata = new CacheableMetadata();
    $cachable_metadata->addCacheContexts([
      'user',
      'session'
    ]);
    
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->cartProvider->getCarts();
    
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });
    
    $url = Url::fromRoute('commerce_checkout.checkout');
    $url->setOption('attributes', [
      'class' => 'btn btn-outline-primary my-5 mx-3'
    ]);
    //
    
    if (!empty($carts)) {
      
      $build['cart'] = [
        '#type' => 'html_tag',
        '#tag' => 'section',
        '#attributes' => [
          'id' => 'commerceformatage_cart_habeuk_view_id'
        ],
        $this->getCartViews($carts),
        [
          '#type' => 'link',
          '#url' => $url,
          '#title' => Markup::create(t('passer la commande ') . '<i class="fas fa-angle-right ml-3"></i>')
        ]
      ];
    }
    else
      $build['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t('Votre panier est vide.'),
        '#attributes' => [
          'id' => 'commerceformatage_cart_habeuk_view_id',
          'class' => [
            'hello',
            'px-4',
            'py-3'
          ]
        ]
      ];
    return $build;
  }
  
  /**
   * Gets the cart views for each cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $carts
   *        The cart orders.
   *        
   * @return array An array of view ids keyed by cart order ID.
   */
  protected function getCartViews(array $carts) {
    $cart_views = [];
    //
    $order_type_ids = array_map(function ($cart) {
      return $cart->bundle();
    }, $carts);
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    $order_types = $order_type_storage->loadMultiple(array_unique($order_type_ids));
    
    $available_views = [];
    foreach ($order_type_ids as $cart_id => $order_type_id) {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
      $order_type = $order_types[$order_type_id];
      $available_views[$cart_id] = $order_type->getThirdPartySetting('commerce_cart', 'cart_block_view', 'commerce_cart_block');
    }
    
    foreach ($carts as $cart_id => $cart) {
      $cart_views[] = [
        '#prefix' => '<div class="cart cart-block">',
        '#suffix' => '</div>',
        '#type' => 'view',
        '#name' => $available_views[$cart_id],
        '#arguments' => [
          $cart_id
        ],
        '#embed' => TRUE
      ];
    }
    
    return $cart_views;
  }
  
}