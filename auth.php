<?php
// =============================================
// GAMEVAULT — AUTH API (Login & Register)
// POST /api/auth.php?action=login|register
// =============================================

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../core/DB.php';

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

match ($action) {
  'register' => handleRegister($body),
  'login'    => handleLogin($body),
  'logout'   => handleLogout(),
  'me'       => handleMe(),
  default    => response(404, ['error' => 'Action not found']),
};

function handleRegister(array $body): void {
  $name     = trim($body['name'] ?? '');
  $email    = trim($body['email'] ?? '');
  $password = $body['password'] ?? '';
  $phone    = trim($body['phone'] ?? '');

  if (!$name || !$email || !$password) {
    response(422, ['error' => 'Nama, email, dan password wajib diisi']);
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    response(422, ['error' => 'Format email tidak valid']);
  }
  if (strlen($password) < 8) {
    response(422, ['error' => 'Password minimal 8 karakter']);
  }

  $exists = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
  if ($exists) {
    response(409, ['error' => 'Email sudah terdaftar']);
  }

  $hashed = password_hash($password, PASSWORD_BCRYPT);
  $id = DB::insert('users', [
    'name'     => $name,
    'email'    => $email,
    'password' => $hashed,
    'phone'    => $phone,
  ]);

  $_SESSION['user_id']   = $id;
  $_SESSION['user_name'] = $name;
  $_SESSION['user_role'] = 'user';

  response(201, ['success' => true, 'user' => ['id' => $id, 'name' => $name, 'email' => $email]]);
}

function handleLogin(array $body): void {
  $email    = trim($body['email'] ?? '');
  $password = $body['password'] ?? '';

  if (!$email || !$password) {
    response(422, ['error' => 'Email dan password wajib diisi']);
  }

  $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
  if (!$user || !password_verify($password, $user['password'])) {
    response(401, ['error' => 'Email atau password salah']);
  }

  $_SESSION['user_id']   = $user['id'];
  $_SESSION['user_name'] = $user['name'];
  $_SESSION['user_role'] = $user['role'];

  unset($user['password']);
  response(200, ['success' => true, 'user' => $user]);
}

function handleLogout(): void {
  session_destroy();
  response(200, ['success' => true]);
}

function handleMe(): void {
  if (empty($_SESSION['user_id'])) {
    response(401, ['error' => 'Belum login']);
  }
  $user = DB::fetchOne(
    'SELECT id, name, email, phone, role, balance, created_at FROM users WHERE id = ?',
    [$_SESSION['user_id']]
  );
  response(200, $user ?? ['error' => 'User not found']);
}

function response(int $code, array $data): never {
  http_response_code($code);
  echo json_encode($data);
  exit;
}
