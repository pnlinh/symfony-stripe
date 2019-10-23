<?php

namespace AppBundle;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;

class StripeClient
{
    private $em;

    public function __construct($secretKey, EntityManager $em)
    {
        Stripe::setApiKey($secretKey);
        $this->em = $em;
    }

    public function createCustomer(User $user, $paymentToken)
    {
        $customer = Customer::create([
            'email' => $user->getEmail(),
            'source' => $paymentToken,
        ]);

        $user->setStripeCustomerId($customer->id);
        $em = $this->em;
        $em->persist($user);
        $em->flush();

        return $customer;
    }

    public function updateCustomerCard(User $user, $paymentToken)
    {
        $customer = Customer::retrieve($user->getStripeCustomerId());
        $customer->source = $paymentToken;
        $customer->save();
    }

    public function createInvoiceItem($amount, User $user, $description)
    {
        InvoiceItem::create([
            'amount' => $amount,
            'currency' => 'usd',
            'customer' => $user->getStripeCustomerId(),
            'description' => $description,
        ]);
    }

    public function createInvoice(User $user, $payImmediately = true)
    {
        $invoice = Invoice::create([
            'customer' => $user->getStripeCustomerId(),
        ]);

        if ($payImmediately) {
            $invoice->pay();
        }

        return $invoice;
    }
}
