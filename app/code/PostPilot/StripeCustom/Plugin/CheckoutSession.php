<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use PostPilot\StripeCustom\Helper\Data as StripeHelper;

/**
 * Plugin para adicionar a success_url do Stripe nos parâmetros do pedido.
 */
readonly class CheckoutSession
{
    /**
     * @param StripeHelper $helper
     */
    public function __construct(private StripeHelper $helper)
    {
    }

    /**
     * Adiciona a success_url do Stripe nos parâmetros retornados.
     *
     * @param mixed $subject
     * @param array $result
     * @param Order|OrderInterface $order
     * @return array
     */
    public function afterGetParamsFromOrder($subject, array $result, OrderInterface|Order $order): array
    {
        $successUrl = $this->helper->getSuccessUrl((int)($order->getStoreId() ?? null));
        if ($successUrl) {
            $result['success_url'] = $successUrl;
        }
        return $result;
    }
}
