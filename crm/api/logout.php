<?php
require_once __DIR__ . '/../includes/auth.php';

$user = getSessionUser();
if ($user) {
    registrarLog((int)$user['empresa_id'], (int)$user['id'], 'logout');
}

session_unset();
session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
