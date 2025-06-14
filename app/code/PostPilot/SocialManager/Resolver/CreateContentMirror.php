<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PostPilot\SocialManager\Api\ContentMirrorRepositoryInterface;
use PostPilot\SocialManager\Api\Data\ContentMirrorInterface;
use PostPilot\SocialManager\Model\ContentMirrorFactory;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser\CollectionFactory as SocialUserCollectionFactory;

class CreateContentMirror implements ResolverInterface
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ContentMirrorRepositoryInterface
     */
    private $contentMirrorRepository;

    /**
     * @var ContentMirrorFactory
     */
    private $contentMirrorFactory;

    /**
     * @var SocialUserCollectionFactory
     */
    private $socialUserCollectionFactory;

    /**
     * @param Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param ContentMirrorRepositoryInterface $contentMirrorRepository
     * @param ContentMirrorFactory $contentMirrorFactory
     * @param SocialUserCollectionFactory $socialUserCollectionFactory
     */
    public function __construct(
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ContentMirrorRepositoryInterface $contentMirrorRepository,
        ContentMirrorFactory $contentMirrorFactory,
        SocialUserCollectionFactory $socialUserCollectionFactory
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->contentMirrorRepository = $contentMirrorRepository;
        $this->contentMirrorFactory = $contentMirrorFactory;
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
        if (!isset($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Input data is required'));
        }

        $customerId = $context->getUserId();

        if (!$customerId) {
            throw new GraphQlAuthenticationException(__('Customer is not authenticated'));
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__('Customer not found'));
        }

        $input = $args['input'];

        if (empty($input['userId'])) {
            throw new GraphQlInputException(__('User ID is required'));
        }

        $userId = (int)$input['userId'];

        // Verificar se o usuário social pertence ao cliente logado
        $this->validateCustomerOwnership($userId, $customerId);

        // Buscar espelhamento existente ou criar novo
        $contentMirror = $this->contentMirrorRepository->getByCustomerAndUserId($customerId, $userId);

        if (!$contentMirror) {
            /** @var ContentMirrorInterface $contentMirror */
            $contentMirror = $this->contentMirrorFactory->create();
            $contentMirror->setCustomerId($customerId);
            $contentMirror->setUserId($userId);
        }

        // Atualizar campos
        if (isset($input['enabled'])) {
            $contentMirror->setEnabled((bool)$input['enabled']);
        }

        if (isset($input['profiles_mirror'])) {
            $contentMirror->setProfilesMirror($input['profiles_mirror']);
        }

        // Salvar o espelhamento
        $this->contentMirrorRepository->save($contentMirror);

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
                    __('Customer does not have permission to manage this social user')
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
