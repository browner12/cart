<?php namespace browner12\cart;

use browner12\cart\Contracts\Coupon;
use browner12\money\Accountant;
use browner12\money\Money;

class Cart implements CartInterface
{
    /**
     * @const float
     */
    const TAX_RATE = 5.0;

    /**
     * @var array
     */
    private $data = [
        'name'               => null,
        'email'              => null,
        'shippingStreet'     => null,
        'shippingStreet2'    => null,
        'shippingCity'       => null,
        'shippingState'      => null,
        'shippingZip'        => null,
        'shippingCountry'    => null,
        'shippingCost'       => 0.00,
        'handlingCost'       => 300,
        'billingStreet'      => null,
        'billingCity'        => null,
        'billingState'       => null,
        'billingZip'         => null,
        'easypostShipmentId' => null,
        'easypostRateId'     => null,
        'stripeToken'        => null,
        'cardType'           => null,
    ];

    /**
     * shipping rates
     *
     * @var array
     */
    private $rates = [];

    /**
     * line items
     *
     * @var array
     */
    private $lines = [];

    /**
     * @var \browner12\cart\Contracts\Coupon
     */
    public $coupon;

    /**
     * @var \browner12\money\Accountant
     */
    private $accountant;

    /**
     * @var
     */
    private $session;

    /**
     * constructor
     *
     * @param \browner12\money\Accountant $accountant
     * @param                             $session
     */
    public function __construct(Accountant $accountant, $session)
    {
        //assign
        $this->accountant = $accountant;

        //restore cart
        $this->restore();

        //assign
        $this->session = $session;
    }

    /**
     * restore the cart from the session
     *
     * @return void
     */
    public function restore()
    {
        //retrieve session info
        if ($this->session->has('cart')) {

            //get cart
            $session = $this->session->get('cart');

            //assign session info
            $this->data = $session['data'];
            $this->rates = $session['rates'];
            $this->lines = $session['lines'];
            $this->coupon = $session['coupon'];
        }
    }

    /**
     * save the cart into the session
     *
     * @return void
     */
    public function save()
    {
        //save cart to session
        $this->session->put('cart', $this->forSession());
    }

    /**
     * add line
     *
     * @param int $productId
     * @param int $quantity
     * @return int
     */
    public function add($productId, $quantity = 1)
    {
        //validate
        $this->validateProductId($productId);
        $this->validateQuantity($quantity);

        //product is already in cart
        if ($this->isProductInCart($productId)) {
            $this->lines[$productId]->quantity += $quantity;
        }

        //product is not in cart
        else {

            //get product
            $product = Product::find($productId);

            //create new order line
            $newLine = new OrderLine();
            $newLine->product_id = $productId;
            $newLine->quantity = $quantity;
            $newLine->unitPrice = $product->price->subunits();

            $this->lines[$productId] = $newLine;
        }

        //return the new quantity
        return $this->lines[$productId]->quantity;
    }

    /**
     * subtract line
     *
     * @param int $productId
     * @param int $quantity
     * @return int
     * @throws \browner12\cart\CartException
     */
    public function subtract($productId, $quantity = 1)
    {
        //validate
        $this->validateProductId($productId);
        $this->validateQuantity($quantity);

        //product is not in cart
        if (!$this->isProductInCart($productId)) {
            throw new CartException('Product does not exist in cart.');
        }

        //product is in cart, and we're completely removing it
        elseif ($this->quantity($productId) <= $quantity) {
            $this->remove($productId);
        }

        //product is in cart, but some will remain
        else {
            $this->lines[$productId]->quantity -= $quantity;
        }
    }

    /**
     * update line
     *
     * @param int $productId
     * @param int $quantity
     * @return int
     * @throws \browner12\cart\CartException
     */
    public function update($productId, $quantity)
    {
        //validate
        $this->validateProductId($productId);
        $this->validateQuantity($quantity);

        //quantity is zero, let's remove it
        if ($quantity == 0) {
            $this->remove($productId);
            return 0;
        }

        //product is in cart
        elseif ($this->isProductInCart($productId)) {
            return $this->lines[$productId]->quantity = $quantity;
        }

        //product does not exist
        else {
            return $this->add($productId, $quantity);
        }
    }

    /**
     * remove line
     *
     * @param int $productId
     * @throws \browner12\cart\CartException
     */
    public function remove($productId)
    {
        //validate
        $this->validateProductId($productId);

        //product is in cart
        if ($this->isProductInCart($productId)) {
            unset($this->lines[$productId]);
        }

        //product is not in cart
        else {
            throw new CartException('Product is not in cart.');
        }
    }

