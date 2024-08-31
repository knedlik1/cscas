<?php
session_start();
require 'db.php'; // Ujisti se, že tento soubor je správně nastaven

// Ověření přihlášení
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Zpracování vyhledávání
$officer = null;
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = htmlspecialchars($_GET['search']);
    
    // Příprava a provedení dotazu pro vyhledávání úředníka
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE name LIKE ?");
    $stmt->execute(['%' . $searchTerm . '%']);
    $officer = $stmt->fetch(); // Získáme prvního úředníka
}

// Zpracování dokumentu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitDocument'])) {
    $officerId = $_POST['officer_id'];
    $documentType = htmlspecialchars($_POST['document_type']);
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $signature = htmlspecialchars($_POST['signature']);
    
    try {
        // Uložení dokumentu do databáze
        $stmt = $pdo->prepare("INSERT INTO documents (officer_id, document_type, title, description, signature, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$officerId, $documentType, $title, $description, $signature]);
        
        // Přesměrování zpět na profil po úspěšném uložení
        header("Location: profil.php?search=" . urlencode($officer['name']));
        exit();
    } catch (PDOException $e) {
        echo "Chyba při ukládání dokumentu: " . $e->getMessage();
    }
}

// Zpracování prémií
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitBonus'])) {
    $officerId = $_POST['officer_id'];
    $hours = $_POST['hours'];
    $hodnost = htmlspecialchars($_POST['hodnost']);
    $difficulty = htmlspecialchars($_POST['difficulty']);
    $lifeSaving = htmlspecialchars($_POST['life_saving']);
    $praiseCount = $_POST['praise_count'];
    $reportCount = $_POST['report_count'];
    $totalBonus = $_POST['total_bonus'];

    try {
        // Uložení prémií do databáze
        $stmt = $pdo->prepare("INSERT INTO bonuses (officer_id, hours, hodnost, difficulty, life_saving, praise_count, report_count, total_bonus, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$officerId, $hours, $hodnost, $difficulty, $lifeSaving, $praiseCount, $reportCount, $totalBonus]);

        // Přesměrování zpět na profil po úspěšném uložení
        header("Location: profil.php?search=" . urlencode($officer['name']));
        exit();
    } catch (PDOException $e) {
        echo "Chyba při ukládání prémií: " . $e->getMessage();
    }
}

// Načtení dokumentů a prémií pro daného úředníka, pokud byl nalezen
$documents = [];
$bonuses = [];
if ($officer) {
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE officer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$officer['id']]);
    $documents = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM bonuses WHERE officer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$officer['id']]);
    $bonuses = $stmt->fetchAll();
}

function calculateTotalBonus($hours, $hodnost, $difficulty, $life_saving, $praise_count, $report_count) {
    $base_rate = 100; // Základní sazba za hodinu

    // Určení multiplikátoru podle hodnosti
    switch ($hodnost) {
        case 'low':
            $hodnost_multiplier = 1.0;
            break;
        case 'medium':
            $hodnost_multiplier = 1.5;
            break;
        case 'high':
            $hodnost_multiplier = 2.0;
            break;
        default:
            $hodnost_multiplier = 1.0;
    }

    // Určení multiplikátoru podle náročnosti
    switch ($difficulty) {
        case 'low':
            $difficulty_multiplier = 1.0;
            break;
        case 'medium':
            $difficulty_multiplier = 1.5;
            break;
        case 'high':
            $difficulty_multiplier = 2.0;
            break;
        default:
            $difficulty_multiplier = 1.0;
    }

    // Určení příplatku za záchranu života
    $life_saving_bonus = ($life_saving === 'yes') ? 500 : 0;

    // Výpočet celkové částky
    $total_bonus = ($hours * $base_rate * $hodnost_multiplier * $difficulty_multiplier) + $life_saving_bonus;

    // Přidání dalších bonusů podle počtu pochval a reportů
    $total_bonus += ($praise_count * 20) + ($report_count * 30);

    return $total_bonus;
}

