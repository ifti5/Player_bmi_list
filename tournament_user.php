<?php
session_start();
$conn = new mysqli("localhost", "root", "", "player_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set default page title if not set
if (!isset($_SESSION['page_title'])) {
    $_SESSION['page_title'] = "BFF Elite Club";
}

// Update page title when user submits
if (isset($_POST['set_page_title'])) {
    $_SESSION['page_title'] = $_POST['page_title'];
}

require_once('tcpdf/tcpdf.php');

function calculateBMI($height, $weight) {
    $height_m = $height / 100; // Convert cm to meters
    $bmi = $weight / ($height_m * $height_m);
    $bmi = round($bmi, 2);
    $comment = ($bmi < 18.5) ? "Underweight" : (($bmi < 25) ? "Healthy" : "Overweight");
    return [$bmi, $comment];
}

function convertToFeetInches($height_cm) {
    $inches = round($height_cm / 2.54);
    $feet = intdiv($inches, 12);
    $remaining_inches = $inches % 12;
    return "$feet feet $remaining_inches inches";
}

$page_title = $_SESSION['page_title'];

if (isset($_POST['save_bmi'])) {
    $player_id = $_POST['player_id'];
    $date = $_POST['date'];
    $height = $_POST['height']; // Height is in cm
    $weight = $_POST['weight'];

    list($bmi, $comment) = calculateBMI($height, $weight);

    $existing_record = $conn->query("SELECT id FROM bmi_records WHERE player_id = $player_id AND date = '$date'");
    
    if ($existing_record->num_rows > 0) {
        $conn->query("UPDATE bmi_records SET height = $height, weight = $weight, bmi = $bmi, comment = '$comment' WHERE player_id = $player_id AND date = '$date'");
    } else {
        $conn->query("INSERT INTO bmi_records (player_id, date, height, weight, bmi, comment) VALUES ($player_id, '$date', $height, $weight, $bmi, '$comment')");
    }

    echo "<script>Swal.fire('Success', 'BMI record saved successfully!', 'success');</script>";
}

if (isset($_POST['delete_bmi'])) {
    $record_id = $_POST['record_id'];
    $conn->query("DELETE FROM bmi_records WHERE id = $record_id");
    echo "<script>Swal.fire('Deleted', 'BMI record deleted successfully!', 'success');</script>";
}

if (isset($_POST['generate_pdf'])) {
    $selected_date = $_POST['filter_date'];
    $bmi_records = $conn->query("SELECT p.name, p.position, p.player_type, b.* FROM bmi_records b JOIN players p ON p.id = b.player_id WHERE b.date = '$selected_date' ORDER BY b.id DESC");

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, "$page_title - BMI Report", 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Date: " . date('F j, Y', strtotime($selected_date)), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 10, 'Player Name', 1);
    $pdf->Cell(30, 10, 'Position', 1);
    $pdf->Cell(30, 10, 'Player Type', 1);
    $pdf->Cell(20, 10, 'Height', 1);
    $pdf->Cell(20, 10, 'Weight', 1);
    $pdf->Cell(20, 10, 'BMI', 1);
    $pdf->Cell(30, 10, 'Status', 1, 1);

    $pdf->SetFont('helvetica', '', 10);

    while ($row = $bmi_records->fetch_assoc()) {
        $height_feet_inches = convertToFeetInches($row['height']);
        $pdf->Cell(40, 10, $row['name'], 1);
        $pdf->Cell(30, 10, $row['position'], 1);
        $pdf->Cell(30, 10, $row['player_type'], 1);
        $pdf->Cell(20, 10, $height_feet_inches, 1);
        $pdf->Cell(20, 10, $row['weight'] . ' kg', 1);
        $pdf->Cell(20, 10, number_format($row['bmi'], 2), 1);
        $pdf->Cell(30, 10, $row['comment'], 1, 1);
    }

    $pdf->Output("bmi_report.pdf", "D");
    exit;
}

$players = $conn->query("SELECT * FROM players");
$selected_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : date('Y-m-d');

$bmi_records = $conn->query("SELECT p.name, p.position, p.player_type, b.* FROM bmi_records b JOIN players p ON p.id = b.player_id WHERE b.date = '$selected_date' ORDER BY b.id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="container mt-4">
<form method="post" class="mb-3">
    <div class="row g-3 text-center">
        <div class="col-md-3">
            <select name="player_id" class="form-control">
                <?php while ($player = $players->fetch_assoc()) { ?>
                    <option value="<?= $player['id'] ?>"> <?= $player['name'] ?> </option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="height" placeholder="Height (feet)" class="form-control" required step="0.01">
        </div>
        <div class="col-md-2">
            <input type="number" name="weight" placeholder="Weight (kg)" class="form-control" required step="0.01">
        </div>
        <div class="col-md-2">
            <button type="submit" name="save_bmi" class="btn btn-success w-100">Save BMI</button>
        </div>
    </div>
</form>

<form method="post" class="mb-4 text-center">
    <div class="row g-3">
        <div class="col-md-3">
            <input type="date" name="filter_date" value="<?= $selected_date ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
        <div class="col-md-3">
            <button type="submit" name="generate_pdf" class="btn btn-danger w-100">Generate PDF</button>
        </div>
    </div>
</form>

<form method="post" class="mb-3 text-center">
    <div class="row g-3">
        <div class="col-md-4">
            <input type="text" name="page_title" class="form-control" placeholder="Enter Page Title" value="<?= htmlspecialchars($page_title) ?>" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="set_page_title" class="btn btn-secondary w-100">Set Title</button>
        </div>
    </div>
</form>


<section style="display: flex; align-items: center; justify-content: center;">
    <img style="height: 50px; width: auto; margin-right: 10px;" src="./logo.jpg" alt="BFF Elite Club Logo">
    <h1 class="text-center"><?= htmlspecialchars($page_title) ?></h1>
</section>

<h2 class="text-center">BMI Reports - <?= date('F j, Y', strtotime($selected_date)) ?></h2>



<?php if ($bmi_records->num_rows > 0) { ?>
    <table class="table table-bordered table-hover text-center">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Player</th>
                <th>Position</th>
                <th>Height</th>
                <th>Weight</th>
                <th>BMI</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $serial = 1; while ($row = $bmi_records->fetch_assoc()) { ?>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= $row['name'] ?></td>
                    <td><?= $row['position'] ?></td>
                    <td><?= convertToFeetInches($row['height']) ?></td>
                    <td><?= $row['weight'] ?> kg</td>
                    <td><?= number_format($row['bmi'], 2) ?></td>
                    <td><?= $row['comment'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } else { ?>
    <p class="text-center text-danger">No records found for this date.</p>
<?php } ?>

</body>
</html>
