<?php declare(strict_types=1);

namespace Subscriber;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HostedCheckoutSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack               $requestStack,
        private readonly SystemConfigService        $systemConfigService,
        private readonly AbstractCartPersister      $cartPersister,
        private readonly SalesChannelContextService $salesChannelContextService,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        /**
         * Original core file you can find here:
         * @see StorefrontSubscriber
         */
        return [
            KernelEvents::REQUEST => [
                ['beforeStartSession', 50],
            ],
        ];
    }

    public function beforeStartSession(): void
    {
        $master = $this->requestStack->getMainRequest();

        if (!$master) {
            return;
        }
        if (!$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$master->hasSession()) {
            return;
        }

        $session = $master->getSession();

        if (!$session->isStarted()) {
            $session->setName('session-');
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        $salesChannelId = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if ($salesChannelId === null) {
            /** @var SalesChannelContext|null $salesChannelContext */
            $salesChannelContext = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
            if ($salesChannelContext !== null) {
                $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
            }
        }

        if ($this->shouldRenewToken($session, $salesChannelId)) {
            $token = Random::getAlphanumericString(32);
        }

        $swContextToken = $master->query->get('token');
        if (!empty($swContextToken) && $this->isTokenValid($salesChannelId, $swContextToken, $master)) {
            $token = $this->updateToken($salesChannelId, $swContextToken, $master);
        }

        if (!empty($token)) {
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
            $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }

        $master->headers->set(
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    private function updateToken(string $salesChannelId, string $oldToken, Request $master): string
    {
        $newToken = Random::getAlphanumericString(32);
        $salesChannelContext = $this->getSalesChannelContext($salesChannelId, $newToken, $master);
        $this->cartPersister->replace($oldToken, $newToken, $salesChannelContext);

        return $newToken;
    }

    private function isTokenValid(string $salesChannelId, string $swContextToken, Request $master): bool
    {
        try {
            $salesChannelContext = $this->getSalesChannelContext($salesChannelId, $swContextToken, $master);
            $cart = $this->cartPersister->load($swContextToken, $salesChannelContext);
        } catch (CartTokenNotFoundException) {
            return false;
        }

        return $cart->getToken() === $swContextToken;
    }

    private function getSalesChannelContext(string $salesChannelId, string $swContextToken, Request $master): SalesChannelContext
    {
        return $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannelId,
                $swContextToken,
                $master->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $master->headers->get(PlatformRequest::HEADER_CURRENCY_ID),
            )
        );
    }

    private function shouldRenewToken(SessionInterface $session, ?string $salesChannelId = null): bool
    {
        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN) || $salesChannelId === null) {
            return true;
        }

        if ($this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel')) {
            return $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID) !== $salesChannelId;
        }

        return false;
    }
}
