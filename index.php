<?php
// Start session
session_start();

// Connect to Database
include 'conn.php';

// Fetch Featured Doctors (Limit 3)
$doctorsQuery = "
    SELECT u.full_name, d.specialization, d.doctor_id, AVG(r.rating) as avg_rating, COUNT(r.rating_id) as review_count 
    FROM users u 
    JOIN doctors d ON u.user_id = d.doctor_id 
    LEFT JOIN reviews r ON d.doctor_id = r.doctor_id 
    WHERE u.role = 'doctor' 
    GROUP BY d.doctor_id 
    ORDER BY avg_rating DESC 
    LIMIT 3
";
$doctorsResult = $con->query($doctorsQuery);

// Fetch Specialties for Dropdown
$specialtiesQuery = "SELECT DISTINCT specialization FROM doctors ORDER BY specialization ASC";
$specialtiesResult = $con->query($specialtiesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediLink - Find the Right Doctor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- CSS Variables & Reset --- */
        :root {
            --primary-blue: #3b82f6; /* Matching the blue in the image */
            /* ... (rest of the CSS variables) ... */
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* [Existing CSS Styles] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--text-dark); line-height: 1.5; background-color: var(--bg-white); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--text-muted); }
        .btn { display: inline-block; padding: 10px 24px; font-weight: 600; border-radius: var(--radius-md); text-decoration: none; transition: background-color 0.2s; border: none; cursor: pointer; font-size: 14px; }
        .btn-primary { background-color: var(--primary-blue); color: white; }
        .btn-primary:hover { background-color: var(--primary-blue-hover); }
        .btn-block { display: block; width: 100%; text-align: center; }
        header { padding: 20px 0; background: white; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-weight: 700; font-size: 20px; display: flex; align-items: center; gap: 8px; color: var(--text-dark); }
        .logo i { color: var(--primary-blue); font-size: 24px; }
        nav { display: flex; align-items: center; gap: 24px; }
        .login-link { text-decoration: none; color: var(--text-dark); font-weight: 600; font-size: 14px; }
        .hero-section { padding: 60px 0; }
        .hero-container { display: flex; align-items: center; gap: 40px; }
        .hero-content { flex: 1; }
        .hero-content h1 { font-size: 48px; font-weight: 800; line-height: 1.2; margin-bottom: 20px; }
        .hero-content p { font-size: 18px; color: var(--text-muted); margin-bottom: 40px; max-width: 480px; }
        .hero-image { flex: 1; }
        .hero-image img { width: 100%; height: auto; border-radius: var(--radius-lg); object-fit: cover; box-shadow: var(--shadow-sm); }
        .search-widget { background: var(--bg-white); padding: 24px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
        .search-inputs-row { display: flex; gap: 16px; margin-bottom: 20px; }
        .input-group { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 14px; font-weight: 600; color: var(--text-dark); }
        .input-wrapper { position: relative; }
        .input-wrapper input, .input-wrapper select { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; color: var(--text-dark); background: white; appearance: none; }
        .select-wrapper { position: relative; }
        .select-wrapper::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; font-size: 12px; }
        .icon-left i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .icon-left input { padding-left: 40px; }
        .icon-right i { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .icon-right input { padding-right: 40px; }
        .section-bg-light { background-color: var(--bg-light); padding: 80px 0; }
        .features-grid { display: flex; gap: 30px; }
        .feature-card { flex: 1; background: var(--bg-white); padding: 32px; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .icon-circle { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 24px; }
        .icon-blue { background-color: #dbeafe; color: var(--primary-blue); }
        .icon-green { background-color: #d1fae5; color: #10b981; }
        .icon-purple { background-color: #ede9fe; color: #8b5cf6; }
        .feature-card h3 { font-size: 18px; margin-bottom: 12px; }
        .feature-card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; }
        .doctors-section { padding: 80px 0; }
        .section-header { margin-bottom: 50px; }
        .section-header h2 { font-size: 30px; font-weight: 800; margin-bottom: 12px; }
        .doctors-grid { display: flex; gap: 30px; }
        .doctor-card { flex: 1; background: var(--bg-white); padding: 24px; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .doctor-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 16px; }
        .doctor-card h3 { font-size: 18px; margin-bottom: 4px; }
        .doctor-specialty { font-size: 14px; color: var(--text-muted); margin-bottom: 12px; }
        .rating { display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 20px; font-size: 14px; }
        .rating i { color: var(--star-yellow); }
        .rating span { color: var(--text-muted); margin-left: 4px; }
        @media (max-width: 992px) { .hero-container { flex-direction: column; } .hero-image { width: 100%; } .hero-content { width: 100%; } .hero-content h1 { font-size: 36px; } }
        @media (max-width: 768px) { .search-inputs-row, .features-grid, .doctors-grid { flex-direction: column; } .nav-container { flex-wrap: wrap; } nav { margin-top: 16px; width: 100%; justify-content: flex-end; } }
    </style>
</head>
<body>

    <header>
        <div class="container nav-container">
            <div class="logo">
                <i class="fas fa-user-doctor"></i> MediLink
            </div>
            <nav>
                <a href="login.php" class="login-link">Login</a>
                <a href="signup.php" class="btn btn-primary">Sign Up</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero-section">
            <div class="container hero-container">
                <div class="hero-content">
                    <h1>Find the Right Doctor Fast</h1>
                    <p>Connect with qualified healthcare professionals in your area. Book appointments instantly and get the care you need.</p>

                    <div class="search-widget">
                        <form action="search.php" method="GET">
                            <div class="search-inputs-row">
                                <div class="input-group">
                                    <label for="doctor-name">Doctor</label>
                                    <div class="input-wrapper icon-left">
                                        <i class="fas fa-magnifying-glass"></i>
                                        <input type="text" name="doctor" id="doctor-name" placeholder="Doctor name">
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="specialty">Specialty</label>
                                    <div class="input-wrapper select-wrapper">
                                        <select name="specialty" id="specialty" style="padding-right: 40px;">
                                            <option value="" selected>All Specialties</option>
                                            <?php 
                                            if($specialtiesResult->num_rows > 0){
                                                while($spec = $specialtiesResult->fetch_assoc()){
                                                    echo '<option value="'.htmlspecialchars($spec['specialization']).'">'.ucfirst(htmlspecialchars($spec['specialization'])).'</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="location">Location</label>
                                    <div class="input-wrapper icon-right">
                                        <input type="text" name="location" id="location" placeholder="Enter location">
                                        <i class="fas fa-location-dot"></i>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-magnifying-glass" style="margin-right: 8px;"></i> Search Doctors
                            </button>
                        </form>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="assets/img/hero.png" alt="Doctor consulting patient">
                </div>
            </div>
        </section>

        <section class="section-bg-light">
            <div class="container">
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="icon-circle icon-blue">
                            <i class="far fa-calendar-check"></i>
                        </div>
                        <h3>Book Instantly</h3>
                        <p class="text-muted">Schedule appointments with available doctors in real-time. No waiting, no hassle.</p>
                    </div>
                    <div class="feature-card">
                        <div class="icon-circle icon-green">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3>Video Consult</h3>
                        <p class="text-muted">Connect with doctors remotely through secure video consultations from anywhere.</p>
                    </div>
                    <div class="feature-card">
                        <div class="icon-circle icon-purple">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Trusted Doctors</h3>
                        <p class="text-muted">All our healthcare professionals are verified and highly qualified specialists.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="doctors-section">
            <div class="container">
                <div class="section-header text-center">
                    <h2>Featured Doctors</h2>
                    <p class="text-muted">Meet some of our top-rated healthcare professionals</p>
                </div>
                <div class="doctors-grid">
                    <?php if ($doctorsResult->num_rows > 0): ?>
                        <?php while($doctor = $doctorsResult->fetch_assoc()): ?>
                            <div class="doctor-card">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doctor['full_name']); ?>&background=random" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>" class="doctor-avatar">
                                <h3>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                                <p class="doctor-specialty"><?php echo htmlspecialchars(ucfirst($doctor['specialization'])); ?></p>
                                <div class="rating">
                                    <?php 
                                    $rating = round($doctor['avg_rating'] ?? 0, 1);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i - 0.5 <= $rating) {
                                            echo '<i class="fas fa-star-half-stroke"></i>';
                                        } else {
                                            echo '<i class="far fa-star" style="color: #cbd5e1;"></i>';
                                        }
                                    }
                                    ?>
                                    <span><?php echo $rating > 0 ? $rating : 'No ratings'; ?> (<?php echo $doctor['review_count']; ?> reviews)</span>
                                </div>
                                <a href="login.php" class="btn btn-primary btn-block">Book Appointment</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center" style="width: 100%;">
                            <p class="text-muted">No featured doctors available at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

</body>
</html>