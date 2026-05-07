-- 種子資料：建立預設管理員帳號 (LDAP 登入後仍可 upsert)
INSERT INTO USERS (USERNAME, DISPLAY_NAME, EMAIL, ROLE)
VALUES ('admin', 'System Admin', 'admin@example.com', 'ADMIN');

INSERT INTO USERS (USERNAME, DISPLAY_NAME, EMAIL, ROLE)
VALUES ('user1', 'Demo User', 'user1@example.com', 'USER');

COMMIT;
