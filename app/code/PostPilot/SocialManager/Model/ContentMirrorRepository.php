<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Api\ContentMirrorRepositoryInterface;
use PostPilot\SocialManager\Api\Data\ContentMirrorInterface;
use PostPilot\SocialManager\Model\ResourceModel\ContentMirror as ContentMirrorResource;
use PostPilot\SocialManager\Model\ResourceModel\ContentMirror\CollectionFactory;

class ContentMirrorRepository implements ContentMirrorRepositoryInterface
{
    /**
     * @var ContentMirrorResource
     */
    private $resource;

    /**
     * @var ContentMirrorFactory
     */
    private $contentMirrorFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @param ContentMirrorResource $resource
     * @param ContentMirrorFactory $contentMirrorFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        ContentMirrorResource $resource,
        ContentMirrorFactory $contentMirrorFactory,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->contentMirrorFactory = $contentMirrorFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @param ContentMirrorInterface $contentMirror
     * @return ContentMirrorInterface
     * @throws CouldNotSaveException
     */
    public function save(ContentMirrorInterface $contentMirror): ContentMirrorInterface
    {
        try {
            $this->resource->save($contentMirror);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $contentMirror;
    }

    /**
     * @param int $id
     * @return ContentMirrorInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): ContentMirrorInterface
    {
        $contentMirror = $this->contentMirrorFactory->create();
        $this->resource->load($contentMirror, $id);
        if (!$contentMirror->getId()) {
            throw new NoSuchEntityException(__('Content Mirror with id "%1" does not exist.', $id));
        }
        return $contentMirror;
    }

    /**
     * @param SearchCriteriaInterface $criteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @param ContentMirrorInterface $contentMirror
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ContentMirrorInterface $contentMirror): bool
    {
        try {
            $this->resource->delete($contentMirror);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    /**
     * @param int $customerId
     * @param string $username
     * @return ContentMirrorInterface|null
     */
    public function getByCustomerAndUsername(int $customerId, string $username): ?ContentMirrorInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);

        // Juntar com a tabela de conexões e usuários sociais para filtrar pelo username
        $collection->getSelect()
            ->join(
                ['sc' => $collection->getTable('postpilot_social_connections')],
                'main_table.user_id = sc.user_id',
                []
            )
            ->where('sc.username = ?', $username);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * @param int $customerId
     * @param int $userId
     * @return ContentMirrorInterface|null
     */
    public function getByCustomerAndUserId(int $customerId, int $userId): ?ContentMirrorInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('user_id', $userId);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }
}
