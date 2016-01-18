<?php namespace App\Cart;

use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * register the bindings
     */
    public function register()
    {
        $this->app->bind('App\Cart\CartInterface', 'App\Cart\Cart');
    }

}
