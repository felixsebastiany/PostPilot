<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Api\Data;

interface ContentMirrorInterface
{
    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * @return int
     */
    public function getUserId(): int;

    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId(int $userId): self;

    /**
     * @return bool|null
     */
    public function getEnabled(): ?bool;

    /**
     * @param bool|null $enabled
     * @return $this
     */
    public function setEnabled(?bool $enabled): self;

    /**
     * @return string|null
     */
    public function getProfilesMirror(): ?string;

    /**
     * @param string|null $profilesMirror
     * @return $this
     */
    public function setProfilesMirror(?string $profilesMirror): self;

    /**
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * @return string
     */
    public function getUpdatedAt(): string;
}
