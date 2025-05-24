<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;

class CancelStripeSubscription implements ResolverInterface
{
    private $helper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config
    ) {
        $this->helper = $helper;
        $this->config = $config;
    }

    public function resolve(
        Field $field,
              $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(
                __('É necessário estar logado para cancelar uma assinatura.')
            );
        }

        if (empty($args['input']['subscription_id'])) {
            throw new GraphQlInputException(
                __('O ID da assinatura é obrigatório.')
            );
        }

        try {
            $subscriptionId = $args['input']['subscription_id'];
            $customerId = $context->getUserId();
            $stripeCustomer = $this->helper->getCustomerModel()->getStripeId();

            if (!$stripeCustomer) {
                throw new GraphQlAuthorizationException(
                    __('Cliente não encontrado no Stripe.')
                );
            }

            // Buscar a assinatura no Stripe
            $stripe = $this->config->getStripeClient();
            $subscription = $stripe->subscriptions->retrieve($subscriptionId);

            // Verificar se a assinatura pertence ao cliente atual
            if ($subscription->customer !== $stripeCustomer) {
                throw new GraphQlAuthorizationException(
                    __('Esta assinatura não pertence ao cliente atual.')
                );
            }

            // Verificar se passaram mais de 7 dias desde o início da assinatura
            $sevenDaysAgo = time() - (7 * 24 * 60 * 60);
            if ($subscription->start_date < $sevenDaysAgo) {
                throw new GraphQlAuthorizationException(
                    __('Não é possível cancelar assinaturas com mais de 7 dias desde o início.')
                );
            }

            // Cancelar a assinatura
            $result = $stripe->subscriptions->cancel($subscriptionId, []);

            if (!$result) {
                throw new GraphQlNoSuchEntityException(
                    __('Não foi possível cancelar a assinatura.')
                );
            }

            return [
                'success' => true,
                'message' => __('Assinatura cancelada com sucesso.'),
                'subscription' => [
                    'id' => $result->id,
                    'status' => $result->status,
                    'canceled_at' => $this->formatDate($result->canceled_at),
                    'cancel_at_period_end' => $result->cancel_at_period_end,
                    'current_period_end' => $this->formatDate($result->current_period_end),
                    'order_number' => $result->metadata["Order #"] ?? null,
                    'name' => $result->metadata["Product Name"] ?? null
                ]
            ];
        } catch (\Exception $e) {
            $this->helper->logError($e->getMessage());
            throw new GraphQlInputException(
                __('Erro ao cancelar assinatura: %1', $e->getMessage())
            );
        }
    }

    private function formatDate(?int $timestamp): ?string
    {
        if (!$timestamp) {
            return null;
        }

        try {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp($timestamp);
            $dateTime->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

            return $dateTime->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            $this->helper->logError($e->getMessage());
            return null;
        }
    }
}
