<?php namespace browner12\cart\Contracts;

interface Product
{
    /**
     * get value of the product
     *
     * @return int
     */
    public function getValue();

    /**
     * get weight of the product
     *
     * @return float
     */
    public function getWeight();
}
