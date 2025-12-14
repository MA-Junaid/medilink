<?php
session_start();
// patient/join_consultation.php
require '../../conn.php'; // Access the DB connection

// 1. Session and Role Check
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php'); // Redirect unauthenticated users
    exit;
}

// 2. Appointment ID Check
if (!isset($_GET['id'])) {
    die("Error: Appointment ID is missing.");
}
$appointmentId = $_GET['id'];
$patientEmail = $_SESSION['email'];

// 3. Fetch Appointment Data
$sql = "
    SELECT 
        a.appointment_id, a.patient_id, a.doctor_id, a.appointment_date, a.status, 
        u_doc.full_name AS doctor_name, u_doc.email AS doctor_email, 
        u_pat.user_id AS patient_user_id
    FROM appointments a
    JOIN users u_pat ON u_pat.email = ? AND u_pat.role = 'patient'
    JOIN users u_doc ON u_doc.user_id = a.doctor_id
    WHERE a.appointment_id = ? AND a.patient_id = u_pat.user_id
";

$stmt = $con->prepare($sql);
$stmt->bind_param("ss", $patientEmail, $appointmentId);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    die("Error: Appointment not found or you don't have access.");
}

$patientId = $appointment['patient_user_id'];
$doctorName = htmlspecialchars($appointment['doctor_name']);
$appointmentTime = date('h:i A, F j', strtotime($appointment['appointment_date']));
$appointmentStatus = $appointment['status'];
$returnUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Consultation - <?php echo $doctorName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS variables and styles from the doctor's dashboard CSS block go here */
        /* Start of CSS Block */
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
            --status-green: #22c55e;
            --status-green-bg: #dcfce7;
            --status-blue: #3b82f6;
            --status-blue-bg: #dbeafe;
            --status-yellow: #f59e0b;
            --status-yellow-bg: #fef3c7;
            --status-red: #ef4444;
            --status-red-bg: #fee2e2;
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
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        .main-consultation {
            flex-grow: 1;
            display: flex;
            max-width: 1400px;
            margin: 20px auto;
            width: 95%;
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .video-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            background-color: #1a1a1a;
            position: relative;
        }

        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #2c2c2c;
        }

        #localVideo {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 180px;
            height: 120px;
            object-fit: cover;
            border: 3px solid var(--primary-blue);
            border-radius: var(--radius-md);
            z-index: 10;
        }
        
        .controls-bar {
            background-color: #333;
            padding: 15px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .control-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 18px;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .control-btn.active {
            background-color: var(--primary-blue);
        }
        
        .control-btn.end-call {
            background-color: var(--status-red);
            width: auto;
            height: auto;
            border-radius: var(--radius-md);
            padding: 10px 20px;
            font-weight: 600;
        }

        .control-btn.end-call:hover {
            background-color: #dc2626;
        }

        .chat-panel {
            flex: 1;
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            max-width: 400px;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--bg-light);
        }

        .chat-header h3 {
            font-size: 18px;
            font-weight: 700;
        }
        
        .session-timer {
            margin-top: 5px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .session-status {
            margin-top: 5px;
            font-size: 14px;
            color: var(--status-red);
        }

        .chat-messages {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: var(--bg-white);
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        .message.self {
            align-items: flex-end;
        }

        .message-bubble {
            padding: 10px 15px;
            border-radius: 20px;
            max-width: 80%;
            line-height: 1.4;
        }

        .message.other .message-bubble {
            background-color: var(--bg-light);
            border-bottom-left-radius: 4px;
        }

        .message.self .message-bubble {
            background-color: var(--primary-blue);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-time {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .chat-input {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
        }

        .chat-input input {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
        }

        .chat-input button {
            padding: 10px 15px;
            border: none;
            background-color: var(--primary-blue);
            color: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .chat-input button:hover {
            background-color: var(--primary-blue-hover);
        }
        /* End of CSS Block */
    </style>
</head>
<body>

    <div class="main-consultation">
        <div class="video-panel">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" muted autoplay playsinline></video>
            
            <div class="controls-bar">
                <button id="toggleMic" class="control-btn active" title="Toggle Microphone">
                    <i class="fas fa-microphone"></i>
                </button>
                <button id="toggleCam" class="control-btn active" title="Toggle Camera">
                    <i class="fas fa-video"></i>
                </button>
                <button id="endCall" class="control-btn end-call">
                    <i class="fas fa-phone-slash"></i> Leave Consultation
                </button>
            </div>
        </div>
        
        <div class="chat-panel">
            <div class="chat-header">
                <h3>Consultation with Dr. <?php echo $doctorName; ?></h3>
                <p>Appointment: **<?php echo $appointmentTime; ?>**</p>
                <div class="session-timer" id="sessionTimer">Status: Waiting for doctor...</div>
                <div class="session-status" id="sessionStatus">Current Status: <?php echo htmlspecialchars($appointmentStatus); ?></div>
            </div>
            <div class="chat-messages" id="chatMessages">
                </div>
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type a message...">
                <button id="sendChat">Send</button>
            </div>
        </div>
    </div>


<script>
    // Configuration from PHP
    const WS_HOST = `ws://${window.location.hostname}:8080`;
    const APPOINTMENT_ID = '<?php echo $appointmentId; ?>';
    const USER_ID = '<?php echo $patientId; ?>'; // Patient ID
    const USER_ROLE = 'patient';
    const RETURN_URL = '<?php echo $returnUrl; ?>';
    
    // WebRTC globals
    let ws;
    let peerConnection;
    let localStream;
    let remoteStream;
    let iceQueue = []; // Queue for ICE candidates
    const iceServers = {
        'iceServers': [
            { 'urls': 'stun:stun.l.google.com:19302' },
        ]
    };
    
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');
    const sessionTimerEl = document.getElementById('sessionTimer');
    const sessionStatusEl = document.getElementById('sessionStatus');
    
    let sessionStartTime = null;
    let sessionInterval;
    const SESSION_START_DELAY_MS = 30000; // 30 seconds delay

    // --- Utility Functions ---

    function startSessionTimer() {
        // This timer runs AFTER the server confirms the session is started
        sessionStartTime = Date.now();
        sessionStatusEl.innerHTML = 'Current Status: **In Progress**';
        sessionTimerEl.classList.remove('status-red');
        sessionTimerEl.classList.add('status-green');
        
        sessionInterval = setInterval(() => {
            const now = Date.now();
            const elapsedSeconds = Math.floor((now - sessionStartTime) / 1000);
            const minutes = Math.floor(elapsedSeconds / 60);
            const seconds = elapsedSeconds % 60;
            sessionTimerEl.innerText = `Session Duration: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }, 1000);
    }
    
    function appendMessage(sender, message, isSelf) {
        const chatBox = document.getElementById('chatMessages');
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${isSelf ? 'self' : 'other'}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerText = message;
        
        const time = document.createElement('span');
        time.className = 'message-time';
        time.innerText = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        msgDiv.appendChild(bubble);
        msgDiv.appendChild(time);
        chatBox.appendChild(msgDiv);
        
        chatBox.scrollTop = chatBox.scrollHeight;
    }
    
    // --- WebRTC Functions ---

    async function initWebRTC() {
        try {
            // 1. Get local media stream
            localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            localVideo.srcObject = localStream;

            // 2. Create RTCPeerConnection
            peerConnection = new RTCPeerConnection(iceServers);
            
            // 3. Add local tracks to RTCPeerConnection
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            // 4. Handle ICE candidates (send to peer via WebSocket)
            peerConnection.onicecandidate = ({ candidate }) => {
                if (candidate) {
                    ws.send(JSON.stringify({
                        type: 'webrtc_ice_candidate',
                        appt_id: APPOINTMENT_ID,
                        role: USER_ROLE,
                        candidate: candidate
                    }));
                }
            };

            // 5. Handle remote track addition
            peerConnection.ontrack = (event) => {
                if (remoteVideo.srcObject !== event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    remoteStream = event.streams[0];
                    console.log('Remote stream received.');
                }
            };
            
        } catch (error) {
            console.error("Error accessing media devices: ", error);
            Swal.fire('Error', 'Could not access camera/microphone. Please check permissions.', 'error');
        }
    }
    
    async function processIceQueue() {
        if (peerConnection && iceQueue.length > 0) {
            console.log(`Processing ${iceQueue.length} queued ICE candidates`);
            for (const candidate of iceQueue) {
                try {
                    await peerConnection.addIceCandidate(candidate);
                } catch (e) {
                    console.error('Error adding queued ICE candidate:', e);
                }
            }
            iceQueue = [];
        }
    }
    
    async function createAnswer(offer) {
        if (!peerConnection) await initWebRTC(); 
        
        await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
        await processIceQueue(); // Process any queued candidates
        
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        
        ws.send(JSON.stringify({
            type: 'webrtc_answer',
            appt_id: APPOINTMENT_ID,
            role: USER_ROLE,
            answer: answer
        }));
    }
    
    async function handleAnswer(answer) {
        if (!peerConnection) return;
        await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
        await processIceQueue(); // Process any queued candidates
    }
    
    async function handleIceCandidate(candidate) {
        if (!peerConnection) return;
        
        // If remote description is not set, queue the candidate
        if (!peerConnection.remoteDescription) {
            console.log('Remote description not set. Queueing ICE candidate.');
            iceQueue.push(candidate);
            return;
        }
        
        try {
            await peerConnection.addIceCandidate(candidate);
        } catch (e) {
            console.error('Error adding received ICE candidate:', e);
        }
    }


    // --- WebSocket Handlers ---
    
    function connectWebSocket() {
        ws = new WebSocket(WS_HOST);

        ws.onopen = () => {
            console.log("WebSocket connected. Registering client...");
            // 1. Register with the signaling server
            ws.send(JSON.stringify({
                type: 'register',
                appt_id: APPOINTMENT_ID,
                role: USER_ROLE,
                user_id: USER_ID
            }));
        };

        ws.onmessage = async (event) => {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'ready':
                    // Doctor is connected. Patient starts the 30-second waiting period.
                    sessionTimerEl.innerText = 'Doctor connected. Consultation starts in 30 seconds...';
                    
                    // 2. Patient waits 30 seconds, then sends the session_start signal
                    setTimeout(() => {
                        sendSessionStart();
                    }, SESSION_START_DELAY_MS);
                    break;

                case 'webrtc_offer':
                    // Doctor initiated the offer, Patient must create the answer
                    await createAnswer(data.offer);
                    break;

                case 'webrtc_answer':
                    // This is unexpected for the patient, but handle defensively
                    await handleAnswer(data.answer); 
                    break;

                case 'webrtc_ice_candidate':
                    await handleIceCandidate(data.candidate);
                    break;

                case 'chat_message':
                    appendMessage(data.sender_id, data.message, false);
                    break;
                    
                case 'session_started':
                    // Ratchet server confirmed session start (and billing begins)
                    startSessionTimer();
                    sessionStatusEl.innerText = 'Current Status: Consultation is officially **In Progress**!';
                    break;
                    
                case 'session_ended':
                    endCallHandler(data.duration_min, data.earnings);
                    break;
                    
                case 'peer_disconnected':
                    sessionStatusEl.innerText = data.message;
                    // If the peer (doctor) disconnects, the session may end automatically on the server
                    setTimeout(() => endCallHandler(), 5000); 
                    break;

                case 'error':
                    console.error("Server Error:", data.message);
                    sessionStatusEl.innerText = "Error: " + data.message;
                    break;

                default:
                    console.log("Unknown message type:", data.type);
            }
        };

        ws.onclose = () => {
            console.log("WebSocket disconnected.");
            sessionStatusEl.innerText = 'Connection lost. Please refresh.';
            clearInterval(sessionInterval);
        };

        ws.onerror = (error) => {
            console.error("WebSocket error:", error);
            sessionStatusEl.innerText = 'WebSocket Error. Check server.';
        };
    }
    
    function sendSessionStart() {
         if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'session_start',
                appt_id: APPOINTMENT_ID,
                role: USER_ROLE,
                message: 'Patient initiating session start after 30 seconds.'
            }));
            sessionTimerEl.innerText = 'Session start signal sent. Waiting for server confirmation...';
         }
    }

    // --- UI/Control Handlers ---

    function setupControls() {
        document.getElementById('toggleMic').addEventListener('click', () => {
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                document.getElementById('toggleMic').classList.toggle('active', audioTrack.enabled);
                document.getElementById('toggleMic').querySelector('i').className = audioTrack.enabled ? 'fas fa-microphone' : 'fas fa-microphone-slash';
            }
        });

        document.getElementById('toggleCam').addEventListener('click', () => {
            const videoTrack = localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                document.getElementById('toggleCam').classList.toggle('active', videoTrack.enabled);
                document.getElementById('toggleCam').querySelector('i').className = videoTrack.enabled ? 'fas fa-video' : 'fas fa-video-slash';
            }
        });

        document.getElementById('endCall').addEventListener('click', () => {
            Swal.fire({
                title: 'End Consultation?',
                text: "Are you sure you want to leave?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, leave'
            }).then((result) => {
                if (result.isConfirmed) {
                     // Request session end from server to calculate fees
                     if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'session_end',
                            appt_id: APPOINTMENT_ID,
                            role: USER_ROLE
                        }));
                        // We will wait for 'session_ended' message to handle redirection
                     } else {
                        endCallHandler(); // Fallback if no connection
                     }
                }
            });
        });
        
        const chatInput = document.getElementById('chatInput');
        document.getElementById('sendChat').addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendChatMessage();
        });
    }
    
    function sendChatMessage() {
        const chatInput = document.getElementById('chatInput');
        const message = chatInput.value.trim();
        if (message === '') return;

        if (ws && ws.readyState === WebSocket.OPEN) {
            const chatData = {
                type: 'chat_message',
                appt_id: APPOINTMENT_ID,
                role: USER_ROLE,
                sender_id: USER_ID,
                message: message
            };
            ws.send(JSON.stringify(chatData));
            appendMessage(USER_ID, message, true);
            chatInput.value = '';
        } else {
            Swal.fire('Connection Error', 'Cannot send message: Connection is closed.', 'error');
        }
    }
    
    function endCallHandler(durationMin = null, earnings = null) {
        clearInterval(sessionInterval);
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localVideo.srcObject = null;
        }
        if (remoteVideo) {
            remoteVideo.srcObject = null;
        }
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        if (ws) {
            ws.close();
        }

        let message = "Consultation has ended.";
        let icon = 'info';
        
        if (earnings !== null) {
            message = `Consultation Complete.\nDuration: ${durationMin} mins.\nTotal Fee: ৳${earnings}`;
            icon = 'success';
            
            Swal.fire({
                title: 'Consultation Ended',
                text: message,
                icon: icon,
                confirmButtonText: 'Proceed to Payment',
                allowOutsideClick: false
            }).then(() => {
                // Redirect to payment gateway
                const redirectUrl = `payment_gateway.php?appt_id=${APPOINTMENT_ID}&amount=${earnings}&return_url=${encodeURIComponent(RETURN_URL)}`;
                window.location.href = redirectUrl;
            });
            return;
        } else {
            message += " Session closed unexpectedly.";
            icon = 'warning';
        }
        
        sessionTimerEl.innerText = "Session Ended";
        sessionStatusEl.innerText = message;
        sessionStatusEl.style.color = 'var(--status-red)';
        
        if (!earnings) {
            Swal.fire('Ended', message, icon).then(() => {
                 window.location.href = RETURN_URL;
            });
        }
    }
    
    // --- Initialization ---

    document.addEventListener('DOMContentLoaded', () => {
        initWebRTC(); // Initialize WebRTC media devices and PeerConnection
        connectWebSocket(); // Establish WebSocket connection
        setupControls(); // Set up buttons
    });
</script>

</body>
</html>