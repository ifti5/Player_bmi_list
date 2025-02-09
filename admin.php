<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bmi_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add player (AJAX request)
if (isset($_POST['ajax']) && $_POST['ajax'] == 'add_player') {
    $name = $conn->real_escape_string($_POST['name']); 
    $position = $conn->real_escape_string($_POST['position']);

    // Check if player exists
    $check_query = "SELECT * FROM players WHERE name='$name'";
    $result = $conn->query($check_query);

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Player name already exists!"]);
    } else {
        $sql = "INSERT INTO players (name, position) VALUES ('$name', '$position')";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Player added successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error: " . $conn->error]);
        }
    }
    exit;
}

// Remove player
if (isset($_POST['remove_player'])) {
    $id = $_POST['player_id'];
    $conn->query("DELETE FROM players WHERE id=$id");
    $conn->query("DELETE FROM bmi_records WHERE player_id=$id");
    echo "<script>alert('Player removed successfully!');</script>";
}

$players = $conn->query("SELECT * FROM players");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-image: url('./background1.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .glass-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        .glass-container h1 {
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="glass-container">
        <h1 class="text-center mb-4">Admin Panel</h1>

        <!-- Add Player Form (Uses AJAX) -->
        <form id="addPlayerForm" class="mb-4">
            <div class="mb-3">
                <input type="text" id="playerName" class="form-control" placeholder="Player Name" required>
            </div>
            <div class="mb-3">
                <input type="text" id="playerPosition" class="form-control" placeholder="Player Position" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Add Player</button>
        </form>

        <!-- Remove Player Form -->
        <form method="post">
            <div class="mb-3">
                <select name="player_id" class="form-control" required>
                    <?php while ($player = $players->fetch_assoc()) { ?>
                        <option value="<?= $player['id'] ?>"><?= $player['name'] ?> (<?= $player['position'] ?>)</option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" name="remove_player" class="btn btn-danger w-100">Remove Player</button>
        </form>
    </div>

    <script>
        // AJAX for adding player
        document.getElementById("addPlayerForm").addEventListener("submit", function(e) {
            e.preventDefault(); // Prevent page reload
            
            let playerName = document.getElementById("playerName").value;
            let playerPosition = document.getElementById("playerPosition").value;
            
            fetch("", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "ajax=add_player&name=" + encodeURIComponent(playerName) + "&position=" + encodeURIComponent(playerPosition)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire("Success", data.message, "success").then(() => location.reload()); // Refresh page to update list
                } else {
                    Swal.fire("Error", data.message, "error"); // Show pop-up without reload
                }
            });
        });
    </script>
</body>
</html>
