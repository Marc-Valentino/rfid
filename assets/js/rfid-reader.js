/**
 * RFID Attendance System - RFID Reader JavaScript
 * Handles RFID scanning functionality, communication with the RFID hardware,
 * and processing scan results
 */

// RFID Reader Configuration
const RFID_CONFIG = {
    scanTimeout: 10000,      // 10 seconds timeout for scanning
    pollInterval: 1000,      // 1 second polling interval
    readMode: 'read_only',   // Default read mode (read_only, attendance)
    apiEndpoint: '../api/rfid-scan.php'
};

// RFID Reader State
let rfidScanTimer = null;
let rfidPollInterval = null;
let isScanning = false;
let currentScanMode = RFID_CONFIG.readMode;
let rfidInputBuffer = ''; // Buffer to store keyboard input for NFC reader

// Create global rfidReader object
window.rfidReader = {
    // Add existing functions as methods
    initializeNFCKeyboardCapture: function() {
        let rfidInput = '';
        let lastKeyTime = 0;
        const RFID_TIMEOUT = 500; // Time in ms to consider a sequence complete
        
        document.addEventListener('keypress', function(e) {
            const currentTime = new Date().getTime();
            
            // If there's a significant delay since the last keypress, reset the input
            if (currentTime - lastKeyTime > RFID_TIMEOUT) {
                rfidInput = '';
            }
            
            // Update the last key time
            lastKeyTime = currentTime;
            
            // Add the character to our input buffer
            if (e.key !== 'Enter') {
                rfidInput += e.key;
            } else if (rfidInput.length > 0) {
                // Process the complete RFID input when Enter is pressed
                console.log('NFC card detected:', rfidInput);
                this.processRFIDScan(rfidInput);
                rfidInput = ''; // Reset for next scan
                e.preventDefault(); // Prevent form submission if in a form
            }
        }.bind(this));
        
        console.log('NFC keyboard capture initialized');
    },
    
    processRFIDScan: function(uid) {
        // Move the existing processRFIDScan function code here
        // This is the function that processes the RFID scan
        // You can copy the implementation from the existing function
        // Make sure to use 'this' to reference other methods of the rfidReader object
        
        // For example:
        // this.handleSuccessfulScan(...)
        
        // The rest of your existing processRFIDScan function...
    }
    
    // Add other functions as methods here
};

// Keep the standalone functions for backward compatibility
function initializeNFCKeyboardCapture() {
    if (typeof window.rfidReader !== 'undefined' && 
        typeof window.rfidReader.initializeNFCKeyboardCapture === 'function') {
        window.rfidReader.initializeNFCKeyboardCapture();
    } else {
        console.warn('rfidReader object not available, using fallback');
        // Fallback implementation if needed
    }
}

function processRFIDScan(uid) {
    if (typeof window.rfidReader !== 'undefined' && 
        typeof window.rfidReader.processRFIDScan === 'function') {
        window.rfidReader.processRFIDScan(uid);
    } else {
        console.warn('rfidReader object not available, using fallback');
        // Fallback implementation if needed
    }
}

// Make sure the function is exposed to the window object
if (typeof window.rfidReader === 'undefined') {
    window.rfidReader = {};
}

// Expose the initializeNFCKeyboardCapture function
window.rfidReader.initializeNFCKeyboardCapture = initializeNFCKeyboardCapture;

// Also expose the initializeRFIDScan function if it's not already exposed
window.rfidReader.initializeRFIDScan = initializeRFIDScan;

/**
 * Process RFID scan from keyboard input
 * @param {string} rfidUid - The RFID card UID
 */
function processRFIDScan(rfidUid) {
    // Get UI elements
    const scanStatus = document.getElementById('scanStatus');
    const scanResult = document.getElementById('scanResult');
    
    if (!scanStatus || !scanResult) {
        console.error('RFID scan UI elements not found');
        return;
    }
    
    // Update UI to show processing
    scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>' + 
                          '<p class="text-light">Processing RFID card...</p>';
    
    // Send the RFID UID to the API
    fetch(RFID_CONFIG.apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rfid_uid: rfidUid,
            attendance_type: 'Auto'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Card detected successfully
            handleSuccessfulScan(data, scanStatus, scanResult, null);
        } else if (data.status === 'unregistered') {
            // Unregistered card detected
            handleUnregisteredCard(data, scanStatus, scanResult);
        } else {
            // Error processing card
            stopRFIDScan(scanStatus, data.message || 'Error processing RFID card');
        }
    })
    .catch(error => {
        console.error('RFID Scan Error:', error);
        stopRFIDScan(scanStatus, 'Error communicating with the server');
    });
}

