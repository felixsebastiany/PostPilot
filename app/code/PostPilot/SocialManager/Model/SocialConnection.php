<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection as SocialConnectionResource;

class SocialConnection extends AbstractModel
{
    const STATUS_CONNECTED = 'connected';
    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_ERROR = 'error';

    const PLATFORM_INSTAGRAM = 'instagram';
    const PLATFORM_FACEBOOK = 'facebook';
    const PLATFORM_YOUTUBE = 'youtube';
    const PLATFORM_TIKTOK = 'tiktok';

    /**
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(SocialConnectionResource::class);
    }

    public function getId()
    {
        return $this->getData('id');
    }

    public function getUserId(): int
    {
        return (int)$this->getData('user_id');
    }

    public function setUserId(int $userId): self
    {
        return $this->setData('user_id', $userId);
    }

    public function getPlatform(): string
    {
        return $this->getData('platform') ?? '';
    }

    public function setPlatform(string $platform): self
    {
        return $this->setData('platform', $platform);
    }

    public function getStatus(): string
    {
        return $this->getData('status') ?? self::STATUS_DISCONNECTED;
    }

    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    public function getDisplayName(): ?string
    {
        return $this->getData('display_name');
    }

    public function setDisplayName(?string $displayName): self
    {
        return $this->setData('display_name', $displayName);
    }

    public function getUsername(): ?string
    {
        return $this->getData('username');
    }

    public function setUsername(?string $username): self
    {
        return $this->setData('username', $username);
    }

    public function getSocialImages(): ?string
    {
        return $this->getData('social_images');
    }

    public function setSocialImages(?string $socialImages): self
    {
        return $this->setData('social_images', $socialImages);
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
