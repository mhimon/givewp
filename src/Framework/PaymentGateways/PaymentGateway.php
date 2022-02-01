<?php

namespace Give\Framework\PaymentGateways;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\FieldsAPI\Exceptions\TypeNotSupported;
use Give\Framework\LegacyPaymentGateways\Contracts\LegacyPaymentGatewayInterface;
use Give\Framework\PaymentGateways\Actions\GenerateGatewayRouteUrl;
use Give\Framework\PaymentGateways\CommandHandlers\PaymentCompleteHandler;
use Give\Framework\PaymentGateways\CommandHandlers\PaymentProcessingHandler;
use Give\Framework\PaymentGateways\CommandHandlers\RedirectOffsiteHandler;
use Give\Framework\PaymentGateways\CommandHandlers\RespondToBrowserHandler;
use Give\Framework\PaymentGateways\CommandHandlers\SubscriptionCompleteHandler;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\Commands\PaymentProcessing;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Commands\RespondToBrowser;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\Contracts\PaymentGatewayInterface;
use Give\Framework\PaymentGateways\Contracts\SubscriptionModuleInterface;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Framework\PaymentGateways\Routes\RouteSignature;
use Give\Framework\PaymentGateways\Traits\ResponseHelpers;
use Give\Helpers\Call;
use Give\PaymentGateways\DataTransferObjects\GatewayPaymentData;
use Give\PaymentGateways\DataTransferObjects\GatewaySubscriptionData;

use function Give\Framework\Http\Response\response;

/**
 * @since 2.18.0
 */
abstract class PaymentGateway implements PaymentGatewayInterface, LegacyPaymentGatewayInterface
{
    use ResponseHelpers;

    /**
     * Route methods are used to extend the gateway api.
     * By adding a custom routeMethod, you are effectively
     * registering a new public route url that will resolve itself and
     * call your method.
     *
     * @var string[]
     */
    public $routeMethods = [];

    /**
     * Secure Route methods are used to extend the gateway api with an additional wp_nonce.
     * By adding a custom secureRouteMethod, you are effectively
     * registering a new route url that will resolve itself and
     * call your method after validating the nonce.
     *
     * @var string[]
     */
    public $secureRouteMethods = [];

    /**
     * @var SubscriptionModuleInterface $subscriptionModule
     */
    public $subscriptionModule;

    /**
     * @since 2.18.0
     *
     * @param SubscriptionModuleInterface|null $subscriptionModule
     */
    public function __construct(SubscriptionModuleInterface $subscriptionModule = null)
    {
        $this->subscriptionModule = $subscriptionModule;
    }

    /**
     * @inheritDoc
     */
    public function supportsSubscriptions()
    {
        return isset($this->subscriptionModule);
    }

    /**
     * @inheritDoc
     */
    public function handleCreatePayment(GatewayPaymentData $gatewayPaymentData)
    {
        try {
            $command = $this->createPayment($gatewayPaymentData);
            $this->handleGatewayPaymentCommand($command, $gatewayPaymentData);
        } catch (PaymentGatewayException $paymentGatewayException) {
            $this->handleResponse(response()->json($paymentGatewayException->getMessage()));
            exit;
        } catch (Exception $exception) {
            PaymentGatewayLog::error($exception->getMessage());

            $message = __(
                'An unexpected error occurred while processing your donation.  Please try again or contact us to help resolve.',
                'give'
            );

            $this->handleResponse(response()->json($message));
            exit;
        }
    }

    /**
     * @inheritDoc
     */
    public function handleCreateSubscription(GatewayPaymentData $paymentData, GatewaySubscriptionData $subscriptionData)
    {
        try {
            $command = $this->createSubscription($paymentData, $subscriptionData);
            $this->handleGatewaySubscriptionCommand($command, $paymentData, $subscriptionData);
        } catch (PaymentGatewayException $paymentGatewayException) {
            $this->handleResponse(response()->json($paymentGatewayException->getMessage()));
            exit;
        } catch (Exception $exception) {
            PaymentGatewayLog::error($exception->getMessage());

            $message = __(
                'An unexpected error occurred while processing your donation.  Please try again or contact us to help resolve.',
                'give'
            );

            $this->handleResponse(response()->json($message));
            exit;
        }
    }

