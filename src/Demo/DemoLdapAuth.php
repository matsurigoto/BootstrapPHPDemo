<?php
declare(strict_types=1);

namespace App\Demo;

/**
 * Demo 認證：固定使用者表 + 簡易密碼（密碼 = 帳號）；不連 LDAP / DB。
 */
class DemoLdapAuth
{
    public function __construct(private DemoStore $store) {}

    public function authenticate(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') return null;
        // 預設規則：密碼 = 帳號
        if ($password !== $username) return null;
        foreach ($this->store->table('users') as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                return [
                    'id' => (int)$u['id'],
                    'username' => $u['username'],
                    'display_name' => $u['display_name'],
                    'email' => $u['email'],
                    'role' => $u['role'],
                    'groups' => [],
                ];
            }
        }
        // 不存在則自動新增為 USER
        $id = $this->store->nextId('users');
        $row = ['id' => $id, 'username' => $username, 'display_name' => $username,
                'email' => null, 'role' => 'USER', 'last_login_at' => date('Y-m-d H:i:s')];
        $rows = $this->store->table('users');
        $rows[] = $row;
        $this->store->setTable('users', $rows);
        $this->store->save();
        return ['id' => $id, 'username' => $username, 'display_name' => $username,
                'email' => null, 'role' => 'USER', 'groups' => []];
    }
}
