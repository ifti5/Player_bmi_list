<?php
// Database Connection
$conn = new mysqli('localhost', 'root', '', 'player_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin Panel: Add Player
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_player'])) {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $player_type = $_POST['player_type'];
    
    // Check if player with the same name already exists
    $check_sql = "SELECT * FROM players WHERE name = '$name'";
    $result = $conn->query($check_sql);
    if ($result->num_rows > 0) {
        $message = "Player with this name already exists!";
        $alert_type = "danger";
    } else {
        // Insert new player if not exists
        $sql = "INSERT INTO players (name, position, player_type) VALUES ('$name', '$position', '$player_type')";
        if ($conn->query($sql) === TRUE) {
            $message = "Player added successfully!";
            $alert_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $alert_type = "danger";
        }
    }
}

// Delete Player
if (isset($_POST['delete_player']) && isset($_POST['player_id'])) {
    $delete_id = $_POST['player_id'];
    $delete_sql = "DELETE FROM players WHERE id = $delete_id";
    if ($conn->query($delete_sql) === TRUE) {
        $message = "Player deleted successfully!";
        $alert_type = "success";
    } else {
        $message = "Error deleting player: " . $conn->error;
        $alert_type = "danger";
    }
}

// Fetch Players
$players_sql = "SELECT * FROM players";
$players_result = $conn->query($players_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel: Add Player</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Custom CSS -->
    <style>
        body {
            background-image: url('./background2.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .glass-panel {
            background: rgba(24, 17, 17, 0.39); 
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.36);
        }
        .glass-panel h2 {
            color: white;
        }
        .glass-panel input, .glass-panel button {
            width: 100%;
            margin: 10px 0;
        }
        /* Popup Animation */
        .alert {
            display: none;
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            z-index: 9999;
        }
        .glass-panel input[type="radio"] {
    width: auto;
    display: none; /* Hide default radio button */
}

.glass-panel label.custom-radio {
    position: relative;
    padding-left: 35px;
    cursor: pointer;
    font-size: 18px;
    color: white;
    display: inline-block;
}

.glass-panel label.custom-radio::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #fff;
    background-color: transparent;
    transition: background-color 0.3s, border-color 0.3s;
}

.glass-panel input[type="radio"]:checked + label.custom-radio::before {
    background-color: #007bff; /* Change to your preferred color */
    border-color: #007bff;
}

.glass-panel input[type="radio"]:checked + label.custom-radio::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 50%;
    transform: translateY(-50%);
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: white;
}

.glass-panel label.custom-radio:hover::before {
    background-color: rgba(0, 123, 255, 0.2); /* Change to hover color */
    border-color: #007bff;
}

    </style>
</head>
<body>
    <div class="glass-panel">
        <h2 class="text-center text-white">Add Player</h2>
        
        <!-- Success/Error Message Popup -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $alert_type; ?> fade show" role="alert">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label text-white">Name:</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="position" class="form-label text-white">Position:</label>
                <input type="text" id="position" name="position" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-white">Player Type:</label>
                <div>
                    <input type="radio" id="club_player" name="player_type" value="Club Player" required>
                    <label class="custom-radio" for="club_player">Club Player</label>

                    <input type="radio" id="overseas_player" name="player_type" value="Overseas Player" required>
                    <label class="custom-radio" for="overseas_player">Overseas Player</label>
                </div>
            </div>
            <button type="submit" name="add_player" class="btn btn-primary">Add Player</button>
        </form>

        <!-- Player List and Delete -->
        <h2 class="text-center text-white mt-5">Player List</h2>
        <form method="POST">
            <div class="mb-3">
                <label for="player_id" class="form-label text-white">Select Player to Delete:</label>
                <select name="player_id" class="form-select" required>
                    <option value="">Select a player</option>
                    <?php while ($player = $players_result->fetch_assoc()): ?>
                        <option value="<?= $player['id']; ?>"><?= $player['name']; ?> (<?= $player['position']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" name="delete_player" class="btn btn-danger">Delete Player</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Show popup message if there is one -->
    <script>
        <?php if (isset($message)): ?>
            document.querySelector('.alert').style.display = 'block';
            setTimeout(function() {
                document.querySelector('.alert').style.display = 'none';
            }, 3000); // Hide popup after 3 seconds
        <?php endif; ?>
    </script>
</body>
</html>
