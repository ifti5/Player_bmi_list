<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bmi_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once('tcpdf/tcpdf.php');

function calculateBMI($height, $weight) {
    $height_m = ($height * 30.48) / 100;
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

if (isset($_POST['save_bmi'])) {
    $player_id = $_POST['player_id'];
    $date = $_POST['date'];
    $height = $_POST['height'];
    $weight = $_POST['weight'];

    list($bmi, $comment) = calculateBMI($height, $weight);
    $height_cm = $height * 30.48;

    $existing_record = $conn->query("SELECT id FROM bmi_records WHERE player_id = $player_id AND date = '$date'");
    
    if ($existing_record->num_rows > 0) {
        $conn->query("UPDATE bmi_records SET height = $height_cm, weight = $weight, bmi = $bmi, comment = '$comment' WHERE player_id = $player_id AND date = '$date'");
    } else {
        $conn->query("INSERT INTO bmi_records (player_id, date, height, weight, bmi, comment) VALUES ($player_id, '$date', $height_cm, $weight, $bmi, '$comment')");
    }

    echo "<script>Swal.fire('Success', 'BMI record saved successfully!', 'success');</script>";
}

if (isset($_POST['delete_bmi'])) {
    $record_id = $_POST['record_id'];
    $conn->query("DELETE FROM bmi_records WHERE id = $record_id");
    echo "<script>Swal.fire('Deleted', 'BMI record deleted successfully!', 'success');</script>";
}

// Generate PDF report
if (isset($_POST['generate_pdf'])) {
    $selected_date = $_POST['filter_date'];
    $bmi_records = $conn->query("SELECT p.name, p.position, b.* FROM bmi_records b JOIN players p ON p.id = b.player_id WHERE b.date = '$selected_date' ORDER BY b.id DESC");

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 10, 10); // Set margins (left, top, right)
    $pdf->AddPage();

    // Set font for text (h2 equivalent size)
    $pdf->SetFont('helvetica', 'B', 14); // Set a smaller font size for the header

    // Set logo image (normal size)
    $logoPath = './logo.jpg';
    $logoWidth = 15; // Adjust the width of the logo to make it smaller
    $logoHeight = 10; // Auto scale the height proportionally
    $pdf->Image($logoPath, 12, 10, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);

    // Add the club name next to the logo (centered)
    $pdf->SetXY(0, 2); // Adjust position after the logo
    $pdf->SetFont('helvetica', 'C', 14); // Match the font size for club name
    $pdf->Cell(0, 10, "BFF Elite Club", 0, 1, 'C', false); // Centered text

    // Add the report title
    $pdf->SetFont('helvetica', '', 13);
    $pdf->Cell(0, 15, "BMI Report - " . date('F j, Y', strtotime($selected_date)), 0, 1, 'C',false);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10); // Smaller font for the header
    $pdf->Cell(25, 10, 'Player Name', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Position', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Height', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Weight', 1, 0, 'C');
    $pdf->Cell(25, 10, 'BMI', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Prev. BMI', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Status', 1, 1, 'C');

    // Reset font for table data
    $pdf->SetFont('helvetica', '', 10); // Smaller font for the data

    // Initialize row counter
    $rowCount = 0;

    // Add the BMI records to the table
    while ($row = $bmi_records->fetch_assoc()) {
        if ($rowCount == 20) {
            // Add a new page if we have reached 20 rows
            $pdf->AddPage();
            // Re-add the header and logo
            $pdf->Image($logoPath, 15, 15, $logoWidth, $logoHeight, '', '', 'T', false, 300, '', false, false, 0);
            $pdf->SetXY(0, 15);
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, "BFF Elite Club", 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 20, "BMI Report - " . date('F j, Y', strtotime($selected_date)), 0, 1, 'C');
            // Re-add the table header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(25, 10, 'Player Name', 1, 0, 'C');
            $pdf->Cell(25, 10, 'Position', 1, 0, 'C');
            $pdf->Cell(25, 10, 'Height', 1, 0, 'C');
            $pdf->Cell(25, 10, 'Weight', 1, 0, 'C');
            $pdf->Cell(25, 10, 'BMI', 1, 0, 'C');
            $pdf->Cell(25, 10, 'Prev. BMI', 1, 0, 'C');
            $pdf->Cell(25, 10, 'Status', 1, 1, 'C');
            $rowCount = 0; // Reset row count for the new page
        }

        $previous_bmi_query = $conn->query("SELECT bmi FROM bmi_records WHERE player_id = {$row['player_id']} AND date < '{$row['date']}' ORDER BY date DESC LIMIT 1");
        $previous_bmi = ($previous_bmi_query-> num_rows > 0) ? number_format($previous_bmi_query->fetch_assoc()['bmi'], 2) : 'N/A';
        $height_feet_inches = convertToFeetInches($row['height']);
        
        $pdf->Cell(25, 10, $row['name'], 1, 0, 'C');
        $pdf->Cell(25, 10, $row['position'], 1, 0, 'C');
        $pdf->Cell(25, 10, $height_feet_inches, 1, 0, 'C');
        $pdf->Cell(25, 10, $row['weight'] . ' kg', 1, 0, 'C');
        $pdf->Cell(25, 10, number_format($row['bmi'], 2), 1, 0, 'C');
        $pdf->Cell(25, 10, $previous_bmi, 1, 0, 'C');
        $pdf->Cell(25, 10, $row['comment'], 1, 1, 'C');

        $rowCount++; // Increment row count
    }

    // Output the PDF
    $pdf->Output("bmi_report.pdf", "D");
    exit;
}

$players = $conn->query("SELECT * FROM players");
$selected_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : date('Y-m-d');

$bmi_records = $conn->query("
    SELECT p.name, p.position, b.*
    FROM bmi_records b
    JOIN players p ON p.id = b.player_id
    WHERE b.date = '$selected_date'
    AND b.id = (SELECT MAX(id) FROM bmi_records WHERE player_id = b.player_id AND date = b.date)
    ORDER BY b.id DESC
");
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

<section style="display: flex; align-items: center; justify-content: center; position: relative;">
    <img style="height: 50px; width: auto; margin-right: 10px;" src="./logo.jpg" alt="BFF Elite Club Logo">
    <h1 class="text-center" style="position: relative;">BFF Elite Club</h1>
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
                <th>Previous BMI</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $serial = 1; while ($row = $bmi_records->fetch_assoc()) { 
                $previous_bmi_query = $conn->query("SELECT bmi FROM bmi_records WHERE player_id = {$row['player_id']} AND date < '{$row['date']}' ORDER BY date DESC LIMIT 1");
                $previous_bmi = ($previous_bmi_query->num_rows > 0) ? number_format($previous_bmi_query->fetch_assoc()['bmi'], 2) : 'N/A';
                $height_feet_inches = convertToFeetInches($row['height']);
            ?>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= $row['name'] ?></td>
                    <td><?= $row['position'] ?></td>
                    <td><?= $height_feet_inches ?></td>
                    <td><?= $row['weight'] ?> kg</td>
                    <td><?= number_format($row['bmi'], 2) ?></td>
                    <td><?= $previous_bmi ?></td>
                    <td><?= $row['comment'] ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="record_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="delete_bmi" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this record?');">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } else { ?>
    <p class="text-center text-danger">No records found for this date.</p>
<?php } ?>
</body>
</html>