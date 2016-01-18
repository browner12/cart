<?php namespace App\Cart;

interface CouponInterface
{
    /**
     * get the flat discount of a coupon
     *
     * @return int
     */
    public function getFlatDiscount();

    /**
     * get the percentage discount of a coupon
     *
     * @return int
     */
    public function getPercentageDiscount();

    /**
     * get the maximum number of times the coupon can be used
     *
     * @return int
     */
    public function getMaximumUses();

    /**
     * get the number of times the coupon has been used
     *
     * @return int
     */
    public function getUses();

    /**
     * get the expiration date of the coupon
     *
     * @return \Carbon\Carbon
     */
    public function getExpirationDate();
}