/**
 * Initialize RFID scanning process
 * @param {string} mode - Scan mode ('read_only' or 'attendance')
 * @param {Function} callback - Optional callback function to execute after successful scan
 */
function initializeRFIDScan(mode = 'read_only', callback = null) {
    // Set current scan mode
    currentScanMode = mode || RFID_CONFIG.readMode;
    
    // Get UI elements
    const scanStatus = document.getElementById('scanStatus');
    const scanResult = document.getElementById('scanResult');
    
    if (!scanStatus || !scanResult) {
        console.error('RFID scan UI elements not found');
        return;
    }
    
    // Reset UI
    scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>' + 
                          '<p class="text-light">Scanning... Please place the RFID card on the reader.</p>';
    scanResult.innerHTML = '';
    scanResult.classList.add('d-none');
    
    // Set scanning state
    isScanning = true;
    
    // Clear any existing timers
    if (rfidScanTimer) clearTimeout(rfidScanTimer);
    if (rfidPollInterval) clearInterval(rfidPollInterval);
    
    // Start polling for RFID card
    startRFIDPolling(scanStatus, scanResult, callback);
    
    // Set timeout for scanning
    rfidScanTimer = setTimeout(() => {
        stopRFIDScan(scanStatus, 'Scan timeout. Please try again.');
    }, RFID_CONFIG.scanTimeout);
}

/**
 * Start polling for RFID card detection
 */
function startRFIDPolling(scanStatus, scanResult, callback) {
    // In a real implementation, this would connect to the actual RFID hardware
    // For this simulation, we'll use a simulated API endpoint
    
    rfidPollInterval = setInterval(() => {
        if (!isScanning) {
            clearInterval(rfidPollInterval);
            return;
        }
        
        // Simulate RFID scan by calling the API
        simulateRFIDScan(scanStatus, scanResult, callback);
    }, RFID_CONFIG.pollInterval);
}

/**
 * Simulate RFID scan by calling the API
 */
// Add this function to handle unregistered cards
function handleUnregisteredCard(data, scanStatus, scanResult) {
    // Temporarily pause scanning
    clearTimeout(rfidScanTimer);
    clearInterval(rfidPollInterval);
    
    // Update UI
    scanStatus.innerHTML = '<i class="fas fa-user-plus fa-3x text-info mb-3"></i>' + 
                          '<p class="text-light">Unregistered RFID Card</p>';
    
    // Display registration form
    let resultHTML = `
        <div class="alert alert-info mb-3">
            <h5><i class="fas fa-info-circle me-2"></i>New RFID Card Detected</h5>
            <p>This card (UID: ${data.rfid_uid}) is not registered in the system.</p>
        </div>
        <div class="card bg-dark">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Student Registration Form</h5>
            </div>
            <div class="card-body">
                <form id="rfidRegistrationForm">
                    <input type="hidden" name="rfid_uid" value="${data.rfid_uid}">
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="student_number" class="form-label">Student ID/Number *</label>
                            <input type="text" class="form-control" id="student_number" name="student_number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="year_level" class="form-label">Year Level</label>
                            <select class="form-select" id="year_level" name="year_level">
                                <option value="">Select Year Level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="5th Year">5th Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <!-- Departments will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="course_id" class="form-label">Course</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">Select Course</option>
                                <!-- Courses will be loaded dynamically -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary" onclick="cancelRegistration()">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register & Record Attendance
                        </button>
                    </div>
                </form>
                <div id="registrationMessage" class="mt-3 d-none"></div>
            </div>
        </div>
    `;
    
    scanResult.innerHTML = resultHTML;
    scanResult.classList.remove('d-none');
    
    // Load departments and courses
    loadDepartmentsAndCourses();
    
    // Add event listener for form submission
    document.getElementById('rfidRegistrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        registerStudent(this);
    });
    
    // Add a cancel button event listener that restarts scanning
    document.querySelector('button[onclick="cancelRegistration()"]').addEventListener('click', function() {
        setTimeout(() => {
            isScanning = true;
            startRFIDPolling(scanStatus, scanResult, null);
        }, 1000);
    });
}

