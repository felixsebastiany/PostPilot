<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser as SocialUserResource;

class SocialUser extends AbstractModel
{
    const string STATUS_ACTIVE = 'active';
    const string STATUS_INACTIVE = 'inactive';

    /**
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(SocialUserResource::class);
    }

    public function getId()
    {
        return $this->getData('id');
    }

    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getName(): string
    {
        return $this->getData('name') ?? '';
    }

    public function setName(string $name): self
    {
        return $this->setData('name', $name);
    }

    public function getCreatedAt(): string
    {
        return $this->getData('created_at') ?? '';
    }

    public function getUpdatedAt(): string
    {
        return $this->getData('updated_at') ?? '';
    }
}
