<?PHP
include '../../db/conn.php';
session_start();
ob_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['delete_item_id'])) {
        $deleteID = $data['delete_item_id'];

        try {
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            if ($stmt->execute([$deleteID])) {
                echo json_encode(['success' => true]);
                exit;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Error executing delete statement: " . implode(", ", $errorInfo));
                echo json_encode(['success' => false, 'error' => 'Database error.']);
                exit;
            }
        } catch (PDOException $e) {
            error_log("PDOException: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request data.']);
        exit;
    }
}
