<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    /**
     * @Route("/cart/product/{slug}", name="order_add_product_to_cart")
     * @Method("POST")
     */
    public function addProductToCartAction(Product $product)
    {
        $this->get('shopping_cart')->addProduct($product);

        $this->addFlash('success', 'Product added!');

        return $this->redirectToRoute('order_checkout');
    }

    /**
     * @Route("/checkout", name="order_checkout")
     * @Security("is_granted('ROLE_USER')")
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get('shopping_cart')->getProducts();

        if ($request->isMethod('POST')) {
            $token = $request->get('stripeToken');

            Stripe::setApiKey($this->getParameter('stripe_secret_key'));

            /** @var User $user */
            $user = $this->getUser();

            if (!$user->getStripeCustomerId()) {
                $customer = Customer::create([
                    'email' => $user->getEmail(),
                    'source' => $token
                ]);

                $user->setStripeCustomerId($customer->id);
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
            } else {
                $customer = Customer::retrieve($user->getStripeCustomerId());
                $customer->source = $token;
                $customer->save();
            }

            foreach ($this->get('shopping_cart')->getProducts() as $product) {
                InvoiceItem::create([
                    'amount' => $product->getPrice() * 100,
                    'currency' => 'usd',
                    'customer' => $user->getStripeCustomerId(),
                    'description' => $product->getName(),
                ]);
            }

            $invoice = Invoice::create([
                'customer' => $user->getStripeCustomerId(),
            ]);
            $invoice->pay();

            $this->get('shopping_cart')->emptyCart();
            $this->addFlash('success', 'Order complete! Yay');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('order/checkout.html.twig', [
            'products' => $products,
            'cart' => $this->get('shopping_cart'),
            'stripe_public_ket' => $this->getParameter('stripe_public_key'),
        ]);
    }
}
