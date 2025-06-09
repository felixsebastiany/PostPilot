<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PostPilot\SocialManager\Service\SocialManagerService;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class AddSocialUser implements ResolverInterface
{
    public function __construct(
        private readonly SocialManagerService $socialManagerService
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Current customer does not have access to the resource'));
        }

        if (!isset($args['input']['name'])) {
            throw new GraphQlInputException(__('Name is required'));
        }

        try {
            return $this->socialManagerService->addSocialUser($args['input']['name']);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'user' => null
            ];
        }
    }
}
