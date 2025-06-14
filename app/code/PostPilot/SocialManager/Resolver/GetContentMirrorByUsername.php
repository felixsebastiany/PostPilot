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
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection\CollectionFactory as SocialConnectionCollectionFactory;

class GetContentMirrorByUsername implements ResolverInterface
{
    /**
     * @var ContentMirrorRepositoryInterface
     */
    private $contentMirrorRepository;

    /**
     * @var SocialConnectionCollectionFactory
     */
    private $socialConnectionCollectionFactory;

    /**
     * @param ContentMirrorRepositoryInterface $contentMirrorRepository
     * @param SocialConnectionCollectionFactory $socialConnectionCollectionFactory
     */
    public function __construct(
        ContentMirrorRepositoryInterface $contentMirrorRepository,
        SocialConnectionCollectionFactory $socialConnectionCollectionFactory
    ) {
        $this->contentMirrorRepository = $contentMirrorRepository;
        $this->socialConnectionCollectionFactory = $socialConnectionCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field       $field,
        $context,
        ResolveInfo $info,
        ?array      $value = null,
        ?array $args = null
    ) {
        // Verificar se o usuário está autenticado
        $customerId = $context->getUserId();
        if (!$customerId) {
            throw new GraphQlAuthenticationException(__('Customer is not authenticated'));
        }

        // Validar argumento username
        if (empty($args['username'])) {
            throw new GraphQlInputException(__('Username is required'));
        }

        $username = $args['username'];

        // Verificar se a conexão existe
        $connection = $this->findConnectionByUsername($username);
        if (!$connection) {
            throw new GraphQlNoSuchEntityException(
                __('No social connection found with username %1', $username)
            );
        }

        // Verificar propriedade
        $this->validateCustomerOwnership($connection->getUserId(), $customerId);

        // Buscar espelhamento
        $contentMirror = $this->contentMirrorRepository->getByCustomerAndUsername($customerId, $username);

        if (!$contentMirror) {
            throw new GraphQlNoSuchEntityException(
                __('No content mirror found for username %1', $username)
            );
        }

        return [
            'content_mirror' => $this->getContentMirrorData($contentMirror)
        ];
    }

    /**
     * @param string $username
     * @return object|null
     */
    private function findConnectionByUsername(string $username)
    {
        try {
            $collection = $this->socialConnectionCollectionFactory->create();
            $collection->addFieldToFilter('username', $username);
            return $collection->getFirstItem()->getId() ? $collection->getFirstItem() : null;
        } catch (\Exception $e) {
            return null;
        }
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
            // Usar a coleção para buscar o customer_id associado ao usuário social
            $collection = $this->socialConnectionCollectionFactory->create();
            $collection->getSelect()
                ->join(
                    ['su' => $collection->getTable('postpilot_social_users')],
                    'main_table.user_id = su.id',
                    ['customer_id']
                )
                ->where('su.id = ?', $userId)
                ->group('su.id');

            $item = $collection->getFirstItem();
            $connectionCustomerId = (int)$item->getData('customer_id');

            // Verificar se o customer_id do usuário corresponde ao customer_id autenticado
            if (!$connectionCustomerId || $connectionCustomerId !== $customerId) {
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
