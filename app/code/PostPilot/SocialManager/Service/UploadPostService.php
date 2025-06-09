<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PostPilot\SocialManager\Helper\Config as PostPilotConfig;
use Psr\Log\LoggerInterface;

class UploadPostService
{
    private const string API_BASE_URL = 'https://api.upload-post.com';

    public function __construct(
        private readonly PostPilotConfig $config,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cria um usuário na API do Upload-Post
     *
     * @param string $username
     * @return array
     * @throws LocalizedException
     */
    public function createUser(string $username): array
    {
        try {
            // Configurar headers
            $this->curl->addHeader('Authorization', 'ApiKey ' . $this->config->getApiKey());
            $this->curl->addHeader('Content-Type', 'application/json');

            // Preparar dados
            $postData = $this->json->serialize([
                'username' => $username
            ]);

            // Fazer a requisição
            $this->curl->post(self::API_BASE_URL . '/api/uploadposts/users', $postData);

            // Obter resposta
            $response = $this->curl->getBody();

            // Verificar status HTTP
            $statusCode = $this->curl->getStatus();
            if ($statusCode !== 200 && $statusCode !== 201) {
                throw new LocalizedException(
                    __('API retornou status code inválido: %1', $statusCode)
                );
            }

            $responseData = $this->json->unserialize($response);

            return [
                'success' => true,
                'message' => 'Usuário criado com sucesso na API',
                'data' => $responseData
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erro ao criar usuário na API Upload-Post: ' . $e->getMessage(), [
                'name' => $username,
                'error' => $e->getMessage()
            ]);

            throw new LocalizedException(
                __('Não foi possível criar o usuário na API Upload-Post: %1', $e->getMessage())
            );
        }
    }

    /**
     * Deleta um usuário na API do Upload-Post
     *
     * @param string $user ID do usuário na API do Upload-Post
     * @return array
     * @throws LocalizedException
     */
    public function deleteUser(string $user): array
    {
        try {
            // Configurar headers
            $this->curl->addHeader('Authorization', 'ApiKey ' . $this->config->getApiKey());
            $this->curl->addHeader('Content-Type', 'application/json');

            // Configurar método DELETE
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

            // Preparar dados
            $postData = $this->json->serialize([
                'username' => $user
            ]);

            // URL da API
            $url = self::API_BASE_URL . '/api/uploadposts/users';

            // Fazer a requisição
            $this->curl->post($url, $postData);

            // Verificar status HTTP
            $statusCode = $this->curl->getStatus();

            if ($statusCode === 404) {
                return [
                    'success' => false,
                    'message' => 'Usuário não encontrado na API'
                ];
            }

            if ($statusCode !== 200 && $statusCode !== 204) {
                throw new LocalizedException(
                    __('API retornou status code inválido: %1', $statusCode)
                );
            }

            return [
                'success' => true,
                'message' => 'Usuário deletado com sucesso na API'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao deletar usuário na API Upload-Post: ' . $e->getMessage(), [
                'userId' => $user,
                'error' => $e->getMessage()
            ]);

            throw new LocalizedException(
                __('Não foi possível deletar o usuário na API Upload-Post: %1', $e->getMessage())
            );
        }
    }

    /**
     * Gera um token JWT para um usuário conectar suas redes sociais
     *
     * @param string $username O identificador do perfil do usuário para o qual o JWT está sendo gerado
     * @param string|null $redirectUrl URL para a qual o usuário será redirecionado após vincular sua conta social
     * @param string|null $logoImage URL para uma imagem de logo a ser exibida na página de vinculação
     * @param string|null $redirectButtonText Texto a ser exibido no botão de redirecionamento após a vinculação
     * @param array|null $platforms Lista de plataformas a serem mostradas para conexão
     * @return array
     * @throws LocalizedException
     */
    public function generateJwt(
        string $username,
        ?string $redirectUrl = null,
        ?string $logoImage = null,
        ?string $redirectButtonText = null,
        ?array $platforms = null
    ): array {
        try {
            // Configurar headers
            $this->curl->addHeader('Authorization', 'ApiKey ' . $this->config->getApiKey());
            $this->curl->addHeader('Content-Type', 'application/json');

            // Preparar dados
            $postData = [
                'username' => $username
            ];

            // Adicionar parâmetros opcionais se fornecidos
            if ($redirectUrl) {
                $postData['redirect_url'] = $redirectUrl;
            } elseif ($this->config->getRedirectUrl()) {
                $postData['redirect_url'] = $this->config->getRedirectUrl();
            }

            if ($logoImage) {
                $postData['logo_image'] = $logoImage;
            } elseif ($this->config->getLogoImage()) {
                $postData['logo_image'] = $this->config->getLogoImage();
            }

            if ($redirectButtonText) {
                $postData['redirect_button_text'] = $redirectButtonText;
            } elseif ($this->config->getRedirectButtonText()) {
                $postData['redirect_button_text'] = $this->config->getRedirectButtonText();
            }

            if ($platforms) {
                $postData['platforms'] = $platforms;
            } elseif (!empty($this->config->getPlatforms())) {
                $postData['platforms'] = $this->config->getPlatforms();
            }

            // Serializar dados
            $postDataJson = $this->json->serialize($postData);

            // Fazer a requisição
            $this->curl->post(self::API_BASE_URL . '/api/uploadposts/users/generate-jwt', $postDataJson);

            // Obter resposta
            $response = $this->curl->getBody();

            // Verificar status HTTP
            $statusCode = $this->curl->getStatus();
            if ($statusCode !== 200) {
                throw new LocalizedException(
                    __('API retornou status code inválido: %1', $statusCode)
                );
            }

            // Desserializar a resposta
            $responseData = $this->json->unserialize($response);

            if (!isset($responseData['access_url']) || !isset($responseData['success']) || !$responseData['success']) {
                throw new LocalizedException(
                    __('Resposta da API incompleta ou inválida')
                );
            }

            return $responseData;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao gerar JWT na API Upload-Post: ' . $e->getMessage(), [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new LocalizedException(
                __('Não foi possível gerar o JWT na API Upload-Post: %1', $e->getMessage())
            );
        }
    }

    /**
     * Recupera a lista de todos os perfis de usuário criados com a chave de API
     *
     * @return array
     * @throws LocalizedException
     */
    public function getUserProfiles(): array
    {
        try {
            // Configurar headers
            $this->curl->addHeader('Authorization', 'ApiKey ' . $this->config->getApiKey());
            $this->curl->addHeader('Content-Type', 'application/json');

            // Fazer a requisição GET
            $this->curl->get(self::API_BASE_URL . '/api/uploadposts/users');

            // Obter resposta
            $response = $this->curl->getBody();

            // Verificar status HTTP
            $statusCode = $this->curl->getStatus();
            if ($statusCode !== 200) {
                throw new LocalizedException(
                    __('API retornou status code inválido: %1', $statusCode)
                );
            }

            // Desserializar a resposta
            $responseData = $this->json->unserialize($response);

            if (!isset($responseData['success']) || !$responseData['success']) {
                throw new LocalizedException(
                    __('Resposta da API incompleta ou inválida')
                );
            }

            return $responseData;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter perfis de usuário na API Upload-Post: ' . $e->getMessage(), [
                'error' => $e->getMessage()
            ]);

            throw new LocalizedException(
                __('Não foi possível obter os perfis de usuário na API Upload-Post: %1', $e->getMessage())
            );
        }
    }
}
