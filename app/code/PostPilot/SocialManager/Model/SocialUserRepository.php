<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection\CollectionFactory as SocialConnectionCollectionFactory;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser as SocialUserResource;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser\CollectionFactory;

class SocialUserRepository
{
    public function __construct(
        private readonly SocialUserFactory $socialUserFactory,
        private readonly SocialUserResource $socialUserResource,
        private readonly CollectionFactory $socialUserCollectionFactory,
        private readonly SocialConnectionCollectionFactory $socialConnectionCollectionFactory
    ) {
    }

    /**
     * Salva um usuário social
     *
     * @param SocialUser $socialUser
     * @return SocialUser
     * @throws CouldNotSaveException
     */
    public function save(SocialUser $socialUser): SocialUser
    {
        try {
            $this->socialUserResource->save($socialUser);
            return $socialUser;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Não foi possível salvar o usuário social: %1', $e->getMessage()));
        }
    }

    /**
     * Obtém um usuário social pelo nome
     *
     * @param string $name
     * @return DataObject|null
     */
    public function getByName(string $name): ?DataObject
    {
        $collection = $this->socialUserCollectionFactory->create();
        $collection->addFieldToFilter('name', $name);
        $collection->setPageSize(1);

        if ($collection->getSize() > 0) {
            return $collection->getFirstItem();
        }

        return null;
    }

    /**
     * Obtém um usuário social por ID
     *
     * @param int $id
     * @return SocialUser
     * @throws NoSuchEntityException
     */
    public function getById(int $id): SocialUser
    {
        $socialUser = $this->socialUserFactory->create();
        $this->socialUserResource->load($socialUser, $id);

        if (!$socialUser->getId()) {
            throw new NoSuchEntityException(__('Usuário social com ID "%1" não encontrado.', $id));
        }

        return $socialUser;
    }

    /**
     * Busca usuários sociais por ID do cliente
     *
     * @param int $customerId
     * @return array
     */
    public function getByCustomerId(int $customerId): array
    {
        $collection = $this->socialUserCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);

        $users = [];
        foreach ($collection as $user) {
            // Buscar conexões para este usuário
            $connections = $this->getConnectionsByUserId((int)$user->getId());

            $users[] = [
                'id' => (int)$user->getId(),
                'name' => $user->getName(),
                'connections' => $connections
            ];
        }

        return $users;
    }

    /**
     * Busca conexões sociais por ID do usuário
     *
     * @param int $userId
     * @return array
     */
    private function getConnectionsByUserId(int $userId): array
    {
        $collection = $this->socialConnectionCollectionFactory->create();
        $collection->addFieldToFilter('user_id', $userId);

        $connections = [];
        foreach ($collection as $connection) {
            $connections[] = [
                'platform' => $connection->getData('platform'),
                'status' => $connection->getData('status'),
                'display_name' => $connection->getData('display_name'),
                'username' => $connection->getData('username'),
                'social_images' => $connection->getData('social_images')
            ];
        }

        return $connections;
    }

    /**
     * Deleta um usuário social
     *
     * @param int $userId
     * @param int $customerId
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteByIdAndCustomer(int $userId, int $customerId): bool
    {
        $collection = $this->socialUserCollectionFactory->create();
        $collection->addFieldToFilter('id', $userId)
            ->addFieldToFilter('customer_id', $customerId);

        $user = $collection->getFirstItem();

        if (!$user->getId()) {
            throw new NoSuchEntityException(
                __('Não foi possível encontrar o usuário social com ID "%1" para este cliente.', $userId)
            );
        }

        try {
            $this->socialUserResource->delete($user);
            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Não foi possível deletar o usuário social: %1', $e->getMessage())
            );
        }
    }
}
