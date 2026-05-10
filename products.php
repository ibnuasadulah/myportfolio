<?php
// =============================================
// GAMEVAULT — API: Products
// GET /api/products.php
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../core/DB.php';

$cat   = $_GET['cat']    ?? null;
$q     = $_GET['q']      ?? null;
$limit = (int)($_GET['limit'] ?? 20);
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql    = 'SELECT p.*, c.name AS category FROM products p
           JOIN categories c ON c.id = p.category_id
           WHERE p.is_active = 1';
$params = [];

if ($cat) {
  $sql .= ' AND c.slug = ?';
  $params[] = $cat;
}
if ($q) {
  $sql .= ' AND (p.name LIKE ? OR c.name LIKE ?)';
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

$sql .= ' ORDER BY p.sold DESC LIMIT ? OFFSET ?';
$params[] = $limit;
$params[] = $offset;

$rows = DB::fetchAll($sql, $params);
echo json_encode($rows);
