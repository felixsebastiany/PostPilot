<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection as SocialConnectionResource;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection\CollectionFactory;

class SocialConnectionRepository
{
    public function __construct(
        private readonly SocialConnectionFactory $socialConnectionFactory,
        private readonly SocialConnectionResource $socialConnectionResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Salva uma conexão social
     *
     * @param SocialConnection $connection
     * @return SocialConnection
     * @throws CouldNotSaveException
     */
    public function save(SocialConnection $connection): SocialConnection
    {
        try {
            $this->socialConnectionResource->save($connection);
            return $connection;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Não foi possível salvar a conexão social: %1', $e->getMessage()));
        }
    }

    /**
     * Obtém uma conexão social por ID
     *
     * @param int $id
     * @return SocialConnection
     * @throws NoSuchEntityException
     */
    public function getById(int $id): SocialConnection
    {
        $connection = $this->socialConnectionFactory->create();
        $this->socialConnectionResource->load($connection, $id);

        if (!$connection->getId()) {
            throw new NoSuchEntityException(__('Conexão social com ID "%1" não encontrada.', $id));
        }

        return $connection;
    }

    /**
     * Busca conexões por ID do usuário
     *
     * @param int $userId
     * @return array
     */
    public function getByUserId(int $userId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('user_id', $userId);

        $connections = [];
        foreach ($collection as $connection) {
            $connections[] = $connection;
        }

        return $connections;
    }

    /**
     * Deleta todas as conexões sociais de um usuário
     *
     * @param int $userId
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function deleteByUserId(int $userId): bool
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('user_id', $userId);

            foreach ($collection as $connection) {
                $this->socialConnectionResource->delete($connection);
            }

            return true;
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Não foi possível deletar as conexões sociais do usuário: %1', $e->getMessage())
            );
        }
    }
}
