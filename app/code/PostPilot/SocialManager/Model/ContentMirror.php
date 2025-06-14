<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use PostPilot\SocialManager\Api\Data\ContentMirrorInterface;
use PostPilot\SocialManager\Model\ResourceModel\ContentMirror as ContentMirrorResource;

class ContentMirror extends AbstractModel implements ContentMirrorInterface
{
    /**
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ContentMirrorResource::class);
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->getData('id') ? (int)$this->getData('id') : null;
    }

    /**
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return (int)$this->getData('user_id');
    }

    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId(int $userId): self
    {
        return $this->setData('user_id', $userId);
    }

    /**
     * @return bool|null
     */
    public function getEnabled(): ?bool
    {
        return $this->getData('enabled') !== null ? (bool)$this->getData('enabled') : null;
    }

    /**
     * @param bool|null $enabled
     * @return $this
     */
    public function setEnabled(?bool $enabled): self
    {
        return $this->setData('enabled', $enabled);
    }

    /**
     * @return string|null
     */
    public function getProfilesMirror(): ?string
    {
        return $this->getData('profiles_mirror');
    }

    /**
     * @param string|null $profilesMirror
     * @return $this
     */
    public function setProfilesMirror(?string $profilesMirror): self
    {
        return $this->setData('profiles_mirror', $profilesMirror);
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->getData('created_at') ?? '';
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): string
    {
        return $this->getData('updated_at') ?? '';
    }
}