// Function to load departments and courses
function loadDepartmentsAndCourses() {
    // Load departments
    fetch('../api/get-departments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const departmentSelect = document.getElementById('department_id');
                data.departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.department_id;
                    option.textContent = dept.department_name;
                    departmentSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading departments:', error));
    
    // Load courses
    fetch('../api/get-courses.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const courseSelect = document.getElementById('course_id');
                data.courses.forEach(course => {
                    const option = document.createElement('option');
                    option.value = course.course_id;
                    option.textContent = course.course_name;
                    courseSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading courses:', error));
}

// Function to register a student
function registerStudent(form) {
    const formData = new FormData(form);
    const registrationData = {};
    
    formData.forEach((value, key) => {
        registrationData[key] = value;
    });
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...';
    submitBtn.disabled = true;
    
    // Send registration request
    fetch('../api/register-rfid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(registrationData)
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('registrationMessage');
        messageDiv.classList.remove('d-none');
        
        if (data.success) {
            // Registration successful
            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i>Registration Successful!</h5>
                    <p>Student ${data.data.student_name} has been registered and attendance recorded.</p>
                </div>
            `;
            form.reset();
            form.classList.add('d-none');
            
            // Play success sound
            playSound('success');
            
            // Restart scanning after 3 seconds
            setTimeout(() => {
                if (typeof window.checkRFIDReaderAndScan === 'function') {
                    window.checkRFIDReaderAndScan();
                } else {
                    window.location.reload(); // Fallback to page reload
                }
            }, 3000);
        } else {
            // Registration failed
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-circle me-2"></i>Registration Failed</h5>
                    <p>${data.message}</p>
                </div>
            `;
            
            // Play error sound
            playSound('error');
        }
        
        // Reset button
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        console.error('Registration error:', error);
        document.getElementById('registrationMessage').innerHTML = `
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-circle me-2"></i>System Error</h5>
                <p>An error occurred during registration. Please try again.</p>
            </div>
        `;
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });
}

// Function to cancel registration
function cancelRegistration() {
    // Reset the scan UI
    const scanStatus = document.getElementById('scanStatus');
    const scanResult = document.getElementById('scanResult');
    
    scanStatus.innerHTML = '<i class="fas fa-wifi scanner-icon"></i>' + 
                          '<h4 class="text-light mb-4">RFID Scanner Ready</h4>' + 
                          '<p class="text-muted mb-4">Restarting scanner...</p>';
    
    scanResult.innerHTML = '';
    scanResult.classList.add('d-none');
    
    // Restart scanning
    setTimeout(() => {
        isScanning = true;
        // Clear any existing timers to prevent conflicts
        if (rfidScanTimer) clearTimeout(rfidScanTimer);
        if (rfidPollInterval) clearInterval(rfidPollInterval);
        
        if (typeof window.checkRFIDReaderAndScan === 'function') {
            window.checkRFIDReaderAndScan();
        } else {
            // Start polling for RFID card
            startRFIDPolling(scanStatus, scanResult, null);
            
            // Set timeout for scanning
            rfidScanTimer = setTimeout(() => {
                stopRFIDScan(scanStatus, 'Scan timeout. Please try again.');
                
                // Even after timeout, try to restart scanning
                setTimeout(() => {
                    isScanning = true;
                    startRFIDPolling(scanStatus, scanResult, null);
                }, 3000);
            }, RFID_CONFIG.scanTimeout);
        }
    }, 1000);
}

// Modify the simulateRFIDScan function to handle NFC cards better
function simulateRFIDScan(scanStatus, scanResult, callback) {
    
    // Send the simulated RFID UID to the API
    fetch(RFID_CONFIG.apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rfid_uid: rfidUid.toString(),
            attendance_type: 'Auto',
            mode: currentScanMode // Include the mode in the request body
        })
    })
    // In the simulateRFIDScan function, modify the fetch error handling
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        // Check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // If not JSON, get the text and throw an error with the content
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Invalid response format. Expected JSON.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        // Clear any existing timers to prevent conflicts
        clearTimeout(rfidScanTimer);
        clearInterval(rfidPollInterval);
        
        if (data.success) {
            // Card detected successfully
            handleSuccessfulScan(data, scanStatus, scanResult, callback);
        } else if (data.status === 'unregistered') {
            // Unregistered card detected
            handleUnregisteredCard(data, scanStatus, scanResult);
        } else {
            // Error processing card
            stopRFIDScan(scanStatus, data.message || 'Error processing RFID card');
        }
    })
    .catch(error => {
        console.error('RFID Scan Error:', error);
        stopRFIDScan(scanStatus, 'Error communicating with the server. Please check the server logs.');
    });
}

