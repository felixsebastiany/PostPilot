<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Helper para acessar configurações customizadas do Stripe.
 */
class Data extends AbstractHelper
{
    /** @var string Caminho da configuração da URL de sucesso do Stripe */
    public const string XML_PATH_SUCCESS_URL = 'stripe_payments/payments/success_url';

    /**
     * Retorna a URL de sucesso configurada para o Stripe.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getSuccessUrl(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SUCCESS_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
