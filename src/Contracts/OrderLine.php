<?php namespace browner12\cart\Contracts;

interface OrderLine
{
    /**
     * get the product
     *
     * @return \browner12\cart\Contracts\Product
     */
    public function getProduct();

    /**
     * get quantity of the order line
     *
     * @return int
     */
    public function getQuantity();

    /**
     * get value of the order line
     *
     * @return int
     */
    public function getValue();

    /**
     * get weight of the order line
     *
     * @return float
     */
    public function getWeight();
}
