class RFIDScanner {
    constructor() {
        this.rfidBuffer = '';
        this.lastKeystroke = Date.now();
        this.keyTimeout = 50; // Adjusted for better RFID reading
        this.isListening = true;
    }

    init() {
        this.setupKeyboardListener();
        this.setupAttendanceButtons();
        this.setupCourseSelection();
    }

    setupKeyboardListener() {
        document.addEventListener('keypress', (e) => {
            if (!this.isListening) return;

            const currentTime = Date.now();
            if (currentTime - this.lastKeystroke > this.keyTimeout) {
                this.rfidBuffer = '';
            }
            this.lastKeystroke = currentTime;

            if (e.key === 'Enter') {
                if (this.rfidBuffer) {
                    this.handleRFIDScan(this.rfidBuffer);
                }
                this.rfidBuffer = '';
                e.preventDefault();
            } else {
                this.rfidBuffer += e.key;
            }
        });
    }

    handleRFIDScan(rfidUid) {
        const courseSelect = document.getElementById('course_select');
        const courseId = courseSelect.value;

        if (!courseId) {
            this.showError('Please select a course first');
            return;
        }

        const attendanceType = document.querySelector('.attendance-mode.active').dataset.mode;
        this.updateScannerStatus('processing');

        const formData = new FormData();
        formData.append('rfid_uid', rfidUid);
        formData.append('course_id', courseId);
        formData.append('attendance_type', attendanceType);

        fetch('scan-attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess(data);
            } else {
                this.showError(data.message || 'Failed to process scan');
            }
            this.refreshAttendanceTable();
        })
        .catch(error => {
            console.error('Scan error:', error);
            this.showError('Failed to process scan. Please try again.');
        });
    }

    showSuccess(data) {
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');
        scanResult.innerHTML = `
            <div class="alert alert-success">
                <h4 class="alert-heading">
                    <i class="fas fa-check-circle me-2"></i>Attendance Recorded
                </h4>
                <p class="mb-1"><strong>Name:</strong> ${data.student_name}</p>
                <p class="mb-1"><strong>ID:</strong> ${data.student_number}</p>
                <p class="mb-0"><strong>Type:</strong> ${data.attendance_type}</p>
            </div>
        `;
        setTimeout(() => this.resetScannerState(), 3000);
    }

    showError(message) {
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');
        scanResult.innerHTML = `
            <div class="alert alert-danger">
                <h4 class="alert-heading">
                    <i class="fas fa-exclamation-circle me-2"></i>Error
                </h4>
                <p class="mb-0">${message}</p>
            </div>
        `;
        setTimeout(() => this.resetScannerState(), 3000);
    }

    refreshAttendanceTable() {
        const courseId = document.getElementById('course_select').value;
        if (!courseId) return;

        fetch(`get-attendance.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('attendanceRecords');
                tbody.innerHTML = this.generateTableRows(data);
            })
            .catch(error => console.error('Error updating table:', error));
    }

    generateTableRows(data) {
        if (!data || data.length === 0) {
            return '<tr><td colspan="6" class="text-center">No records found</td></tr>';
        }

        return data.map(record => `
            <tr>
                <td>${new Date(record.scan_time).toLocaleTimeString()}</td>
                <td>${record.student_name}</td>
                <td>${record.student_number}</td>
                <td>${record.course_name}</td>
                <td>
                    <span class="badge ${record.attendance_type === 'Time In' ? 'bg-success' : 'bg-danger'}">
                        ${record.attendance_type}
                    </span>
                </td>
                <td><span class="badge bg-success">Verified</span></td>
            </tr>
        `).join('');
    }

    updateScannerStatus(status) {
        const scanStatus = document.getElementById('scanStatus');
        if (status === 'processing') {
            scanStatus.innerHTML = `
                <i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>
                <h4 class="text-light mb-4">Processing Scan...</h4>
                <p class="text-muted">Please wait</p>
            `;
        } else {
            scanStatus.innerHTML = `
                <i class="fas fa-wifi scanner-icon"></i>
                <h4 class="text-light mb-4">Ready to Scan</h4>
                <p class="text-muted">Please scan your RFID card</p>
            `;
        }
    }

    resetScannerState() {
        this.updateScannerStatus('ready');
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.add('d-none');
    }

    setupAttendanceButtons() {
        document.querySelectorAll('.attendance-mode').forEach(button => {
            button.addEventListener('click', (e) => {
                document.querySelectorAll('.attendance-mode').forEach(btn => 
                    btn.classList.remove('active'));
                e.target.closest('.attendance-mode').classList.add('active');
            });
        });
    }

    setupCourseSelection() {
        const courseSelect = document.getElementById('course_select');
        courseSelect.addEventListener('change', () => {
            if (courseSelect.value) {
                this.refreshAttendanceTable();
            }
        });
    }
}

// Initialize scanner when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.scanner = new RFIDScanner();
    window.scanner.init();
});