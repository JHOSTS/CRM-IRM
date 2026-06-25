<?php
// Configuração de conexão com o banco de dados
// ATENÇÃO: Este arquivo nunca deve ser acessível publicamente.
// O .htaccess na raiz do projeto bloqueia acesso direto à pasta /config/

define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco_crm');   // Alterar para o nome do banco criado no cPanel
define('DB_USER', 'seu_usuario_db');  // Alterar para o usuário MySQL do cPanel
define('DB_PASS', 'sua_senha_db');    // Alterar para a senha do usuário MySQL
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção nunca exibir detalhes do erro de conexão
            http_response_code(500);
            echo json_encode(['error' => 'Erro de conexão com o banco de dados.']);
            exit;
        }
    }
    return $pdo;
}
