<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_PREFIX = 'postpilot/upload_post/';
    private const XML_PATH_SYNC_PREFIX = 'postpilot/sync_settings/';

    /**
     * Caminhos das configurações
     */
    private const XML_PATH_API_KEY = self::XML_PATH_PREFIX . 'api_key';
    private const XML_PATH_REDIRECT_URL = self::XML_PATH_PREFIX . 'redirect_url';
    private const XML_PATH_LOGO_IMAGE = self::XML_PATH_PREFIX . 'logo_image';
    private const XML_PATH_REDIRECT_BUTTON_TEXT = self::XML_PATH_PREFIX . 'redirect_button_text';
    private const XML_PATH_PLATFORMS = self::XML_PATH_PREFIX . 'platforms';

    /**
     * Caminhos das configurações de sincronização
     */
    private const XML_PATH_SYNC_ENABLED = self::XML_PATH_SYNC_PREFIX . 'enabled';

    /**
     * Retorna a API Key
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getApiKey(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retorna a URL de redirecionamento
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getRedirectUrl(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_REDIRECT_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retorna a URL da imagem do logo
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getLogoImage(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LOGO_IMAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retorna o texto do botão de redirecionamento
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getRedirectButtonText(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_REDIRECT_BUTTON_TEXT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retorna as plataformas como array
     *
     * @param int|null $storeId
     * @return array
     */
    public function getPlatforms(?int $storeId = null): array
    {
        $platformsString = $this->scopeConfig->getValue(
            self::XML_PATH_PLATFORMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $platformsString ? array_map('trim', explode(',', $platformsString)) : [];
    }

    /**
     * Verifica se a sincronização está habilitada
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSyncEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retorna todas as configurações em um array
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAllConfig(?int $storeId = null): array
    {
        return [
            'api_key' => $this->getApiKey($storeId),
            'redirect_url' => $this->getRedirectUrl($storeId),
            'logo_image' => $this->getLogoImage($storeId),
            'redirect_button_text' => $this->getRedirectButtonText($storeId),
            'platforms' => $this->getPlatforms($storeId),
            'sync_enabled' => $this->isSyncEnabled($storeId)
        ];
    }
}
