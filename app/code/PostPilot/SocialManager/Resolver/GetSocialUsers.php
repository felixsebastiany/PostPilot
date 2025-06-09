<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PostPilot\SocialManager\Service\SocialManagerService;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;

class GetSocialUsers implements ResolverInterface
{
    public function __construct(
        private readonly SocialManagerService $socialManagerService,
        private GetCustomer                   $getCustomer
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Current customer does not have access to the resource'));
        }

        try {
            return $this->socialManagerService->getSocialUsers();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'users' => []
            ];
        }
    }
}
