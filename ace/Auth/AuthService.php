<?php declare(strict_types=1);

namespace ACE\Auth;

use ACE\Database\Db;
use ACE\Database\DatabaseDriverInterface;

/**
 * AuthService - Handles authentication, registration, and 2FA
 */
class AuthService
{
    private TokenManager $tokenManager;
    private DatabaseDriverInterface $db;

    public function __construct()
    {
        $this->tokenManager = new TokenManager();
        $dbManager = app(Db::class);
        $this->db = $dbManager->driver(env('DB_CONNECTION', 'mysql'));
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            throw new \InvalidArgumentException('Email and password are required');
        }

        // Check if email already exists
        if ($this->emailExists($data['email'])) {
            throw new \RuntimeException('Email already exists');
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        // Insert user
        $this->db->prepareQuery(
            "INSERT INTO users (email, password, user_type, status, created_at, updated_at)
             VALUES (?, ?, ?, 'active', NOW(), NOW())",
            [$data['email'], $hashedPassword, $data['user_type'] ?? 'member']
        );

        $userId = $this->db->getLastInsertId();

        // Create corresponding profile based on user type
        if (($data['user_type'] ?? 'member') === 'member') {
            $this->createMemberProfile($userId, $data);
        } else {
            $this->createAdminProfile($userId, $data);
        }

        return [
            'id' => $userId,
            'email' => $data['email'],
            'user_type' => $data['user_type'] ?? 'member',
        ];
    }

