<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Cron;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use PostPilot\SocialManager\Helper\Config;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection\CollectionFactory;
use PostPilot\SocialManager\Model\SocialConnection;
use PostPilot\SocialManager\Model\SocialConnectionFactory;
use PostPilot\SocialManager\Model\SocialConnectionRepository;
use PostPilot\SocialManager\Model\SocialUserRepository;
use PostPilot\SocialManager\Service\UploadPostService;
use Psr\Log\LoggerInterface;
use Magento\Framework\DataObject;

class SyncSocialConnections
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly UploadPostService $uploadPostService,
        private readonly SocialConnectionRepository $socialConnectionRepository,
        private readonly SocialConnectionFactory $socialConnectionFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SocialUserRepository $socialUserRepository,
    ) {
    }

    /**
     * Executa a sincronização das conexões sociais
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // Verifica se a sincronização está habilitada
            if (!$this->config->isSyncEnabled()) {
                $this->logger->info('Sincronização de conexões sociais desabilitada nas configurações.');
                return;
            }

            $this->logger->info('Iniciando sincronização de conexões sociais...');

            // Obtém os perfis de usuário da API
            $profilesData = $this->uploadPostService->getUserProfiles();

            if (!isset($profilesData['profiles']) || !is_array($profilesData['profiles'])) {
                throw new LocalizedException(__('Dados de perfis ausentes ou inválidos na resposta da API.'));
            }

            $this->processProfiles($profilesData['profiles']);

            $this->logger->info('Sincronização de conexões sociais concluída com sucesso.');
        } catch (\Exception $e) {
            $this->logger->error('Erro durante a sincronização de conexões sociais: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Processa os perfis recebidos da API
     *
     * @param array $profiles
     * @return void
     * @throws CouldNotSaveException
     */
    private function processProfiles(array $profiles): void
    {
        foreach ($profiles as $profile) {
            try {
                if (!isset($profile['username']) || !isset($profile['social_accounts'])) {
                    $this->logger->warning('Perfil inválido recebido da API', ['profile' => $profile]);
                    continue;
                }

                $username = $profile['username'];
                $socialAccounts = $profile['social_accounts'];

                // Busca usuário pelo username (você pode adaptar de acordo com sua lógica)
                $user = $this->socialUserRepository->getByName($username);
                if (!$user) {
                    $this->logger->warning('Usuário não encontrado para o username: ' . $username);
                    continue;
                }

                // Processa cada plataforma social
                foreach ($socialAccounts as $platform => $accountData) {
                    $this->processPlatformConnection($user, $platform, $accountData);
                }
            } catch (\Exception $e) {
                $this->logger->error('Erro ao processar perfil: ' . $e->getMessage(), [
                    'username' => $profile['username'] ?? 'desconhecido',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Processa uma conexão de plataforma específica
     *
     * @param DataObject$user
     * @param string $platform
     * @param mixed $accountData
     * @return void
     * @throws CouldNotSaveException
     */
    private function processPlatformConnection(DataObject $user, string $platform, mixed $accountData): void
    {
        if (!in_array($platform, [
            SocialConnection::PLATFORM_INSTAGRAM,
            SocialConnection::PLATFORM_FACEBOOK,
            SocialConnection::PLATFORM_YOUTUBE,
            SocialConnection::PLATFORM_TIKTOK
        ])) {
            $this->logger->warning('Plataforma não suportada: ' . $platform);
            return;
        }

        $userId = (int)$user->getId();
        $connection = $this->getOrCreateConnection($userId, $platform);

        // Determina o status com base nos dados da conta
        if (empty($accountData) || $accountData === null || $accountData === '') {
            $connection->setStatus(SocialConnection::STATUS_DISCONNECTED);
        } else {
            $connection->setStatus(SocialConnection::STATUS_CONNECTED);

            // Extrai informações da conta social
            $displayName = '';
            $username = '';
            $socialImage = '';

            if (is_array($accountData)) {
                if (isset($accountData['username'])) {
                    $username = $accountData['username'];
                }
                if (isset($accountData['display_name'])) {
                    $displayName = $accountData['display_name'];
                }
                if (isset($accountData['social_images'])) {
                    $socialImage = $accountData['social_images'];
                }


            }

            $connection->setDisplayName($displayName);
            $connection->setUsername($username);
            $connection->setSocialImages($socialImage);
        }

        // Salva a conexão
        $this->socialConnectionRepository->save($connection);
    }

    /**
     * Busca ou cria uma conexão social para um usuário e plataforma
     *
     * @param int $userId
     * @param string $platform
     * @return SocialConnection
     */
    private function getOrCreateConnection(int $userId, string $platform): SocialConnection
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('user_id', $userId);
        $collection->addFieldToFilter('platform', $platform);
        $connection = $collection->getFirstItem();

        if (!$connection->getId()) {
            $connection = $this->socialConnectionFactory->create();
            $connection->setUserId($userId);
            $connection->setPlatform($platform);
        }

        return $connection;
    }
}
