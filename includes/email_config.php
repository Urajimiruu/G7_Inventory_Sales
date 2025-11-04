<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 🔔 Email address where low stock alerts will be sent
const MAIN_OFFICE_EMAIL = 'vanjorodriguez19@outlook.com'; // can be any address to receive alerts

/**
 * Send a low-stock email using PHPMailer + Gmail SMTP.
 */
function sendLowStockEmail(string $productName, string $branchName, int $qty): void
{
    $mail = new PHPMailer(true);

    try {
        // Debug (optional)
        $mail->SMTPDebug  = 0; // change to 2 for verbose debug output
        $mail->Debugoutput = 'html';

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vanjorodriguezit@gmail.com';     // your Gmail address
        $mail->Password   = 'gtev phci cyfl csep';       // 16-char app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // From / To
        $mail->setFrom('vanjorodriguezit@gmail.com', 'BizzTrack System');
        $mail->addAddress(MAIN_OFFICE_EMAIL, 'Main Office');

        // Email content
        $mail->isHTML(false);
        $mail->Subject = "Low stock alert: $productName - $branchName";
        $mail->Body    = "
Hello Main Office,

The product \"$productName\" at branch \"$branchName\" is LOW ON STOCK.
Current quantity: $qty

Please restock as soon as possible.

This is an automated message from BIZZTRACK.
";

        $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer exception: " . $e->getMessage());
        error_log("Mailer ErrorInfo: " . $mail->ErrorInfo);

    }
}

/**
 * Check if a product just crossed under the low-stock threshold and send email.
 */
function checkLowStockAndNotify(mysqli $conn, int $branchId, int $productId, int $oldQty, int $threshold = 20): void
{
    if ($oldQty < $threshold) {
        return; // Already low before
    }

    $sql = "
        SELECT 
            bi.quantity,
            p.product_name,
            b.branch_name
        FROM branchinventory bi
        JOIN products  p ON bi.product_id = p.product_id
        JOIN branches  b ON bi.branch_id = b.branch_id
        WHERE bi.branch_id = ? AND bi.product_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $branchId, $productId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $newQty      = (int)$row['quantity'];
        $productName = $row['product_name'];
        $branchName  = $row['branch_name'];

        if ($newQty < $threshold) {
            sendLowStockEmail($productName, $branchName, $newQty);
        }
    }

    $stmt->close();
}
