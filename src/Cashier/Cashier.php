<?php
namespace NAWebCo\Cashier;

use Stripe\Stripe;

class Cashier
{
    /**
     * Cashier constructor.
     * @param string $stripeApiKey
     */
    public function __construct( string $stripeApiKey )
    {
        Stripe::setApiKey($stripeApiKey);
    }

    /**
     * @param StripeChargeableUser $user
     * @param string $cardToken
     * @param string|null $name
     * @return Cashier
     * @throws \Exception
     */
    public function linkUserToStripeCustomer(StripeChargeableUser $user, string $cardToken, string $name = null): Cashier
    {
        // User is already a Stripe customer
        if( $user->getStripeCustomerId() ){
            return $this;
        }

        if( !$user->getEmail() ){
            throw new \Exception('Cannot create Stripe customer without an email address.');
        }

        $data = [
            'email' => $user->getEmail(),
            'source' => $cardToken
        ];
        if( $name ){
            $data['name'] = $name;
        };

        $customer = \Stripe\Customer::create($data);

        $user->setStripeCustomerId($customer->id);
        $user->save();

        return $this;
    }

    /**
     * @param StripeChargeableUser $user
     * @param int $amountInCents
     * @param string $description
     * @return object
     */
    public function createCharge(StripeChargeableUser $user, int $amountInCents, string $description): string
    {
        return \Stripe\Charge::create([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'description' => $description,
            'customer' => $user->getStripeCustomerId()
        ]);
    }
    
}