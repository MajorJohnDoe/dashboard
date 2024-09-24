<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\SecureSession;

class User
{
    private DatabaseInterface $db;
    private SecureSession $session;

    private ?int    $userId = null;
    private ?string $username = null;
    private ?string $userIp = null;
    private bool    $isLoggedIn = false;
    private ?int    $activeTaskBoardId = null;

    private const COOKIE_NAME = 'auth_project';
    private const COOKIE_SALT = '!!PLonSIMDSAM35324dfg5DAUSHODNASDJ353NMASDSA&%&A/SD&HASNJDdfghAS&DGIHYAUSDNA3535SDFASDF%A3532dfgsdfggsdg53532535SDGIASYDU'; // We salt our cookies. security is important
    private const SESSION_TIMEOUT = 60 * 60 * 24 * 30; // 30 days

    public function __construct(DatabaseInterface $db, SecureSession $session)
    {
        $this->db = $db;
        $this->session = $session;
        $this->checkUserAuthentication();
    }

    
    public function login(string $username, string $password, bool $persistentConnection = true): bool
    {    
        $user = $this->db->q("SELECT user_id, user_username, user_password FROM `user` WHERE user_username = ? LIMIT 1", 's', $username);
    
        if ($user && is_array($user) && !empty($user)) {
            $storedHash = $user[0]['user_password'] ?? null;
            $userId     = $user[0]['user_id'] ?? null;
            $dbUsername = $user[0]['user_username'] ?? null;
    
            if ($storedHash === null || $userId === null || $dbUsername === null) {
                error_log("Login failed: Missing user data");
                return false;
            }
    
            $computedHash = $this->passwordHashFunction($password, _USER_PASSWORD_SALT);
    
            if ($computedHash === $storedHash) {
                $this->setUserData($userId, $dbUsername);
                $this->session->set('user_logged_in', true);
                $this->session->set('user_id', $userId);
                $this->session->set('user_username', $dbUsername);
                $this->session->set('user_session_persistent', $persistentConnection);
                $this->isLoggedIn = true;
    
                if ($persistentConnection) {
                    $this->initiatePersistentSession();
                }
    
                error_log("Login successful for username: $username. User ID: $userId");
                return true;
            } 
        } 
    
        error_log("Login failed for username: $username");
        return false;
    }

    // We hash user passwords for security purposes
    public function passwordHashFunction(string $password, string $salt): string
    {
        // Generate a unique salt string to slow down the process
        $new_salt = $salt;
        for ($i = 0; $i < 100; $i++) {
            $new_salt = hash('whirlpool', ($salt . substr($password, -3) . $new_salt));
        }
        
        // Use the first 20 characters as salt
        $first_salt = substr($new_salt, 0, 10);
        $second_salt = substr($new_salt, 10, 10);
        
        // Split the password into two parts, ensuring both parts exist
        $password_length = strlen($password);
        $first_part = substr($password, 0, (int)ceil($password_length / 2));
        $second_part = substr($password, (int)ceil($password_length / 2));
        
        // Create the password hash to use
        $hashcode = hash('whirlpool', $first_salt . $first_part . $second_salt . $second_part);
        
        return $hashcode;
    }

    public function logout(): void
    {
        $this->db->q("DELETE FROM user_session WHERE userid = ? LIMIT 1", 'i', $this->userId);
        $this->session->destroy();
        $this->resetUserData();
        $this->removePersistentCookie();
    }

    public function isLoggedIn(): bool
    {
        $sessionLoggedIn = $this->session->get('user_logged_in');
        $userId = $this->session->get('user_id');
        
        error_log("isLoggedIn check - Session logged in: " . ($sessionLoggedIn ? 'true' : 'false') . ", User ID: " . ($userId ?? 'null'));
    
        if ($sessionLoggedIn === true && $userId !== null) {
            // Additional check: verify user exists in database
            $user = $this->db->q("SELECT user_id FROM `user` WHERE user_id = ? LIMIT 1", 'i', $userId);
            if ($user && count($user) > 0) {
                $this->isLoggedIn = true;
                error_log("User verified in database. isLoggedIn set to true.");
            } else {
                $this->isLoggedIn = false;
                error_log("User not found in database. isLoggedIn set to false.");
                // Clear invalid session
                $this->session->destroy();
            }
        } else {
            $this->isLoggedIn = false;
            error_log("Session check failed. isLoggedIn set to false.");
        }
    
        return $this->isLoggedIn;
    }


