<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use StripeIntegration\Payments\Helper\Generic;
use StripeIntegration\Payments\Helper\Subscriptions;
use StripeIntegration\Payments\Helper\PaymentMethod;
use StripeIntegration\Payments\Helper\Product;
use StripeIntegration\Payments\Model\Stripe\SubscriptionFactory;
use StripeIntegration\Payments\Model\Config;

/**
 * Resolver GraphQL para buscar assinaturas ativas do cliente Stripe.
 */
class CustomerStripeSubscriptions implements ResolverInterface
{
    public function __construct(
        private readonly Generic $helper,
        private readonly Subscriptions $subscriptionsHelper,
        private readonly PaymentMethod $paymentMethodHelper,
        private readonly Product $productHelper,
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly Config $config
    ) {}

    /**
     * Resolve a consulta GraphQL para assinaturas ativas.
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     */
    public function resolve(
        Field       $field,
        mixed       $context,
        ResolveInfo $info,
        ?array      $value = null,
        ?array $args = null
    ): array {
        try {
            $activeSubscriptions = [];
            $stripeCustomer = $this->helper->getCustomerModel();
            $allSubscriptions = $stripeCustomer->getAllSubscriptions();

            if (!is_iterable($allSubscriptions)) {
                return ['items' => []];
            }

            foreach ($allSubscriptions as $subscription) {
                if (!is_object($subscription)) {
                    continue;
                }

                if (in_array($subscription->status, ['canceled', 'incomplete', 'incomplete_expired'], true)) {
                    continue;
                }

                $activeSubscriptions[] = $this->formatSubscription($subscription);
            }

            return ['items' => $activeSubscriptions];
        } catch (\Throwable $e) {
            $this->helper->logError($e->getMessage());
            throw new GraphQlInputException(
                __('Não foi possível recuperar as assinaturas ativas: %1', $e->getMessage())
            );
        }
    }

    /**
     * Formata uma assinatura Stripe para o formato GraphQL.
     *
     * @param object $subscription
     * @return array
     */
    private function formatSubscription(object $subscription): array
    {
        $stripeSubscriptionModel = $this->subscriptionFactory->create()->fromSubscription($subscription);
        $formattedPaymentMethod = $this->formatPaymentMethod($subscription->default_payment_method ?? null);

        return [
            'id' => $subscription->id ?? '',
            'status' => $subscription->status ?? '',
            'formatted_status' => $this->getFormattedStatus($subscription->status ?? ''),
            'current_period_start' => $this->formatDate($subscription->current_period_start ?? 0),
            'current_period_end' => $this->formatDate($subscription->current_period_end ?? 0),
            'created' => $subscription->created ?? 0,
            'subscription_name' => $this->subscriptionsHelper->generateSubscriptionName($subscription),
            'default_payment_method' => $formattedPaymentMethod,
            'items' => $this->formatSubscriptionItems($subscription->items->data ?? []),
            'metadata' => [
                'Product_ID' => $subscription->metadata->{"Product ID"} ?? null,
                'SubscriptionProductIDs' => $subscription->metadata->{"SubscriptionProductIDs"} ?? null
            ],
            'product_ids' => $this->getProductIds($subscription),
            'is_salable' => $this->checkProductIsSalable($subscription)
        ];
    }

    /**
     * Formata o método de pagamento.
     *
     * @param object|null $paymentMethod
     * @return array|null
     */
    private function formatPaymentMethod(?object $paymentMethod): ?array
    {
        if (!$paymentMethod) {
            return null;
        }

        $methods = [
            $paymentMethod->type => [
                $paymentMethod
            ]
        ];
        $formattedPaymentMethods = $this->paymentMethodHelper->formatPaymentMethods($methods);
        $formattedMethod = reset($formattedPaymentMethods);

        return [
            'id' => $paymentMethod->id ?? '',
            'type' => $paymentMethod->type ?? '',
            'card' => isset($paymentMethod->card) ? [
                'brand' => $paymentMethod->card->brand ?? '',
                'brand_image_url' => $formattedMethod['icon'] ?? '',
                'last4' => $paymentMethod->card->last4 ?? '',
                'exp_month' => $paymentMethod->card->exp_month ?? 0,
                'exp_year' => $paymentMethod->card->exp_year ?? 0
            ] : null
        ];
    }

