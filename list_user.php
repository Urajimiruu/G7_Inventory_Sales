<?php
require_once "db_connection.php";

// Optional filters from GET parameters
$roleFilter   = $_GET['role'] ?? '';
$branchFilter = $_GET['branch'] ?? '';
$sortOrder    = $_GET['sort'] ?? 'ASC';
$search       = $_GET['search'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 50; // items per page
$offset       = ($page - 1) * $limit;

// Base query
$sqlBase = "FROM Users u
            LEFT JOIN Branches b ON u.branch_id = b.branch_id
            WHERE 1=1";

// Add filters dynamically
$params = [];
$types  = '';
if (!empty($roleFilter)) {
    $sqlBase .= " AND u.role = ?";
    $types .= 's';
    $params[] = $roleFilter;
}
if (!empty($branchFilter)) {
    $sqlBase .= " AND b.branch_id = ?";
    $types .= 's';
    $params[] = $branchFilter;
}
if (!empty($search)) {
    $sqlBase .= " AND (u.username LIKE ? OR b.branch_name LIKE ?)";
    $types .= 'ss';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total rows
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if (!empty($params)) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

// Get paginated rows
$sql = "SELECT u.user_id, u.username, u.role, u.phone_number, b.branch_name $sqlBase ORDER BY u.username $sortOrder LIMIT ? OFFSET ?";
$typesPag = $types . 'ii';
$paramsPag = array_merge($params, [$limit, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

$counter = $offset + 1;
if ($result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?= $counter++ ?></td>
    <td><?= htmlspecialchars($row['username']) ?></td>
    <td><?= strtoupper(htmlspecialchars($row['role'])) ?></td>
    <td><?= htmlspecialchars($row['phone_number']) ?></td>
    <td><?= strtoupper(htmlspecialchars($row['branch_name'] ?? 'N/A')) ?></td>
    <td>
        <button class="btn btn-warning btn-sm"
            onclick="openEditUserModal({
                id: '<?= $row['user_id'] ?>',
                username: '<?= htmlspecialchars($row['username']) ?>',
                phone: '<?= htmlspecialchars($row['phone_number'] ?? '') ?>',
                role: '<?= htmlspecialchars($row['role']) ?>',
                branch: '<?= htmlspecialchars($row['branch_name'] ?? '') ?>'
            })">Edit</button>

        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $row['user_id'] ?>)">Delete</button>
    </td>
</tr>
<?php
    endwhile;
else:
?>
<tr>
    <td colspan="6" style="text-align:center;">No users found.</td>
</tr>
<?php
endif;

// Pagination buttons
if ($totalPages > 1):
?>
<tr>
    <td colspan="6" style="text-align:center;">
        <div class="pagination">
            <?php if ($page > 1): ?>
                <button class="btn btn-primary" onclick="loadTable(<?= $page - 1 ?>)">Prev</button>
            <?php endif; ?>

            <?php
            $window = 2;
            $start = max(1, $page - $window);
            $end = min($totalPages, $page + $window);

            if ($start > 1) {
                echo "<button class='btn btn-secondary' onclick='loadTable(1)'>1</button>";
                if ($start > 2) echo "<span>...</span>";
            }

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? "btn-warning" : "btn-primary";
                echo "<button class='btn $active' onclick='loadTable($i)'>$i</button>";
            }

            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo "<span>...</span>";
                echo "<button class='btn btn-secondary' onclick='loadTable($totalPages)'>$totalPages</button>";
            }

            if ($page < $totalPages) {
                echo "<button class='btn btn-primary' onclick='loadTable(" . ($page + 1) . ")'>Next</button>";
            }
            ?>
        </div>
    </td>
</tr>
<?php
endif;

$stmt->close();
$conn->close();
?>
