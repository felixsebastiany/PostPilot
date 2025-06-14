<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Api\Data\ContentMirrorInterface;

interface ContentMirrorRepositoryInterface
{
    /**
     * @param ContentMirrorInterface $contentMirror
     * @return ContentMirrorInterface
     * @throws CouldNotSaveException
     */
    public function save(ContentMirrorInterface $contentMirror): ContentMirrorInterface;

    /**
     * @param int $id
     * @return ContentMirrorInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): ContentMirrorInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface;

    /**
     * @param ContentMirrorInterface $contentMirror
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ContentMirrorInterface $contentMirror): bool;

    /**
     * @param int $id
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * @param int $customerId
     * @param string $username
     * @return ContentMirrorInterface|null
     */
    public function getByCustomerAndUsername(int $customerId, string $username): ?ContentMirrorInterface;
}