    /**
     * get lines
     *
     * @return array
     */
    public function lines()
    {
        return $this->lines;
    }

    /**
     * count lines
     *
     * @return int
     */
    public function count()
    {
        return count($this->lines);
    }

    /**
     * quantity
     *
     * @param int $productId
     * @return int
     */
    public function quantity($productId)
    {
        //validate
        $this->validateProductId($productId);

        //in cart
        if ($this->isProductInCart($productId)) {
            return $this->lines[$productId]->quantity;
        }

        //not in cart
        return 0;
    }

    /**
     * set purchaser info
     *
     * @param array $input
     */
    public function setPurchaserInfo(array $input)
    {
        //set info
        $this->data['name'] = $input['name'];
        $this->data['email'] = $input['email'];
    }

    /**
     * set shipping rates
     *
     * @param array $rates
     */
    public function setRates(array $rates)
    {
        $this->rates = $rates;
    }

    /**
     * set shipment id
     *
     * @param string $shipmentId
     */
    public function setShipmentId($shipmentId)
    {
        $this->data['easypostShipmentId'] = $shipmentId;
    }

    /**
     * set shipping info
     *
     * this method is coupled tightly to USD
     *
     * @todo can we uncouple this from USD / base 100 currency?
     * @param array $input
     * @throws \browner12\cart\CartException
     */
    public function setShippingInfo(array $input)
    {
        //set info
        $this->data['shippingStreet'] = $input['street'];
        $this->data['shippingStreet2'] = $input['street2'];
        $this->data['shippingCity'] = $input['city'];
        $this->data['shippingState'] = $input['state'];
        $this->data['shippingZip'] = $input['zip'];
        $this->data['shippingCountry'] = $input['country'];
        $this->data['easypostRateId'] = $input['method'];

        //set shipping cost
        foreach ($this->rates as $rate) {
            if ($input['method'] == $rate->id) {
                $shippingCost = $rate->rate * 100;
                $shippingCost = (int)$shippingCost;
                $this->data['shippingCost'] = $shippingCost;
                break;
            }
        }

        //unable to set shipping cost
        if (!$this->data['shippingCost']) {
            throw new CartException('Unable to set the shipping cost.');
        }
    }

    /**
     * set billing info
     *
     * @param array $input
     */
    public function setBillingInfo(array $input)
    {
        //set info
        $this->data['billingStreet'] = $input['street'];
        $this->data['billingCity'] = $input['city'];
        $this->data['billingState'] = $input['state'];
        $this->data['billingZip'] = $input['zip'];
        $this->data['stripeToken'] = $input['stripeToken'];
        $this->data['cardType'] = $input['cardType'];
    }

    /**
     * clear checkout data
     *
     * @return void
     */
    public function clearCheckoutData()
    {
        foreach ($this->data as $key => $value) {
            $this->data[$key] = null;
        }
    }

    /**
     * clear shipping rates
     *
     * @return void
     */
    public function clearShippingRates()
    {
        $this->rates = [];
    }

    /**
     * clear order lines
     *
     * @return void
     */
    public function clearOrderLines()
    {
        $this->lines = [];
    }

    /**
     * clear coupon
     *
     * @return void
     */
    public function clearCoupon()
    {
        $this->coupon = null;
    }

    /**
     * removes checkout data, shipping rates, and empties cart
     *
     * @return $this
     */
    public function cleanup()
    {
        //cleanup everything
        $this->clearCheckoutData();
        $this->clearShippingRates();
        $this->clearOrderLines();
        $this->clearCoupon();

        //return
        return $this;
    }

    /**
     * calculate weight
     *
     * @return float
     */
    public function weight()
    {
        //initialize
        $weight = 0;

        //loop
        foreach ($this->lines as $line) {
            $weight += $line->weight();
        }

        //return
        return round($weight, 2);
    }

    /**
     * calculate subtotal
     *
     * @return \browner12\money\Money|float
     */
    public function subtotal()
    {
        //initialize
        $subtotal = new Money(0, 'USD');

        //loop through lines
        foreach ($this->lines as $line) {
            $subtotal = $this->accountant->add($subtotal, $line->subtotal());
        }

        //return
        return $subtotal;
    }

    /**
     * shipping
     *
     * @return \browner12\money\Money
     */
    public function shipping()
    {
        return new Money($this->data['shippingCost'], 'USD');
    }

