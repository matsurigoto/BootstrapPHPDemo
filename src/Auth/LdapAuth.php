<?php
declare(strict_types=1);

namespace App\Auth;

use App\Db\OciConnection;

class LdapAuth
{
    public function __construct(private array $cfg, private OciConnection $db) {}

    /**
     * 認證使用者；成功時 upsert USERS 並回傳 user 陣列，失敗回 null。
     *
     * @return array<string,mixed>|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') return null;

        if (!($this->cfg['enabled'] ?? true)) {
            // 開發模式：以 USERS 表帳號通過 (僅供本機測試)
            return $this->devModeAuth($username, $password);
        }

        $username = trim($username);
        $hostString = implode(' ', $this->cfg['hosts'] ?? []);
        if ($hostString === '') {
            throw new \RuntimeException('LDAP hosts 未設定');
        }

        // 開發環境忽略憑證
        if (!empty($this->cfg['tls_skip_verify'])) {
            putenv('LDAPTLS_REQCERT=never');
        }

        $ds = @ldap_connect($hostString);
        if ($ds === false) return null;

        foreach (($this->cfg['options'] ?? []) as $opt => $val) {
            @ldap_set_option($ds, (int)$opt, $val);
        }

        $bindDn = str_replace('{username}', $username, $this->cfg['bind_format'] ?? '{username}');
        if (!@ldap_bind($ds, $bindDn, $password)) {
            @ldap_unbind($ds);
            return null;
        }

        // 搜尋使用者屬性
        $attrs = $this->cfg['attributes'] ?? [];
        $filter = str_replace('{username}', ldap_escape($username, '', LDAP_ESCAPE_FILTER), $this->cfg['search_filter'] ?? '(sAMAccountName={username})');
        $sr = @ldap_search($ds, $this->cfg['base_dn'] ?? '', $filter, $attrs);
        $entry = $sr ? @ldap_get_entries($ds, $sr) : false;
        @ldap_unbind($ds);

        $display = $username;
        $email = null;
        $groups = [];
        if (is_array($entry) && ($entry['count'] ?? 0) > 0) {
            $row = $entry[0];
            $display = $row['displayname'][0] ?? $username;
            $email   = $row['mail'][0] ?? null;
            if (!empty($row['memberof'])) {
                for ($i = 0; $i < (int)$row['memberof']['count']; $i++) {
                    $groups[] = $row['memberof'][$i];
                }
            }
        }

        $userId = $this->upsertUser($username, $display, $email);

        return [
            'id'           => $userId,
            'username'     => $username,
            'display_name' => $display,
            'email'        => $email,
            'role'         => $this->roleOf($userId),
            'groups'       => $groups,
        ];
    }

    /**
     * 開發模式：直接由 USERS 表辨識（密碼以 username 相同視為通過）。
     *
     * @return array<string,mixed>|null
     */
    private function devModeAuth(string $username, string $password): ?array
    {
        if ($password !== $username) return null;
        $row = $this->db->fetchOne(
            'SELECT ID, USERNAME, DISPLAY_NAME, EMAIL, ROLE FROM USERS WHERE USERNAME = :u',
            ['u' => $username]
        );
        if (!$row) {
            $id = $this->upsertUser($username, $username, null);
            return [
                'id' => $id, 'username' => $username, 'display_name' => $username,
                'email' => null, 'role' => 'USER', 'groups' => []
            ];
        }
        return [
            'id'           => (int)$row['id'],
            'username'     => $row['username'],
            'display_name' => $row['display_name'],
            'email'        => $row['email'],
            'role'         => $row['role'],
            'groups'       => [],
        ];
    }

    private function upsertUser(string $username, ?string $display, ?string $email): int
    {
        $existing = $this->db->fetchOne(
            'SELECT ID FROM USERS WHERE USERNAME = :u',
            ['u' => $username]
        );
        if ($existing) {
            $this->db->execute(
                'UPDATE USERS SET DISPLAY_NAME = :d, EMAIL = :e, LAST_LOGIN_AT = SYSTIMESTAMP WHERE ID = :id',
                ['d' => $display, 'e' => $email, 'id' => (int)$existing['id']]
            );
            return (int)$existing['id'];
        }
        return $this->db->insertReturningId(
            'INSERT INTO USERS (USERNAME, DISPLAY_NAME, EMAIL, ROLE, LAST_LOGIN_AT)
             VALUES (:u, :d, :e, :r, SYSTIMESTAMP)
             RETURNING ID INTO :new_id',
            ['u' => $username, 'd' => $display, 'e' => $email, 'r' => 'USER']
        );
    }

    private function roleOf(int $id): string
    {
        $r = $this->db->fetchOne('SELECT ROLE FROM USERS WHERE ID = :id', ['id' => $id]);
        return $r['role'] ?? 'USER';
    }
}
