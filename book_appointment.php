<?php
session_start();
// Assuming this path is correct based on the structure (e.g., patient/book_appointment.php)
include '../../conn.php'; 

// Custom ID Generation Function
// Generates an ID like APPT-251201-0001 (APPT-YYMMDD-Serial)
function generateCustomAppointmentId($con) {
    // 1. Define prefix and date part
    $prefix = "APPT";
    $date_part = date('ymd'); 

    // 2. Query the highest existing ID for today
    $like_pattern = $prefix . "-" . $date_part . "-%";
    $query = $con->prepare("
        SELECT appointment_id
        FROM appointments
        WHERE appointment_id LIKE ?
        ORDER BY appointment_id DESC
        LIMIT 1
    ");
    $query->bind_param("s", $like_pattern);
    $query->execute();
    $result = $query->get_result();
    
    $serial = 1;
    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['appointment_id']; // e.g., APPT-251201-0005
        // Extract the numeric part (0005)
        $last_serial = (int) substr($last_id, -4); 
        $serial = $last_serial + 1;
    }
    
    // 3. Format the new ID (e.g., APPT-251201-0006)
    $new_serial_part = str_pad($serial, 4, '0', STR_PAD_LEFT);
    return $prefix . "-" . $date_part . "-" . $new_serial_part;
}

// 1. SESSION PROTECTION
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
$patient_id = null;
$message = '';

