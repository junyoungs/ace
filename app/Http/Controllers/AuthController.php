<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use ACE\Auth\AuthService;

/**
 * AuthController - Handles authentication endpoints
 *
 * This is a ready-to-use authentication controller.
 * All endpoints are automatically available once you run: ./ace api
 */
class AuthController extends \ACE\Http\Control
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth/register
     * Register a new user
     *
     * Body:
     * {
     *   "email": "user@example.com",
     *   "password": "password123",
     *   "name": "John Doe",
     *   "nickname": "johndoe",
     *   "user_type": "member"  // optional: "member" or "admin"
     * }
     */
    public function postRegister(): array
    {
        try {
            $data = $this->request->getParsedBody();
            $user = $this->authService->register($data);

            http_response_code(201);
            return [
                'message' => 'User registered successfully',
                'user' => $user,
            ];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/login
     * Login and get access token
     *
     * Body:
     * {
     *   "email": "user@example.com",
     *   "password": "password123",
     *   "two_factor_code": "123456"  // optional, required if 2FA is enabled
     * }
     */
    public function postLogin(): array
    {
        try {
            $data = $this->request->getParsedBody();

            if (empty($data['email']) || empty($data['password'])) {
                http_response_code(400);
                return ['error' => 'Email and password are required'];
            }

            $result = $this->authService->login(
                $data['email'],
                $data['password'],
                $data['two_factor_code'] ?? null
            );

            return $result;
        } catch (\Exception $e) {
            http_response_code(401);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/logout
     * Logout (requires authentication)
     *
     * Headers:
     * Authorization: Bearer <token>
     */
    public function postLogout(): array
    {
        try {
            $authHeader = $this->request->getHeader('Authorization')[0] ?? '';

            if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                $this->authService->logout($matches[1]);
            }

            return ['message' => 'Logged out successfully'];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/refresh
     * Refresh access token using refresh token
     *
     * Body:
     * {
     *   "refresh_token": "<refresh_token>"
     * }
     */
    public function postRefresh(): array
    {
        try {
            $data = $this->request->getParsedBody();

            if (empty($data['refresh_token'])) {
                http_response_code(400);
                return ['error' => 'Refresh token is required'];
            }

            $tokens = $this->authService->refreshToken($data['refresh_token']);

            return $tokens;
        } catch (\Exception $e) {
            http_response_code(401);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * GET /api/auth/me
     * Get current user information (requires authentication)
     *
     * Headers:
     * Authorization: Bearer <token>
     */
    public function getMe(): array
    {
        try {
            $authHeader = $this->request->getHeader('Authorization')[0] ?? '';

            if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                http_response_code(401);
                return ['error' => 'Missing authorization token'];
            }

            $user = $this->authService->getCurrentUser($matches[1]);

            if (!$user) {
                http_response_code(401);
                return ['error' => 'Invalid or expired token'];
            }

            return ['user' => $user];
        } catch (\Exception $e) {
            http_response_code(401);
            return ['error' => $e->getMessage()];
        }
    }

    // ==============================================
    // 2FA (Two-Factor Authentication) Endpoints
    // ==============================================

    /**
     * POST /api/auth/enable
     * Enable 2FA for current user (requires authentication)
     *
     * Headers:
     * Authorization: Bearer <token>
     *
     * Returns QR code URL and backup codes
     */
    public function postEnable2fa(): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');

            if (!$authUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $result = $this->authService->enable2FA($authUser['id']);

            return [
                'message' => '2FA enabled successfully',
                'qr_code_url' => $result['qr_code_url'],
                'backup_codes' => $result['backup_codes'],
                'instructions' => 'Scan the QR code with Google Authenticator or Authy app',
            ];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/disable
     * Disable 2FA for current user (requires authentication)
     *
     * Headers:
     * Authorization: Bearer <token>
     */
    public function postDisable2fa(): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');

            if (!$authUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $this->authService->disable2FA($authUser['id']);

            return ['message' => '2FA disabled successfully'];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/auth/verify
     * Verify 2FA code (requires authentication)
     *
     * Headers:
     * Authorization: Bearer <token>
     *
     * Body:
     * {
     *   "code": "123456"
     * }
     */
    public function postVerify2fa(): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');

            if (!$authUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $data = $this->request->getParsedBody();

            if (empty($data['code'])) {
                http_response_code(400);
                return ['error' => 'Code is required'];
            }

            $valid = $this->authService->verify2FACode($authUser['id'], $data['code']);

            if ($valid) {
                return ['message' => 'Code verified successfully', 'valid' => true];
            } else {
                http_response_code(400);
                return ['message' => 'Invalid code', 'valid' => false];
            }
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}
