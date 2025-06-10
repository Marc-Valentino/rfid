class AttendanceScanner {
    constructor() {
        this.isScanning = false;
        this.rfidBuffer = '';
        this.lastKeystroke = Date.now();
        this.scanTimeout = 100; // Milliseconds between keystrokes
    }

    init() {
        this.setupKeyboardListener();
        this.setupCourseSelection();
        this.setupManualEntry();
        this.initializeAutoRefresh();
    }

    setupKeyboardListener() {
        document.addEventListener('keypress', (e) => {
            if (!this.isScanning) return;
            
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

    startScanning() {
        const courseId = document.getElementById('course_select').value;
        if (!courseId) {
            this.showError('Please select a course first');
            return;
        }

        this.isScanning = true;
        this.updateScannerUI('scanning');
    }

    stopScanning() {
        this.isScanning = false;
        this.updateScannerUI('ready');
    }

    processScan(rfidUid) {
        const courseId = document.getElementById('course_select').value;
        const formData = new FormData();
        formData.append('action', 'scan');
        formData.append('rfid_uid', rfidUid);
        formData.append('course_id', courseId);

        fetch('scan-attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            this.handleScanResult(result);
            this.playSound(result.success ? 'success' : 'error');
            if (result.success) {
                setTimeout(() => this.refreshAttendanceTable(), 2000);
            }
        })
        .catch(error => {
            console.error('Scan error:', error);
            this.showError('Failed to process scan');
        });
    }

    updateScannerUI(state) {
        const scanStatus = document.getElementById('scanStatus');
        const scanResult = document.getElementById('scanResult');

        switch(state) {
            case 'scanning':
                scanStatus.innerHTML = this.getScanningHTML();
                break;
            case 'ready':
                scanStatus.innerHTML = this.getReadyHTML();
                break;
            default:
                scanStatus.innerHTML = this.getReadyHTML();
        }

        scanResult.classList.add('d-none');
        scanResult.innerHTML = '';
    }

    handleScanResult(result) {
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');
        scanResult.innerHTML = this.getScanResultHTML(result);
    }

    refreshAttendanceTable() {
        fetch('get-recent-scans.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('recentScansTable');
                tbody.innerHTML = this.generateTableRows(data);
            })
            .catch(error => console.error('Error updating table:', error));
    }

    showError(message) {
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');
        scanResult.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>${message}
            </div>`;
    }

    playSound(type) {
        // Implement sound feedback if needed
    }

    // Helper methods for HTML templates
    getScanningHTML() {
        return `
            <i class="fas fa-spinner fa-spin scanner-icon text-primary scan-animation"></i>
            <h4 class="text-light mb-4">Scanning in Progress...</h4>
            <p class="text-muted mb-4">Please place the RFID card on the reader.</p>
            <button class="btn btn-danger btn-lg" onclick="scanner.stopScanning()">
                <i class="fas fa-stop me-2"></i>Stop Scanning
            </button>`;
    }

    getReadyHTML() {
        return `
            <i class="fas fa-wifi scanner-icon"></i>
            <h4 class="text-light mb-4">RFID Scanner Ready</h4>
            <p class="text-muted mb-4">Click 'Start Scanning' to begin reading RFID cards</p>
            <button class="btn btn-primary btn-lg" onclick="scanner.startScanning()">
                <i class="fas fa-play me-2"></i>Start Scanning
            </button>
            <button class="btn btn-outline-secondary btn-lg ms-2" onclick="scanner.toggleManualEntry()">
                <i class="fas fa-keyboard me-2"></i>Manual Entry
            </button>`;
    }
}

// Initialize scanner when document is ready
const scanner = new AttendanceScanner();
document.addEventListener('DOMContentLoaded', () => scanner.init());