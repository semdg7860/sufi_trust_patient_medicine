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
$sql = "SELECT * FROM medicines
        WHERE medicine_name LIKE '%$q%'
        OR salt_name LIKE '%$q%'
        OR trader_name LIKE '%$q%'
        OR batch_no LIKE '%$q%'
        ORDER BY id DESC";
	} else {
        $sql = "SELECT * FROM medicines ORDER BY id DESC LIMIT 10";
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()){
            $med_id        = $row['id'];
            $med_name      = $row['medicine_name'];
			$salt_name	   = $row['salt_name'];
            $trader_name   = $row['trader_name'];
            $batch_no      = $row['batch_no'];
            $expiry_date   = $row['expiry_date'];
            $quantity      = $row['quantity'];
            $amount        = isset($row['amount']) ? $row['amount'] : '0'; 
            $med_form      = isset($row['med_form']) ? $row['med_form'] : '';
            $med_unit      = isset($row['med_unit']) ? $row['med_unit'] : 'Pcs';
            $med_packing   = isset($row['med_packing']) ? $row['med_packing'] : '';
            $med_date      = isset($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '-';
            
            $stock  = $med_packing * $quantity;
            $stock_style = ($stock <= 15) ? 'stock-low' : 'stock-good';
            
            $details = [];
            if(!empty($med_form)) $details[] = $med_form;
            if(!empty($med_packing)) $details[] = "Pack: " . $med_packing . " " . $med_unit;
            
            $display_details = !empty($details) ? " <small style='color:#6c757d; font-weight:normal; display:block; font-size:12px;'>(" . implode(' | ', $details) . ")</small>" : "";
            ?>
            <div class="token-row">
                <div class="token-cell"><?php echo $med_date; ?></div>
                <div class="token-cell data-name"><?php echo $med_name; if(!empty($salt_name)){    echo "<br><small style='color:#009688;font-weight:600;'>Salt : ".$salt_name."</small>";}echo $display_details; ?></div>
                <div class="token-cell trader-tag"><?php echo $trader_name; ?></div>
                <div class="token-cell"><?php echo $batch_no; ?></div>
                <div class="token-cell"><?php echo date('d-m-Y', strtotime($expiry_date)); ?></div>
                <div class="token-cell <?php echo $stock_style; ?>"><?php echo $med_packing.' x '.$quantity.' ('.$stock.' '.$med_unit.')'; ?></div>
                <div class="token-cell" style="font-weight: 600; color: #2b2d42;">Rs. <?php echo number_format($amount, 2); ?></div>
                <div class="token-cell" style="text-align: center;">
                    <button class="btn-edit-token" 
                            data-id="<?php echo $med_id; ?>"
                            data-name="<?php echo $med_name; ?>"
							data-salt="<?php echo htmlspecialchars($row['salt_name']); ?>"
                            data-packing="<?php echo $med_packing; ?>"
                            data-form="<?php echo $med_form; ?>"
                            data-unit="<?php echo $med_unit; ?>"
                            data-trader="<?php echo $trader_name; ?>"
                            data-batch="<?php echo $batch_no; ?>"
                            data-expiry="<?php echo $expiry_date; ?>"
                            data-stock="<?php echo $quantity; ?>"
                            data-amount="<?php echo $amount; ?>">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                </div>
            </div>
            <?php 
        }
    } else {
        echo "<div class='token-row' style='justify-content:center; font-weight:600;'>No matching medicine stock found.</div>";
    }
    exit();
}

