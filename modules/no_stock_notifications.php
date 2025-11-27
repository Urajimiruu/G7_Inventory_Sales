<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../db_connection.php";

$role       = $_SESSION['role'] ?? '';
$userBranch = (int)($_SESSION['branch_id'] ?? 0);

/* Base Query – same logic as your inventory code, but ONLY 0 qty */
$sql = "
SELECT 
    b.branch_name,
    p.product_name,
    bi.quantity
FROM BranchInventory bi
JOIN Products p ON bi.product_id = p.product_id
JOIN Branches b ON bi.branch_id = b.branch_id
WHERE bi.quantity = 0
";

/* SHOP ROLE → filter to their own branch only */
$params = [];
$types = "";

if ($role === 'shop') {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $userBranch;
}

$sql .= " ORDER BY b.branch_name ASC, p.product_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* No items */
if ($result->num_rows === 0) {
    echo "<div class='notif-empty'>All stocks are OK ✔</div>";
    exit;
}

$currentBranch = "";
while ($row = $result->fetch_assoc()) {

    if ($currentBranch !== $row['branch_name']) {
        if ($currentBranch !== "") echo "</ul>";
        $currentBranch = $row['branch_name'];

        echo "<h4 class='notif-branch'>" . htmlspecialchars($currentBranch) . "</h4>";
        echo "<ul class='notif-list'>";
    }

    echo "<li class='notif-item'>" . htmlspecialchars($row["product_name"]) . " (No Stock)</li>";
}

echo "</ul>";