    /**
     * handling
     *
     * @return \browner12\money\Money
     */
    public function handling()
    {
        $handlingCost = ($this->data['handlingCost']) ?: 300;

        return new Money($handlingCost, 'USD');
    }

    /**
     * calculate tax
     *
     * @return \browner12\money\Money|float
     */
    public function tax()
    {
        //normalize state
        $state = strtolower($this->data['billingState']);

        //tax wisconsin orders
        if ($state == 'wi' OR $state == 'wisconsin') {
            return $this->accountant->tax($this->subtotal(), self::TAX_RATE);
        }

        //do not tax outside of wisconsin
        return new Money(0, 'USD');
    }

    /**
     * apply a coupon to the cart
     *
     * @param \browner12\cart\Contracts\Coupon $coupon
     */
    public function applyCoupon(Coupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * calculate the value of the coupon
     *
     * @return \browner12\money\Money|mixed
     */
    public function couponValue()
    {
        //check that we have a coupon
        if (!$this->coupon instanceof Coupon) {
            return new Money(0, 'usd');
        }

        //percentage discount
        if ($this->coupon->getPercentageDiscount()) {
            return $this->accountant->tax($this->preTotal(), $this->coupon->getPercentageDiscount());
        }

        //flat discount
        if ($this->coupon->getFlatDiscount()) {

            //discount is more than total
            if ($this->coupon->getFlatDiscount() > $this->preTotal()) {
                return $this->preTotal();
            }

            return $this->coupon->getFlatDiscount();
        }

        //problem
        return new Money(0, 'usd');
    }

    /**
     * retrieve the applied coupon
     *
     * @return \browner12\cart\Contracts\Coupon
     */
    public function coupon()
    {
        return $this->coupon;
    }

    /**
     * could not think of a good name for this
     *
     * @return \browner12\money\Money
     */
    public function preTotal()
    {
        return $this->accountant->sum([
            $this->subtotal(),
            $this->tax(),
            $this->shipping(),
            $this->handling(),
        ]);
    }

    /**
     * calculate total
     *
     * @return \browner12\money\Money|float
     */
    public function total()
    {
        return $this->accountant->subtract($this->preTotal(), $this->couponValue());
    }

    /**
     * total in pennies
     *
     * @return integer
     */
    public function totalInCents()
    {
        return $this->total()->subunits();
    }

    /**
     * format the information to create order
     *
     * @return array
     */
    public function forOrder()
    {
        return [
            'subtotal'            => $this->subtotal(),
            'shipping'            => $this->shipping(),
            'handling'            => $this->handling(),
            'tax'                 => $this->tax(),
            'couponValue'         => $this->couponValue(),
            'coupon'              => ($this->coupon) ? $this->coupon->id : null,
            'total'               => $this->total(),
            'name'                => $this->data['name'],
            'email'               => $this->data['email'],
            'easypostShipment_id' => $this->data['easypostShipmentId'],
            'easypostRate_id'     => $this->data['easypostRateId'],
            'shippingStreet'      => $this->data['shippingStreet'],
            'shippingCity'        => $this->data['shippingCity'],
            'shippingState'       => $this->data['shippingState'],
            'shippingZip'         => $this->data['shippingZip'],
        ];
    }

    /**
     * format for session storage
     *
     * @return array
     */
    public function forSession()
    {
        return [
            'data'   => $this->data,
            'rates'  => $this->rates,
            'lines'  => $this->lines,
            'coupon' => $this->coupon,
        ];
    }

    /**
     * check if product is in cart
     *
     * @param int $productId
     * @return bool
     */
    private function isProductInCart($productId)
    {
        //product is in cart
        if (array_key_exists($productId, $this->lines)) {
            return true;
        }

        //product is not in cart
        return false;
    }

    /**
     * validate productId
     *
     * actually sent through as strings, which makes this a PITA
     *
     * @param int $productId
     * @throws \browner12\cart\CartException
     */
    private function validateProductId($productId)
    {
        if (!preg_match('/^[0-9]+$/', $productId)) {
            throw new CartException('Invalid product ID.');
        }
    }

    /**
     * validate quantity
     *
     * actually sent through as strings, which makes this a PITA
     *
     * @param int $quantity
     * @throws \browner12\cart\CartException
     */
    private function validateQuantity($quantity)
    {
        if (!preg_match('/^[0-9]+$/', $quantity)) {
            throw new CartException('Invalid quantity.');
        }
    }

    /**
     * magic getter for data
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        //only pull from data
        if (array_key_exists($property, $this->data)) {
            return $this->data[$property];
        }

        return null;
    }
}
