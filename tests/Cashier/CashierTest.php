<?php

namespace NAWebCo\CashierTest\Cashier;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use NAWebCo\Cashier\Cashier;
use NAWebCo\Cashier\StripeChargeableUser;
use Mockery;

class CashierTest extends MockeryTestCase
{

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testCashierSetsStripeApiKey()
    {
        new Cashier('dummy-api-key');
        $this->assertEquals('dummy-api-key', \Stripe\Stripe::$apiKey);
    }

    public function testLinkUserToStripeCustomerWithGoodData()
    {
        $customerMock = Mockery::mock('alias:\Stripe\Customer');
        $customerMock->shouldReceive('create')
            ->once()
            ->with([
                'email' => 'customer@example.com',
                'source' => 'fake-token',
                'name' => 'fake name'
            ])
            ->andReturn((object) ['id' => 'customer-id']);

        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive([
            'getEmail' => 'customer@example.com',
            'getStripeCustomerId' => null,
            'save' => null])
            ->atLeast()->times(1);
        $user->shouldReceive('setStripeCustomerId')->once()->with('customer-id')
            ->atLeast()->times(1);

        $cashier = new Cashier('dummy-api-key');
        $cashier->linkUserToStripeCustomer($user, 'fake-token', 'fake name');
    }

    public function testLinkUserToStripeCustomerWithGoodDataButNoName()
    {
        $customerMock = Mockery::mock('alias:\Stripe\Customer');
        $customerMock->shouldReceive('create')
            ->once()
            ->with([
                'email' => 'customer@example.com',
                'source' => 'fake-token',
            ])
            ->andReturn((object) ['id' => 'customer-id']);

        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive([
            'getEmail' => 'customer@example.com',
            'getStripeCustomerId' => null,
            'save' => null])
            ->atLeast()->times(1);
        $user->shouldReceive('setStripeCustomerId')->once()->with('customer-id');

        $cashier = new Cashier('dummy-api-key');
        $cashier->linkUserToStripeCustomer($user, 'fake-token');
    }

    public function testLinkUserToStripeCustomerWithNoEmail()
    {
        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive([
            'getEmail' => null,
            'getStripeCustomerId' => null])->once();

        $this->expectException(\Exception::class);

        $cashier = new Cashier('dummy-api-key');
        $cashier->linkUserToStripeCustomer($user, 'fake-token');
    }

    public function testLinkUserToStripeCustomerWithCustomerIdAlreadyExists()
    {
        $customerMock = Mockery::mock('alias:\Stripe\Customer');
        $customerMock->shouldReceive('create')->never();

        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive(['getStripeCustomerId' => '123'])->once();

        $cashier = new Cashier('dummy-api-key');
        $cashier->linkUserToStripeCustomer($user, 'fake-token');
    }

    public function testCreateCharge()
    {
        $chargeMock = Mockery::mock('alias:\Stripe\Charge');
        $chargeMock->shouldReceive('create')
            ->once()
            ->with([
                'amount' => 150,
                'currency' => 'usd',
                'description' => 'fake description',
                'customer' => '1'
            ])
            ->andReturn('charged');

        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive('getStripeCustomerId')->once()->andReturn('1');

        $cashier = new Cashier('api-key');
        $response = $cashier->createCharge($user, 150, 'fake description');

        $this->assertEquals('charged', $response);
    }

