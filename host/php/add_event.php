<?php
// Load .env file from parent directory
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$host = getenv('POSTGRES_HOST') ?: 'db';
$db = getenv('POSTGRES_DB');
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');
$port = getenv('POSTGRES_PORT') ?: '5432';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Get form data
        $start_datetime = $_POST['start_datetime'];
        $team1_id = $_POST['team1_id'];
        $team2_id = $_POST['team2_id'];
        $venue_id = $_POST['venue_id'];
        $round_id = $_POST['round_id'];
        $status = $_POST['status'];
        $description = $_POST['description'] ?: null;
        $score_first = isset($_POST['score_first']) && $_POST['score_first'] !== '' ? $_POST['score_first'] : null;
        $score_second = isset($_POST['score_second']) && $_POST['score_second'] !== '' ? $_POST['score_second'] : null;

        // Require scores for finished or live events
        if (($status === 'finished' || $status === 'live') && ($score_first === null || $score_second === null)) {
            $error = "You must enter scores for both teams if the event is finished or live.";
        }

        // Server-side validation: check if date is within season range
        $season_label = $_POST['season_label'] ?? null;
        $comp_id = $_POST['comp_id'] ?? null;
        $seasonStmt = $pdo->prepare("SELECT start_date, end_date FROM season WHERE season_label = ? AND _comp_id = ? LIMIT 1");
        $seasonStmt->execute([$season_label, $comp_id]);
        $season = $seasonStmt->fetch(PDO::FETCH_ASSOC);
        if ($season) {
            $startDate = $season['start_date'];
            $endDate = $season['end_date'];
            if ($start_datetime < $startDate || $start_datetime > $endDate) {
                $error = "The date for this event must be between $startDate and $endDate for the selected season.";
            }
        }

        if (!$error) {
            // Insert new event
            $stmt = $pdo->prepare("
                INSERT INTO event (start_datetime, _first_team_id, _second_team_id, _venue_id, _round_id, status, description, score_first, score_second)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$start_datetime, $team1_id, $team2_id, $venue_id, $round_id, $status, $description, $score_first, $score_second]);
            $success = true;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Load teams, venues, competitions, seasons, sports, countries, and rounds for dropdowns
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $teams = $pdo->query("SELECT team_id, display_name, _comp_id FROM team ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);
    $venues = $pdo->query("SELECT venue_id, name FROM venue ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $competitions = $pdo->query("SELECT comp_id, comp_name, _sport_name, _country_code FROM competition ORDER BY comp_name")->fetchAll(PDO::FETCH_ASSOC);
    $seasons = $pdo->query("SELECT season_label, _comp_id, start_date, end_date FROM season ORDER BY season_label DESC")->fetchAll(PDO::FETCH_ASSOC);
    $rounds = $pdo->query("SELECT round_id, round_name, _season_label, _comp_id FROM round ORDER BY _season_label DESC, round_name")->fetchAll(PDO::FETCH_ASSOC);
    $sports = $pdo->query("SELECT sport_name FROM sport ORDER BY sport_name")->fetchAll(PDO::FETCH_ASSOC);
    $countries = $pdo->query("SELECT country_code, country_name FROM country ORDER BY country_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load form data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Event</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f9;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .back-btn {
            padding: 8px 16px;
            background: #007bff;
<div id="season-info" style="display:none;color:#333;background:none;border:none;padding:10px 0 0 0;margin-bottom:0;text-align:center;font-size:1.05em;"></div>
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .back-btn:hover {
            background: #0056b3;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .submit-btn {
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .submit-btn:hover {
            background: #218838;
        }
        .error {
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
        .success {
            color: #28a745;
            margin-top: 10px;
            padding: 15px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Add New Event</h1>
        <a href="index.html" class="back-btn">← Back</a>
    </div>

    <?php if ($success): ?>
        <div class="success">
            Event added successfully! <a href="index.html">Return to timeline</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="add_event.php">
        <div class="form-group">
            <label for="start_datetime">Date & Time:</label>
            <input type="datetime-local" id="start_datetime" name="start_datetime" required>
            <input type="hidden" id="status" name="status" value="scheduled">
            <small id="status-display" style="color: #666; margin-left: 10px;">Status will be set automatically</small>
            <div id="date-caption" style="color:#007bff; font-size:0.95em; margin-top:4px;"></div>
        </div>

        <div id="score-fields" class="form-row" style="display: none;">
            <div class="form-group">
                <label for="score_first">Team 1 Score:</label>
                <input type="number" id="score_first" name="score_first" min="0">
            </div>
            <div class="form-group">
                <label for="score_second">Team 2 Score:</label>
                <input type="number" id="score_second" name="score_second" min="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sport_name">Sport:</label>
                <select id="sport_name" name="sport_name" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo htmlspecialchars($sport['sport_name']); ?>"><?php echo htmlspecialchars($sport['sport_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="country_code">Country:</label>
                <select id="country_code" name="country_code" required>
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo htmlspecialchars($country['country_code']); ?>"><?php echo htmlspecialchars($country['country_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="comp_id">Competition:</label>
                <select id="comp_id" name="comp_id" required disabled>
                    <option value="">Select Competition</option>
                </select>
            </div>
            <div class="form-group">
                <label for="season_label">Season:</label>
                <select id="season_label" name="season_label" required disabled>
                    <option value="">Select Season</option>
                </select>
            </div>
        </div>

        <div class="form-row">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="team1_id">Team 1:</label>
                <select id="team1_id" name="team1_id" required disabled>
                    <option value="">Select Team 1</option>
                </select>
            </div>
            <div class="form-group">
                <label for="team2_id">Team 2:</label>
        <select id="team2_id" name="team2_id" required disabled>
            <option value="">Select Team 2</option>
        </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
        <label for="round_id">Round:</label>
        <select id="round_id" name="round_id" required disabled>
            <option value="">Select Round</option>
        </select>
        <div id="add-round-container" style="display:none; margin-top:8px;">
            <input type="text" id="new-round-name" placeholder="New round name" style="width:60%; padding:6px;">
            <button type="button" id="add-round-btn" style="padding:6px 12px;">Add</button>
        </div>
    </div>
            <div class="form-group">
                <label for="venue_id">Venue:</label>
                <select id="venue_id" name="venue_id" required>
                    <option value="">Select Venue</option>
                    <?php foreach ($venues as $venue): ?>
                        <option value="<?php echo $venue['venue_id']; ?>"><?php echo htmlspecialchars($venue['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description (optional):</label>
            <textarea id="description" name="description" placeholder="Enter event description..."></textarea>
        </div>

        <button type="submit" class="submit-btn">Add Event</button>
    </form>
</div>

<script>
// Store data for JavaScript
const roundsData = <?php echo json_encode($rounds); ?>;
const teamsData = <?php echo json_encode($teams); ?>;
const countriesData = <?php echo json_encode($countries); ?>;
const seasonsData = <?php echo json_encode($seasons); ?>;
const competitionsData = <?php echo json_encode($competitions); ?>;
const sportsData = <?php echo json_encode($sports); ?>;

const infoDiv = document.getElementById('season-info');
const dateCaption = document.getElementById('date-caption');
// Validate event date against season range
const startDatetimeInput = document.getElementById('start_datetime');
const seasonSelect = document.getElementById('season_label');
const form = document.querySelector('form');

function validateSeasonDate() {
    const selectedSeasonLabel = seasonSelect.value;
    const selectedComp = document.getElementById('comp_id').value;
    const selectedDateStr = startDatetimeInput.value;
    let showInfo = false;
    let infoText = '';
    let captionText = '';
    if (selectedSeasonLabel && selectedComp) {
        const selectedSeason = seasonsData.find(s => s.season_label === selectedSeasonLabel && s._comp_id == selectedComp);
        if (selectedSeason) {
            infoText = `The date range for this season is ${selectedSeason.start_date} to ${selectedSeason.end_date}`;
            showInfo = true;
            if (selectedDateStr) {
                const startDate = new Date(selectedSeason.start_date);
                const endDate = new Date(selectedSeason.end_date);
                const chosenDate = new Date(selectedDateStr);
                if (chosenDate < startDate || chosenDate > endDate) {
                    captionText = `Date must be between ${selectedSeason.start_date} and ${selectedSeason.end_date}`;
                }
            }
        }
    }
    infoDiv.textContent = infoText;
    infoDiv.style.display = showInfo ? '' : 'none';
    dateCaption.textContent = captionText;
}

startDatetimeInput.addEventListener('change', validateSeasonDate);
seasonSelect.addEventListener('change', validateSeasonDate);
document.getElementById('comp_id').addEventListener('change', validateSeasonDate);

form.addEventListener('submit', function(e) {
    validateSeasonDate();
    if (dateCaption.textContent) {
        startDatetimeInput.focus();
        e.preventDefault();
        return;
    }
    // Prevent submission if round is '__add_new__'
    const roundSelect = document.getElementById('round_id');
    if (roundSelect && roundSelect.value === '__add_new__') {
        alert('Please finish adding the new round and select it before submitting the form.');
        roundSelect.focus();
        e.preventDefault();
        return;
    }
});

// Auto-set status based on selected date
startDatetimeInput.addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const now = new Date();
    const statusInput = document.getElementById('status');
    const statusDisplay = document.getElementById('status-display');
    // Reset to default if no date selected
    if (!this.value) {
        statusInput.value = 'scheduled';
        statusDisplay.textContent = 'Status will be set automatically';
        return;
    }
    // Calculate difference in hours
    const diffHours = (selectedDate - now) / (1000 * 60 * 60);
    if (diffHours > 1) {
        statusInput.value = 'scheduled';
        statusDisplay.textContent = 'Status: Scheduled';
    } else if (diffHours >= -2) { // Within 2 hours past or future
        statusInput.value = 'live';
        statusDisplay.textContent = 'Status: Live';
    } else {
        statusInput.value = 'final';
        statusDisplay.textContent = 'Status: Final';
    }
    // Show/hide score fields for final or live events
    const scoreFields = document.getElementById('score-fields');
    if (statusInput.value === 'final' || statusInput.value === 'live') {
        scoreFields.style.display = 'flex';
    } else {
        scoreFields.style.display = 'none';
        // Clear score values when hiding
        document.getElementById('score_first').value = '';
        document.getElementById('score_second').value = '';
    }
});

// Enable and filter competition dropdown only when both sport and country are selected
const sportSelect = document.getElementById('sport_name');
const countrySelect = document.getElementById('country_code');
const compSelect = document.getElementById('comp_id');

function updateCompetitionOptions() {
    const selectedSport = sportSelect.value;
    const selectedCountry = countrySelect.value;
    compSelect.innerHTML = '<option value="">Select Competition</option>';
    if (selectedSport && selectedCountry) {
        compSelect.disabled = false;
        const availableCompetitions = competitionsData.filter(comp =>
            comp._sport_name === selectedSport && comp._country_code === selectedCountry
        );
        availableCompetitions.forEach(comp => {
            const option = document.createElement('option');
            option.value = comp.comp_id;
            option.textContent = comp.comp_name;
            compSelect.appendChild(option);
        });
    } else {
        compSelect.disabled = true;
    }
    resetCompetitionSelections();
}

sportSelect.addEventListener('change', updateCompetitionOptions);
countrySelect.addEventListener('change', updateCompetitionOptions);

// Competition selection to populate seasons and teams
document.getElementById('comp_id').addEventListener('change', function() {
    const selectedComp = this.value;
    const seasonSelect = document.getElementById('season_label');
    
    // Clear current options
    seasonSelect.innerHTML = '<option value="">Select Season</option>';
    seasonSelect.disabled = !selectedComp;
    
    if (selectedComp) {
        // Filter seasons for selected competition
        const compSeasons = seasonsData.filter(season => season._comp_id == selectedComp);
        
        // Add matching seasons
        compSeasons.forEach(season => {
            const option = document.createElement('option');
            option.value = season.season_label;
            option.textContent = season.season_label;
            seasonSelect.appendChild(option);
        });
        
        // Also populate teams for this competition
        updateTeamSelections(selectedComp);
    } else {
        // Reset team selections if no competition selected
        resetTeamSelections();
    }
    
    // Reset round selection
    document.getElementById('round_id').innerHTML = '<option value="">Select Round</option>';
    document.getElementById('round_id').disabled = true;
});

// Season selection to populate rounds
const roundSelect = document.getElementById('round_id');
const addRoundContainer = document.getElementById('add-round-container');
const newRoundNameInput = document.getElementById('new-round-name');
const addRoundBtn = document.getElementById('add-round-btn');

document.getElementById('season_label').addEventListener('change', function() {
    const selectedSeason = this.value;
    const selectedComp = document.getElementById('comp_id').value;
    // Clear current options
    roundSelect.innerHTML = '<option value="">Select Round</option>';
    roundSelect.disabled = !(selectedSeason && selectedComp);
    addRoundContainer.style.display = 'none';
    if (selectedSeason && selectedComp) {
        // Filter rounds for selected season and competition
        const seasonRounds = roundsData.filter(round => 
            round._season_label === selectedSeason && round._comp_id == selectedComp
        );
        // Add matching rounds
        seasonRounds.forEach(round => {
            const option = document.createElement('option');
            option.value = round.round_id;
            option.textContent = round.round_name;
            roundSelect.appendChild(option);
        });
        // Add 'Add new round...' option
        const addOption = document.createElement('option');
        addOption.value = '__add_new__';
        addOption.textContent = 'Add new round...';
        roundSelect.appendChild(addOption);
    }
// Show input for new round if 'Add new round...' is selected
roundSelect.addEventListener('change', function() {
    if (this.value === '__add_new__') {
        addRoundContainer.style.display = '';
        newRoundNameInput.value = '';
        newRoundNameInput.focus();
    } else {
        addRoundContainer.style.display = 'none';
    }
});

// Add round via AJAX
addRoundBtn.addEventListener('click', function() {
    const roundName = newRoundNameInput.value.trim();
    const selectedSeason = document.getElementById('season_label').value;
    const selectedComp = document.getElementById('comp_id').value;
    if (!roundName) {
        alert('Please enter a round name.');
        newRoundNameInput.focus();
        return;
    }
    addRoundBtn.disabled = true;
    fetch('insert_round.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ round_name: roundName, _season_label: selectedSeason, _comp_id: selectedComp })
    })
    .then(res => res.json())
    .then(data => {
        addRoundBtn.disabled = false;
        if (data.success && data.round_id) {
            // Add new round to dropdown and select it
            const option = document.createElement('option');
            option.value = data.round_id;
            option.textContent = roundName;
            roundSelect.insertBefore(option, roundSelect.lastElementChild); // before 'Add new round...'
            roundSelect.value = data.round_id;
            addRoundContainer.style.display = 'none';
            // Update roundsData for future use
            roundsData.push({ round_id: data.round_id, round_name: roundName, _season_label: selectedSeason, _comp_id: selectedComp });
        } else {
            alert('Failed to add round: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(() => {
        addRoundBtn.disabled = false;
        alert('Failed to add round (network error).');
    });
});
});

// Function to reset competition-related selections
function resetCompetitionSelections() {
    document.getElementById('season_label').innerHTML = '<option value="">Select Season</option>';
    document.getElementById('season_label').disabled = true;
    document.getElementById('round_id').innerHTML = '<option value="">Select Round</option>';
    document.getElementById('round_id').disabled = true;
    resetTeamSelections();
}

// Function to reset team selections
function resetTeamSelections() {
    document.getElementById('team1_id').innerHTML = '<option value="">Select Team 1</option>';
    document.getElementById('team1_id').disabled = true;
    document.getElementById('team2_id').innerHTML = '<option value="">Select Team 2</option>';
    document.getElementById('team2_id').disabled = true;
}

// Function to update team selections based on competition
function updateTeamSelections(compId) {
    const team1Select = document.getElementById('team1_id');
    const team2Select = document.getElementById('team2_id');
    
    // Clear current options
    team1Select.innerHTML = '<option value="">Select Team 1</option>';
    team2Select.innerHTML = '<option value="">Select Team 2</option>';
    
    const enabled = compId;
    team1Select.disabled = !enabled;
    team2Select.disabled = !enabled;
    
    if (enabled) {
        // Filter teams by competition
        const filteredTeams = teamsData.filter(team => team._comp_id == compId);
        
        // Add matching teams to both dropdowns
        filteredTeams.forEach(team => {
            const option1 = document.createElement('option');
            option1.value = team.team_id;
            option1.textContent = team.display_name;
            team1Select.appendChild(option1);
            
            const option2 = document.createElement('option');
            option2.value = team.team_id;
            option2.textContent = team.display_name;
            team2Select.appendChild(option2);
        });
    }
}

// Prevent selecting the same team for both positions
document.getElementById('team1_id').addEventListener('change', function() {
    const team2Select = document.getElementById('team2_id');
    const selectedTeam1 = this.value;
    
    // Enable/disable options in team2 based on team1 selection
    Array.from(team2Select.options).forEach(option => {
        if (option.value === selectedTeam1 && option.value !== '') {
            option.disabled = true;
            option.style.display = 'none';
        } else {
            option.disabled = false;
            option.style.display = 'block';
        }
    });
    
    // If team2 has the same value as team1, reset it
    if (team2Select.value === selectedTeam1) {
        team2Select.value = '';
    }
});

document.getElementById('team2_id').addEventListener('change', function() {
    const team1Select = document.getElementById('team1_id');
    const selectedTeam2 = this.value;
    
    // Enable/disable options in team1 based on team2 selection
    Array.from(team1Select.options).forEach(option => {
        if (option.value === selectedTeam2 && option.value !== '') {
            option.disabled = true;
            option.style.display = 'none';
        } else {
            option.disabled = false;
            option.style.display = 'block';
        }
    });
    
    // If team1 has the same value as team2, reset it
    if (team1Select.value === selectedTeam2) {
        team1Select.value = '';
    }
});
</script>

</body>
</html>