    /**
     * If a subscription module isn't wanted this method can be overridden by a child class instead.
     * Just make sure to override the supportsSubscriptions method as well.
     *
     * @inheritDoc
     */
    public function createSubscription(GatewayPaymentData $paymentData, GatewaySubscriptionData $subscriptionData)
    {
        return $this->subscriptionModule->createSubscription($paymentData, $subscriptionData);
    }

    /**
     * Handle gateway command
     *
     * @since 2.18.0
     *
     * @param  GatewayCommand  $command
     * @param  GatewayPaymentData  $gatewayPaymentData
     *
     * @throws TypeNotSupported
     */
    public function handleGatewayPaymentCommand(GatewayCommand $command, GatewayPaymentData $gatewayPaymentData)
    {
        if ($command instanceof PaymentComplete) {
            $handler = new PaymentCompleteHandler($command);

            $handler->handle($gatewayPaymentData->donationId);

            $response = response()->redirectTo($gatewayPaymentData->redirectUrl);

            $this->handleResponse($response);
        }

        if ($command instanceof PaymentProcessing) {
            $handler = new PaymentProcessingHandler($command);

            $handler->handle($gatewayPaymentData->donationId);

            $response = response()->redirectTo($gatewayPaymentData->redirectUrl);

            $this->handleResponse($response);
        }

        if ($command instanceof RedirectOffsite) {
            $response = Call::invoke(RedirectOffsiteHandler::class, $command);

            $this->handleResponse($response);
        }

        if ($command instanceof RespondToBrowser) {
            $response = Call::invoke(RespondToBrowserHandler::class, $command);

            $this->handleResponse($response);
        }

        throw new TypeNotSupported(
            sprintf(
                "Return type must be an instance of %s",
                GatewayCommand::class
            )
        );
    }

    /**
     * Handle gateway subscription command
     *
     * @since 2.18.0
     *
     * @param GatewayCommand $command
     * @param GatewayPaymentData $gatewayPaymentData
     * @param GatewaySubscriptionData $gatewaySubscriptionData
     *
     * @throws TypeNotSupported
     */
    public function handleGatewaySubscriptionCommand(
        GatewayCommand $command,
        GatewayPaymentData $gatewayPaymentData,
        GatewaySubscriptionData $gatewaySubscriptionData
    ) {
        if ($command instanceof SubscriptionComplete) {
            Call::invoke(
                SubscriptionCompleteHandler::class,
                $command,
                $gatewaySubscriptionData->subscriptionId,
                $gatewayPaymentData->donationId
            );

            $response = response()->redirectTo($gatewayPaymentData->redirectUrl);

            $this->handleResponse($response);
        }

        throw new TypeNotSupported(
            sprintf(
                "Return type must be an instance of %s",
                GatewayCommand::class
            )
        );
    }

    /**
     * Generate gateway route url
     *
     * @since 2.18.0
     * @unreleased remove $donationId param in favor of args
     *
     * @param  string  $gatewayMethod
     * @param  array|null  $args
     *
     * @return string
     *
     */
    public function generateGatewayRouteUrl($gatewayMethod, $args = null)
    {
        return Call::invoke(GenerateGatewayRouteUrl::class, $this->getId(), $gatewayMethod, $args);
    }

    /**
     * Generate secure gateway route url
     *
     * @unreleased
     *
     * @param  string  $gatewayMethod
     * @param  array|null  $args
     *
     * @return string
     *
     */
    public function generateSecureGatewayRouteUrl($gatewayMethod, $args = null)
    {
        $nonce = RouteSignature::make($this->getId(), $gatewayMethod, $args);

        return Call::invoke(
            GenerateGatewayRouteUrl::class,
            $this->getId(),
            $gatewayMethod,
            array_merge($args, [
                'give-route-signature' => $nonce->toNonce()
            ])
        );
    }
}
