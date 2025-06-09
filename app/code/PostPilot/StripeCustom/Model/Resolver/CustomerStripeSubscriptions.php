<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Stripe\CustomerSession;
use StripeIntegration\Payments\Helper\Generic;
use StripeIntegration\Payments\Helper\PaymentMethod;
use StripeIntegration\Payments\Helper\Product;
use StripeIntegration\Payments\Helper\Subscriptions;
use StripeIntegration\Payments\Model\Config;
use StripeIntegration\Payments\Model\Stripe\SubscriptionFactory;
use Magento\Customer\Model\Session as CustomerMagentoSession;


/**
 * Resolver GraphQL para buscar assinaturas ativas do cliente Stripe.
 */
class CustomerStripeSubscriptions implements ResolverInterface
{
    private const CACHE_TTL = 3600;

    /**
     * Nome do cache
     */
    private const CACHE_KEY_PREFIX = 'stripe_subscriptions_';

    /**
     * Tag de cache
     */
    private const CACHE_TAG = 'STRIPE_SUBSCRIPTIONS';

    public function __construct(
        private readonly Generic $helper,
        private readonly Subscriptions $subscriptionsHelper,
        private readonly PaymentMethod $paymentMethodHelper,
        private readonly Product $productHelper,
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerMagentoSession $customerMagentoSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CollectionFactory $orderCollectionFactory
    ) {
    }

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
            if (!$this->customerMagentoSession->isLoggedIn()) {
                throw new LocalizedException(__('Cliente não está logado'));
            }


            $stripeCustomer = $this->helper->getCustomerModel();

            // Gera chave de cache única para o cliente atual
            $customerId = $stripeCustomer->getStripeId();
            if (!$customerId) {
                return ['items' => []];
            }

            $cacheKey = self::CACHE_KEY_PREFIX . $customerId;

            // Tenta obter dados do cache
            $cachedData = $this->cache->load($cacheKey);
            if ($cachedData) {
                return json_decode($cachedData, true);
            }

            // Busca todas as assinaturas em uma única chamada
            $activeSubscriptions = [];
            $allSubscriptions = $stripeCustomer->getAllSubscriptions();

            if (!is_iterable($allSubscriptions)) {
                return ['items' => []];
            }

            // Otimização: colete todos os IDs de produtos necessários em uma única passagem
            $productIds = [];
            $products = [];

            foreach ($allSubscriptions as $subscription) {
                if (!is_object($subscription)) {
                    continue;
                }

                if (in_array($subscription->status, ['canceled', 'incomplete', 'incomplete_expired'], true)) {
                    continue;
                }

                // Colete todos os IDs de produtos de uma vez
                if (isset($subscription->items->data) && is_array($subscription->items->data)) {
                    foreach ($subscription->items->data as $item) {
                        if (isset($item->price, $item->price->product) && is_string($item->price->product)) {
                            $productIds[$item->price->product] = $item->price->product;
                        }
                    }
                }
            }

            // Busque todos os produtos de uma vez para evitar múltiplas chamadas à API
            if (!empty($productIds)) {
                try {
                    $stripe = $this->config->getStripeClient();
                    // Limite a 100 produtos por consulta (limite da API Stripe)
                    $chunks = array_chunk(array_values($productIds), 100);

                    foreach ($chunks as $chunk) {
                        $stripeProducts = $stripe->products->all(['ids' => $chunk]);
                        foreach ($stripeProducts->data as $product) {
                            $products[$product->id] = $product;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->helper->logError('Erro ao buscar produtos em lote: ' . $e->getMessage());
                }
            }

            // Agora processe as assinaturas com os produtos já carregados
            foreach ($allSubscriptions as $subscription) {
                if (!is_object($subscription)) {
                    continue;
                }

                if (in_array($subscription->status, ['canceled', 'incomplete', 'incomplete_expired'], true)) {
                    continue;
                }

                $activeSubscriptions[] = $this->formatSubscription($subscription, $products);
            }

            $result = ['items' => $activeSubscriptions];

            // Salva no cache
            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
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
     * @param array $preloadedProducts Produtos pré-carregados
     * @return array
     */
    private function formatSubscription(object $subscription, array $preloadedProducts = []): array
    {
        $formattedPaymentMethod = $this->formatPaymentMethod($subscription->default_payment_method ?? null);
        $customerId = (int)$this->customerMagentoSession->getCustomerId();
        $customer = $this->customerRepository->getById($customerId);



        return [
            'id' => $subscription->id ?? '',
            'status' => $subscription->status ?? '',
            'formatted_status' => $this->getFormattedStatus($subscription->status ?? ''),
            'current_period_start' => $this->formatDate($subscription->current_period_start ?? 0),
            'current_period_end' => $this->formatDate($subscription->current_period_end ?? 0),
            'created' => $subscription->created ?? 0,
            'subscription_name' => $this->subscriptionsHelper->generateSubscriptionName($subscription),
            'default_payment_method' => $formattedPaymentMethod,
            'items' => $this->formatSubscriptionItems($subscription->items->data ?? [], $preloadedProducts),
            'product_ids' => $this->getProductIds($subscription),
            'postpilot_users_qty' => $customer->getCustomAttribute('postpilot_users_qty')?->getValue() ?? 0,
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
     * Formata os itens da assinatura usando produtos pré-carregados.
     *
     * @param array $items
     * @param array $preloadedProducts Produtos pré-carregados
     * @return array
     */
    private function formatSubscriptionItems(array $items, array $preloadedProducts = []): array
    {
        $formattedItems = [];
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->price)) {
                continue;
            }

            $price = $item->price;
            $product = null;

            // Verifica primeiro se o produto está disponível no objeto price
            if (isset($price->product) && is_object($price->product)) {
                $product = $this->formatProduct($price->product);
            }
            // Depois verifica se está no objeto plan
            elseif (isset($item->plan, $item->plan->product) && is_object($item->plan->product)) {
                $product = $this->formatProduct($item->plan->product);
            }
            // Por último, usa o produto pré-carregado
            elseif (isset($price->product) && is_string($price->product) && isset($preloadedProducts[$price->product])) {
                $product = $this->formatProduct($preloadedProducts[$price->product]);
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
     * @param object|null $product
     * @return array|null
     */
    private function formatProduct(?object $product): ?array
    {
        if (!$product) {
            return null;
        }
        $customerId = (int)$this->customerMagentoSession->getCustomerId();
        $lastVirtualProduct = $this->getLastVirtualProduct($customerId);
        return [
            'id' => $product->id ?? '',
            'name' => $product->name ?? '',
            'images' => $product->images ?? [],
            'postpilot_users_limit' => $lastVirtualProduct->getData('postpilot_users_limit') ?? null,
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

    /**
     * Obtém o último produto virtual comprado pelo cliente
     *
     * @param int $customerId
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getLastVirtualProduct(int $customerId)
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC');

        foreach ($orderCollection as $order) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getProduct()->getTypeId() === 'virtual') {
                    return $this->productRepository->getById($item->getProductId());
                }
            }
        }

        return null;
    }
}
