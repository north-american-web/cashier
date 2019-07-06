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
            throw new \InvalidArgumentException('$user must have an email address.');
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

    /**
     * @param int $amount
     * @param string $interval
     * @param string $productId
     * @param int $intervalCount
     * @return object|null
     * @throws \Stripe\Error\Api
     */
    public function findPlan(int $amount, string $interval, string $productId, int $intervalCount = 1)
    {
        $params = [
            'active' => true,
            'product' => $productId,
            'limit' => 100
        ];
        do {
            $list = \Stripe\Plan::all($params);
            foreach( $list->data as $plan ){
                if( $plan->amount == $amount
                    && $plan->interval == $interval
                    && $plan->interval_count == $intervalCount ) {
                    return $plan;
                }
                $params['starting_after'] = $plan->id;
            }
        } while( $list->has_more );

        return null;
    }

    /**
     * @param int $amount
     * @param string $interval
     * @param string $productId
     * @param int $intervalCount
     * @return \Stripe\Plan
     */
    public function createPlan(int $amount, string $interval, string $productId, $intervalCount = 1)
    {
        $allowedIntervals = [
            'day',
            'week',
            'month',
            'year'
        ];
        if( !in_array($interval, $allowedIntervals)){
            throw new \InvalidArgumentException('Invalid interval. Must be one of '
                . implode(', ', $allowedIntervals) . ". \"$interval\" provided." );
        }

        $plan = \Stripe\Plan::create([
            'amount' => $amount,
            'currency' => 'usd',
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'product' => $productId,
        ]);

        return $plan;
    }

    /**
     * @param StripeChargeableUser $user
     * @param $planId
     * @return \Stripe\Subscription
     */
    public function createSubscription(StripeChargeableUser $user, $planId)
    {
        if( !$user->getStripeCustomerId() ){
            throw new \InvalidArgumentException('$user must have a Stripe customer id. Did you forget to call
                Cashier::linkUserToStripeCustomer to get one?');
        }

        return \Stripe\Subscription::create([
            'customer' => $user->getStripeCustomerId(),
            'items' => [
                [
                    'plan' => $planId
                ]
            ]
        ]);
    }
}