// Update the cancelRegistration function to ensure scanning restarts
function cancelRegistration() {
    // Reset the scan UI
    const scanStatus = document.getElementById('scanStatus');
    const scanResult = document.getElementById('scanResult');
    
    scanStatus.innerHTML = '<i class="fas fa-wifi scanner-icon"></i>' + 
                          '<h4 class="text-light mb-4">RFID Scanner Ready</h4>' + 
                          '<p class="text-muted mb-4">Restarting scanner...</p>';
    
    scanResult.innerHTML = '';
    scanResult.classList.add('d-none');
    
    // Restart scanning
    setTimeout(() => {
        isScanning = true;
        // Clear any existing timers to prevent conflicts
        if (rfidScanTimer) clearTimeout(rfidScanTimer);
        if (rfidPollInterval) clearInterval(rfidPollInterval);
        
        if (typeof window.checkRFIDReaderAndScan === 'function') {
            window.checkRFIDReaderAndScan();
        } else {
            // Start polling for RFID card
            startRFIDPolling(scanStatus, scanResult, null);
            
            // Set timeout for scanning
            rfidScanTimer = setTimeout(() => {
                stopRFIDScan(scanStatus, 'Scan timeout. Please try again.');
                
                // Even after timeout, try to restart scanning
                setTimeout(() => {
                    isScanning = true;
                    startRFIDPolling(scanStatus, scanResult, null);
                }, 3000);
            }, RFID_CONFIG.scanTimeout);
        }
    }, 1000);
}

/**
 * Handle successful RFID scan
 */
function handleSuccessfulScan(data, scanStatus, scanResult, callback) {
    // Update UI
    scanStatus.innerHTML = '<i class="fas fa-check-circle fa-3x text-success mb-3"></i>' + 
                          '<p class="text-light">Card detected!</p>';
    
    // Display result
    let resultHTML = '';
    
    // Check if this is a duplicate scan
    const isDuplicateScan = data.message && data.message.includes('Recent scan detected');
    
    if (isDuplicateScan) {
        // Add a clear duplicate scan indicator
        resultHTML = '<div class="alert alert-warning">';
        resultHTML += '<h5><i class="fas fa-exclamation-triangle me-2"></i>Duplicate Scan Detected</h5>';
        resultHTML += '<p>This card was already scanned in the last 30 seconds.</p>';
    } else {
        resultHTML = '<div class="alert alert-success">';
    }
    
    if (currentScanMode === 'read_only') {
        resultHTML += `<p class="mb-1"><strong>RFID UID:</strong> ${data.rfid_uid || (data.data && data.data.rfid_uid) || 'N/A'}</p>`;
    } else {
        // Attendance mode - show student info
        if (data.data) {
            resultHTML += `
                <p class="mb-1"><strong>Student:</strong> ${data.data.student_name || 'N/A'}</p>
                <p class="mb-1"><strong>ID:</strong> ${data.data.student_number || 'N/A'}</p>
                <p class="mb-1"><strong>Type:</strong> ${data.data.attendance_type || 'N/A'}</p>
                <p class="mb-1"><strong>Time:</strong> ${formatDateTime(data.data.scan_time || new Date())}</p>
            `;
        } else {
            resultHTML += `<p class="mb-1"><strong>RFID UID:</strong> ${data.rfid_uid || 'N/A'}</p>`;
            
        }
        
        if (isDuplicateScan) {
            resultHTML += `<p class="mb-1"><strong>Note:</strong> Previous attendance record is being used.</p>`;
        }
    }
    
    resultHTML += '</div>';
    scanResult.innerHTML = resultHTML;
    scanResult.classList.remove('d-none');
    
    // Play appropriate sound
    if (isDuplicateScan) {
        playSound('error'); // Use warning sound for duplicate scans
    } else {
        playSound('success');
    }
    
    // Dispatch event with RFID data
    window.dispatchEvent(new CustomEvent('rfidScanned', {
        detail: { 
            rfidUid: data.rfid_uid || data.data.rfid_uid,
            studentData: data.data || null,
            success: true,
            isDuplicate: isDuplicateScan
        }
    }));
    
    // Execute callback if provided
    if (typeof callback === 'function') {
        callback(data);
    }
    
    // Restart scanning after 3 seconds
    setTimeout(() => {
        isScanning = true;
        // Clear any existing timers to prevent conflicts
        if (rfidScanTimer) clearTimeout(rfidScanTimer);
        if (rfidPollInterval) clearInterval(rfidPollInterval);
        
        // Start polling for RFID card
        startRFIDPolling(scanStatus, scanResult, callback);
        
        // Set timeout for scanning
        rfidScanTimer = setTimeout(() => {
            stopRFIDScan(scanStatus, 'Scan timeout. Please try again.');
        }, RFID_CONFIG.scanTimeout);
    }, 3000);
}

