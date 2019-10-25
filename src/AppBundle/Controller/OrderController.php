<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Exception\CardException;
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
     * @Route("/checkout", name="order_checkout", schemes={"https"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get('shopping_cart')->getProducts();

        $error = false;
        if ($request->isMethod('POST')) {
            $token = $request->get('stripeToken');

            /** @var User $user */
            $user = $this->getUser();

            try {
                $this->chargeCustomer($user, $token);
            } catch (CardException $exception) {
                $error = 'There was a problem charging your card '.$exception->getMessage();
            }

            if (! $error) {
                $this->get('shopping_cart')->emptyCart();
                $this->addFlash('success', 'Order complete! Yay');

                return $this->redirectToRoute('homepage');
            }
        }

        return $this->render('order/checkout.html.twig', [
            'products' => $products,
            'cart' => $this->get('shopping_cart'),
            'stripe_public_key' => $this->getParameter('stripe_public_key'),
            'error' => $error,
        ]);
    }

    /**
     * @param \AppBundle\Entity\User $user
     * @param $token
     * @throws \Stripe\Exception\CardException
     */
    private function chargeCustomer(User $user, $token)
    {
        $stripeClient = $this->get('stripe_client');

        if (! $user->getStripeCustomerId()) {
            $stripeClient->createCustomer($user, $token);
        } else {
            $stripeClient->updateCustomerCard($user, $token);
        }

        foreach ($this->get('shopping_cart')->getProducts() as $product) {
            $stripeClient->createInvoiceItem($product->getPrice() * 100, $user, $product->getName());
        }

        $stripeClient->createInvoice($user);
    }
}
