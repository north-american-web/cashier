<?php


namespace NAWebCo\Cashier;


interface StripeChargeableUser
{

    public function getEmail();

    public function getStripeCustomerId();

    public function setStripeCustomerId($id);

    public function save();

}