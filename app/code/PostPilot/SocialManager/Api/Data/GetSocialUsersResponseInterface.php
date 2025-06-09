<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Api\Data;

interface GetSocialUsersResponseInterface
{
    /**
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): static;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * @param string|null $message
     * @return $this
     */
    public function setMessage(?string $message): static;

    /**
     * @return array
     */
    public function getUsers(): array;

    /**
     * @param array $users
     * @return $this
     */
    public function setUsers(array $users): static;
}
