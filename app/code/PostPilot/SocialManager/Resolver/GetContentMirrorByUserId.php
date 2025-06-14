<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PostPilot\SocialManager\Api\ContentMirrorRepositoryInterface;
use PostPilot\SocialManager\Api\Data\ContentMirrorInterface;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser\CollectionFactory as SocialUserCollectionFactory;

class GetContentMirrorByUserId implements ResolverInterface
{
    /**
     * @var ContentMirrorRepositoryInterface
     */
    private $contentMirrorRepository;

    /**
     * @var SocialUserCollectionFactory
     */
    private $socialUserCollectionFactory;

    /**
     * @param ContentMirrorRepositoryInterface $contentMirrorRepository
     * @param SocialUserCollectionFactory $socialUserCollectionFactory
     */
    public function __construct(
        ContentMirrorRepositoryInterface $contentMirrorRepository,
        SocialUserCollectionFactory $socialUserCollectionFactory
    ) {
        $this->contentMirrorRepository = $contentMirrorRepository;
        $this->socialUserCollectionFactory = $socialUserCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        // Verificar se o usuário está autenticado
        $customerId = $context->getUserId();
        if (!$customerId) {
            throw new GraphQlAuthenticationException(__('Customer is not authenticated'));
        }

        // Validar argumento userId
        if (empty($args['userId'])) {
            throw new GraphQlInputException(__('User ID is required'));
        }

        $userId = (int)$args['userId'];

        // Verificar propriedade do usuário social
        $this->validateCustomerOwnership($userId, $customerId);

        // Buscar espelhamento
        $contentMirror = $this->contentMirrorRepository->getByCustomerAndUserId($customerId, $userId);

        if (!$contentMirror) {
            throw new GraphQlNoSuchEntityException(
                __('No content mirror found for user ID %1', $userId)
            );
        }

        return [
            'content_mirror' => $this->getContentMirrorData($contentMirror)
        ];
    }

    /**
     * Validar se o usuário social pertence ao cliente autenticado
     *
     * @param int $userId
     * @param int $customerId
     * @throws GraphQlAuthenticationException
     */
    private function validateCustomerOwnership(int $userId, int $customerId): void
    {
        try {
            $collection = $this->socialUserCollectionFactory->create();
            $collection->addFieldToFilter('id', $userId);
            $collection->addFieldToFilter('customer_id', $customerId);

            if (!$collection->getSize()) {
                throw new GraphQlAuthenticationException(
                    __('Customer does not have permission to access this social user')
                );
            }
        } catch (\Exception $e) {
            throw new GraphQlAuthenticationException(
                __('Failed to validate customer ownership: %1', $e->getMessage())
            );
        }
    }

    /**
     * @param ContentMirrorInterface $contentMirror
     * @return array
     */
    private function getContentMirrorData(ContentMirrorInterface $contentMirror): array
    {
        return [
            'id' => $contentMirror->getId(),
            'customer_id' => $contentMirror->getCustomerId(),
            'user_id' => $contentMirror->getUserId(),
            'enabled' => $contentMirror->getEnabled(),
            'profiles_mirror' => $contentMirror->getProfilesMirror(),
            'created_at' => $contentMirror->getCreatedAt(),
            'updated_at' => $contentMirror->getUpdatedAt()
        ];
    }
}