    /**
     * Formata os itens da assinatura.
     *
     * @param array $items
     * @return array
     */
    private function formatSubscriptionItems(array $items): array
    {
        $formattedItems = [];
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->price)) {
                continue;
            }

            $price = $item->price;
            $product = null;

            if (isset($price->product) && is_object($price->product)) {
                $product = $this->formatProduct($price->product);
            } elseif (isset($item->plan, $item->plan->product) && is_object($item->plan->product)) {
                $product = $this->formatProduct($item->plan->product);
            } elseif (isset($price->product) && is_string($price->product)) {
                try {
                    $stripe = $this->config->getStripeClient();
                    $stripeProduct = $stripe->products->retrieve($price->product);
                    $product = $this->formatProduct($stripeProduct);
                } catch (\Throwable $e) {
                    $this->helper->logError($e->getMessage());
                }
            }

            $recurring = isset($price->recurring)
                ? ['interval' => $price->recurring->interval ?? '']
                : null;

            $formattedItems[] = [
                'id' => $item->id ?? '',
                'price' => [
                    'id' => $price->id ?? '',
                    'unit_amount' => $price->unit_amount ?? 0,
                    'currency' => $price->currency ?? '',
                    'product' => $product,
                    'recurring' => $recurring
                ],
                'quantity' => $item->quantity ?? 1
            ];
        }
        return $formattedItems;
    }

    /**
     * Formata um produto Stripe.
     *
     * @param object|string|null $product
     * @return array|null
     */
    private function formatProduct(object|string|null $product): ?array
    {
        if (!$product || is_string($product)) {
            return null;
        }

        return [
            'id' => $product->id ?? '',
            'name' => $product->name ?? '',
            'images' => $product->images ?? []
        ];
    }

    /**
     * Retorna o status formatado da assinatura.
     *
     * @param string $status
     * @return string
     */
    private function getFormattedStatus(string $status): string
    {
        return match ($status) {
            'trialing', 'active' => __("Active")->render(),
            'past_due' => __("Past Due")->render(),
            'unpaid' => __("Unpaid")->render(),
            'canceled' => __("Canceled")->render(),
            default => __(ucwords(str_replace('_', ' ', $status)))->render(),
        };
    }

    /**
     * Obtém os IDs dos produtos da assinatura.
     *
     * @param object $subscription
     * @return array
     */
    private function getProductIds(object $subscription): array
    {
        $productIds = [];
        if (isset($subscription->metadata->{"Product ID"})) {
            $productIds = explode(",", $subscription->metadata->{"Product ID"});
        } elseif (isset($subscription->metadata->{"SubscriptionProductIDs"})) {
            $productIds = explode(",", $subscription->metadata->{"SubscriptionProductIDs"});
        }
        return array_map('trim', $productIds);
    }

    /**
     * Verifica se algum produto da assinatura está disponível para venda.
     *
     * @param object $subscription
     * @return bool
     */
    private function checkProductIsSalable(object $subscription): bool
    {
        $productIds = $this->getProductIds($subscription);

        if (empty($productIds)) {
            return false;
        }

        foreach ($productIds as $productId) {
            try {
                $product = $this->productHelper->getProduct($productId);
                if ($product && $product->getIsSalable()) {
                    return true;
                }
            } catch (\Throwable $e) {
                $this->helper->logError($e->getMessage());
            }
        }
        return false;
    }

    /**
     * Formata timestamp Unix para data no formato brasileiro com timezone de Brasília.
     *
     * @param int $timestamp
     * @return string
     */
    private function formatDate(int $timestamp): string
    {
        try {
            if ($timestamp <= 0) {
                return '';
            }

            $dateTime = new \DateTimeImmutable('@' . $timestamp);
            $dateTime = $dateTime->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

            return $dateTime->format('d/m/Y');
        } catch (\Throwable $e) {
            $this->helper->logError($e->getMessage());
            return '';
        }
    }
}
