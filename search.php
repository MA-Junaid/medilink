<?php
session_start();
include 'conn.php';

// Get Search Parameters
$doctorName = isset($_GET['doctor']) ? trim($_GET['doctor']) : '';
$specialty = isset($_GET['specialty']) ? trim($_GET['specialty']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Build Query dynamically based on inputs
$sql = "
    SELECT u.full_name, d.specialization, d.doctor_id, d.experience_years, d.rate, AVG(r.rating) as avg_rating, COUNT(r.rating_id) as review_count
    FROM users u
    JOIN doctors d ON u.user_id = d.doctor_id
    LEFT JOIN reviews r ON d.doctor_id = r.doctor_id
    WHERE u.role = 'doctor'
    AND d.verified = 1
";

$params = [];
$types = "";

if (!empty($doctorName)) {
    $sql .= " AND u.full_name LIKE ?";
    $params[] = "%$doctorName%";
    $types .= "s";
}

if (!empty($specialty)) {
    $sql .= " AND d.specialization = ?";
    $params[] = $specialty;
    $types .= "s";
}

// Note: 'location' search would require an address field in users or doctors table. 
// Assuming users table has address or doctors table has clinic_address. 
// For now, I'll search against a hypothetical address field in users table joined earlier.
// If address is in users table:
// $sql .= " AND u.address LIKE ?";
// For now, I won't filter by location strictly unless I confirm the schema, but I'll add the placeholder.
/*
if (!empty($location)) {
    $sql .= " AND u.address LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}
*/

$sql .= " GROUP BY d.doctor_id ORDER BY avg_rating DESC";

$stmt = $con->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - MediLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reusing variables from index.php for consistency */
        :root {
            --primary-blue: #3b82f6;
            --primary-blue-hover: #2563eb;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --star-yellow: #fbbf24;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-dark);
            line-height: 1.5;
            background-color: var(--bg-light);
            margin: 0;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        header {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-weight: 700;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
            text-decoration: none;
        }
        .logo i { color: var(--primary-blue); font-size: 24px; }

        .btn-back {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Results List */
        .results-header {
            margin-bottom: 20px;
        }
        .results-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        .results-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .doctor-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s;
        }

        .doctor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .doctor-info {
            flex: 1;
        }

        .doctor-info h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 5px;
        }

        .specialty-badge {
            display: inline-block;
            background-color: #eff6ff;
            color: var(--primary-blue);
            padding: 4px 10px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: capitalize;
        }
        
        .doctor-meta {
            font-size: 14px;
            color: var(--text-muted);
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .rating i { color: var(--star-yellow); font-size: 13px; }

        .btn-book {
            background-color: var(--primary-blue);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-book:hover {
            background-color: var(--primary-blue-hover);
        }

        /* Empty State */
        .no-results {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }
        .no-results i {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 600px) {
            .doctor-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            .doctor-meta {
                justify-content: center;
                flex-direction: column;
                gap: 5px;
            }
            .btn-book {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="container header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-user-doctor"></i> MediLink
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </header>

    <div class="container">
        <div class="results-header">
            <h1>Search Results</h1>
            <p>
                Showing results for 
                <?php if($specialty) echo "Specialty: <strong>" . htmlspecialchars($specialty) . "</strong>"; ?>
                <?php if($doctorName) echo " Name: <strong>" . htmlspecialchars($doctorName) . "</strong>"; ?>
            </p>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="doctor-card">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['full_name']); ?>&background=random" class="doctor-avatar" alt="<?php echo htmlspecialchars($row['full_name']); ?>">
                    
                    <div class="doctor-info">
                        <h2>Dr. <?php echo htmlspecialchars($row['full_name']); ?></h2>
                        <span class="specialty-badge"><?php echo htmlspecialchars($row['specialization']); ?></span>
                        
                        <div class="doctor-meta">
                            <span><i class="fas fa-briefcase"></i> <?php echo $row['experience_years'] ?? 0; ?>+ Years Exp.</span>
                            <div class="rating">
                                <span><?php echo number_format($row['avg_rating'] ?? 0, 1); ?></span>
                                <i class="fas fa-star"></i>
                                <span>(<?php echo $row['review_count']; ?> reviews)</span>
                            </div>
                            <span><i class="fas fa-tag"></i> ৳<?php echo number_format($row['rate'], 0); ?> / Visit</span>
                        </div>
                    </div>

                    <a href="login.php" class="btn-book">Book Appointment</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-user-md"></i>
                <h2>No doctors found</h2>
                <p>We couldn't find any doctors matching your search criteria. Try different keywords or browse all.</p>
                <br>
                <a href="index.php" class="btn-book" style="display:inline-block; width:auto;">Clear Search</a>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
