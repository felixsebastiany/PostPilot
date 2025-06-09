<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Api;

interface SocialManagerInterface
{
    /**
     * Get social users for current customer
     *
     * @return array
     */
    public function getSocialUsers(): array;

    /**
     * Add new social user
     *
     * @param string $name
     * @return array
     */
    public function addSocialUser(string $name): array;

    /**
     * Generate JWT for user profile
     *
     * @param int $userId
     * @return array
     */
    public function generateJwtUP(int $userId): array;

    /**
     * Delete social user
     *
     * @param int $userId
     * @return array
     */
    public function deleteSocialUser(int $userId): array;
}
