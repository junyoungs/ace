<?php declare(strict_types=1);

namespace ACE\Auth;

/**
 * TokenManager - Simple JWT-like token management
 * Handles token creation, validation, and storage
 */
class TokenManager
{
    private const ACCESS_TOKEN_LIFETIME = 3600; // 1 hour
    private const REFRESH_TOKEN_LIFETIME = 2592000; // 30 days

    /**
     * Generate access and refresh tokens for a user
     */
    public function generateTokens(int $userId, string $userType): array
    {
        $accessToken = $this->createToken($userId, $userType, 'access');
        $refreshToken = $this->createToken($userId, $userType, 'refresh');

        // Store tokens in database
        $this->storeToken($userId, $accessToken, 'access', self::ACCESS_TOKEN_LIFETIME);
        $this->storeToken($userId, $refreshToken, 'refresh', self::REFRESH_TOKEN_LIFETIME);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => self::ACCESS_TOKEN_LIFETIME,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Create a token string
     */
    private function createToken(int $userId, string $userType, string $type): string
    {
        $data = [
            'user_id' => $userId,
            'user_type' => $userType,
            'type' => $type,
            'iat' => time(),
            'exp' => time() + ($type === 'access' ? self::ACCESS_TOKEN_LIFETIME : self::REFRESH_TOKEN_LIFETIME),
        ];

        // Simple base64 encoding (for production, use proper JWT library)
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $this->getSecret());

        return "{$payload}.{$signature}";
    }

    /**
     * Validate and decode a token
     */
    public function validateToken(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        // Check format
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->getSecret());
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return null;
        }

        // Check expiration
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        // Verify token exists in database and not expired
        if (!$this->tokenExistsInDb($token)) {
            return null;
        }

        return $data;
    }

    /**
     * Store token in database
     */
    private function storeToken(int $userId, string $token, string $type, int $lifetime): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $db = $this->getDb();
        $db->prepareQuery(
            "INSERT INTO tokens (user_id, token, type, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$userId, $token, $type, $expiresAt]
        );
    }

    /**
     * Check if token exists in database
     */
    private function tokenExistsInDb(string $token): bool
    {
        $db = $this->getDb();
        $result = $db->prepareQuery(
            "SELECT id FROM tokens WHERE token = ? AND expires_at > NOW() LIMIT 1",
            [$token]
        );

        if ($result instanceof \PDOStatement) {
            return $result->rowCount() > 0;
        } elseif ($result instanceof \mysqli_result) {
            return $result->num_rows > 0;
        }

        return false;
    }

    /**
     * Revoke a token (logout)
     */
    public function revokeToken(string $token): bool
    {
        $db = $this->getDb();
        $db->prepareQuery("DELETE FROM tokens WHERE token = ?", [$token]);
        return true;
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllUserTokens(int $userId): void
    {
        $db = $this->getDb();
        $db->prepareQuery("DELETE FROM tokens WHERE user_id = ?", [$userId]);
    }

    /**
     * Clean expired tokens (should be run periodically)
     */
    public function cleanExpiredTokens(): int
    {
        $db = $this->getDb();
        $db->prepareQuery("DELETE FROM tokens WHERE expires_at < NOW()", []);
        return $db->getAffectedRows();
    }

    /**
     * Get secret key for token signing
     */
    private function getSecret(): string
    {
        $secret = env('APP_KEY', '');
        if (empty($secret)) {
            throw new \RuntimeException('APP_KEY not set in .env file');
        }
        return $secret;
    }

    /**
     * Get database connection
     */
    private function getDb(): \ACE\Database\DatabaseDriverInterface
    {
        $dbManager = app(\ACE\Database\Db::class);
        return $dbManager->driver(env('DB_CONNECTION', 'mysql'));
    }
}
