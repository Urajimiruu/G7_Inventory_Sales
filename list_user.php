<?php
require_once "db_connection.php";

// Optional filters from GET parameters
$roleFilter = $_GET['role'] ?? '';
$branchFilter = $_GET['branch'] ?? '';
$sortOrder = $_GET['sort'] ?? 'ASC';
$search = $_GET['search'] ?? '';

// Base query
$sql = "SELECT 
            u.user_id, 
            u.username, 
            u.role, 
            u.phone_number,
            b.branch_name
        FROM Users u
        LEFT JOIN Branches b ON u.branch_id = b.branch_id
        WHERE 1=1";

// Add filters dynamically
if (!empty($roleFilter)) {
    $sql .= " AND u.role = ?";
}
if (!empty($branchFilter)) {
    $sql .= " AND b.branch_name = ?";
}
if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR b.branch_name LIKE ?)";
}

$sql .= " ORDER BY u.username $sortOrder";

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$params = [];
$types = '';

if (!empty($roleFilter)) {
    $types .= 's';
    $params[] = $roleFilter;
}
if (!empty($branchFilter)) {
    $types .= 's';
    $params[] = $branchFilter;
}
if (!empty($search)) {
    $types .= 'ss';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows > 0):
    $counter = 1;
    while ($row = $result->fetch_assoc()):
?>
        <tr>
            <td><?= $counter++ ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= strtoupper(htmlspecialchars($row['role'])) ?></td>
            <td><?= htmlspecialchars($row['phone_number']) ?></td>
            <td><?= strtoupper(htmlspecialchars($row['branch_name'] ?? 'N/A')) ?></td>

            <td>
                <button type="button" class="btn btn-warning btn-sm"
                    onclick="openEditUserModal({
                        id: '<?= $row['user_id'] ?>',
                        username: '<?= htmlspecialchars($row['username']) ?>',
                        phone: '<?= htmlspecialchars($row['phone_number'] ?? '') ?>',
                        role: '<?= htmlspecialchars($row['role']) ?>',
                        branch: '<?= htmlspecialchars($row['branch_name'] ?? '') ?>'
                    })">
                    Edit
                </button>

                <button type="button" class="btn btn-danger btn-sm"
                    onclick="deleteUser(<?= $row['user_id'] ?>)">
                    Delete
                </button>
            </td>
        </tr>

<?php
    endwhile;
else:
?>
    <tr>
        <td colspan="5">No users found.</td>
    </tr>
<?php
endif;

$stmt->close();
$conn->close();
?>
