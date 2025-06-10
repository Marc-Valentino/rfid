class AttendanceScanner {
    constructor() {
        this.isScanning = false;
        this.rfidBuffer = '';
        this.lastKeystroke = Date.now();
        this.scanTimeout = 100;
    }

    init() {
        this.setupKeyboardListener();
        this.setupAttendanceButtons();
        this.initializeTable();
        this.setupCourseSelection();
    }

    setupAttendanceButtons() {
        const timeInBtn = document.querySelector('[data-mode="Time In"]');
        const timeOutBtn = document.querySelector('[data-mode="Time Out"]');

        if (timeInBtn && timeOutBtn) {
            [timeInBtn, timeOutBtn].forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.attendance-mode').forEach(b => 
                        b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });
        }
    }

    setupKeyboardListener() {
        document.addEventListener('keypress', (e) => {
            const currentTime = Date.now();
            
            if (currentTime - this.lastKeystroke > this.scanTimeout) {
                this.rfidBuffer = '';
            }
            
            this.lastKeystroke = currentTime;
            
            if (e.key !== 'Enter') {
                this.rfidBuffer += e.key;
            } else if (this.rfidBuffer.length > 0) {
                this.processScan(this.rfidBuffer);
                this.rfidBuffer = '';
                e.preventDefault();
            }
        });
    }

    processScan(rfidUid) {
        const scanStatus = document.getElementById('scanStatus');
        const courseId = document.getElementById('course_select').value;
        const attendanceType = document.querySelector('.attendance-mode.active')?.dataset.mode || 'Time In';

        scanStatus.innerHTML = `
            <i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>
            <h4 class="text-light mb-4">Processing Scan...</h4>
            <p class="text-muted">Reading RFID: ${rfidUid}</p>
        `;

        fetch('../api/process-attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                rfid_uid: rfidUid,
                course_id: courseId,
                attendance_type: attendanceType
            })
        })
        .then(response => response.json())
        .then(result => {
            this.handleScanResult(result);
            this.refreshAttendanceTable();
        })
        .catch(error => {
            console.error('Scan error:', error);
            this.showError('Failed to process scan');
        });
    }

    handleScanResult(result) {
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');

        if (result.success) {
            scanResult.innerHTML = `
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Success!</h4>
                    <p>Student: ${result.data.student_name}</p>
                    <p>ID: ${result.data.student_number}</p>
                    <p>Type: ${result.data.attendance_type}</p>
                </div>
            `;
        } else {
            scanResult.innerHTML = `
                <div class="alert alert-danger">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i>Error</h4>
                    <p>${result.message}</p>
                </div>
            `;
        }

        setTimeout(() => {
            this.resetScannerState();
        }, 3000);
    }

    resetScannerState() {
        const scanStatus = document.getElementById('scanStatus');
        scanStatus.innerHTML = `
            <i class="fas fa-wifi scanner-icon"></i>
            <h4 class="text-light mb-4">Ready to Scan</h4>
            <p class="text-muted">Please scan your RFID card</p>
        `;

        const scanResult = document.getElementById('scanResult');
        scanResult.classList.add('d-none');
    }

    refreshAttendanceTable() {
        const tbody = document.getElementById('attendanceRecords');
        const date = document.getElementById('attendanceDate')?.value || new Date().toISOString().split('T')[0];

        fetch(`../api/get-attendance.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(record => `
                        <tr>
                            <td>${new Date(record.scan_time).toLocaleTimeString()}</td>
                            <td>${record.student_name}</td>
                            <td>${record.student_number}</td>
                            <td>${record.department || 'N/A'}</td>
                            <td>${record.course || 'N/A'}</td>
                            <td><span class="badge ${record.attendance_type === 'Time In' ? 'bg-success' : 'bg-danger'}">${record.attendance_type}</span></td>
                            <td><span class="badge bg-success">Verified</span></td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-muted">No records found</td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading attendance:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger">Error loading records</td>
                    </tr>
                `;
            });
    }
}

// Initialize scanner when document is ready
document.addEventListener('DOMContentLoaded', () => {
    const scanner = new AttendanceScanner();
    scanner.init();
});