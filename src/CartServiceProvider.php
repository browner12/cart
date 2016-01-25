<?php namespace browner12\cart;

use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * register the bindings
     */
    public function register()
    {
        $this->app->bind('browner12\cart\CartInterface', 'browner12\cart\Cart');
    }

    /**
     * boot
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/cart.php' => config_path('cart.php'),
        ]);
    }

}
