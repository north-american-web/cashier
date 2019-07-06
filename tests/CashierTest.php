<?php

namespace NAWebCo\CashierTest;

use NAWebCo\Cashier\Cashier;
use PHPUnit\Framework\TestCase;

class CashierTest extends TestCase
{
    public function testLoadCashier()
    {
        $cashier = new Cashier();
        $this->assertInstanceOf(Cashier::class, $cashier );
    }
}