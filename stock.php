<?php
ob_start();
session_start();
include('../db.php'); // Database connection file
$keyys_session = isset($_SESSION['reg_keyys']) ? $_SESSION['reg_keyys'] : '';

// Session security check
$sqla = "SELECT * FROM registers WHERE keyys='$keyys_session'";
$resulta = $conn->query($sqla);
$rows = $resulta->fetch_assoc();

if($rows && isset($_SESSION['reg_username']) && $_SESSION['reg_username'] == $rows['username'] && $_SESSION['reg_role'] == $rows['role'] && $_SESSION['reg_name'] == $rows['name']){

// --- 1. AJAX: LIVE SEARCH & TABLE REFRESH (Bina Page Reload) ---
if (isset($_GET['ajax_med_search'])) {
    $q = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
    
    if ($q !== '') {
        // UPDATED: Query ab medicine_name, med_packing, aur med_unit teeno par search karegi
$sql = "SELECT
            SUM(quantity) as total_qty,
            SUM(total_stock) as sum_stock,
            SUM(amount) as total_amt,
            medicine_name,
            salt_name,
            med_form,
            med_unit,
            med_packing
        FROM medicines
        WHERE medicine_name LIKE '%$q%'
           OR salt_name LIKE '%$q%'
           OR med_packing LIKE '%$q%'
           OR med_unit LIKE '%$q%'
        GROUP BY medicine_name
        ORDER BY id DESC";
    } else {
$sql = "SELECT
            SUM(quantity) as total_qty,
            SUM(total_stock) as sum_stock,
            SUM(amount) as total_amt,
            medicine_name,
            salt_name,
            med_form,
            med_unit,
            med_packing
        FROM medicines
        GROUP BY medicine_name
        ORDER BY id DESC
        LIMIT 10";
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()){
            $med_name      = $row['medicine_name'];
			$salt_name 	   = isset($row['salt_name']) ? $row['salt_name'] : '';
            $total_stock   = $row['sum_stock'];
            $quantity      = $row['total_qty'];
            $amount        = isset($row['total_amt']) ? $row['total_amt'] : '0'; 
            $med_form      = isset($row['med_form']) ? $row['med_form'] : '';
            $med_unit      = isset($row['med_unit']) ? $row['med_unit'] : 'Pcs';
            $med_packing   = isset($row['med_packing']) ? $row['med_packing'] : '';
            
            $stock  = $med_packing * $quantity;
            $stock_style = ($stock <= 15) ? 'stock-low' : 'stock-good';
            
            $details = [];
            if(!empty($med_form)) $details[] = $med_form;
            if(!empty($med_packing)) $details[] = "Pack: " . $med_packing . " " . $med_unit;
            
            $display_details = !empty($details) ? " <small style='color:#6c757d; font-weight:normal; display:block; font-size:12px;'>(" . implode(' | ', $details) . ")</small>" : "";
            
            // Fetch used medicine records to deduct from stock
            $sqluse = "SELECT SUM(quantity), SUM(amount) FROM patient_medicines WHERE medicine_name = '$med_name'";
            $resultuse = $conn->query($sqluse);
            $rowuse = $resultuse->fetch_assoc();
            
            $final_stock  = $total_stock - (isset($rowuse['SUM(quantity)']) ? $rowuse['SUM(quantity)'] : 0);
            $final_amount = $amount - (isset($rowuse['SUM(amount)']) ? $rowuse['SUM(amount)'] : 0);
            
            // Validation: Negative values check
            if($final_stock < 0) $final_stock = 0;
            if($final_amount < 0) $final_amount = 0;
            ?>
            <div class="token-row">
                <div class="token-cell data-name"><?php echo "<strong>".$med_name."</strong>";if(!empty($salt_name)){    echo "<br><small style='color:#0d6efd;font-weight:600;'>Salt : ".$salt_name."</small>";}echo $display_details;?></div>
                <div class="token-cell trader-tag"><?php echo $quantity; ?></div>
                <div class="token-cell"><?php echo $med_packing.' '.$med_unit; ?></div>
                <div class="token-cell"><?php echo $med_form; ?></div>
                <div class="token-cell <?php echo $stock_style; ?>"><?php echo $final_stock.' '.$med_unit; ?></div>
                <div class="token-cell" style="text-align: center;">Rs. <?php echo $final_amount; ?></div>
            </div>
            <?php 
        }
        
        // --- DATABASE SE OVERALL ALL-TIME GRAND TOTAL AMOUNT (For AJAX View) ---
        $sql_all = "SELECT SUM(amount) as grand_amt, medicine_name FROM medicines GROUP BY medicine_name";
        $res_all = $conn->query($sql_all);
        $absolute_total_amount = 0;
        if($res_all && $res_all->num_rows > 0) {
            while($row_all = $res_all->fetch_assoc()) {
                $m_name = $row_all['medicine_name'];
                $m_amt = isset($row_all['grand_amt']) ? $row_all['grand_amt'] : 0;
                $sql_u = "SELECT SUM(amount) as used_amt FROM patient_medicines WHERE medicine_name = '$m_name'";
                $res_u = $conn->query($sql_u)->fetch_assoc();
                $net_amt = $m_amt - (isset($res_u['used_amt']) ? $res_u['used_amt'] : 0);
                if($net_amt > 0) $absolute_total_amount += $net_amt;
            }
        }
        ?>
        <div class="token-row footer-total-row" style="background-color: #e9ecef; font-weight: bold; border-top: 2px solid #0083b0;">
            <div class="token-cell" style="flex: 0 0 85%;">Grand Total Remaining Amount:</div>
            <div class="token-cell" style="flex: 0 0 15%; text-align: center; color: #2a9d8f;">Rs. <?php echo $absolute_total_amount; ?></div>
        </div>
        <?php
    } else {
        echo "<div class='token-row' style='justify-content:center; font-weight:600;'>No matching medicine stock found.</div>";
    }
    exit();
}

