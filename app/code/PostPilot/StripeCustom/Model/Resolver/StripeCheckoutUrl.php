<?php
declare(strict_types=1);

namespace PostPilot\StripeCustom\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class StripeCheckoutUrl implements ResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?string
    {
        return $_SESSION['checkout']['stripe_payments_checkout_session_u_r_l'] ?? null;
    }
}
