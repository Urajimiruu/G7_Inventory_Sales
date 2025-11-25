<?php
// modules/main_inventory_fetch.php
require_once __DIR__ . '/../db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// ---------------- PAGINATION ----------------
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// ---------------- FILTERS ----------------
$filters = [];
$types = '';
$params = [];

// Unit filter
if (!empty($_GET['unit'])) {
    $filters[] = "unit = ?";
    $types .= 's';
    $params[] = $_GET['unit'];
}

// Search filter (product name)
if (!empty($_GET['search'])) {
    $filters[] = "product_name LIKE ?";
    $types .= 's';
    $params[] = "%" . $_GET['search'] . "%";
}

// ---------------- WHERE CLAUSE ----------------
$where = '';
if ($filters) $where = 'WHERE ' . implode(' AND ', $filters);

// ---------------- COUNT TOTAL ----------------
$countSql = "SELECT COUNT(*) AS cnt FROM products $where";
$stmt = $conn->prepare($countSql);
if ($types) {
    $bindNames = [];
    $bindNames[] = & $types;
    for ($i=0; $i<count($params); $i++) {
        $bindNames[] = & $params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
}
$stmt->execute();
$res = $stmt->get_result();
$total = ($row = $res->fetch_assoc()) ? (int)$row['cnt'] : 0;
$stmt->close();

// ---------------- FETCH DATA ----------------
$sort = strtoupper($_GET['sort'] ?? 'ASC');
if (!in_array($sort, ['ASC','DESC'])) $sort = 'ASC';

$dataSql = "
    SELECT product_id, product_name, unit, cost_price, selling_price, quantity
    FROM products
    $where
    ORDER BY quantity $sort, product_name ASC
    LIMIT ? OFFSET ?
";

$stmt2 = $conn->prepare($dataSql);

// bind params + limit/offset
$types2 = $types . 'ii';
$bindParams = $params;
$bindParams[] = $limit;
$bindParams[] = $offset;

$bind = [];
$bind[] = & $types2;
for ($i=0; $i<count($bindParams); $i++){
    $bind[] = & $bindParams[$i];
}
call_user_func_array([$stmt2, 'bind_param'], $bind);

$stmt2->execute();
$result = $stmt2->get_result();

$inventory = [];
while ($r = $result->fetch_assoc()) {
    $inventory[] = [
        'product_id' => (int)$r['product_id'],
        'product_name' => $r['product_name'],
        'unit' => $r['unit'],
        'cost_price' => (float)$r['cost_price'],
        'selling_price' => (float)$r['selling_price'],
        'quantity' => (int)$r['quantity'],
    ];
}

$stmt2->close();

// ---------------- OUTPUT JSON ----------------
echo json_encode([
    'success' => true,
    'inventory' => $inventory,
    'total' => $total,
    'limit' => $limit,
    'page' => $page
], JSON_UNESCAPED_UNICODE);
