<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . "/../db_connection.php";

$productId = $_GET['product_id'] ?? '';
$fromDate  = $_GET['from_date'] ?? '';
$toDate    = $_GET['to_date'] ?? '';

$sql = "
    SELECT 
        st.transfer_id,
        st.quantity,
        st.transfer_date,
        p.product_name,
        b.branch_name
    FROM stocktransfers st
    JOIN products p ON st.product_id = p.product_id
    JOIN branches b ON st.branch_id = b.branch_id
    WHERE 1=1
";

$params = [];
$types  = "";

/* Filter product */
if ($productId !== "") {
    $sql .= " AND st.product_id = ? ";
    $types .= "i";
    $params[] = (int)$productId;
}

/* Date range */
if ($fromDate !== "") {
    $sql .= " AND st.transfer_date >= ? ";
    $types .= "s";
    $params[] = $fromDate;
}

if ($toDate !== "") {
    $sql .= " AND st.transfer_date <= ? ";
    $types .= "s";
    $params[] = $toDate;
}

$sql .= " ORDER BY st.transfer_date DESC, st.transfer_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='4' style='text-align:center;'>No transfer records found</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td class="right"><?= (int)$row['quantity'] ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td class="right"><?= htmlspecialchars($row['transfer_date']) ?></td>
</tr>
<?php endwhile; ?>
