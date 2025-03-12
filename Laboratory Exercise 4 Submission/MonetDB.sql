use monetdb;

INSERT INTO users (uuid, name, email, email_verified_at, password, remember_token, created_at, updated_at)
VALUES 
  (UUID(), 'Marjovic Alejado', 'marjovic.alejado@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa2yIK1yyRq1C5y/f7Kwy5Is4G6', 'token1', NOW(), NOW()),
  (UUID(), 'Aslainie Maruhom', 'aslainie.maruhom@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa2yIK1yyRq1C5y/f7Kwy5Is4G6', 'token2', NOW(), NOW()),
  (UUID(), 'Gerald Michael Ablitado', 'gerald.michael.ablitado@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa2yIK1yyRq1C5y/f7Kwy5Is4G6', 'token3', NOW(), NOW()),
  (UUID(), 'Dainty Deanne Lamberto', 'dainty.deanne.lamberto@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa2yIK1yyRq1C5y/f7Kwy5Is4G6', 'token4', NOW(), NOW());

select * from users;