<?php

namespace Give\PaymentGateways\Gateways\PayPalStandard;

use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Helpers\Call;
use Give\PaymentGateways\DataTransferObjects\GatewayPaymentData;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\CreatePayPalStandardPaymentURL;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\GenerateDonationFailedPageUrl;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\GenerateDonationReceiptPageUrl;
use Give\PaymentGateways\Gateways\PayPalStandard\Controllers\PayPalStandardWebhook;
use Give\PaymentGateways\Gateways\PayPalStandard\Views\PayPalStandardBillingFields;
use Give_Payment;

class PayPalStandard extends PaymentGateway
{
    public $routeMethods = [
        'handleIpnNotification'
    ];

    public $secureRouteMethods = [
        'handleSuccessPaymentReturn',
        'handleFailedPaymentReturn'
    ];

    /**
     * @inheritDoc
     */
    public function getLegacyFormFieldMarkup($formId, $args)
    {
        return Call::invoke(PayPalStandardBillingFields::class, $formId);
    }

    /**
     * @inheritDoc
     */
    public static function id()
    {
        return 'paypal';
    }

    /**
     * @inerhitDoc
     */
    public function getId()
    {
        return self::id();
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return esc_html__('PayPal Standard', 'give');
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodLabel()
    {
        return esc_html__('PayPal', 'give');
    }

    /**
     * @inheritDoc
     */
    public function createPayment(GatewayPaymentData $paymentData)
    {
        return new RedirectOffsite(
            Call::invoke(
                CreatePayPalStandardPaymentURL::class,
                $paymentData,
                $this->generateSecureGatewayRouteUrl(
                    'handleSuccessPaymentReturn',
                    $paymentData->donationId,
                    ['donation-id' => $paymentData->donationId]
                ),
                $this->generateSecureGatewayRouteUrl(
                    'handleFailedPaymentReturn',
                    $paymentData->donationId,
                    ['donation-id' => $paymentData->donationId]
                ),
                $this->generateGatewayRouteUrl(
                    'handleIpnNotification'
                )
            )
        );
    }

    /**
     * Handle payment redirect after successful payment on PayPal standard.
     *
     * @since 2.19.0
     * @since 2.19.4 Only pending PayPal Standard donation set to processing.
     *
     * @param  array  $queryParams  Query params in gateway route. {
     *
     * @type string "donation-id" Donation id.
     *
     * }
     *
     * @return RedirectResponse
     */
    public function handleSuccessPaymentReturn($queryParams)
    {
        $donationId = (int)$queryParams['donation-id'];
        $payment = new Give_Payment($donationId);

        if( 'pending' === $payment->status ) {
            $payment->update_status('processing');
        }

        return new RedirectResponse(Call::invoke(GenerateDonationReceiptPageUrl::class, $donationId));
    }

    /**
     * Handle payment redirect after failed payment on PayPal standard.
     *
     * @since 2.19.0
     *
     * @param array $queryParams Query params in gateway route. {
     *
     * @type string "donation-id" Donation id.
     *
     * }
     *
     * @return RedirectResponse
     */
    public function handleFailedPaymentReturn($queryParams)
    {
        $donationId = (int)$queryParams['donation-id'];
        $payment = new Give_Payment($donationId);
        $payment->update_status('failed');

        return new RedirectResponse(Call::invoke(GenerateDonationFailedPageUrl::class, $donationId));
    }

    /**
     * Handle PayPal IPN notification.
     *
     * @since 2.19.0
     */
    public function handleIpnNotification()
    {
        give(PayPalStandardWebhook::class)->handle();
    }

    /**
     * This function returns payment gateway settings.
     *
     * @since 2.19.0
     *
     * @return array
     */
    public function getOptions()
    {
        $setting = [
            // Section 2: PayPal Standard.
            [
                'type' => 'title',
                'id' => 'give_title_gateway_settings_2',
            ],
            [
                'name' => esc_html__('PayPal Email', 'give'),
                'desc' => esc_html__(
                    'Enter the email address associated with your PayPal account to connect with the gateway.',
                    'give'
                ),
                'id' => 'paypal_email',
                'type' => 'email',
            ],
            [
                'name' => esc_html__('PayPal Page Style', 'give'),
                'desc' => esc_html__(
                    'Enter the name of the PayPal page style to use, or leave blank to use the default.',
                    'give'
                ),
                'id' => 'paypal_page_style',
                'type' => 'text',
            ],
            [
                'name' => esc_html__('PayPal Transaction Type', 'give'),
                'desc' => esc_html__(
                    'Nonprofits must verify their status to withdraw donations they receive via PayPal. PayPal users that are not verified nonprofits must demonstrate how their donations will be used, once they raise more than $10,000. By default, GiveWP transactions are sent to PayPal as donations. You may change the transaction type using this option if you feel you may not meet PayPal\'s donation requirements.',
                    'give'
                ),
                'id' => 'paypal_button_type',
                'type' => 'radio_inline',
                'options' => [
                    'donation' => esc_html__('Donation', 'give'),
                    'standard' => esc_html__('Standard Transaction', 'give'),
                ],
                'default' => 'donation',
            ],
            [
                'name' => esc_html__('Billing Details', 'give'),
                'desc' => esc_html__(
                    'If enabled, required billing address fields are added to PayPal Standard forms. These fields are not required by PayPal to process the transaction, but you may have a need to collect the data. Billing address details are added to both the donation and donor record in GiveWP.',
                    'give'
                ),
                'id' => 'paypal_standard_billing_details',
                'type' => 'radio_inline',
                'default' => 'disabled',
                'options' => [
                    'enabled' => esc_html__('Enabled', 'give'),
                    'disabled' => esc_html__('Disabled', 'give'),
                ],
            ],
            [
                'id' => 'paypal_invoice_prefix',
                'name' => esc_html__('Invoice ID Prefix', 'give'),
                'desc' => esc_html__(
                    'Enter a prefix for your invoice numbers. If you use your PayPal account for multiple fundraising platforms or ecommerce stores, ensure this prefix is unique. PayPal will not allow orders or donations with the same invoice number.',
                    'give'
                ),
                'type' => 'text',
                'default' => 'GIVE-',
            ],
            [
                'name' => esc_html__('PayPal Standard Gateway Settings Docs Link', 'give'),
                'id' => 'paypal_standard_gateway_settings_docs_link',
                'url' => esc_url('http://docs.givewp.com/settings-gateway-paypal-standard'),
                'title' => esc_html__('PayPal Standard Gateway Settings', 'give'),
                'type' => 'give_docs_link',
            ],
            [
                'type' => 'sectionend',
                'id' => 'give_title_gateway_settings_2',
            ],
        ];

        /**
         * filter the settings.
         *
         * @since 2.9.6
         */
        return apply_filters('give_get_settings_paypal_standard', $setting);
    }
}