    // When user selects a task board, we set the board as active
    public function setActiveTaskBoard(int $boardId): bool
    {
        $result = $this->db->q("SELECT `id` FROM `tm_board` WHERE `user_id` = ? AND `id` = ? LIMIT 1", "ii", $this->userId, $boardId);

        if ($result && count($result) > 0) {
            $updateSuccess = $this->db->q("UPDATE `user` SET `active_task_board` = ? WHERE user_id = ? LIMIT 1", "ii", $boardId, $this->userId);

            if ($updateSuccess !== false) {
                $this->activeTaskBoardId = $boardId;
                return true;
            }
        }

        return false;
    }

    // Fetch and cache the user's active T board ID
    // Returns null if no active board is set
    public function getActiveTaskBoard(): ?int
    {
        if ($this->activeTaskBoardId === null) {
            $result = $this->db->q("SELECT `active_task_board` FROM `user` WHERE `user_id` = ? LIMIT 1", "i", $this->userId);

            if ($result && count($result) > 0) {
                $this->activeTaskBoardId = $result[0]['active_task_board'];
            }
        }

        return $this->activeTaskBoardId;
    }


    public function getProfilePhotoPath(): string
    {
        $user = $this->db->q("SELECT `user_avatar` FROM `user` WHERE `user_id` = ? LIMIT 1", 'i', $this->getUserId());
        if ($user && isset($user[0]['user_avatar']) && $user[0]['user_avatar'] !== '') {
            return $user[0]['user_avatar'];
        }
        return '/assets/img/default_profile.jpg';
    }    


    public function getChatGPTAPIKey(): string
    {
        $user = $this->db->q("SELECT `gpt_api_key` FROM `user` WHERE `user_id` = ? LIMIT 1", 'i', $this->getUserId());
        if ($user && isset($user[0]['gpt_api_key']) && $user[0]['gpt_api_key'] !== '') {
            return $user[0]['gpt_api_key'];
        }
        return '';
    }        

    // Set up a persistent session for "remember me" functionality
    // Creates a new session entry in the database and sets a persistent cookie
    private function initiatePersistentSession(): bool
    {
        $this->db->q("DELETE FROM `user_session` WHERE userid = ?", 'i', $this->userId);

        $identifier = $this->generateIdentifier();
        $token = $this->generateToken();
        $timeout = time() + self::SESSION_TIMEOUT;

        $initSession = $this->db->q(
            "INSERT INTO user_session 
             SET 
                session = ?, 
                token = ?, 
                userid = ?, 
                sess_start = NOW(), 
                last_activity = NOW(), 
                sess_expire = DATE_ADD(NOW(), 
                INTERVAL ? SECOND), 
                ip = ?",
            'ssiis',
            $identifier,
            $token,
            $this->userId,
            self::SESSION_TIMEOUT,
            $this->userIp
        );

        if ($initSession !== false) {
            $this->setPersistentCookie($identifier, $token, $timeout);
            return true;
        }

        return false;
    }

    // Verify user's authentication status
    // Retrieves user data from session or attempts to authenticate via persistent session
    private function checkUserAuthentication(): void
    {
        $this->isLoggedIn = ($this->session->get('user_logged_in') === true);
        if ($this->isLoggedIn) {
            $this->userId = $this->session->get('user_id');
            $this->username = $this->session->get('user_username');
            $this->userIp = $this->session->get('user_ip');
        } else {
            $this->handlePersistentSession();
        }
        /* error_log("checkUserAuthentication called. isLoggedIn: " . var_export($this->isLoggedIn, true)); */
    }

