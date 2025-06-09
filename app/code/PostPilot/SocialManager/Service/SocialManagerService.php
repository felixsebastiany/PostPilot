<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostPilot\SocialManager\Api\SocialManagerInterface;
use PostPilot\SocialManager\Model\SocialConnectionFactory;
use PostPilot\SocialManager\Model\SocialConnectionRepository;
use PostPilot\SocialManager\Model\SocialUser;
use PostPilot\SocialManager\Model\SocialUserFactory;
use PostPilot\SocialManager\Model\SocialUserRepository;

class SocialManagerService implements SocialManagerInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly UploadPostService $uploadPostService,
        private readonly SocialUserFactory $socialUserFactory,
        private readonly SocialUserRepository $socialUserRepository,
        private readonly SocialConnectionFactory $socialConnectionFactory,
        private readonly SocialConnectionRepository $socialConnectionRepository
    ) {
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function getSocialUsers(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer must be logged in'));
        }

        try {
            $customerId = (int)$this->customerSession->getCustomerId();
            $users = $this->socialUserRepository->getByCustomerId($customerId);

            return [
                'success' => true,
                'message' => null,
                'users' => $users
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'users' => []
            ];
        }
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function addSocialUser(string $name): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer must be logged in'));
        }

        if (empty(trim($name))) {
            return [
                'success' => false,
                'message' => 'Nome do usuário é obrigatório',
                'user' => null
            ];
        }

        try {
            // Criar usuário na API do Upload-Post
            $apiResponse = $this->uploadPostService->createUser($name);

            if (!$apiResponse['success']) {
                return [
                    'success' => false,
                    'message' => $apiResponse['message'],
                    'user' => null
                ];
            }

            // Criar e salvar o usuário no banco de dados local
            $socialUser = $this->socialUserFactory->create();
            $socialUser->setName(trim($name))
                ->setCustomerId((int)$this->customerSession->getCustomerId())
                ->setStatus(SocialUser::STATUS_INACTIVE);

            $this->socialUserRepository->save($socialUser);

            return [
                'success' => true,
                'message' => 'Usuário adicionado com sucesso',
                'user' => [
                    'id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'status' => $socialUser->getStatus(),
                    'connections' => [] // Novo usuário não tem conexões ainda
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * @param int $userId
     * @return array
     * @throws LocalizedException
     */
    public function generateJwtUP(int $userId): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer must be logged in'));
        }

        try {
            $customerId = (int)$this->customerSession->getCustomerId();

            // Primeiro busca o usuário para obter o ID da API
            $user = $this->socialUserRepository->getById($userId);

            // Verifica se o usuário pertence ao cliente atual
            if ($user->getCustomerId() !== $customerId) {
                throw new LocalizedException(
                    __('Não é permitido conectar em usuários de outros clientes')
                );
            }

            $apiResponse = $this->uploadPostService->generateJwt($user->getName());

            if (!$apiResponse['success']) {
                return [
                    'success' => false,
                    'message' => __('Falha ao conectar usuário na API: %1', $apiResponse['message'])
                ];
            }

            return [
                'success' => true,
                'access_url' => $apiResponse['access_url'],
                'message' => __('JWT gerado com sucesso')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function deleteSocialUser(int $userId): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer must be logged in'));
        }

        try {
            $customerId = (int)$this->customerSession->getCustomerId();

            // Primeiro busca o usuário para obter o ID da API
            $user = $this->socialUserRepository->getById($userId);

            // Verifica se o usuário pertence ao cliente atual
            if ($user->getCustomerId() !== $customerId) {
                throw new LocalizedException(
                    __('Não é permitido deletar usuários de outros clientes')
                );
            }

            $apiResponse = $this->uploadPostService->deleteUser($user->getName());

            if (!$apiResponse['success']) {
                return [
                    'success' => false,
                    'message' => __('Falha ao deletar usuário na API: %1', $apiResponse['message'])
                ];
            }

            // Deletar todas as conexões sociais do usuário
            $this->socialConnectionRepository->deleteByUserId($userId);

            $deleted = $this->socialUserRepository->deleteByIdAndCustomer($userId, $customerId);

            if ($deleted) {
                return [
                    'success' => true,
                    'message' => __('Usuário social deletado com sucesso')
                ];
            }

            return [
                'success' => false,
                'message' => __('Não foi possível deletar o usuário social')
            ];

        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Ocorreu um erro ao deletar o usuário social: %1', $e->getMessage())
            ];
        }

    }

}
