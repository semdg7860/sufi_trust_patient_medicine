<?php
include('../db.php'); // Database connection

$suggestions = [];

if (isset($_GET['q']) && isset($_GET['type'])) {
    $q = mysqli_real_escape_string($conn, $_GET['q']);
    $type = $_GET['type'];
    
    if ($type === 'medicine') {
        // Medicine Name ke suggestions lane ke liye query
$query = "SELECT DISTINCT medicine_name
FROM medicines
WHERE medicine_name LIKE '$q%'
OR salt_name LIKE '$q%'
ORDER BY medicine_name ASC
LIMIT 10";

        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
while ($row = $result->fetch_assoc()) {

    if(!empty($row['salt_name'])){
        $suggestions[] = $row['medicine_name']." (".$row['salt_name'].")";
    }else{
        $suggestions[] = $row['medicine_name'];
    }

}
        }
    } elseif ($type === 'trader') {
        // Trader Name ke suggestions lane ke liye query
        $query = "SELECT DISTINCT trader_name FROM medicines WHERE trader_name LIKE '$q%' ORDER BY trader_name ASC LIMIT 10";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['trader_name'];
            }
        }
    }elseif ($type === 'token') {
        // Token Name ke suggestions lane ke liye query
        $query = "SELECT DISTINCT trader_name FROM medicines WHERE trader_name LIKE '$q%' ORDER BY trader_name ASC LIMIT 10";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['trader_name'];
            }
        }
    }
}

// Data ko JSON format mein output dena
header('Content-Type: application/json');
echo json_encode($suggestions);
?>