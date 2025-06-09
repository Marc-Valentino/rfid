// Time In button click handler
document.getElementById('timeInBtn').addEventListener('click', function() {
    currentMode = 'Time In';  // Changed from 'in' to 'Time In'
    enableScanning('Time In');
});

// Time Out button click handler
document.getElementById('timeOutBtn').addEventListener('click', function() {
    currentMode = 'Time Out';  // Changed from 'out' to 'Time Out'
    enableScanning('Time Out');
});

// Update the function that processes RFID scans
function processRFIDScan(rfidUid) {
    // Get the current attendance mode from the active button
    const currentMode = document.querySelector('.attendance-mode.active').dataset.mode;
    
    fetch('../api/rfid-scan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rfid_uid: rfidUid,
            attendance_type: currentMode // This will be either 'Time In' or 'Time Out'
        })
    })
    .then(response => response.json())
    .then(data => {
        handleScanResult(data);
        loadAttendanceData(); // Refresh the attendance table
    })
    .catch(error => {
        console.error('Error:', error);
        handleScanError(error);
    });
}