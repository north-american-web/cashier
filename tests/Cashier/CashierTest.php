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
        $chargeMock = Mockery::mock('alias:\Stripe\Customer');
        $chargeMock->shouldReceive('create')
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
        $chargeMock = Mockery::mock('alias:\Stripe\Customer');
        $chargeMock->shouldReceive('create')
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
        $chargeMock = Mockery::mock('alias:\Stripe\Customer');
        $chargeMock->shouldReceive('create')->never();

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

}