// Zpracování dat po odeslání formuláře
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hours = $_POST['hours'];
    $hodnost = $_POST['hodnost'];
    $difficulty = $_POST['difficulty'];
    $life_saving = $_POST['life_saving'];
    $praise_count = $_POST['praise_count'];
    $report_count = $_POST['report_count'];

    $total_bonus = calculateTotalBonus($hours, $hodnost, $difficulty, $life_saving, $praise_count, $report_count);
    echo "Celková částka prémií: " . $total_bonus;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Profil úředníka</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ccc;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin: 0 20px;
        }

        .admin-button {
            margin-left: auto;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .search-form {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .search-input {
            width: 300px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px 0 0 5px;
            font-size: 16px;
            transition: width 0.3s ease;
        }

        .search-input:focus {
            width: 400px;
            border-color: #4CAF50;
            outline: none;
        }

        .search-button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            font-size: 16px;
        }

        .personal-info, .records {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 45%;
            float: left;
            margin-right: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background-color: #4CAF50; 
            color: white;
        }

        .clear {
            clear: both;
        }

        .modal-overlay {
            display: none; /* Skryté ve výchozím stavu */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Profil úředníka</div>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="dashboard.php" class="admin-button">Admin</a>
        <?php endif; ?>
    </div>

    <form method="GET" action="profil.php" class="search-form">
        <input type="text" name="search" placeholder="Hledej úředníka..." value="<?= $searchTerm ?>" class="search-input" required>
        <button type="submit" class="search-button">Hledat</button>
    </form>

    <?php if ($officer): ?>
        <h2>Profil úředníka: <?= htmlspecialchars($officer['name']) ?></h2>
        <div class="personal-info">
            <table>
                <tr>
                    <th>Volací znak</th>
                    <td><?= htmlspecialchars($officer['call_sign']) ?></td>
                </tr>
                <tr>
                    <th>Badge</th>
                    <td><?= htmlspecialchars($officer['badge_number']) ?></td>
                </tr>
                <tr>
                    <th>Jméno</th>
                    <td><?= htmlspecialchars($officer['name']) ?></td>
                </tr>
                <tr>
                    <th>Datum narození</th>
                    <td><?= htmlspecialchars($officer['date_of_birth']) ?></td>
                </tr>
                <tr>
                    <th>Telefonní číslo</th>
                    <td><?= htmlspecialchars($officer['phone_number']) ?></td>
                </tr>
                <tr>
                    <th>Hodnost</th>
                    <td><?= htmlspecialchars($officer['rank']) ?></td>
                </tr>
                <tr>
                    <th>Datum nástupu</th>
                    <td><?= htmlspecialchars($officer['created_at']) ?></td>
                </tr>
                <tr>
                    <th>Discord</th>
                    <td><?= htmlspecialchars($officer['discord']) ?></td>
                </tr>
                <tr>
                    <th>Rank UP</th>
                    <td><?= htmlspecialchars($officer['rank_up']) ?></td>
                </tr>
            </table>
        </div>

        <div class="records">
            <h3>Záznamy</h3>
            <button class="admin-button" onclick="openDocumentModal()">Vytvořit dokument</button>
            <table>
                <tr>
                    <th>Typ</th>
                    <th>Název</th>
                    <th>Popis</th>
                    <th>Podpis</th>
                    <th>Datum</th>
                </tr>
                <?php foreach ($documents as $document): ?>
                    <tr onclick="toggleDetails(<?= $document['id'] ?>)">
                        <td><?= htmlspecialchars($document['document_type']) ?></td>
                        <td><?= htmlspecialchars($document['title']) ?></td>
                        <td><?= htmlspecialchars($document['description']) ?></td>
                        <td><?= htmlspecialchars($document['signature']) ?></td>
                        <td><?= htmlspecialchars($document['created_at']) ?></td>
                    </tr>
                    <tr class="record-details" id="details-<?= $document['id'] ?>" style="display: none;">
                        <td colspan="5">
                            <strong>Podrobnosti:</strong>
                            <p><strong>Popis:</strong> <?= htmlspecialchars($document['description']) ?></p>
                            <p><strong>Podpis:</strong> <?= htmlspecialchars($document['signature']) ?></p>
                            <p><strong>Datum vytvoření:</strong> <?= htmlspecialchars($document['created_at']) ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="clear"></div>

        <div class="records">
            <h3>Záznamy prémií</h3>
            <button class="admin-button" onclick="openBonusModal()">Přidat prémii</button>
            <table>
                <tr>
                    <th>Hodiny</th>
                    <th>Hodnost</th>
                    <th>Náročnost</th>
                    <th>Záchrana života</th>
                    <th>Počet pochval</th>
                    <th>Počet reportů</th>
                    <th>Celková částka</th>
                    <th>Datum</th>
                </tr>
                <?php foreach ($bonuses as $bonus): ?>
                    <tr>
                        <td><?= htmlspecialchars($bonus['hours']) ?></td>
                        <td><?= htmlspecialchars($bonus['hodnost']) ?></td>
                        <td><?= htmlspecialchars($bonus['difficulty']) ?></td>
                        <td><?= htmlspecialchars($bonus['life_saving']) ?></td>
                        <td><?= htmlspecialchars($bonus['praise_count']) ?></td>
                        <td><?= htmlspecialchars($bonus['report_count']) ?></td>
                        <td><?= htmlspecialchars($bonus['total_bonus']) ?></td>
                        <td><?= htmlspecialchars($bonus['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

<!-- Modální okno pro vytvoření dokumentu -->
<div class="modal-overlay" id="documentModal" onclick="closeDocumentModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close" onclick="closeDocumentModal()">&times;</span>
        <h3>Vytvořit nový dokument</h3>
        <form method="POST" action="profil.php">
            <input type="hidden" name="officer_id" value="<?= $officer['id'] ?>">
            <label for="document_type">Typ dokumentu:</label>
            <select name="document_type" required>
                <option value="praise">Pochvala</option>
                <option value="report">Report</option>
                <option value="note">Poznámka</option>
            </select>
            <br>
            <label for="title">Předmět:</label>
            <input type="text" name="title" required>
            <br>
            <label for="description">Popis:</label>
            <textarea name="description" required></textarea>
            <br>
            <label for="signature">Podpis:</label>
            <input type="text" name="signature" required>
            <br>
            <button type="submit" name="submitDocument">Uložit dokument</button>
        </form>
    </div>
</div>

<!-- Modální okno pro přidání prémií -->
<div class="modal-overlay" id="bonusModal" onclick="closeBonusModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close" onclick="closeBonusModal()">&times;</span>
        <h3>Přidat prémii</h3>
        <form id="bonusForm">
            <label for="hours">Počet hodin:</label>
            <input type="number" name="hours" required>
            
            <label for="hodnost">Hodnost:</label>
            <select name="hodnost" required>
                <option value="low">Nízká</option>
                <option value="medium">Střední</option>
                <option value="high">Vysoká</option>
            </select>
            
            <label for="difficulty">Náročnost:</label>
            <select name="difficulty" required>
                <option value="low">Nízká</option>
                <option value="medium">Střední</option>
                <option value="high">Vysoká</option>
            </select>
            
            <label for="life_saving">Záchrana života:</label>
            <select name="life_saving" required>
                <option value="no">Ne</option>
                <option value="yes">Ano</option>
            </select>
            
            <label for="praise_count">Počet pochval:</label>
            <input type="number" name="praise_count" required>
            
            <label for="report_count">Počet reportů:</label>
            <input type="number" name="report_count" required>
            
            <button type="button" onclick="calculateBonus()">Vypočítat prémii</button>
        </form>
        <div id="bonusResult"></div>
    </div>
</div>



<script>
    function toggleDetails(documentId) {
        var details = document.getElementById('details-' + documentId);
        if (details.style.display === 'none' || details.style.display === '') {
            details.style.display = 'table-row'; // Zobrazí detaily
        } else {
            details.style.display = 'none'; // Skryje detaily
        }
    }

    function openDocumentModal() {
        document.getElementById('documentModal').style.display = 'block';
    }

    function closeDocumentModal() {
        document.getElementById('documentModal').style.display = 'none';
    }

    function openBonusModal() {
        document.getElementById('bonusModal').style.display = 'block';
    }

    function closeBonusModal() {
        document.getElementById('bonusModal').style.display = 'none';
    }

    // Kliknutí mimo modální okno zavře okno
    window.onclick = function(event) {
        if (event.target === document.getElementById('documentModal')) {
            closeDocumentModal();
        }
        if (event.target === document.getElementById('bonusModal')) {
            closeBonusModal();
        }
    };
</script>

    <?php elseif ($searchTerm): ?>
        <p>Úředník s tímto jménem nebyl nalezen.</p>
    <?php endif; ?>
</body>
</html>