// Update handleInvalidCard function
function handleInvalidCard(data, scanStatus, scanResult, callback) {
    // Update UI
    scanStatus.innerHTML = '<i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>' + 
                          `<p class="text-light">${data.message || 'Invalid RFID card'}</p>`;
    
    // Display result
    let resultHTML = `<div class="alert alert-warning">
        <p class="mb-1"><strong>RFID UID:</strong> ${data.rfid_uid}</p>
        <p class="mb-0"><strong>Status:</strong> ${data.message}</p>
    </div>`;
    
    scanResult.innerHTML = resultHTML;
    scanResult.classList.remove('d-none');
    
    // Play error sound
    playSound('error');
    
    // Dispatch event with RFID data
    window.dispatchEvent(new CustomEvent('rfidScanned', {
        detail: { 
            rfidUid: data.rfid_uid,
            message: data.message,
            success: false
        }
    }));
    
    // Restart scanning after 3 seconds
    setTimeout(() => {
        isScanning = true;
        // Clear any existing timers to prevent conflicts
        if (rfidScanTimer) clearTimeout(rfidScanTimer);
        if (rfidPollInterval) clearInterval(rfidPollInterval);
        
        // Start polling for RFID card
        startRFIDPolling(scanStatus, scanResult, callback);
        
        // Set timeout for scanning
        rfidScanTimer = setTimeout(() => {
            stopRFIDScan(scanStatus, 'Scan timeout. Please try again.');
        }, RFID_CONFIG.scanTimeout);
    }, 3000);
}

/**
 * Stop RFID scanning with error message
 */
function stopRFIDScan(scanStatus, errorMessage) {
    isScanning = false;
    clearInterval(rfidPollInterval);
    
    scanStatus.innerHTML = '<i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>' + 
                          `<p class="text-light">${errorMessage}</p>`;
    
    // Play error sound
    playSound('error');
}

/**
 * Format date and time for display
 */
function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString();
}

/**
 * Play sound effect
 */
function playSound(type) {
    // Check if Audio API is supported
    if (typeof Audio !== 'undefined') {
        let soundPath;
        
        if (type === 'success') {
            soundPath = '/rfid/assets/sounds/success.mp3';
        } else if (type === 'error') {
            soundPath = '/rfid/assets/sounds/error.mp3';
        } else {
            return;
        }
        
        // Check if file exists before playing
        fetch(soundPath, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    const sound = new Audio(soundPath);
                    sound.play().catch(e => {
                        console.log('Sound play prevented:', e);
                    });
                } else {
                    console.log(`Sound file not found: ${soundPath}`);
                }
            })
            .catch(error => {
                console.log(`Error checking sound file: ${error}`);
            });
    }
}

/**
 * Manual RFID input for testing
 */
function manualRFIDInput() {
    const rfidInput = prompt('Enter RFID UID for testing:');
    if (rfidInput && rfidInput.trim() !== '') {
        // Simulate a successful scan with the manual input
        const scanStatus = document.getElementById('scanStatus');
        const scanResult = document.getElementById('scanResult');
        
        if (scanStatus && scanResult) {
            handleSuccessfulScan({
                success: true,
                rfid_uid: rfidInput.trim()
            }, scanStatus, scanResult);
        }
    }
}

