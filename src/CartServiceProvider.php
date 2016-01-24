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

}