    public function testFindPlanSuccessfully()
    {
        $planMock = Mockery::mock('alias:\Stripe\Plan');
        $planMock->shouldReceive('all')
            ->once()
            ->with([
                    'active' => true,
                    'product' => 'product-id',
                    'limit' => 100
                ]
            )
            ->andReturn(
                (object) [
                    'has_more' => true,
                    'data' => [
                        (object) [ 'id' => 'p1', 'amount' => 5, 'interval' => 'month'],
                        (object) [ 'id' => 'p2', 'amount' => 6, 'interval' => 'month'],
                        (object) [ 'id' => 'p3', 'amount' => 7, 'interval' => 'month'],
                    ],
                ]
            );

        // Second call to \Stripe\Plan::all should contain the result we're looking for
        $planMock->shouldReceive('all')
        ->once()
        ->with([
                'active' => true,
                'product' => 'product-id',
                'limit' => 100,
                'starting_after' => 'p3',
            ]
        )
        ->andReturn(
            (object) [
                'has_more' => false,
                'data' => [
                    (object) [ 'id' => 'p4', 'amount' => 8, 'interval' => 'quarter', 'interval_count' => 1],
                    (object) [ 'id' => 'p5', 'amount' => 8, 'interval' => 'month', 'interval_count' => 2],
                    (object) [ 'id' => 'p6', 'amount' => 8, 'interval' => 'month', 'interval_count' => 3], // This one!
                    (object) [ 'id' => 'p7', 'amount' => 9, 'interval' => 'month', 'interval_count' => 1],
                ],
            ]
        );

        $cashier = new Cashier('stripe-api-key');
        $plan = $cashier->findPlan(8, 'month', 'product-id', 3);

        $this->assertEquals('p6', $plan->id);
    }

    public function testFindPlanNoResults()
    {
        $planMock = Mockery::mock('alias:\Stripe\Plan');
        $planMock->shouldReceive('all')->once()
            ->andReturn(
                (object) [
                    'has_more' => false,
                    'data' => [
                        (object) [ 'id' => 'p4', 'amount' => 8, 'interval' => 'quarter', 'interval_count' => 1],
                        (object) [ 'id' => 'p5', 'amount' => 8, 'interval' => 'month', 'interval_count' => 1],
                        (object) [ 'id' => 'p6', 'amount' => 9, 'interval' => 'month', 'interval_count' => 1],
                    ],
                ]
            );

        $cashier = new Cashier('stripe-api-key');
        $plan = $cashier->findPlan(1, 'month', 'product-id');

        $this->assertNull($plan);
    }

    public function testCreatePlan()
    {
        $planMock = Mockery::mock('alias:\Stripe\Plan');
        $planMock->shouldReceive('create')->once()
            ->with([
                'amount' => 123,
                'interval' => 'month',
                'product' => 'product-id',
                'interval_count' => 3,
                'currency' => 'usd',
            ])->andReturn((object) ['id' => 'plan-id']);

        $cashier = new Cashier('stripe-api-key');
        $plan = $cashier->createPlan(123, 'month', 'product-id', 3);

        $this->assertEquals('plan-id', $plan->id);
    }

    public function testCreatePlanWithoutIntervalCount()
    {
        $planMock = Mockery::mock('alias:\Stripe\Plan');
        $planMock->shouldReceive('create')->once()
            ->with([
                'amount' => 123,
                'interval' => 'month',
                'product' => 'product-id',
                'interval_count' => 1,
                'currency' => 'usd',
            ])->andReturn((object) ['id' => 'plan-id']);

        $cashier = new Cashier('stripe-api-key');
        $plan = $cashier->createPlan(123, 'month', 'product-id');

        $this->assertEquals('plan-id', $plan->id);
    }

    public function testCreateSubscriptionWithValidUser()
    {
        $subscriptionMock = Mockery::mock('alias:\Stripe\Subscription');
        $subscriptionMock->shouldReceive('create')->once()
            ->with([
                'customer' => 'customer-id',
                'items' => [['plan' => 'plan-id']]
            ])->andReturn((object)['id' => 'subscription-id']);

        $user = Mockery::mock(StripeChargeableUser::class);
        $user->shouldReceive('getStripeCustomerId')->andReturn('customer-id');

        $cashier = new Cashier('stripe-api-key');
        $subscription = $cashier->createSubscription($user, 'plan-id');

        $this->assertEquals('subscription-id', $subscription->id);
    }

}