// Export functions for use in other scripts
// Add this at the end of the file to expose the RFID reader functions to the window object

// Expose RFID reader functions to the window object for external access
window.rfidReader = {
    initializeRFIDScan,
    initializeNFCKeyboardCapture,
    processRFIDScan,
    stopRFIDScan
};

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('RFID Reader module initialized');
    // You can add any initialization code here if needed
});

// NFC Reader Keyboard Input Handling
let nfcInputBuffer = '';
let nfcInputTimeout = null;
let isCapturingNFC = false;

// Function to initialize NFC keyboard input capture
function initializeNFCKeyboardCapture() {
    // Add event listener for keyboard input
    document.addEventListener('keydown', handleNFCKeyboardInput);
    isCapturingNFC = true;
    console.log('NFC keyboard input capture initialized');
    
    // Update UI to show ready state
    const scanStatus = document.getElementById('scanStatus');
    if (scanStatus) {
        scanStatus.innerHTML = '<i class="fas fa-wifi scanner-icon text-primary"></i>' +
                              '<h4 class="text-light mb-4">USB NFC Reader Ready</h4>' +
                              '<p class="text-muted">Please scan your NFC card on the reader</p>';
    }
}

// Function to handle keyboard input from NFC reader
function handleNFCKeyboardInput(event) {
    // Most USB NFC readers act as keyboard devices and send digits followed by Enter
    
    // Check if input is coming from a form field
    const activeElement = document.activeElement;
    const isInputField = activeElement.tagName === 'INPUT' || 
                         activeElement.tagName === 'TEXTAREA' || 
                         activeElement.isContentEditable;
    
    // Only capture if not in an input field
    if (!isInputField) {
        // If Enter key is pressed, process the collected input
        if (event.key === 'Enter') {
            if (nfcInputBuffer.length > 0) {
                console.log('NFC card detected via USB reader:', nfcInputBuffer);
                
                // Process the NFC card UID
                processNFCCardUID(nfcInputBuffer);
                
                // Clear the buffer
                nfcInputBuffer = '';
                
                // Prevent default action (form submission, etc)
                event.preventDefault();
            }
        } else if (/^\d$/.test(event.key)) {
            // Only add numeric characters to the buffer
            nfcInputBuffer += event.key;
            
            // Reset the timeout
            if (nfcInputTimeout) {
                clearTimeout(nfcInputTimeout);
            }
            
            // Set a timeout to clear the buffer if no more input is received
            nfcInputTimeout = setTimeout(() => {
                nfcInputBuffer = '';
            }, 1000); // Clear after 1 second of inactivity
            
            // Prevent default action to avoid typing in other inputs
            event.preventDefault();
        }
    }
}

// Function to process NFC card UID
// Function to process NFC card UID
function processNFCCardUID(uid) {
    // Get UI elements
    const scanStatus = document.getElementById('scanStatus');
    const scanResult = document.getElementById('scanResult');
    
    if (!scanStatus || !scanResult) {
        console.error('RFID scan UI elements not found');
        return;
    }
    
    // Update UI to show scanning in progress
    scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>' + 
                          '<p class="text-light">Processing NFC card...</p>';
    scanResult.innerHTML = '';
    scanResult.classList.add('d-none');
    
    // Send the NFC card UID to the API
    fetch(RFID_CONFIG.apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rfid_uid: uid.toString(),
            attendance_type: 'Auto'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Card detected successfully
            handleSuccessfulScan(data, scanStatus, scanResult, null);
            // Play success sound
            playSound('success');
        } else if (data.status === 'unregistered') {
            // Unregistered card detected
            handleUnregisteredCard(data, scanStatus, scanResult);
        } else {
            // Card detected but not valid
            handleInvalidCard(data, scanStatus, scanResult);
            // Play error sound
            playSound('error');
        }
        
        // Refresh attendance data if the function exists
        if (typeof window.loadAttendanceData === 'function') {
            window.loadAttendanceData();
        }
    })
    .catch(error => {
        console.error('NFC Card Processing Error:', error);
        scanStatus.innerHTML = '<i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>' + 
                              '<p class="text-light">Error connecting to server</p>';
        // Play error sound
        playSound('error');
    });
}