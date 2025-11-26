<?php
require_once "db_connection.php";

$unit   = $_GET['unit'] ?? '';
$search = $_GET['search'] ?? '';
$sort   = $_GET['sort'] ?? 'ASC';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50; // items per page
$offset = ($page - 1) * $limit;

$unit   = $conn->real_escape_string($unit);
$search = $conn->real_escape_string($search);
$sort   = ($sort === "DESC") ? "DESC" : "ASC";

// --- Count total rows for pagination ---
$countQuery = "SELECT COUNT(*) AS total FROM Products WHERE 1";
if (!empty($unit)) $countQuery .= " AND unit = '$unit'";
if (!empty($search)) $countQuery .= " AND (product_name LIKE '%$search%' OR description LIKE '%$search%' OR selling_price LIKE '%$search%' OR cost_price LIKE '%$search%')";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// --- Fetch paginated rows ---
$query = "SELECT * FROM Products WHERE 1";
if (!empty($unit)) $query .= " AND unit = '$unit'";
if (!empty($search)) $query .= " AND (product_name LIKE '%$search%' OR description LIKE '%$search%' OR selling_price LIKE '%$search%' OR cost_price LIKE '%$search%')";
$query .= " ORDER BY selling_price $sort LIMIT $limit OFFSET $offset";

$result = $conn->query($query);

// --- Output table rows ---
while ($row = $result->fetch_assoc()):
?>
<tr>
    <td class="right"><?= $row['product_id'] ?></td>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td><?= htmlspecialchars($row['description']) ?></td>
    <td><?= htmlspecialchars($row['unit']) ?></td>
    <td class="right">₱<?= number_format($row['cost_price'], 2) ?></td>
    <td class="right">₱<?= number_format($row['selling_price'], 2) ?></td>
    <td>
        <button class="btn btn-warning btn-sm"
            onclick='openEditProductModal(<?= json_encode($row) ?>)'>
            Edit
        </button>
        <button class="btn btn-danger btn-sm"
            onclick="deleteProduct(<?= $row['product_id'] ?>)">
            Delete
        </button>
    </td>
</tr>
<?php endwhile; ?>

<tr>
    <td colspan="8" style="text-align:center;">
        <?php if ($totalPages > 1): ?>
        <div class="pagination">

            <!-- Prev -->
            <?php if ($page > 1): ?>
            <button class="btn btn-primary" onclick="loadProducts(<?= $page - 1 ?>)">Prev</button>
            <?php endif; ?>

            <?php
            $window = 2;
            $start = max(1, $page - $window);
            $end   = min($totalPages, $page + $window);

            if ($start > 1) {
                echo "<button class='btn btn-secondary' onclick='loadProducts(1)'>1</button>";
                if ($start > 2) echo "<span>...</span>";
            }

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? "btn-warning" : "btn-primary";
                echo "<button class='btn $active' onclick='loadProducts($i)'>$i</button>";
            }

            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo "<span>...</span>";
                echo "<button class='btn btn-secondary' onclick='loadProducts($totalPages)'>$totalPages</button>";
            }
            ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <button class="btn btn-primary" onclick="loadProducts(<?= $page + 1 ?>)">Next</button>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </td>
</tr>
