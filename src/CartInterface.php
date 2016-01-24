<?php namespace browner12\cart;

use browner12\cart\Contracts\Coupon;

interface CartInterface
{
    /**
     * restore
     *
     * @return void
     */
    public function restore();

    /**
     * save
     *
     * @return void
     */
    public function save();

    /**
     * add line
     *
     * @param int $productId
     * @param int $quantity
     * @return int
     */
    public function add($productId, $quantity = 1);

    /**
     * update line
     *
     * @param int $productId
     * @param int $quantity
     * @return bool
     * @throws \browner12\cart\CartException
     */
    public function update($productId, $quantity);

    /**
     * remove line
     *
     * @param int $productId
     * @throws \browner12\cart\CartException
     */
    public function remove($productId);

    /**
     * clear cart
     *
     * @return void
     */
    public function clear();

    /**
     * get lines
     *
     * @return array
     */
    public function lines();

    /**
     * count lines
     *
     * @return int
     */
    public function count();

    /**
     * set shipping info
     *
     * @param array $input
     */
    public function setPurchaserInfo(array $input);

    /**
     * set rates
     *
     * @param array
     */
    public function setRates(array $rates);

    /**
     * set shipment id
     *
     * @param string
     */
    public function setShipmentId($shipmentId);

    /**
     * set shipping info
     *
     * @param array $input
     */
    public function setShippingInfo(array $input);

    /**
     * set billing info
     *
     * @param array $input
     */
    public function setBillingInfo(array $input);

    /**
     * clear checkout data
     *
     * removes checkout data
     *
     * @return bool
     */
    public function clearCheckoutData();

    /**
     * cleanup
     *
     * removes checkout data and empties cart
     */
    public function cleanup();

    /**
     * calculate weight
     *
     * @return float
     */
    public function weight();

    /**
     * calculate subtotal
     *
     * @return float
     */
    public function subtotal();

    /**
     * shipping
     *
     * @return float
     */
    public function shipping();

    /**
     * calculate tax
     *
     * @return float
     */
    public function tax();

    /**
     * calculate total
     *
     * @return float
     */
    public function total();

    /**
     * total in pennies
     *
     * @return integer
     */
    public function totalInCents();

    /**
     * format the information to create order
     *
     * @return array
     */
    public function forOrder();

    /**
     * format for session storage
     *
     * @return array
     */
    public function forSession();

    /**
     * apply a coupon to the cart
     *
     * @param \browner12\cart\Contracts\Coupon $coupon
     */
    public function applyCoupon(Coupon $coupon);

}
