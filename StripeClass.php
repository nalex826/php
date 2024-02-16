<?php

/**
 * Class StripeAPI
 * A class for interacting with the Stripe API.
 */
class StripeAPI
{
    private $stripe;

    /**
     * StripeAPI constructor.
     * Initializes the Stripe client with the provided secret key.
     */
    public function __construct()
    {
        $this->stripe = new Stripe\StripeClient(WP_STRIPE_SECERT_KEY);
    }

    /**
     * Fetches all events from Stripe.
     *
     * @return mixed The API response
     */
    public function fetch_all_events()
    {
        try {
            return $this->stripe->paymentIntents->search(['query' => 'status:"succeeded" AND amount>79500 AND amount<99700']);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetches a user's subscription details from Stripe.
     *
     * @param string $customerId The ID of the customer
     *
     * @return mixed The API response
     */
    public function fetch_user_subscription($customerId)
    {
        try {
            return $this->stripe->customers->retrieve($customerId, ['expand' => ['subscriptions']]);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Searches for a user's payment details in Stripe.
     *
     * @param string $customerId The ID of the customer
     *
     * @return mixed The API response
     */
    public function search_user_payment($customerId)
    {
        try {
            return $this->stripe->paymentIntents->search(['query' => 'status:"succeeded" AND amount>79500 AND amount<99700 AND customer:"' . $customerId . '"']);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetches all subscriptions from Stripe.
     *
     * @param array $options Additional options for fetching subscriptions
     *
     * @return mixed The API response
     */
    public function fetch_all_subscriptions($options)
    {
        try {
            return $this->stripe->subscriptions->all($options);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cancels a user's subscription.
     *
     * @param string $subId The ID of the subscription to cancel
     *
     * @return mixed The API response
     */
    public function cancel_user_subscription($subId)
    {
        try {
            return $this->stripe->subscriptions->cancel($subId);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Processes actions on successful payment.
     *
     * @param string $session_id   The ID of the payment session
     * @param object $current_user The current user object
     *
     * @return mixed The API response
     */
    public function on_success($session_id, $current_user)
    {
        if (empty($session_id) || empty($current_user)) {
            return ['error' => 'Empty User or Session'];
        }
        try {
            $customer = $this->stripe->checkout->sessions->retrieve($session_id);
            // Check if Paid
            if (! empty($customer->payment_status) && 'paid' == $customer->payment_status) {
                if (! empty($customer->customer_details->email)) {
                    update_user_meta($current_user->ID, 'stripe_access_token', $customer->customer);
                }
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Processes actions on successful product purchase.
     *
     * @param string $session_id   The ID of the payment session
     * @param object $current_user The current user object
     *
     * @return mixed The API response
     */
    public function on_product_success($session_id, $current_user)
    {
        if (empty($session_id) || empty($current_user)) {
            return ['error' => 'Empty User or Session'];
        }
        try {
            $customer = $this->stripe->checkout->sessions->retrieve($session_id);
            // Check if Paid
            if (! empty($customer->payment_status) && 'paid' == $customer->payment_status) {
                if (! empty($customer->customer_details->email)) {
                    update_user_meta($current_user->ID, 'stripe_product_access_token', $customer->customer);
                }
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetches all users from Stripe.
     *
     * @param array $options Additional options for fetching users
     *
     * @return mixed The API response
     */
    public function fetch_all_users($options)
    {
        try {
            return $this->stripe->customers->all($options);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetches single user with invoice from Stripe.
     *
     * @param string $invoiceId The ID of the invoice
     *
     * @return mixed The API response
     */
    public function fetch_user_invoice($invoiceId)
    {
        try {
            return $this->stripe->invoices->retrieve($invoiceId);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