    // Manage temporary (non-persistent) sessions
    // Destroys invalid sessions or updates user data for valid ones
    private function handleTemporarySession(): void
    {
        if (!$this->session->check() || !$this->session->get('user_logged_in')) {
            $this->session->destroy();
        } else {
            $this->setUserData(
                $this->session->get('user_id'),
                $this->session->get('user_username')
            );
        }
    }

    // Process persistent login attempts
    // Validates and refreshes the session if persistent cookie data is present
    private function handlePersistentSession(): void
    {
        $cookieData = $this->getPersistentCookieData();
        if ($cookieData) {
            $authUser = $this->getAuthUser($cookieData['identifier'], $cookieData['token']);
            if ($authUser) {
                $this->validateAndRefreshSession($authUser, $cookieData);
            }
        }
    }

    // Set user data in object and session after successful login
    private function setUserData(int $userId, string $username): void
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->userIp = $_SERVER['REMOTE_ADDR'];
        $this->isLoggedIn = true;

        $this->session->set('user_id', $this->userId);
        $this->session->set('user_username', $this->username);
        $this->session->set('user_ip', $this->userIp);
        $this->session->set('user_logged_in', true);
        
        error_log("setUserData called. userId: $userId, username: $username, isLoggedIn: true");
    }

    // Clear user data when logging out or session expires
    private function resetUserData(): void
    {
        $this->isLoggedIn = false;
        $this->userId = null;
        $this->username = null;
        $this->userIp = null;
    }

    // Create a unique identifier for the user's session
    private function generateIdentifier(): string
    {
        return md5(self::COOKIE_SALT . md5($this->username . $this->userIp) . self::COOKIE_SALT);
    }

    // Generate a random token for additional security
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    // Set a secure, persistent cookie for "remember me" functionality
    private function setPersistentCookie(string $identifier, string $token, int $timeout): void
    {
        setcookie(self::COOKIE_NAME, "$identifier:$token", [
            'expires' => $timeout,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    // Remove the persistent cookie when logging out
    private function removePersistentCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    // Retrieve and validate persistent cookie data
    private function getPersistentCookieData(): ?array
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            list($identifier, $token) = explode(':', $_COOKIE[self::COOKIE_NAME]);
            if (ctype_alnum($identifier) && ctype_alnum($token)) {
                return [
                    'identifier' => $identifier,
                    'token' => $token
                ];
            }
        }
        return null;
    }

    // Fetch user data from database based on session identifier and token
    private function getAuthUser(string $identifier, string $token): ?array
    {
        return $this->db->q(
            "SELECT * FROM user_session a 
            LEFT JOIN user b ON a.userid = b.user_id 
            WHERE a.session = ? AND a.token = ? LIMIT 1",
            'ss',
            $identifier,
            $token
        );
    }

    // Validate session and refresh if valid, destroy if expired
    private function validateAndRefreshSession(array $authUser, array $cookieData): void
    {
        if (time() > strtotime($authUser[0]['sess_expire'])) {
            $this->session->destroy();
            return;
        }
    
        $sessionIdentifier = $this->generateIdentifier();
        if ($cookieData['identifier'] === $sessionIdentifier) {
            $this->setUserData($authUser[0]['user_id'], $authUser[0]['user_username']);
            $this->session->set('user_logged_in', true);
            $this->session->set('user_session_persistent', true);
    
            $this->refreshSession($sessionIdentifier, $cookieData['token']);
        } else {
            $this->session->destroy();
        }
    }

    // Update session expiry in database and set new persistent cookie
    private function refreshSession(string $sessionIdentifier, string $token): void
    {
        $this->db->q("  UPDATE 
                            user_session 
                        SET 
                            last_activity = NOW(), sess_expire = DATE_ADD(NOW(), INTERVAL ? SECOND) 
                        WHERE 
                            session = ? AND token = ? LIMIT 1",
                        'iss',
                        self::SESSION_TIMEOUT,
                        $sessionIdentifier,
                        $token
        );

        $this->setPersistentCookie($sessionIdentifier, $token, time() + self::SESSION_TIMEOUT);
    }

    // Getter for user ID, can return null if no user is logged in
    public function user_id(): ?int
    {
        return $this->userId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
?>