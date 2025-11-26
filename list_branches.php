<?php
require_once "db_connection.php";

$search = $_GET['search'] ?? '';
$search = $conn->real_escape_string($search);

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50; // rows per page
$offset = ($page - 1) * $limit;

// Count total rows
$countQuery = "SELECT COUNT(*) as total FROM Branches WHERE branch_name LIKE '%$search%' OR location LIKE '%$search%'";
$countResult = $conn->query($countQuery);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch current page rows
$query = "SELECT * FROM Branches WHERE branch_name LIKE '%$search%' OR location LIKE '%$search%' 
          ORDER BY branch_id ASC 
          LIMIT $offset, $limit";

$result = $conn->query($query);

// Display rows
while ($row = $result->fetch_assoc()): ?>
<tr>
    <td class="right"><?= $row['branch_id'] ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td><?= htmlspecialchars($row['location']) ?></td>
    <td>
        <button class="btn btn-warning btn-sm" onclick='openEditBranchModal(<?= json_encode($row) ?>)'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick="deleteBranch(<?= $row['branch_id'] ?>)">Delete</button>
    </td>
</tr>
<?php endwhile; ?>

<tr>
    <td colspan="4" style="text-align:center;">
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <button class="btn btn-primary" onclick="loadBranches(<?= $page - 1 ?>)">Prev</button>
                <?php endif; ?>

                <?php
                $window = 2;
                $start = max(1, $page - $window);
                $end   = min($totalPages, $page + $window);

                if ($start > 1) {
                    echo "<button class='btn btn-secondary' onclick='loadBranches(1)'>1</button>";
                    if ($start > 2) echo "<span>...</span>";
                }

                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $page) ? "btn-warning" : "btn-primary";
                    echo "<button class='btn $active' onclick='loadBranches($i)'>$i</button>";
                }

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo "<span>...</span>";
                    echo "<button class='btn btn-secondary' onclick='loadBranches($totalPages)'>$totalPages</button>";
                }

                if ($page < $totalPages) {
                    echo "<button class='btn btn-primary' onclick='loadBranches(" . ($page + 1) . ")'>Next</button>";
                }
                ?>
            </div>
        <?php endif; ?>
    </td>
</tr>