    /**
     * Login user and generate tokens
     */
    public function login(string $email, string $password, ?string $twoFactorCode = null): array
    {
        // Log attempt
        $this->logLoginAttempt($email, null, false, 'Attempting login');

        // Get user
        $user = $this->getUserByEmail($email);
        if (!$user) {
            $this->logLoginAttempt($email, null, false, 'User not found');
            throw new \RuntimeException('Invalid credentials');
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->logLoginAttempt($email, $user['id'], false, 'Invalid password');
            throw new \RuntimeException('Invalid credentials');
        }

        // Check if account is active
        if ($user['status'] !== 'active') {
            $this->logLoginAttempt($email, $user['id'], false, 'Account ' . $user['status']);
            throw new \RuntimeException('Account is ' . $user['status']);
        }

        // Check 2FA if enabled
        if ($this->is2FAEnabled($user['id'])) {
            if (empty($twoFactorCode)) {
                throw new \RuntimeException('2FA code required');
            }

            if (!$this->verify2FACode($user['id'], $twoFactorCode)) {
                $this->logLoginAttempt($email, $user['id'], false, 'Invalid 2FA code');
                throw new \RuntimeException('Invalid 2FA code');
            }
        }

        // Generate tokens
        $tokens = $this->tokenManager->generateTokens($user['id'], $user['user_type']);

        // Log successful login
        $this->logLoginAttempt($email, $user['id'], true, null);

        return array_merge($tokens, [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
            ],
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(string $token): void
    {
        $this->tokenManager->revokeToken($token);
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        $tokenData = $this->tokenManager->validateToken($refreshToken);

        if (!$tokenData || $tokenData['type'] !== 'refresh') {
            throw new \RuntimeException('Invalid refresh token');
        }

        // Revoke old tokens
        $this->tokenManager->revokeAllUserTokens($tokenData['user_id']);

        // Generate new tokens
        return $this->tokenManager->generateTokens($tokenData['user_id'], $tokenData['user_type']);
    }

    /**
     * Get current user from token
     */
    public function getCurrentUser(string $token): ?array
    {
        $tokenData = $this->tokenManager->validateToken($token);

        if (!$tokenData || $tokenData['type'] !== 'access') {
            return null;
        }

        return $this->getUserById($tokenData['user_id']);
    }

    // ==============================================
    // 2FA (Two-Factor Authentication) Methods
    // ==============================================

    /**
     * Enable 2FA for user
     */
    public function enable2FA(int $userId): array
    {
        // Generate secret
        $secret = $this->generateTOTPSecret();

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();

        // Store in database
        $this->db->prepareQuery(
            "INSERT INTO two_factor_auth (user_id, secret, is_enabled, backup_codes, created_at, updated_at)
             VALUES (?, ?, true, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE secret = ?, is_enabled = true, backup_codes = ?, updated_at = NOW()",
            [$userId, $secret, json_encode($backupCodes), $secret, json_encode($backupCodes)]
        );

        // Get user email for QR code
        $user = $this->getUserById($userId);

        return [
            'secret' => $secret,
            'qr_code_url' => $this->generateQRCodeUrl($user['email'], $secret),
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * Disable 2FA for user
     */
    public function disable2FA(int $userId): void
    {
        $this->db->prepareQuery(
            "UPDATE two_factor_auth SET is_enabled = false, updated_at = NOW() WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Check if 2FA is enabled
     */
    private function is2FAEnabled(int $userId): bool
    {
        $result = $this->db->prepareQuery(
            "SELECT is_enabled FROM two_factor_auth WHERE user_id = ? LIMIT 1",
            [$userId]
        );

        if ($result instanceof \PDOStatement) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return $row && $row['is_enabled'];
        } elseif ($result instanceof \mysqli_result) {
            $row = $result->fetch_assoc();
            return $row && $row['is_enabled'];
        }

        return false;
    }

    /**
     * Verify 2FA code
     */
    public function verify2FACode(int $userId, string $code): bool
    {
        // Get secret
        $result = $this->db->prepareQuery(
            "SELECT secret, backup_codes FROM two_factor_auth WHERE user_id = ? AND is_enabled = true LIMIT 1",
            [$userId]
        );

        $row = null;
        if ($result instanceof \PDOStatement) {
            $row = $result->fetch(\PDO::FETCH_ASSOC);
        } elseif ($result instanceof \mysqli_result) {
            $row = $result->fetch_assoc();
        }

        if (!$row) {
            return false;
        }

        // Try TOTP first
        if ($this->verifyTOTP($row['secret'], $code)) {
            // Update last used
            $this->db->prepareQuery(
                "UPDATE two_factor_auth SET last_used_at = NOW() WHERE user_id = ?",
                [$userId]
            );
            return true;
        }

        // Try backup codes
        $backupCodes = json_decode($row['backup_codes'], true) ?: [];
        if (in_array($code, $backupCodes)) {
            // Remove used backup code
            $backupCodes = array_diff($backupCodes, [$code]);
            $this->db->prepareQuery(
                "UPDATE two_factor_auth SET backup_codes = ?, last_used_at = NOW() WHERE user_id = ?",
                [json_encode(array_values($backupCodes)), $userId]
            );
            return true;
        }

        return false;
    }

    /**
     * Generate TOTP secret
     */
    private function generateTOTPSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * Generate backup codes
     */
    private function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%08d', random_int(0, 99999999));
        }
        return $codes;
    }

    /**
     * Verify TOTP code
     */
    private function verifyTOTP(string $secret, string $code): bool
    {
        $timeSlice = floor(time() / 30);

        // Check current time slice and ±1 for clock skew
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTPCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code for a time slice
     */
    private function generateTOTPCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode
     */
    private function base32Decode(string $secret): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $decoded = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($chars, $secret[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $decoded .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
                $bitsLeft -= 8;
            }
        }

        return $decoded;
    }

    /**
     * Generate QR code URL for Google Authenticator
     */
    private function generateQRCodeUrl(string $email, string $secret): string
    {
        $appName = env('APP_NAME', 'ACE Framework');
        $issuer = urlencode($appName);
        $account = urlencode($email);
        $otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    }

    // ==============================================
    // Helper Methods
    // ==============================================

    private function emailExists(string $email): bool
    {
        $result = $this->db->prepareQuery(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if ($result instanceof \PDOStatement) {
            return $result->rowCount() > 0;
        } elseif ($result instanceof \mysqli_result) {
            return $result->num_rows > 0;
        }

        return false;
    }

    private function getUserByEmail(string $email): ?array
    {
        $result = $this->db->prepareQuery(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if ($result instanceof \PDOStatement) {
            return $result->fetch(\PDO::FETCH_ASSOC) ?: null;
        } elseif ($result instanceof \mysqli_result) {
            return $result->fetch_assoc() ?: null;
        }

        return null;
    }

    private function getUserById(int $id): ?array
    {
        $result = $this->db->prepareQuery(
            "SELECT id, email, user_type, status, created_at FROM users WHERE id = ? LIMIT 1",
            [$id]
        );

        if ($result instanceof \PDOStatement) {
            return $result->fetch(\PDO::FETCH_ASSOC) ?: null;
        } elseif ($result instanceof \mysqli_result) {
            return $result->fetch_assoc() ?: null;
        }

        return null;
    }

    private function createMemberProfile(int $userId, array $data): void
    {
        $this->db->prepareQuery(
            "INSERT INTO members (user_id, name, nickname, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
            [$userId, $data['name'] ?? '', $data['nickname'] ?? 'user' . $userId]
        );
    }

    private function createAdminProfile(int $userId, array $data): void
    {
        $this->db->prepareQuery(
            "INSERT INTO admins (user_id, name, role, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
            [$userId, $data['name'] ?? '', $data['role'] ?? 'admin']
        );
    }

    private function logLoginAttempt(string $email, ?int $userId, bool $success, ?string $failureReason): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $this->db->prepareQuery(
            "INSERT INTO login_logs (user_id, email, ip_address, user_agent, success, failure_reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$userId, $email, $ipAddress, $userAgent, $success, $failureReason]
        );
    }
}
