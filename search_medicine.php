<?php
// search_medicine.php - AJAX Endpoint for Medicine Search
include('../db.php');

header('Content-Type: application/json');

// Session security check
session_start();
$keyys_session = isset($_SESSION['reg_keyys']) ? $_SESSION['reg_keyys'] : '';
$sqla = "SELECT * FROM registers WHERE keyys='$keyys_session'";
$resulta = $conn->query($sqla);
$rows = $resulta->fetch_assoc();

if(!($rows && isset($_SESSION['reg_username']) && $_SESSION['reg_username'] == $rows['username'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$query = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

if(strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

// Search medicines from database
$sql = "SELECT DISTINCT medicine_name FROM medicines WHERE medicine_name LIKE '%$query%' ORDER BY medicine_name ASC LIMIT 20";
$result = $conn->query($sql);

$medicines = [];
if($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $medicines[] = [
            "medicine_name" => $row['medicine_name'],
            "display" => $row['medicine_name']
        ];
    }
}

echo json_encode($medicines);
?>
