<?php
require_once __DIR__ . '/../config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create knowledge_base table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(100) NOT NULL,
    information TEXT NOT NULL,
    keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $topic = trim($_POST['topic'] ?? '');
        $information = trim($_POST['information'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');

        if ($topic && $information) {
            $stmt = $conn->prepare("INSERT INTO knowledge_base (topic, information, keywords) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $topic, $information, $keywords);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Knowledge added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add knowledge']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Topic and information are required']);
        }

    } elseif ($action === 'get') {
        $result = $conn->query("SELECT * FROM knowledge_base ORDER BY updated_at DESC");

        $knowledge = [];
        while ($row = $result->fetch_assoc()) {
            $knowledge[] = $row;
        }

        echo json_encode(['success' => true, 'knowledge' => $knowledge]);

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM knowledge_base WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Knowledge deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete']);
            }
            $stmt->close();
        }

    } elseif ($action === 'import') {
        if (!isset($_FILES['dataset_file']) || $_FILES['dataset_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No dataset file uploaded or upload error']);
            exit;
        }

        $filePath = $_FILES['dataset_file']['tmp_name'];
        $fileName = $_FILES['dataset_file']['name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $inserted = 0;

        if ($extension === 'csv') {
            if (($handle = fopen($filePath, 'r')) !== false) {
                $header = fgetcsv($handle);
                if ($header === false) {
                    echo json_encode(['success' => false, 'message' => 'CSV file is empty or invalid']);
                    exit;
                }

                $header = array_map('strtolower', $header);
                $topicIndex = array_search('topic', $header);
                $infoIndex = array_search('information', $header);
                $keywordsIndex = array_search('keywords', $header);

                if ($topicIndex === false || $infoIndex === false) {
                    echo json_encode(['success' => false, 'message' => 'CSV file must include topic and information columns']);
                    exit;
                }

                $stmt = $conn->prepare("INSERT INTO knowledge_base (topic, information, keywords) VALUES (?, ?, ?)");
                while (($row = fgetcsv($handle)) !== false) {
                    $topic = trim($row[$topicIndex] ?? '');
                    $information = trim($row[$infoIndex] ?? '');
                    $keywords = trim($row[$keywordsIndex] ?? '');
                    if ($topic && $information) {
                        $stmt->bind_param('sss', $topic, $information, $keywords);
                        if ($stmt->execute()) {
                            $inserted++;
                        }
                    }
                }
                $stmt->close();
                fclose($handle);
            }

        } elseif ($extension === 'json') {
            $contents = file_get_contents($filePath);
            $data = json_decode($contents, true);
            if (!is_array($data)) {
                echo json_encode(['success' => false, 'message' => 'JSON file format is invalid']);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO knowledge_base (topic, information, keywords) VALUES (?, ?, ?)");
            foreach ($data as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $topic = trim($item['topic'] ?? $item['Topic'] ?? '');
                $information = trim($item['information'] ?? $item['Information'] ?? '');
                $keywords = trim($item['keywords'] ?? $item['Keywords'] ?? '');
                if ($topic && $information) {
                    $stmt->bind_param('sss', $topic, $information, $keywords);
                    if ($stmt->execute()) {
                        $inserted++;
                    }
                }
            }
            $stmt->close();

        } else {
            echo json_encode(['success' => false, 'message' => 'Unsupported file type. Use CSV or JSON.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Dataset imported', 'inserted' => $inserted]);
    }
}

$conn->close();
?>