// 2. FETCH PATIENT ID
$patientQuery = $con->prepare("
    SELECT user_id, full_name
    FROM users 
    WHERE email = ? AND role = 'patient'
");
$patientQuery->bind_param("s", $email);
$patientQuery->execute();
$patientResult = $patientQuery->get_result();
$fetchedPatient = $patientResult->fetch_assoc();

if ($fetchedPatient) {
    $patient_id = $fetchedPatient['user_id'];
    $patientName = $fetchedPatient['full_name'];
} else {
    die("User not found or role mismatch.");
}

// 3. FETCH LIST OF DOCTORS (Get specialization and JSON availability)
$doctors = []; // Array for HTML dropdown display
$doctorData = []; // Array for JavaScript data passing
$doctorQuery = $con->prepare("
    SELECT u.user_id, u.full_name, d.specialization, d.availability
    FROM users u
    JOIN doctors d ON u.user_id = d.doctor_id
    WHERE u.role = 'doctor'
    ORDER BY u.full_name ASC
");
$doctorQuery->execute();
$doctorResult = $doctorQuery->get_result();

while ($row = $doctorResult->fetch_assoc()) {
    $doctors[] = $row;
    // Store all doctor data indexed by user_id for easy JS lookup
    $doctorData[$row['user_id']] = $row;
}

// Convert PHP doctor data array to a JSON string for JavaScript
$doctorDataJson = json_encode($doctorData);

// 4. HANDLE FORM SUBMISSION (INSERT NEW APPOINTMENT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = $_POST['doctor_id'] ?? '';
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $appointmentTime = $_POST['appointment_time'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $status = 'Scheduled'; // Default status for a new booking

    // 1. Generate Custom ID
    $appointmentId = generateCustomAppointmentId($con); 

    // Combine date and time
    $fullDateTime = date('Y-m-d H:i:s', strtotime("$appointmentDate $appointmentTime"));

    // Basic Validation
    if (!empty($doctorId) && !empty($fullDateTime) && !empty($mode) && $patient_id !== null) {
        $insertQuery = $con->prepare("
            INSERT INTO appointments 
                (appointment_id, patient_id, doctor_id, appointment_date, status, mode, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // IMPORTANT: The custom ID is now bound as the first parameter (s)
        $insertQuery->bind_param("sssssss", 
            $appointmentId,       // custom ID
            $patient_id, 
            $doctorId, 
            $fullDateTime, 
            $status, 
            $mode, 
            $reason
        );

        if ($insertQuery->execute()) {
            $message = '<div style="color: var(--success-green); background-color: #dcfce7; border: 1px solid #a7f3d0; padding: 10px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                            Appointment booked successfully! ID: ' . htmlspecialchars($appointmentId) . '. You will be redirected shortly.
                        </div>';
            // Redirect after a short delay to the appointments list
            header("Refresh: 3; url=appointments.php"); 
        } else {
            $message = '<div style="color: red; background-color: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                            Error booking appointment: ' . $insertQuery->error . '
                        </div>';
        }
    } else {
        $message = '<div style="color: orange; background-color: #fffbeb; border: 1px solid #fde68a; padding: 10px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                        Please fill out all required fields.
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Appointment</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- CSS Styles omitted for brevity, keeping all original styles intact --- */
:root {
    --primary-blue: #3b82f6;
    --primary-blue-hover: #2563eb;
    --text-dark: #1f2937;
    --text-muted: #6b7280;
    --bg-light: #f9fafb;
    --bg-white: #ffffff;
    --border-color: #e5e7eb;
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --success-green: #22c55e;
    --active-blue: #3b82f6;
    --download-green-bg: #dcfce7;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-dark);
    line-height: 1.5;
    background-color: var(--bg-light);
    min-height: 100vh;
}

/* --- Utilities --- */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    font-weight: 600;
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: background-color 0.2s, border-color 0.2s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background-color: var(--primary-blue);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-blue-hover);
}

.btn-outline-primary {
    background-color: transparent;
    color: var(--primary-blue);
    border: 1px solid var(--primary-blue);
}

.btn-outline-primary:hover {
    background-color: var(--primary-blue);
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
}

.status-completed {
    background-color: #dcfce7;
    color: var(--success-green);
}

.status-active {
    background-color: #dbeafe;
    color: var(--active-blue);
}

/* --- Header & Nav --- */
header {
    padding: 15px 0;
    background: white;
    border-bottom: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.navbar {
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
}

.logo i {
    color: var(--primary-blue);
    font-size: 24px;
}

.nav-links {
    display: flex;
    gap: 30px;
}

.nav-links a {
    text-decoration: none;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 15px;
    padding: 5px 0;
    position: relative;
}

.nav-links a.active {
    color: var(--primary-blue);
}

.nav-links a.active::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -5px;
    width: 100%;
    height: 2px;
    background-color: var(--primary-blue);
}

.nav-icons {
    display: flex;
    align-items: center;
    gap: 20px;
}

.nav-icons i {
    font-size: 18px;
    color: var(--text-muted);
    cursor: pointer;
}

.nav-icons .profile-pic {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-blue);
}

/* --- Main Content Area --- */
.main-content {
    padding: 40px 0;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 0;
}

.page-header p {
    font-size: 15px;
    color: var(--text-muted);
    margin-top: 5px;
}

.page-header .back-icon {
    font-size: 24px;
    color: var(--text-dark);
    cursor: pointer;
}

/* --- Form Layout and Card --- */
.booking-layout {
    max-width: 600px; /* Center the form content */
    margin: 0 auto;
}

.card {
    background-color: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    padding: 30px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.card-header h2 {
    font-size: 20px;
    font-weight: 700;
}

/* --- Form Specific Styles --- */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-dark);
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 14px;
    color: var(--text-dark);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.date-time-group {
    display: flex;
    gap: 15px;
}

.date-time-group > div {
    flex: 1;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.action-buttons button,
.action-buttons a {
    flex: 1;
}

/* --- Responsive adjustments --- */
@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    .page-header h1 {
        font-size: 20px;
    }
    .date-time-group {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<header>
      <div class="container navbar">
        <div class="logo">
            <i class="fas fa-user-doctor"></i> MediLink
        </div>
        <nav class="nav-links">
            <a href="patient.php" class="active">Dashboard</a>
            <a href="appointments.php">Appointments</a>
            <a href="prescriptions_history.php">Prescriptions</a>
             <a href="edit_profile.php">Profile</a>
              <a href="logout.php">Logout</a>
        </nav>

        <div class="nav-icons">
          
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patientName); ?>&background=random" class="profile-pic">
        </div>
    </div>
</header>


<main class="main-content">
    <div class="container">

        <div class="page-header">
            <i class="fas fa-arrow-left back-icon" onclick="history.back()"></i>
            <div>
                <h1>Book New Appointment</h1>
                <p>Select your preferred doctor, date, and mode of consultation.</p>
            </div>
        </div>

        <div class="booking-layout">
            <div class="card">
                <?php echo $message; // Display status message ?>

                <form method="POST" action="book_appointment.php">

                    <div class="form-group">
                        <label for="doctor_id">Select Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="" disabled selected>-- Choose a Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['user_id']; ?>">
                                    <?php echo $doctor['full_name']; ?> (<?php echo $doctor['specialization']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                         <label for="availability-info">Doctor Schedule:</label>
                         <p id="availability-info" style="font-style: italic; color: var(--text-dark);">
                            Please select a doctor above.
                         </p>
                    </div>

                    <div class="date-time-group">
                        <div class="form-group">
                            <label for="appointment_date">Date *</label>
                            <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>" disabled>
                            <small id="date-message" style="color: var(--text-muted); display: none;">Select a doctor to see available dates.</small>
                        </div>
                        <div class="form-group">
                            <label for="appointment_time">Time *</label>
                            <select id="appointment_time" name="appointment_time" required disabled>
                                <option value="" disabled selected>Select Time</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mode">Consultation Mode *</label>
                        <select id="mode" name="mode" required>
                            <option value="" disabled selected>-- Select Mode --</option>
                            <option value="In-person">In-person Visit</option>
                            <option value="Online">Online / Video Call</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Appointment</label>
                        <textarea id="reason" name="reason" rows="4" placeholder="Briefly describe your symptoms or reason for the visit (e.g., Annual Checkup, Severe Headache, Follow-up)."></textarea>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Confirm Booking
                        </button>
                        <a href="appointments.php" class="btn btn-outline-primary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
    // 1. Data passed from PHP
    // IMPORTANT: This line injects the doctor availability data as a JSON object
    const DOCTOR_DATA = <?php echo $doctorDataJson; ?>;

    // 2. DOM Elements
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');
    const availabilityInfo = document.getElementById('availability-info');
    const dateMessage = document.getElementById('date-message');

    // 3. Event Listeners
    doctorSelect.addEventListener('change', updateAvailableDates);
    dateInput.addEventListener('change', updateAvailableTimes);
    
    // Initial state: Disable inputs until a doctor is chosen
    dateInput.disabled = true;
    timeSelect.disabled = true;

    // Helper function to get the current day name (Mon, Tue, Wed, etc.)
    function getDayName(dateString) {
        const date = new Date(dateString);
        // Format: Sun, Mon, Tue, etc.
        return date.toLocaleDateString('en-US', { weekday: 'short' }).replace('.', '');
    }

    /* Step 1: When a doctor is selected, enable the date picker and display the schedule.*/
    function updateAvailableDates() {
        const selectedId = doctorSelect.value;
        const doctor = DOCTOR_DATA[selectedId];

        if (!doctor) return;

        // Display the full schedule in the info area
        const availabilityArray = JSON.parse(doctor.availability);
        let scheduleHtml = 'Available: ';
        
        scheduleHtml += availabilityArray.map(slot => 
            `<strong>${slot.day}</strong> (${slot.time})`
        ).join(' | ');

        availabilityInfo.innerHTML = scheduleHtml;
        
        // Enable date picker
        dateInput.disabled = false;
        dateMessage.style.display = 'inline';
        dateMessage.textContent = 'Please select a date to check available slots.';

        // Set the minimum date to today
        dateInput.min = new Date().toISOString().split('T')[0];
        
        // Clear previous time selection and data
        timeSelect.innerHTML = '<option value="" disabled selected>Select Time</option>';
        timeSelect.disabled = true;
        dateInput.value = ''; // Force user to re-select date based on new doctor
    }

    /*Step 2: When a date is selected, filter the doctor's availability for that day.
*/
    function updateAvailableTimes() {
        const selectedId = doctorSelect.value;
        const selectedDate = dateInput.value;

        if (!selectedId || !selectedDate) return;

        const doctor = DOCTOR_DATA[selectedId];
        const selectedDayName = getDayName(selectedDate);
        
        // Ensure availability is parsed from JSON string
        const availabilityArray = JSON.parse(doctor.availability);

        // Filter slots for the selected day OR "Daily"
        const availableSlots = availabilityArray.filter(slot => 
            slot.day === selectedDayName || slot.day === 'Daily'
        );

        // Generate time options
        timeSelect.innerHTML = '<option value="" disabled selected>Select Time</option>';

        if (availableSlots.length === 0) {
            timeSelect.innerHTML += `<option value="" disabled>No slots available on ${selectedDayName}.</option>`;
            timeSelect.disabled = true;
            dateMessage.textContent = `Dr. ${doctor.full_name.split(' ').pop()} is NOT available on ${selectedDayName}.`;
            dateMessage.style.color = 'red';
            return;
        }

        // Display the found slots
        availableSlots.forEach(slot => {
            // Note: We use the full time string as the value for simplicity, 
            // but in a real app, this should be broken down into hourly/30min slots.
            timeSelect.innerHTML += `<option value="${slot.time}">${slot.time}</option>`;
        });

        dateMessage.textContent = `Slots found for ${selectedDayName} (select time above).`;
        dateMessage.style.color = 'var(--success-green)';
        timeSelect.disabled = false;
    }
    
    // Simple JavaScript for the back button
    document.querySelector('.back-icon').addEventListener('click', function() {
        window.history.back();
    });
</script>

</body>
</html>