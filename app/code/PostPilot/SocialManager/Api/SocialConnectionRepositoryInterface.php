<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Api\Data\SocialConnectionInterface;

interface SocialConnectionRepositoryInterface
{
    /**
     * @param SocialConnectionInterface $socialConnection
     * @return SocialConnectionInterface
     * @throws CouldNotSaveException
     */
    public function save(SocialConnectionInterface $socialConnection): SocialConnectionInterface;

    /**
     * @param int $id
     * @return SocialConnectionInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): SocialConnectionInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface;

    /**
     * @param SocialConnectionInterface $socialConnection
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SocialConnectionInterface $socialConnection): bool;

    /**
     * @param int $id
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * @param string $username
     * @return SocialConnectionInterface[]
     */
    public function getByUsername(string $username): array;
}
