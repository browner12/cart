<?php namespace App\Cart;

use App\Models\OrderLine;
use App\Models\Product;
use SebastianBergmann\Money\Money;
use Session;

class Cart2 implements CartInterface
{
    /**
     * @const float
     */
    const TAX = 5.0;

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
        'shippingCost'       => null,
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
     * constructor
     */
    public function __construct()
    {
        //restore cart
        $this->restore();
    }

    /**
     * destructor
     */
    public function __destruct()
    {
        //save cart
        $this->save();
    }

    /**
     * restore the cart from the session
     *
     * @return void
     */
    public function restore()
    {
        Session::put('cartLock', 0);

        //check if cart is locked
        $this->isCartLocked();

        //lock cart
        Session::put('cartLock', 1);

        //retrieve session info
        if (Session::has('cart')) {

            //get cart
            $session = Session::get('cart');

            //assign session info
            $this->data = $session['data'];
            $this->rates = $session['rates'];
            $this->lines = $session['lines'];
        }
    }

    /**
     * save the cart into the session
     *
     * @return void
     */
    public function save()
    {
        //unlock the cart
        Session::put('cartLock', 0);

        //save cart to session
        Session::put('cart', $this->forSession());
    }

    /**
     * is cart locked
     */
    public function isCartLocked()
    {
        if (Session::get('cartLock') === 1) {
            sleep(1);
            $this->isCartLocked();
        }
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
        } //product is not in cart
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
     * @throws \App\Cart\CartException
     */
    public function subtract($productId, $quantity = 1)
    {

        //validate
        $this->validateProductId($productId);
        $this->validateQuantity($quantity);

        //product is not in cart
        if (!$this->isProductInCart($productId)) {
            throw new CartException('Product does not exist in cart.');
        } //product is in cart, and we're completely removing it
        elseif ($this->quantity($productId) <= $quantity) {
            $this->remove($productId);
        } //product is in cart, but some will remain
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
     * @throws \App\Cart\CartException
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
        } //product is in cart
        elseif ($this->isProductInCart($productId)) {
            return $this->lines[$productId]->quantity = $quantity;
        } //product does not exist
        else {
            return $this->add($productId, $quantity);
        }
    }

    /**
     * remove line
     *
     * @param int $productId
     * @throws \App\Cart\CartException
     */
    public function remove($productId)
    {

        //validate
        $this->validateProductId($productId);

        //product is in cart
        if ($this->isProductInCart($productId)) {
            unset($this->lines[$productId]);
        } //product is not in cart
        else {
            throw new CartException('Product is not in cart.');
        }
    }

    /**
     * clear cart
     *
     * @return void
     */
    public function clear()
    {
        $this->lines = [];
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
     * set shipping info
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
     * set rates
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
        empty($this->rates);
    }

    /**
     * removes checkout data, shipping rates, and empties cart
     *
     * @return void
     */
    public function cleanup()
    {
        $this->clearCheckoutData();
        $this->clearShippingRates();
        $this->clear();
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
            $new = new Money($line->subtotal(), 'USD');
            $subtotal = $subtotal->add($new);
        }

        //return
        return $subtotal;
    }

    /**
     * shipping
     *
     * @return \browner12\money\Money|float
     */
    public function shipping()
    {
        return new Money($this->data['shippingCost'], 'USD');
    }

    /**
     * calculate tax
     *
     * @return float
     */
    public function tax()
    {
        return $this->accountant->tax($this->subtotal(), self::TAX);
        //return $this->subtotal()->multiply(self::TAX);
    }

    /**
     * calculate total
     *
     * @return \browner12\money\Money|float
     */
    public function total()
    {
        return $this->accountant->sum([
            $this->subtotal(),
            $this->tax(),
            $this->shipping(),
        ]);
        //return $this->subtotal()->add($this->tax()->add($this->shipping()));
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
            'tax'                 => $this->tax(),
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
            'data'  => $this->data,
            'rates' => $this->rates,
            'lines' => $this->lines,
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
     * @throws \App\Cart\CartException
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
     * @throws \App\Cart\CartException
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
