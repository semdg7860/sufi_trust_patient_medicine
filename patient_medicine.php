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

// ============================================
// AJAX SEARCH ENDPOINT (Embedded)
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'search_medicine') {
    header('Content-Type: application/json');
    $query = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
    
    if(strlen($query) < 2) {
        echo json_encode([]);
        exit();
    }
    
    // Search medicines from database - only medicine name
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
    exit();
}

// URL se token (patient) ki ID lena
if (isset($_GET['id'])) {
    $token_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Patient ki details nikalna
    $patient_query = "SELECT * FROM tokens WHERE id = '$token_id'";
    $patient_result = $conn->query($patient_query);
    if ($patient_result && $patient_result->num_rows > 0) {
        $patient = $patient_result->fetch_assoc();
    } else {
        echo "Patient not found!";
        exit();
    }
} else {
    echo "No patient selected!";
    exit();
}

// --- AJAX Actions Handler ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type'])) {
    $action = $_POST['action_type'];
    
    // 1. ADD MEDICINE VIA AJAX
    if ($action == 'add_patient_med') {
        $token_id      = mysqli_real_escape_string($conn, $_POST['token_id']);
        $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
        $quantity      = floatval($_POST['quantity']);
        $med_form      = mysqli_real_escape_string($conn, $_POST['med_form']);
        $med_unit      = mysqli_real_escape_string($conn, $_POST['med_unit']);
        $dosage_notes  = mysqli_real_escape_string($conn, $_POST['dosage_notes']);
        $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
        $medicine_price = ($payment_status == 'Free') ? 0 : floatval($_POST['medicine_price']);
        
        $amount = 0;
        $sqladd = "SELECT * FROM medicines WHERE medicine_name = '$medicine_name'";
        $resultadd = $conn->query($sqladd);      
        if($resultadd && $resultadd->num_rows > 0) {
            $rowadd = $resultadd->fetch_assoc();
            $amount = $rowadd['amount'] / $rowadd['quantity'] / $rowadd['med_packing'] * $quantity;
        } 

        $sql = "INSERT INTO patient_medicines (token_id, medicine_name, quantity, med_form, med_unit, dosage_notes, payment_status, medicine_price, amount) 
                VALUES ('$token_id', '$medicine_name', '$quantity', '$med_form', '$med_unit', '$dosage_notes', '$payment_status', '$medicine_price', '$amount')";
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit();
    }
    
    // 2. UPDATE MEDICINE VIA AJAX
    if ($action == 'update_patient_med') {
        $med_id        = mysqli_real_escape_string($conn, $_POST['med_id']);
        $medicine_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
        $quantity      = floatval($_POST['quantity']);
        $med_form      = mysqli_real_escape_string($conn, $_POST['med_form']);
        $med_unit      = mysqli_real_escape_string($conn, $_POST['med_unit']);
        $dosage_notes  = mysqli_real_escape_string($conn, $_POST['dosage_notes']);
        $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
        $medicine_price = ($payment_status == 'Free') ? 0 : floatval($_POST['medicine_price']);
        
        $amount = 0;
        $sqladd = "SELECT * FROM medicines WHERE medicine_name = '$medicine_name'";
        $resultadd = $conn->query($sqladd);      
        if($resultadd && $resultadd->num_rows > 0) {
            $rowadd = $resultadd->fetch_assoc();
            $amount = $rowadd['amount'] / $rowadd['quantity'] / $rowadd['med_packing'] * $quantity;
        } 
        
        $sql = "UPDATE patient_medicines SET 
                medicine_name='$medicine_name', quantity='$quantity', med_form='$med_form', 
                med_unit='$med_unit', dosage_notes='$dosage_notes', payment_status='$payment_status', 
                medicine_price='$medicine_price', amount='$amount' 
                WHERE id='$med_id'";
                
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit();
    }
    
    // 3. DELETE MEDICINE VIA AJAX
    if ($action == 'delete_patient_med') {
        $med_id = mysqli_real_escape_string($conn, $_POST['med_id']);
        $sql = "DELETE FROM patient_medicines WHERE id='$med_id'";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit();
    }

    // 4. FETCH LIVE ROW LIST FOR REFRESH
    if ($action == 'fetch_list') {
        $token_id = mysqli_real_escape_string($conn, $_POST['token_id']);
        $med_list_query = "SELECT * FROM patient_medicines WHERE token_id = '$token_id' ORDER BY id DESC";
        $med_list_result = $conn->query($med_list_query);
        $grand_total = 0;
        $html = '';
        
        if ($med_list_result && $med_list_result->num_rows > 0) {
            while($med_row = $med_list_result->fetch_assoc()) {
                $is_free = ($med_row['payment_status'] == 'Free');
                $grand_total += floatval($med_row['amount']);
                
                $html .= '<div class="token-row" id="row-'.$med_row['id'].'">';
                $html .= '  <div class="token-cell">'.$med_row['medicine_name'].'</div>';
                $html .= '  <div class="token-cell">'.$med_row['med_form'].'</div>';
                $html .= '  <div class="token-cell" style="color: var(--primary-color); text-align: center; font-weight: bold;">'.$med_row['quantity'].'</div>';
                $html .= '  <div class="token-cell">'.$med_row['med_unit'].'</div>';
                $html .= '  <div class="token-cell '.($is_free ? 'status-free' : 'status-paid').'">'.$med_row['payment_status'].'</div>';
                $html .= '  <div class="token-cell">'.($is_free ? 'Rs 0' : 'Rs ' . $med_row['medicine_price']).'</div>';
                $html .= '  <div class="token-cell" style="color: #555; font-style: italic;">'.$med_row['dosage_notes'].'</div>';
                $html .= '  <div class="token-cell" style="text-align: right;">Rs. '.number_format($med_row['amount'], 2).'</div>';
                
                // Action Icon trigger to Modal
                $html .= '  <div class="token-cell action-cell" style="text-align: right;">
                                <button type="button" class="btn-action-icon" onclick=\'openEditModal('.json_encode($med_row).')\' title="Edit/Manage" style="background:none; border:none; color:var(--primary-color); cursor:pointer;">
                                    <i class="fa-solid fa-edit"></i>
                                </button>
                            </div>';
                $html .= '</div>';
            }
            
            // Grand Total Row
            $html .= '<div class="token-row" style="background-color: #e9ecef; border-top: 2px solid var(--dark-color); margin-top: 10px; padding: 15px 10px;">
                        <div class="token-cell" style="flex: 0 0 88%; font-weight: 700; text-align: right; color: var(--dark-color); font-size: 14px;">
                            <i class="fa-solid fa-calculator"></i> Grand Total Amount:
                        </div>
                        <div class="token-cell" style="flex: 0 0 12%; font-weight: 700; text-align: right; color: var(--accent-color); font-size: 15px;">
                            Rs. '.number_format($grand_total, 2).'
                        </div>
                      </div>';
        } else {
            $html = "<div class='token-row' style='justify-content:center; font-weight:600; color:var(--text-muted);'>No medicine issued to this patient yet.</div>";
        }
        echo json_encode(["status" => "success", "html" => $html]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Medicine to Patient | Ghulam Mustafa Trust</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-color: #0077b6;
            --secondary-color: #00b4d8;
            --dark-color: #1d3557;
            --light-bg: #f8f9fa;
            --accent-color: #e63946;
            --text-dark: #2b2d42;
            --text-muted: #6c757d;
            --success-color: #2a9d8f;
        }

        body { font-family: 'Open Sans', sans-serif; background-color: var(--light-bg); color: var(--text-dark); line-height: 1.6; }
        h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; font-weight: 700; }

        /* Top Bar & Header */
        .top-bar { background-color: var(--dark-color); color: white; padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; font-size: 14px; border-bottom: 2px solid var(--accent-color); }
        .top-bar .badge { background-color: var(--accent-color); padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; }
        
        header { background: white; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 100; }
        .logo-container { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .logo-icon { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .logo-text h1 { font-size: 20px; color: var(--dark-color); line-height: 1.1; }
        .logo-text span { font-size: 12px; color: var(--primary-color); display: block; font-weight: 600; text-transform: uppercase; }
        nav a { text-decoration: none; color: var(--dark-color); margin-left: 24px; font-weight: 600; font-size: 15px; cursor: pointer; }

        /* Patient Info Card */
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .patient-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; border-left: 5px solid var(--primary-color); }
        .patient-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px; }
        .info-block strong { color: var(--dark-color); display: block; font-size: 13px; text-transform: uppercase; }
        .info-block span { font-size: 16px; color: #333; font-weight: 600; }

        /* Forms & Layout */
        .grid-layout { display: grid; grid-template-columns: 1fr 2.5fr; gap: 25px; }
        @media (max-width: 1024px) { .grid-layout { grid-template-columns: 1fr; } }

        .form-panel { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); height: fit-content; }
        .form-group { margin-bottom: 15px; position: relative; } 
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        
        /* AUTOCOMPLETE STYLES */
        .search-results-box { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            width: 100%; 
            background: white; 
            border: 1px solid #ccc; 
            border-top: none; 
            border-radius: 0 0 4px 4px; 
            max-height: 200px; 
            overflow-y: auto; 
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .search-results-box.show { display: block; }
        .search-item { 
            padding: 12px; 
            cursor: pointer; 
            font-size: 14px; 
            color: #333; 
            border-bottom: 1px solid #f0f0f0; 
            text-align: left;
            transition: all 0.2s;
        }
        .search-item:hover { 
            background-color: #edf6f9; 
            color: var(--primary-color); 
            font-weight: 600;
            padding-left: 15px;
        }
        .search-item:last-child { border-bottom: none; }
        
        .form-row-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        .btn-submit { background-color: var(--success-color); color: white; width: 100%; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 15px; text-transform: uppercase; transition: all 0.3s; }
        .btn-submit:hover { background-color: #218838; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(42, 157, 143, 0.3); }

        /* Fixed Proportional Rows for columns */
        .token-row { display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 12px 10px; margin-bottom: 8px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .header-row { background-color: #0083b0; color: white; font-weight: bold; box-shadow: none; }
        .token-cell { padding: 0 5px; font-size: 13px; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Layout Allocations */
        .token-row .token-cell:nth-child(1) { flex: 0 0 18%; font-weight: 600; color: #0056b3; white-space: normal; }
        .token-row .token-cell:nth-child(2) { flex: 0 0 10%; }
        .token-row .token-cell:nth-child(3) { flex: 0 0 6%; font-weight: bold; text-align: center; }
        .token-row .token-cell:nth-child(4) { flex: 0 0 8%; }
        .token-row .token-cell:nth-child(5) { flex: 0 0 10%; font-weight: 600; }
        .token-row .token-cell:nth-child(6) { flex: 0 0 10%; font-weight: bold; }
        .token-row .token-cell:nth-child(7) { flex: 0 0 18%; white-space: normal; }
        .token-row .token-cell:nth-child(8) { flex: 0 0 10%; font-weight: bold; text-align: right; }
        .token-row .token-cell.action-cell { flex: 0 0 10%; text-align: right; }

        .header-row .token-cell { color: white !important; text-align: left !important; }
        .header-row .token-cell:nth-child(3) { text-align: center !important; }
        .header-row .token-cell:nth-child(8) { text-align: right !important; }
        
        .status-free { color: var(--success-color); font-weight: bold; }
        .status-paid { color: var(--accent-color); font-weight: bold; }
        
        /* Modals */
        .custom-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); animation: fadeIn 0.25s ease; position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        #deleteConfirmationModal { z-index: 1100; background: rgba(0,0,0,0.7); }
        #deleteConfirmationModal .modal-content { max-width: 400px; text-align: center; }

        .btn-modal-save { background: var(--success-color); color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-modal-delete { background: var(--accent-color); color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-modal-close { background: #ccc; color: #333; padding: 10px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        
        .btn-action-icon { font-weight: bold; padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; background: #fff; font-size: 14px; transition: all 0.2s; color: var(--primary-color); }
        .btn-action-icon:hover { background: var(--primary-color); color: white !important; }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        footer { background: var(--dark-color); color: white; padding: 20px 5%; margin-top: 50px; text-align: center; font-size: 14px; opacity: 0.8; }
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
            <div class="logo-text"><h1>Ghulam Mustafa</h1><span>Welfare Trust</span></div>
        </div>
        <nav>
            <a href="index.php" class="active">Tokens</a>
            <a href="medicine.php">Medicine</a>
            <a href="stock.php">Stock</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        
        <div class="patient-card">
            <h3 style="color: var(--dark-color);"><i class="fa-solid fa-user-check"></i> Patient Information</h3>
            <div class="patient-grid">
                <div class="info-block"><strong>Token ID & Name:</strong><span>#<?php echo $patient['id'] . " - " . $patient['patient_fullname']; ?></span></div>
                <div class="info-block"><strong>Assigned Doctor:</strong><span style="color: #28a745;"><?php echo $patient['doctor_for_patient']; ?></span></div>
                <div class="info-block"><strong>Age & Phone:</strong><span><?php echo $patient['patient_age']; ?> Yrs | <?php echo $patient['patient_phone']; ?></span></div>
                <div class="info-block"><strong>Address:</strong><span><?php echo $patient['patient_address']; ?></span></div>
            </div>
        </div>

        <div class="grid-layout">
            
            <div class="form-panel">
                <h4 style="margin-bottom: 15px; color: var(--dark-color);"><i class="fa-solid fa-pills"></i> Add Medicine</h4>
                <form id="addMedicineForm" autocomplete="off">
                    <input type="hidden" name="action_type" value="add_patient_med">
                    <input type="hidden" name="token_id" value="<?php echo $token_id; ?>">

                    <div class="form-group" style="position: relative;">
                        <label>Medicine Name:</label>
                        <input type="text" name="medicine_name" id="medicine_name_add" placeholder="Type 2-3 letters to search..." required autocomplete="off">
                        <div id="medicine_suggestions_add" class="search-results-box"></div>
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

                    <div class="form-row-inner">
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="payment_status" id="payment_status" onchange="togglePriceField()">
                                <option value="Free">Free Medicine</option>
                                <option value="Paid">Paid Medicine</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price (Rs):</label>
                            <input type="number" name="medicine_price" id="medicine_price" value="0" min="0" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Quantity to Give:</label>
                        <input type="number" name="quantity" min="1" placeholder="e.g. 10" required>
                    </div>

                    <div class="form-group">
                        <label>Dosage Instruction / Notes:</label>
                        <input type="text" name="dosage_notes" placeholder="e.g. 1+0+1 (Subah aur Shaam)">
                    </div>

                    <button type="submit" class="btn-submit">Add to List</button>
                </form>
            </div>

            <div style="overflow-x: auto;">
                <h4 style="margin-bottom: 15px; color: var(--dark-color);"><i class="fa-solid fa-list-check"></i> Issued Medicines List</h4>
                
                <div class="token-row header-row">
                    <div class="token-cell">Medicine Name</div>
                    <div class="token-cell">Type</div>
                    <div class="token-cell" style="text-align: center;">Qty</div>
                    <div class="token-cell">Unit</div>
                    <div class="token-cell">Status</div>
                    <div class="token-cell">Charged</div>
                    <div class="token-cell">Dosage Notes</div>
                    <div class="token-cell" style="text-align: right;">Amount</div>
                    <div class="token-cell action-cell" style="text-align: right; color:white;">Actions</div>
                </div>

                <div id="medicineListContainer">
                    <!-- Data populated live dynamically -->
                </div>
            </div>

        </div>
    </div>

    <!-- MAIN EDIT MEDICINE POPUP MODAL -->
    <div class="custom-modal" id="editMedicineModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: var(--dark-color);"><i class="fa-solid fa-sliders"></i> Manage Record</h3>
                <span style="cursor:pointer; font-size:20px; font-weight:bold;" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editMedicineForm">
                <input type="hidden" name="action_type" value="update_patient_med">
                <input type="hidden" name="med_id" id="edit_med_id">
                
                <div class="form-group" style="position: relative;">
                    <label>Medicine Name:</label>
                    <input type="text" name="medicine_name" id="edit_medicine_name" placeholder="Type to search..." required autocomplete="off">
                    <div id="medicine_suggestions_edit" class="search-results-box"></div>
                </div>
                
                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Form Type:</label>
                        <select name="med_form" id="edit_med_form">
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
                        <label>Measuring Unit:</label>
                        <select name="med_unit" id="edit_med_unit">
                            <option value="Tablet">Tablet (Solid)</option>
                            <option value="Ml">Ml (Liquid)</option>
                            <option value="Pc(s)">Pc (Pieces)</option>
                            <option value="Dripset">Dripset</option>
                            <option value="Vial">Vial / Ampoule</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-inner">
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="payment_status" id="edit_payment_status" onchange="toggleModalPriceField()">
                            <option value="Free">Free Medicine</option>
                            <option value="Paid">Paid Medicine</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (Rs):</label>
                        <input type="number" name="medicine_price" id="edit_medicine_price" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Quantity to Give:</label>
                    <input type="number" name="quantity" id="edit_quantity" min="1" required>
                </div>

                <div class="form-group">
                    <label>Dosage Instruction / Notes:</label>
                    <input type="text" name="dosage_notes" id="edit_dosage_notes">
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; gap:10px;">
                    <button type="button" class="btn-modal-delete" onclick="openDeleteConfirmation()"><i class="fa-solid fa-trash"></i> Delete</button>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn-modal-close" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn-modal-save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div class="custom-modal" id="deleteConfirmationModal">
        <div class="modal-content">
            <h3 style="color:var(--accent-color); margin-bottom:12px;"><i class="fa-solid fa-triangle-exclamation"></i> Are you sure?</h3>
            <p style="font-size:14px; color:#555; margin-bottom:20px;">Kya aap is medicine ko waqai list se remove karna chahte hain? Yeh live delete ho jayegi.</p>
            <div style="display:flex; justify-content:center; gap:15px;">
                <button type="button" class="btn-modal-close" onclick="closeDeleteConfirmation()">No, Cancel</button>
                <button type="button" class="btn-modal-delete" id="btnFinalDeleteConfirm" style="background-color: var(--accent-color);">Yes, Confirm Delete</button>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 <strong>ghulammustafatrust.com</strong> | Dedicated to Serving Humanity Freely. All Rights Reserved.</p>
    </footer>

    <script>
    const currentTokenId = "<?php echo $token_id; ?>";
    let activeMedObject = null;

    // ============================================
    // AUTOCOMPLETE SEARCH FUNCTION
    // ============================================
    function setupAutocomplete(inputSelector, suggestionsSelector) {
        const inputElement = document.querySelector(inputSelector);
        const suggestionsBox = document.querySelector(suggestionsSelector);
        
        if (!inputElement || !suggestionsBox) return;

        // On input change
        inputElement.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (query.length >= 2) {
                // Fetch suggestions from backend
                fetch('?action=search_medicine&q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        suggestionsBox.innerHTML = '';
                        
                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.classList.add('search-item');
                                div.textContent = item.medicine_name;
                                
                                div.addEventListener('click', function() {
                                    inputElement.value = item.medicine_name;
                                    suggestionsBox.classList.remove('show');
                                    suggestionsBox.innerHTML = '';
                                });
                                
                                suggestionsBox.appendChild(div);
                            });
                            suggestionsBox.classList.add('show');
                        } else {
                            suggestionsBox.classList.remove('show');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        suggestionsBox.classList.remove('show');
                    });
            } else {
                suggestionsBox.classList.remove('show');
                suggestionsBox.innerHTML = '';
            }
        });

        // On focus - show existing suggestions
        inputElement.addEventListener('focus', function() {
            if (suggestionsBox.innerHTML.trim() !== '') {
                suggestionsBox.classList.add('show');
            }
        });
    }

    // Close suggestions when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.form-group')) {
            document.querySelectorAll('.search-results-box').forEach(box => {
                box.classList.remove('show');
            });
        }
    });

    // Initialize autocomplete for both forms
    setupAutocomplete('#medicine_name_add', '#medicine_suggestions_add');
    setupAutocomplete('#edit_medicine_name', '#medicine_suggestions_edit');

    // ============================================
    // EXISTING FUNCTIONS (Price Field, etc.)
    // ============================================
    function togglePriceField() {
        const status = document.getElementById('payment_status').value;
        const priceField = document.getElementById('medicine_price');
        if (status === 'Free') {
            priceField.value = 0;
            priceField.readOnly = true;
            priceField.style.backgroundColor = '#eee';
        } else {
            priceField.value = '';
            priceField.readOnly = false;
            priceField.style.backgroundColor = '#fff';
            priceField.focus();
        }
    }

    function toggleModalPriceField() {
        const status = document.getElementById('edit_payment_status').value;
        const priceField = document.getElementById('edit_medicine_price');
        if (status === 'Free') {
            priceField.value = 0;
            priceField.readOnly = true;
            priceField.style.backgroundColor = '#eee';
        } else {
            priceField.readOnly = false;
            priceField.style.backgroundColor = '#fff';
        }
    }

    // Fetch Live List Function via AJAX
    function fetchMedicineList() {
        const formData = new FormData();
        formData.append('action_type', 'fetch_list');
        formData.append('token_id', currentTokenId);

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('medicineListContainer').innerHTML = data.html;
            }
        });
    }

    // Add Medicine submission via AJAX
    document.getElementById('addMedicineForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                this.reset();
                togglePriceField();
                document.querySelector('#medicine_suggestions_add').classList.remove('show');
                fetchMedicineList();
            } else {
                alert("Error adding row: " + data.message);
            }
        });
    });

    // Open Main Edit Modal popup
    function openEditModal(medObj) {
        activeMedObject = medObj;
        document.getElementById('edit_med_id').value = medObj.id;
        document.getElementById('edit_medicine_name').value = medObj.medicine_name;
        document.getElementById('edit_med_form').value = medObj.med_form;
        document.getElementById('edit_med_unit').value = medObj.med_unit;
        document.getElementById('edit_payment_status').value = medObj.payment_status;
        document.getElementById('edit_medicine_price').value = medObj.medicine_price;
        document.getElementById('edit_quantity').value = medObj.quantity;
        document.getElementById('edit_dosage_notes').value = medObj.dosage_notes;
        
        toggleModalPriceField();
        document.getElementById('editMedicineModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editMedicineModal').style.display = 'none';
    }

    // Save Changes from Modal Popup via AJAX
    document.getElementById('editMedicineForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                closeEditModal();
                document.querySelector('#medicine_suggestions_edit').classList.remove('show');
                fetchMedicineList();
            } else {
                alert("Error updating: " + data.message);
            }
        });
    });

    // Delete Confirmation Triggers
    function openDeleteConfirmation() {
        document.getElementById('deleteConfirmationModal').style.display = 'flex';
    }

    function closeDeleteConfirmation() {
        document.getElementById('deleteConfirmationModal').style.display = 'none';
    }

    // Execution of final delete after confirmation
    document.getElementById('btnFinalDeleteConfirm').addEventListener('click', function() {
        if(!activeMedObject) return;
        
        const formData = new FormData();
        formData.append('action_type', 'delete_patient_med');
        formData.append('med_id', activeMedObject.id);

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            closeDeleteConfirmation();
            if(data.status === 'success') {
                closeEditModal();
                fetchMedicineList();
            } else {
                alert("Error deleting: " + data.message);
            }
        });
    });

    window.onload = function() {
        togglePriceField();
        fetchMedicineList();
    };
    </script>

</body>
</html>
<?php 
} else {
    header("Location: ../login.php");
    exit();
}
?>