// --- 2. AJAX: LIVE UPDATE FOR MEDICINE DATA (Bina Page Reload) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'ajax_update_med') {
    $med_id        = (int)$_POST['med_id'];
    $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $med_packing   = mysqli_real_escape_string($conn, $_POST['med_packing']);
    $med_form      = mysqli_real_escape_string($conn, $_POST['med_form']);
    $med_unit      = mysqli_real_escape_string($conn, $_POST['med_unit']);
    $trader_name   = mysqli_real_escape_string($conn, $_POST['trader_name']);
    $batch_no      = mysqli_real_escape_string($conn, $_POST['batch_no']);
    $expiry_date   = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $quantity      = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount        = mysqli_real_escape_string($conn, $_POST['amount']); 
    $who_update         = $_SESSION['reg_name'];
    $update_time        = date('d-m-Y H:i'); 

    $update_sql = "UPDATE medicines SET 
                    medicine_name = '$medicine_name', 
                    med_packing = '$med_packing', 
                    med_form = '$med_form', 
                    med_unit = '$med_unit', 
                    trader_name = '$trader_name', 
                    batch_no = '$batch_no', 
                    expiry_date = '$expiry_date', 
                    quantity = '$quantity',
                    amount = '$amount',
                    who_update = '$who_update',
                    update_time = '$update_time'
                   WHERE id = $med_id"; 

    if ($conn->query($update_sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Stock updated successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    exit();
}

// --- 3. AJAX: LIVE DELETE FOR MEDICINE (Bina Page Reload) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'ajax_delete_med') {
    $med_id = (int)$_POST['med_id'];
    
    $delete_sql = "DELETE FROM medicines WHERE id = $med_id";
    if ($conn->query($delete_sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Medicine deleted successfully from system!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Stock Management | Ghulam Mustafa Welfare Trust</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-color: #0077b6; --secondary-color: #00b4d8; --dark-color: #1d3557;
            --light-bg: #f8f9fa; --accent-color: #e63946; --text-dark: #2b2d42;
            --text-muted: #6c757d; --warning-color: #ffb703; --success-color: #2a9d8f;
        }
        body { font-family: 'Open Sans', sans-serif; background-color: var(--light-bg); color: var(--text-dark); line-height: 1.6; }
        h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; font-weight: 700; }

        .top-bar { background-color: var(--dark-color); color: white; padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; font-size: 14px; flex-wrap: wrap; border-bottom: 2px solid var(--secondary-color); }
        .top-bar .info-item { display: flex; align-items: center; gap: 8px; }
        .top-bar .badge { background-color: var(--accent-color); padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; text-transform: uppercase; }

        header { background: white; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .logo-container { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .logo-icon { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 10px rgba(0, 119, 182, 0.3); }
        .logo-text h1 { font-size: 20px; color: var(--dark-color); line-height: 1.1; }
        .logo-text span { font-size: 12px; color: var(--primary-color); display: block; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        nav a { text-decoration: none; color: var(--dark-color); margin-left: 24px; font-weight: 600; font-size: 15px; transition: 0.3s; cursor: pointer; padding: 8px 0; }
        nav a:hover, nav a.active { color: var(--primary-color); }

        .action-flex-container { display: flex; justify-content: space-between; align-items: center; gap: 15px; max-width: 90%; margin: 25px auto; flex-wrap: nowrap; }
        .action-search-box { flex: 1; position: relative; min-width: 250px; }
        .action-search-box input { width: 100%; padding: 12px 45px 12px 20px; border: 2px solid #0083b0; border-radius: 50px; font-size: 15px; outline: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .action-search-box i { position: absolute; right: 20px; top: 15px; color: #0083b0; }

        .token-row { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-row { background-color: #0083b0; color: white; font-weight: bold; box-shadow: none; }
        .token-cell { padding: 0 10px; font-size: 14px; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .token-row .token-cell:nth-child(1) { flex: 0 0 25%; white-space: normal; }
        .token-row .token-cell:nth-child(2) { flex: 0 0 15%; }
        .token-row .token-cell:nth-child(3) { flex: 0 0 15%; }
        .token-row .token-cell:nth-child(4) { flex: 0 0 15%; }
        .token-row .token-cell:nth-child(5) { flex: 0 0 15%; font-weight: bold; }
        .token-row .token-cell:nth-child(6) { flex: 0 0 15%; text-align: center; }

        .footer-total-row .token-cell:nth-child(1) { flex: 0 0 85% !important; }
        .footer-total-row .token-cell:nth-child(2) { flex: 0 0 15% !important; }

        .header-row .token-cell { color: white; }
        .data-name { font-weight: 600; color: #0056b3; }
        .trader-tag { color: #6f42c1; font-weight: bold; }
        .stock-good { color: #28a745; }
        .stock-low { color: #dc3545; }

        .pagination-container { display: flex; justify-content: center; align-items: center; margin: 25px 0; gap: 5px; }
        .pagination-link { color: var(--dark-color); padding: 8px 14px; text-decoration: none; background: white; border: 1px solid #ced4da; border-radius: 4px; font-weight: 600; font-size: 14px; transition: 0.3s ease; }
        .pagination-link:hover { background-color: var(--secondary-color); color: white; border-color: var(--secondary-color); }
        .pagination-link.active-page-link { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }

        footer { background: var(--dark-color); color: white; padding: 40px 5% 30px 5%; border-top: 4px solid var(--primary-color); margin-top: 50px; }
        .footer-bottom { text-align: center; font-size: 14px; opacity: 0.8; }

        @media (max-width: 991px) {
            .action-flex-container { flex-direction: column; gap: 12px; width: 100%; padding: 0 15px;}
            .action-search-box { width: 100%; min-width: auto; }
        }
        @media (max-width: 768px) {
            header { flex-direction: column; gap: 20px; text-align: center; }
            nav a { margin: 0 10px; font-size: 14px; }
            .token-row { flex-direction: column; align-items: flex-start; gap: 5px; }
            .token-cell { width: 100% !important; padding: 2px 0; text-align: left !important; }
            .footer-total-row .token-cell:nth-child(1),
            .footer-total-row .token-cell:nth-child(2) { width: 100% !important; flex: unset !important; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="info-item">
            <i class="fa-solid fa-hospital"></i> 
            <span class="badge"><?php echo isset($_SESSION['reg_name']) ? $_SESSION['reg_name'] : 'Guest'; ?></span> 
            Logged in as 
            <span class="badge"><?php echo isset($_SESSION['reg_role']) ? $_SESSION['reg_role'] : 'User'; ?></span> 
        </div>
        <div class="info-item"><i class="fa-solid fa-phone-volume"></i> <?php echo isset($_SESSION['reg_phone']) ? $_SESSION['reg_phone'] : ''; ?></div>
    </div>

    <header>
        <div class="logo-container" onclick="window.location.href = 'index.php';">
            <div class="logo-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <div class="logo-text">
                <h1>Ghulam Mustafa</h1>
                <span>Welfare Trust</span>
            </div>
        </div>
        <nav>
            <a href="index.php">Tokens</a>
            <a href="medicine.php">Medicine</a>
            <a href="stock.php" class="active">Stock</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'insert_med') {
    $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $trader_name   = mysqli_real_escape_string($conn, $_POST['trader_name']);
    $batch_no      = mysqli_real_escape_string($conn, $_POST['batch_no']);
    $expiry_date   = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $quantity      = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount        = mysqli_real_escape_string($conn, $_POST['amount']); 
    $med_form      = mysqli_real_escape_string($conn, $_POST['med_form']);
    $med_unit      = mysqli_real_escape_string($conn, $_POST['med_unit']);
    $med_packing   = mysqli_real_escape_string($conn, $_POST['med_packing']);
    $total_stock   = $quantity * $med_packing ;
    $current_date  = date('Y-m-d'); 

    $sql = "INSERT INTO medicines (date, medicine_name, trader_name, batch_no, expiry_date, quantity, amount, med_form, med_unit, med_packing, total_stock) 
            VALUES ('$current_date', '$medicine_name', '$trader_name', '$batch_no', '$expiry_date', '$quantity', '$amount', '$med_form', '$med_unit', '$med_packing', '$total_stock')";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>window.location.href='medicine.php';</script>";
        exit();
    }
}
?>

<div class="action-flex-container">
    <div class="action-search-box">
        <!-- UPDATED: Placeholder reflecting new criteria -->
        <input type="text" id="medicineSearchInput" placeholder="Search by Medicine Name, Salt Name, Packing or Unit...">
        <i class="fa-solid fa-magnifying-glass"></i>
    </div>
</div>

<?php 
    $limit = 10; 
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    $total_records = $conn->query("SELECT COUNT(DISTINCT medicine_name) as total FROM medicines")->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    $sql = "SELECT SUM(quantity),
SUM(total_stock),
SUM(amount),
medicine_name,
salt_name,
med_form,
med_unit,
med_packing
FROM medicines
GROUP BY medicine_name ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
?>

<div class="token-row header-row">
    <div class="token-cell">Medicine Name</div>
    <div class="token-cell">Quantity</div>
    <div class="token-cell">Packing</div>
    <div class="token-cell">Unit</div>
    <div class="token-cell">Stock</div>
    <div class="token-cell" style="text-align: center;">Amount</div>
</div>

<div id="medicineTableBody">
<?php
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $med_name      = $row['medicine_name'];
		$salt_name 	   = isset($row['salt_name']) ? $row['salt_name'] : '';
        $total_stock   = $row['SUM(total_stock)'];
        $quantity      = $row['SUM(quantity)'];
        $amount        = isset($row['SUM(amount)']) ? $row['SUM(amount)'] : '0'; 
        $med_form      = isset($row['med_form']) ? $row['med_form'] : '';
        $med_unit      = isset($row['med_unit']) ? $row['med_unit'] : 'Pcs';
        $med_packing   = isset($row['med_packing']) ? $row['med_packing'] : '';
        
        $stock  = $med_packing * $quantity;
        $stock_style = ($stock <= 15) ? 'stock-low' : 'stock-good';
        
        $details = [];
        if(!empty($med_form)) $details[] = $med_form;
        if(!empty($med_packing)) $details[] = "Pack: " . $med_packing." ".$row['med_unit'];
        $display_details = !empty($details) ? " <small style='color:#6c757d; font-weight:normal; display:block; font-size:12px;'>(" . implode(' | ', $details) . ")</small>" : "";

        $sqluse = "SELECT SUM(quantity),SUM(amount) FROM patient_medicines WHERE medicine_name = '$med_name'";
        $resultuse = $conn->query($sqluse);
        $rowuse = $resultuse->fetch_assoc();
        
        $final_stock  = $total_stock - (isset($rowuse['SUM(quantity)']) ? $rowuse['SUM(quantity)'] : 0);
        $final_amount = $amount - (isset($rowuse['SUM(amount)']) ? $rowuse['SUM(amount)'] : 0);
        
        // Validation: Negative values check
        if($final_stock < 0) $final_stock = 0;
        if($final_amount < 0) $final_amount = 0;
?>
<div class="token-row">
    <div class="token-cell data-name"><?php echo "<strong>".$med_name."</strong>";if(!empty($salt_name)){    echo "<br><small style='color:#0d6efd;font-weight:600;'>Salt : ".$salt_name."</small>";}echo $display_details;?></div>
    <div class="token-cell trader-tag"><?php echo $quantity; ?></div>
    <div class="token-cell"><?php echo $med_packing.' '.$med_unit; ?></div>
    <div class="token-cell"><?php echo $med_form; ?></div>
    <div class="token-cell <?php echo $stock_style; ?>"><?php echo $final_stock.' '.$med_unit; ?></div>
    <div class="token-cell" style="text-align: center;">Rs. <?php echo $final_amount; ?></div>
</div>
<?php 
    }
    
    // --- DATABASE SE OVERALL ALL-TIME GRAND TOTAL AMOUNT CALCULATION ---
    $sql_all = "SELECT SUM(amount) as grand_amt, medicine_name FROM medicines GROUP BY medicine_name";
    $res_all = $conn->query($sql_all);
    
    $absolute_total_amount = 0;
    
    if($res_all && $res_all->num_rows > 0) {
        while($row_all = $res_all->fetch_assoc()) {
            $m_name = $row_all['medicine_name'];
            $m_amt = isset($row_all['grand_amt']) ? $row_all['grand_amt'] : 0;
            
            $sql_u = "SELECT SUM(amount) as used_amt FROM patient_medicines WHERE medicine_name = '$m_name'";
            $res_u = $conn->query($sql_u)->fetch_assoc();
            
            $net_amt = $m_amt - (isset($res_u['used_amt']) ? $res_u['used_amt'] : 0);
            if($net_amt > 0) {
                $absolute_total_amount += $net_amt;
            }
        }
    }
    
    // Main Page Grand Total Amount Row
    ?>
    <div class="token-row footer-total-row" style="background-color: #e9ecef; font-weight: bold; border-top: 2px solid #0083b0;">
        <div class="token-cell" style="flex: 0 0 85%;">Grand Total Remaining Amount:</div>
        <div class="token-cell" style="flex: 0 0 15%; text-align: center; color: #2a9d8f;">Rs. <?php echo $absolute_total_amount; ?></div>
    </div>
    <?php
} else {
    echo "<div class='token-row' style='justify-content:center; font-weight:600;'>No medicine stock inventory found.</div>";
}
?> 
</div>

<div id="paginationWrapper">
<?php if ($total_pages > 1): ?>
<div class="pagination-container">
    <?php if($page > 1): ?>
        <a href="medicine.php?page=<?php echo ($page - 1); ?>" class="pagination-link">&laquo; Prev</a>
    <?php endif; ?>
    <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <a href="medicine.php?page=<?php echo $i; ?>" class="pagination-link <?php echo ($page == $i) ? 'active-page-link' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if($page < $total_pages): ?>
        <a href="medicine.php?page=<?php echo ($page + 1); ?>" class="pagination-link">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

<footer>
    <div class="footer-bottom">
        <p>&copy; 2026 <strong>ghulammustafatrust.com</strong> | Pharmacy Stock Control Panel. All Rights Reserved.</p>
    </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const mainSearchInput = document.getElementById('medicineSearchInput');
    const tableBody = document.getElementById('medicineTableBody');
    const paginationWrapper = document.getElementById('paginationWrapper');

    function triggerTableFetch() {
        let searchVal = mainSearchInput ? mainSearchInput.value.trim() : '';
        if(searchVal.length > 0) {
            if(paginationWrapper) paginationWrapper.style.display = 'none';
        } else {
            if(paginationWrapper) paginationWrapper.style.display = 'block';
        }
        fetch('?ajax_med_search=1&q=' + encodeURIComponent(searchVal))
            .then(res => res.text())
            .then(htmlOutput => {
                tableBody.innerHTML = htmlOutput;
            });
    }

    if(mainSearchInput) {
        mainSearchInput.addEventListener('input', triggerTableFetch);
    }
});
</script>
</body>
</html>
<?php 
} else {
    header("Location: ../login.php");
    exit();
}
?>