// --- 2. AJAX: LIVE UPDATE FOR MEDICINE DATA (Bina Page Reload) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'ajax_update_med') {
    $med_id        = (int)$_POST['med_id'];
    $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
	$salt_name = mysqli_real_escape_string($conn, $_POST['salt_name']);
    $med_packing   = mysqli_real_escape_string($conn, $_POST['med_packing']);
    $med_form      = mysqli_real_escape_string($conn, $_POST['med_form']);
    $med_unit      = mysqli_real_escape_string($conn, $_POST['med_unit']);
    $trader_name   = mysqli_real_escape_string($conn, $_POST['trader_name']);
	$salt_name     = mysqli_real_escape_string($conn, $_POST['salt_name']);
    $batch_no      = mysqli_real_escape_string($conn, $_POST['batch_no']);
    $expiry_date   = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $quantity      = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount        = mysqli_real_escape_string($conn, $_POST['amount']); 
    $who_update         = $_SESSION['reg_name'];
    $update_time        = date('d-m-Y H:i'); 

    $update_sql = "UPDATE medicines SET 
                    medicine_name = '$medicine_name', 
					salt_name = '$salt_name',
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
        body { font-family: 'Open Sans', sans-serif; background-color: var(--light-bg); color: var(--text-dark); line-height: 1.6; padding-bottom: 30px; }
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

        .container-wrapper { max-width: 92%; margin: 0 auto; }

        .action-flex-container { display: flex; justify-content: space-between; align-items: center; gap: 15px; margin: 25px 0; flex-wrap: nowrap; }
        .action-search-box { flex: 1; position: relative; min-width: 250px; }
        .action-search-box input { width: 100%; padding: 12px 45px 12px 20px; border: 2px solid #0083b0; border-radius: 50px; font-size: 15px; outline: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .action-search-box i { position: absolute; right: 20px; top: 15px; color: #0083b0; }

        .btn-attractive-reg { background: linear-gradient(135deg, #00b4db, #0083b0); color: white; font-size: 15px; font-weight: 600; padding: 12px 24px; border: none; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 180, 219, 0.3); transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; letter-spacing: 0.5px; white-space: nowrap; flex-shrink: 0; }
        .btn-attractive-reg:hover { background: linear-gradient(135deg, #0083b0, #00b4db); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 180, 219, 0.5); }
        .btn-stock-view { background: linear-gradient(135deg, #6f42c1, #4a154b); box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3); }
        .btn-stock-view:hover { background: linear-gradient(135deg, #4a154b, #6f42c1); box-shadow: 0 6px 20px rgba(111, 66, 193, 0.5); }

        .btn-edit-token { background: linear-gradient(135deg, #ff9f43, #ee5253); color: white; border: none; padding: 6px 14px; font-size: 13px; font-weight: 600; border-radius: 30px; cursor: pointer; box-shadow: 0 3px 10px rgba(238, 82, 83, 0.25); display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease-in-out; }
        .btn-edit-token:hover { background: linear-gradient(135deg, #ee5253, #ff9f43); transform: scale(1.05); box-shadow: 0 5px 15px rgba(238, 82, 83, 0.45); }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background-color: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 500px; position: relative; box-shadow: 0px 8px 30px rgba(0,0,0,0.3); animation: fadeIn 0.3s ease-in-out; max-height: 90vh; overflow-y: auto; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close-btn { position: absolute; top: 12px; right: 18px; font-size: 28px; cursor: pointer; color: #aaa; line-height: 1; }
        .close-btn:hover { color: #000; }

        .form-group { margin-bottom: 14px; text-align: left; position: relative; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 600; color: #444; font-size: 13px;}
        .form-group input, .form-group select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background-color: #fff; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #0083b0; box-shadow: 0 0 5px rgba(0,131,176,0.2); }
        
        .form-row-inner { display: flex; gap: 15px; }
        .form-row-inner .form-group { flex: 1; }

        .search-results-box { position: absolute; top: 100%; left: 0; width: 100%; background: white; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; max-height: 200px; overflow-y: auto; z-index: 99999; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: none; }
        .search-item { padding: 10px; cursor: pointer; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .search-item:hover { background-color: #edf6f9; color: var(--primary-color); font-weight: 600; }

        .btn-submit { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; box-shadow: 0 4px 10px rgba(40,167,69,0.2); }
        .btn-submit:hover { background: linear-gradient(135deg, #1e7e34, #28a745); }
        .btn-submit-update { background: linear-gradient(135deg, #0083b0, #0056b3); color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; box-shadow: 0 4px 10px rgba(0,131,176,0.2); }
        .btn-submit-update:hover { background: linear-gradient(135deg, #0056b3, #0083b0); }

        .confirm-btn-container { display: flex; gap: 15px; margin-top: 20px; }
        .btn-confirm-yes { background: linear-gradient(135deg, #dc3545, #bd2130); color: white; flex: 1; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; }
        .btn-confirm-no { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; flex: 1; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; }
        .btn-confirm-yes:hover { opacity: 0.9; }
        .btn-confirm-no:hover { opacity: 0.9; }

        /* --- Table Layout System Fixed --- */
        .token-row { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-row { background-color: #0083b0; color: white; font-weight: bold; box-shadow: none; margin-top: 15px;}
        .token-cell { padding: 0 8px; font-size: 14px; color: #333; overflow: hidden; text-overflow: ellipsis; }

        /* Perfectly balancing 8 columns grid percentages */
        .token-row .token-cell:nth-child(1) { flex: 0 0 10%; } /* Date */
        .token-row .token-cell:nth-child(2) { flex: 0 0 22%; } /* Medicine Name */
        .token-row .token-cell:nth-child(3) { flex: 0 0 16%; } /* Trader */
        .token-row .token-cell:nth-child(4) { flex: 0 0 10%; } /* Batch */
        .token-row .token-cell:nth-child(5) { flex: 0 0 10%; } /* Expiry */
        .token-row .token-cell:nth-child(6) { flex: 0 0 14%; font-weight: bold; } /* Stock */
        .token-row .token-cell:nth-child(7) { flex: 0 0 10%; font-weight: 600; } /* Amount */
        .token-row .token-cell:nth-child(8) { flex: 0 0 8%; text-align: center; } /* Action Button */

        .header-row .token-cell { color: white; }
        .data-name { font-weight: 600; color: #0056b3; }
        .trader-tag { color: #6f42c1; font-weight: bold; }
        .stock-good { color: #28a745; }
        .stock-low { color: #dc3545; }

        .pagination-container { display: flex; justify-content: center; align-items: center; margin: 25px 0; gap: 5px; }
        .pagination-link { color: var(--dark-color); padding: 8px 14px; text-decoration: none; background: white; border: 1px solid #ced4da; border-radius: 4px; font-weight: 600; font-size: 14px; transition: 0.3s ease; }
        .pagination-link:hover { background-color: var(--secondary-color); color: white; border-color: var(--secondary-color); }
        .pagination-link.active-page-link { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }

        footer { background: var(--dark-color); color: white; padding: 30px 5%; border-top: 4px solid var(--primary-color); margin-top: 50px; }
        .footer-bottom { text-align: center; font-size: 14px; opacity: 0.8; }

        @media (max-width: 1100px) {
            .token-cell { font-size: 13px; padding: 0 4px; }
        }
        @media (max-width: 991px) {
            .action-flex-container { flex-direction: column; gap: 12px; width: 100%; }
            .action-search-box { width: 100%; min-width: auto; }
            .btn-attractive-reg { width: 100%; justify-content: center; }
        }
        @media (max-width: 768px) {
            header { flex-direction: column; gap: 20px; text-align: center; }
            nav a { margin: 0 10px; font-size: 14px; }
            .token-row { flex-direction: column; align-items: flex-start; gap: 8px; padding: 15px; }
            .header-row { display: none; } /* Mobile par grid headers chupa diye */
            .token-cell { width: 100% !important; padding: 2px 0; text-align: left !important; white-space: normal; }
            .token-cell::before { content: attr(data-label); font-weight: bold; display: inline-block; width: 120px; color: var(--text-muted); }
            .form-row-inner { flex-direction: column; gap: 0; }
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
            <a href="medicine.php" class="active">Medicine</a>
            <a href="stock.php">Stock</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

<div class="container-wrapper">

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'insert_med') {
    $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $salt_name	   = mysqli_real_escape_string($conn, $_POST['salt_name']);
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

$sql = "INSERT INTO medicines
(date, medicine_name, salt_name, trader_name, batch_no, expiry_date, quantity, amount, med_form, med_unit, med_packing, total_stock)

VALUES

('$current_date',
'$medicine_name',
'$salt_name',
'$trader_name',
'$batch_no',
'$expiry_date',
'$quantity',
'$amount',
'$med_form',
'$med_unit',
'$med_packing',
'$total_stock')";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>window.location.href='medicine.php';</script>";
        exit();
    }
}
?>

    <div class="action-flex-container">
        <button onclick="window.location.href='stock.php';" class="btn-attractive-reg btn-stock-view">
            <i class="fa-solid fa-boxes-stacked"></i> View Stock Directory
        </button>
        <div class="action-search-box">
            <input type="text" id="medicineSearchInput" placeholder="Search stock by medicine, supplier or batch...">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <button id="openRegModal" class="btn-attractive-reg">
            + Add Purchased Stock (Traders)
        </button>
    </div>

    <!-- POPUP 1: ADD NEW STOCK MODAL -->
    <div id="regModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-btn" id="closeRegModal">&times;</span>
            <h3 style="margin-top:0; color:#333;">New Purchase Medicine Entry</h3>
            <hr style="border:0; border-top:1px solid #eee; margin-bottom:15px;">
            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="action_type" value="insert_med">
                <div class="form-group">
                    <label>Medicine Name (Brand Name):</label>
                    <input type="text" name="medicine_name" id="medicine_name_input" required placeholder="e.g. Panadol, Amoxil, Flagyl">
                    <div id="suggestions_box" class="search-results-box"></div>
                </div>
				<div class="form-group">
					<label>Salt Name:</label>
						<input type="text" name="salt_name" placeholder="e.g. Paracetamol 500mg">
				</div>

                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Form Type:</label>
                        <select name="med_form">
                            <option value="Tablet">Tablet</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Drips">Drips</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Drops">Drops</option>
                            <option value="Ointment">Ointment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Packing Size:</label>
                        <input type="text" name="med_packing" placeholder="e.g. 120ml, 10's" required>
                    </div>
                    <div class="form-group">
                        <label>Measuring Unit:</label>
                        <select name="med_unit">
                            <option value="Tablet">Tablet (Solid)</option>
                            <option value="Ml">Ml (Liquid)</option>
                            <option value="Pc(s)">Pc (Pieces)</option>
                            <option value="Dripset">Dripset</option>
                            <option value="Vial">Vial / Ampoule</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Trader / Supplier Name:</label>
                    <input type="text" name="trader_name" id="trader_name_input" required placeholder="e.g. Ali Traders">
                    <div id="trader_suggestions_box" class="search-results-box"></div>
                </div>
                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Batch No:</label>
                        <input type="text" name="batch_no" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date:</label>
                        <input type="date" name="expiry_date" required>
                    </div>
                </div>
                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Total Quantity (Purchased Count):</label>
                        <input type="number" name="quantity" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Purchase Amount (Rs.):</label>
                        <input type="number" name="amount" required min="0" placeholder="e.g. 1500" step="0.01">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Add Stock</button>
            </form>
        </div>
    </div>

    <!-- POPUP 2: MEDICINE EDIT / UPDATE MODAL -->
    <div id="updateTokenModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-btn" id="closeUpdateModal">&times;</span>
            <h3 style="margin-top:0; color:#333;"><i class="fa-solid fa-pen-to-square" style="color:#ff9f43;"></i> Update Purchased Stock Inventory</h3>
            <hr style="border:0; border-top:1px solid #eee; margin-bottom:15px;">
            
            <div id="ajax_msg_box" style="display:none; padding:10px; margin-bottom:15px; border-radius:6px; font-weight:600; text-align:center;"></div>
            
            <form id="ajaxUpdateForm" autocomplete="off">
                <input type="hidden" name="action_type" value="ajax_update_med">
                <input type="hidden" name="med_id" id="update_med_id">
                
                <div class="form-group">
                    <label>Medicine Name (Brand Name):</label>
                    <input type="text" name="medicine_name" id="update_med_name" required>
                </div>
                <div class="form-group">
					<label>Salt Name:</label>
						<input type="text" name="salt_name" id="update_salt_name">
				</div>


                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Form Type:</label>
                        <select name="med_form" id="update_med_form">
                            <option value="Tablet">Tablet</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Drips">Drips</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Drops">Drops</option>
                            <option value="Ointment">Ointment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Packing Size:</label>
                        <input type="text" name="med_packing" id="update_med_packing" required>
                    </div>
                    <div class="form-group">
                        <label>Measuring Unit:</label>
                        <select name="med_unit" id="update_med_unit">
                            <option value="Tablet">Tablet (Solid)</option>
                            <option value="Ml">Ml (Liquid)</option>
                            <option value="Pc(s)">Pc (Pieces)</option>
                            <option value="Dripset">Dripset</option>
                            <option value="Vial">Vial / Ampoule</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Trader / Supplier Name:</label>
                    <input type="text" name="trader_name" id="update_trader" required>
                </div>
                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Batch No:</label>
                        <input type="text" name="batch_no" id="update_batch" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date:</label>
                        <input type="date" name="expiry_date" id="update_expiry" required>
                    </div>
                </div>
                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Total Quantity (Purchased Count):</label>
                        <input type="number" name="quantity" id="update_stock" required>
                    </div>
                    <div class="form-group">
                        <label>Purchase Amount (Rs.):</label>
                        <input type="number" name="amount" id="update_amount" required min="0" step="0.01">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit-update">Save Stock Changes</button>
            </form>
            
            <button type="button" id="triggerDeleteModalBtn" style="margin-top: 12px; background: linear-gradient(135deg, #dc3545, #bd2130);" class="btn-submit">Delete From System</button>
        </div>
    </div>

    <!-- POPUP 3: CUSTOM CONFIRMATION DIV -->
    <div id="customConfirmModal" class="modal-overlay" style="background-color: rgba(0, 0, 0, 0.75);">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 45px; color: #dc3545; margin-bottom: 15px;"></i>
            <h3 style="color: #333; margin-bottom: 10px;">Are you sure?</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Do you really want to completely delete this medicine stock? This action cannot be undone.</p>
            
            <form id="ajaxDeleteForm">
                <input type="hidden" name="action_type" value="ajax_delete_med">
                <input type="hidden" name="med_id" id="delete_med_id">
                
                <div class="confirm-btn-container">
                    <button type="button" id="confirmNoBtn" class="btn-confirm-no">No, Cancel</button>
                    <button type="submit" id="confirmYesBtn" class="btn-confirm-yes">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <?php 
        $limit = 10; 
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        $total_records = $conn->query("SELECT COUNT(*) as total FROM medicines")->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);

        $sql = "SELECT * FROM medicines ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $result = $conn->query($sql);
    ?>

    <!-- Balanced 8 Columns Header Row -->
    <div class="token-row header-row">
        <div class="token-cell">Date Added</div>
        <div class="token-cell">Medicine Name</div>
        <div class="token-cell">Trader / Supplier</div>
        <div class="token-cell">Batch No</div>
        <div class="token-cell">Expiry</div>
        <div class="token-cell">Available Stock</div>
        <div class="token-cell">Amount</div>
        <div class="token-cell" style="text-align: center;">Action</div>
    </div>

    <div id="medicineTableBody">
    <?php
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()){
            $med_id        = $row['id'];
            $med_name      = $row['medicine_name'];
			$salt_name	   = $row['salt_name'];
            $trader_name   = $row['trader_name'];
            $batch_no      = $row['batch_no'];
            $expiry_date   = $row['expiry_date'];
            $quantity      = $row['quantity'];
            $amount        = isset($row['amount']) ? $row['amount'] : '0'; 
            $med_form      = isset($row['med_form']) ? $row['med_form'] : '';
            $med_unit      = isset($row['med_unit']) ? $row['med_unit'] : 'Pcs';
            $med_packing   = isset($row['med_packing']) ? $row['med_packing'] : '';
            $med_date      = isset($row['date']) ? date('d-m-Y', strtotime($row['date'])) : '-';
            
            $stock  = $med_packing * $quantity;
            $stock_style = ($stock <= 15) ? 'stock-low' : 'stock-good';
            
            $details = [];
            if(!empty($med_form)) $details[] = $med_form;
            if(!empty($med_packing)) $details[] = "Pack: " . $med_packing." ".$row['med_unit'];
            $display_details = !empty($details) ? " <small style='color:#6c757d; font-weight:normal; display:block; font-size:12px;'>(" . implode(' | ', $details) . ")</small>" : "";
    ?>
    <div class="token-row">
        <div class="token-cell" data-label="Date Added:"><?php echo $med_date; ?></div>
        <div class="token-cell data-name" data-label="Medicine:"><?php echo $med_name; if(!empty($salt_name)){    echo "<br><small style='color:#009688;font-weight:600;'>Salt : ".$salt_name."</small>";}echo $display_details; ?></div>
        <div class="token-cell trader-tag" data-label="Supplier:"><?php echo $trader_name; ?></div>
        <div class="token-cell" data-label="Batch No:"><?php echo $batch_no; ?></div>
        <div class="token-cell" data-label="Expiry:"><?php echo date('d-m-Y', strtotime($expiry_date)); ?></div>
        <div class="token-cell <?php echo $stock_style; ?>" data-label="Stock:"><?php echo $med_packing.' x '.$quantity.' ('.$stock.' '.$med_unit.')'; ?></div>
        <div class="token-cell" data-label="Amount:" style="font-weight: 600; color: #2b2d42;">Rs. <?php echo number_format($amount, 2); ?></div>
        <div class="token-cell" style="text-align: center;">
            <button class="btn-edit-token" 
                    data-id="<?php echo $med_id; ?>"
                    data-name="<?php echo $med_name; ?>"
					data-salt="<?php echo htmlspecialchars($row['salt_name']); ?>"
                    data-packing="<?php echo $med_packing; ?>"
                    data-form="<?php echo $med_form; ?>"
                    data-unit="<?php echo $med_unit; ?>"
                    data-trader="<?php echo $trader_name; ?>"
                    data-batch="<?php echo $batch_no; ?>"
                    data-expiry="<?php echo $expiry_date; ?>"
                    data-stock="<?php echo $quantity; ?>"
                    data-amount="<?php echo $amount; ?>">
                <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
        </div>
    </div>
    <?php 
        }
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

</div> <!-- Wrapper End -->

<footer>
    <div class="footer-bottom">
        <p>&copy; 2026 <strong>ghulammustafatrust.com</strong> | Pharmacy Stock Control Panel. All Rights Reserved.</p>
    </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    function setupLiveSearch(inputElement, boxElement, searchType) {
        if (inputElement && boxElement) {
            inputElement.addEventListener('input', function() {
                let query = this.value.trim();
                if (query.length >= 2) { 
                    fetch('search_medicine_endpoint.php?type=' + searchType + '&q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            boxElement.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(name => {
                                    let div = document.createElement('div');
                                    div.classList.add('search-item');
                                    div.textContent = name;
                                    div.addEventListener('click', function() {
                                        inputElement.value = this.textContent;
                                        boxElement.style.display = 'none';
                                    });
                                    boxElement.appendChild(div);
                                });
                                boxElement.style.display = 'block';
                            } else {
                                boxElement.style.display = 'none';
                            }
                        });
                } else { boxElement.style.display = 'none'; }
            });
        }
    }

    setupLiveSearch(document.getElementById('medicine_name_input'), document.getElementById('suggestions_box'), 'medicine');
    setupLiveSearch(document.getElementById('trader_name_input'), document.getElementById('trader_suggestions_box'), 'trader');

    const openModalBtn = document.getElementById('openRegModal');
    const closeModalBtn = document.getElementById('closeRegModal');
    const modal = document.getElementById('regModal');
    const updateTokenModal = document.getElementById('updateTokenModal');
    const closeUpdateModal = document.getElementById('closeUpdateModal');
    const customConfirmModal = document.getElementById('customConfirmModal');
    const triggerDeleteModalBtn = document.getElementById('triggerDeleteModalBtn');
    const confirmNoBtn = document.getElementById('confirmNoBtn');

    if(openModalBtn) {
        openModalBtn.addEventListener('click', () => modal.style.display = 'flex');
        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
    }

    function rebindEditButtons() {
        document.querySelectorAll('.btn-edit-token').forEach(button => {
            button.onclick = function() {
                document.getElementById('ajax_msg_box').style.display = 'none'; 
                
                const id = this.getAttribute('data-id');
                document.getElementById('update_med_id').value = id;
                document.getElementById('delete_med_id').value = id;
                
                document.getElementById('update_med_name').value = this.getAttribute('data-name');
				document.getElementById('update_salt_name').value =this.getAttribute('data-salt');
                document.getElementById('update_med_packing').value = this.getAttribute('data-packing');
                document.getElementById('update_med_form').value = this.getAttribute('data-form');
                document.getElementById('update_med_unit').value = this.getAttribute('data-unit');
                document.getElementById('update_trader').value = this.getAttribute('data-trader');
                document.getElementById('update_batch').value = this.getAttribute('data-batch');
                document.getElementById('update_expiry').value = this.getAttribute('data-expiry');
                document.getElementById('update_stock').value = this.getAttribute('data-stock');
                document.getElementById('update_amount').value = this.getAttribute('data-amount');

                updateTokenModal.style.display = 'flex';
            };
        });
    }
    rebindEditButtons(); 

    if(closeUpdateModal) {
        closeUpdateModal.addEventListener('click', () => updateTokenModal.style.display = 'none');
    }

    if(triggerDeleteModalBtn) {
        triggerDeleteModalBtn.addEventListener('click', () => {
            updateTokenModal.style.display = 'none'; 
            customConfirmModal.style.display = 'flex'; 
        });
    }

    if(confirmNoBtn) {
        confirmNoBtn.addEventListener('click', () => {
            customConfirmModal.style.display = 'none'; 
            updateTokenModal.style.display = 'flex'; 
        });
    }

    window.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
        if (e.target === updateTokenModal) updateTokenModal.style.display = 'none';
    });

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
                rebindEditButtons(); 
            });
    }

    if(mainSearchInput) {
        mainSearchInput.addEventListener('input', triggerTableFetch);
    }

    // --- AJAX SUBMISSION: SAVE CHANGES ---
    const ajaxUpdateForm = document.getElementById('ajaxUpdateForm');
    if(ajaxUpdateForm) {
        ajaxUpdateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            const msgBox = document.getElementById('ajax_msg_box');

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    msgBox.style.backgroundColor = '#d4edda';
                    msgBox.style.color = '#155724';
                    msgBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + data.message;
                    msgBox.style.display = 'block';
                    
                    triggerTableFetch(); 
                    setTimeout(() => { updateTokenModal.style.display = 'none'; }, 1200);
                } else {
                    msgBox.style.backgroundColor = '#f8d7da';
                    msgBox.style.color = '#721c24';
                    msgBox.innerHTML = 'Error: ' + data.message;
                    msgBox.style.display = 'block';
                }
            });
        });
    }

    // --- AJAX SUBMISSION: DELETE ON CONFIRM ---
    const ajaxDeleteForm = document.getElementById('ajaxDeleteForm');
    if(ajaxDeleteForm) {
        ajaxDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let formData = new FormData(this);
            const msgBox = document.getElementById('ajax_msg_box');

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                customConfirmModal.style.display = 'none'; 
                
                if(data.status === 'success') {
                    updateTokenModal.style.display = 'flex';
                    msgBox.style.backgroundColor = '#f8d7da';
                    msgBox.style.color = '#721c24';
                    msgBox.innerHTML = '<i class="fa-solid fa-trash-can"></i> ' + data.message;
                    msgBox.style.display = 'block';
                    
                    triggerTableFetch(); 
                    
                    setTimeout(() => { updateTokenModal.style.display = 'none'; }, 1300);
                } else {
                    alert("Database Error: " + data.message);
                }
            })
            .catch(err => console.error("Error deleting item:", err));
        });
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