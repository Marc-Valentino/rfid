/**
 * RFID Attendance System - Dashboard JavaScript
 * Provides dashboard-specific functionality including real-time updates,
 * chart visualizations, and dashboard widgets management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard components
    initializeDashboardStats();
    initializeAttendanceCharts();
    setupAutoRefresh();
    
    // Setup quick action buttons
    setupQuickActions();
});

/**
 * Initialize dashboard statistics with counters animation
 */
function initializeDashboardStats() {
    const statCounters = document.querySelectorAll('.card h4');
    
    statCounters.forEach(counter => {
        const target = parseInt(counter.textContent);
        let count = 0;
        const duration = 1000; // 1 second animation
        const frameDuration = 1000 / 60; // 60fps
        const totalFrames = Math.round(duration / frameDuration);
        const increment = target / totalFrames;
        
        // Only animate if it's a number and greater than 0
        if (!isNaN(target) && target > 0) {
            const timer = setInterval(() => {
                count += increment;
                if (count >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(count);
                }
            }, frameDuration);
        }
    });
}

/**
 * Initialize attendance charts if Chart.js is available
 */
function initializeAttendanceCharts() {
    // Check if we have the charts container and Chart.js is loaded
    const weeklyChartEl = document.getElementById('weeklyAttendanceChart');
    const dailyChartEl = document.getElementById('dailyAttendanceChart');
    
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded. Charts will not be displayed.');
        return;
    }
    
    // If we have the weekly chart element, initialize it
    if (weeklyChartEl) {
        initializeWeeklyChart(weeklyChartEl);
    }
    
    // If we have the daily chart element, initialize it
    if (dailyChartEl) {
        initializeDailyChart(dailyChartEl);
    }
}

/**
 * Initialize weekly attendance chart
 */
function initializeWeeklyChart(chartElement) {
    // Fetch weekly attendance data
    fetch('../api/reports-data.php?type=weekly')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error fetching weekly data:', data.message);
                return;
            }
            
            const labels = data.data.map(item => item.day_name);
            const timeInData = data.data.map(item => item.time_in_count);
            const timeOutData = data.data.map(item => item.time_out_count);
            
            new Chart(chartElement, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Time In',
                            data: timeInData,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Time Out',
                            data: timeOutData,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#ffffff'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Weekly Attendance',
                            color: '#ffffff'
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#cccccc' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#cccccc' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            chartElement.innerHTML = '<div class="alert alert-danger">Failed to load chart data</div>';
        });
}

/**
 * Initialize daily attendance chart
 */
function initializeDailyChart(chartElement) {
    // Fetch daily attendance data
    fetch('../api/reports-data.php?type=daily')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error fetching daily data:', data.message);
                return;
            }
            
            const labels = data.data.map(item => formatTime(item.hour_block));
            const countData = data.data.map(item => item.scan_count);
            
            new Chart(chartElement, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Scans',
                        data: countData,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#ffffff'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Today\'s Attendance by Hour',
                            color: '#ffffff'
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#cccccc' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#cccccc' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            chartElement.innerHTML = '<div class="alert alert-danger">Failed to load chart data</div>';
        });
}

/**
 * Format hour block (e.g., "08:00") to a more readable format
 */
function formatTime(hourBlock) {
    return hourBlock + ' hrs';
}

/**
 * Setup auto-refresh for dashboard data
 */
function setupAutoRefresh() {
    const refreshInterval = 60000; // 1 minute
    
    setInterval(() => {
        updateDashboardData();
    }, refreshInterval);
}

/**
 * Update dashboard data without full page reload
 */
function updateDashboardData() {
    // Update recent attendance
    fetch('../api/get-attendance.php?limit=10')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error fetching attendance data:', data.message);
                return;
            }
            
            updateRecentAttendanceTable(data.data);
            updateStatCounters(data.summary);
        })
        .catch(error => {
            console.error('Error updating dashboard data:', error);
        });
}

/**
 * Update the recent attendance table with new data
 */
function updateRecentAttendanceTable(attendanceData) {
    const tableBody = document.querySelector('.table-dark tbody');
    if (!tableBody) return;
    
    // If no data, show empty message
    if (attendanceData.length === 0) {
        const container = tableBody.closest('.card-body');
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No attendance records for today</p>
            </div>
        `;
        return;
    }
    
    // Clear existing rows
    tableBody.innerHTML = '';
    
    // Add new rows
    attendanceData.forEach(record => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong>${record.first_name} ${record.last_name}</strong><br>
                <small class="text-muted">${record.student_number}</small>
            </td>
            <td>${record.department_name || 'N/A'}</td>
            <td>
                <span class="badge ${record.attendance_type === 'Time In' ? 'bg-success' : 'bg-info'}">
                    ${record.attendance_type}
                </span>
            </td>
            <td>${formatTime(record.scan_time)}</td>
            <td>
                <span class="badge bg-success">Verified</span>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

/**
 * Update statistic counters with new data
 */
function updateStatCounters(summary) {
    if (!summary) return;
    
    // Update total students counter
    const totalStudentsEl = document.querySelector('.card:nth-child(1) h4');
    if (totalStudentsEl && summary.total_students) {
        totalStudentsEl.textContent = summary.total_students;
    }
    
    // Update today's scans counter
    const todayScansEl = document.querySelector('.card:nth-child(2) h4');
    if (todayScansEl && summary.today_scans) {
        todayScansEl.textContent = summary.today_scans;
    }
    
    // Update present today counter
    const presentTodayEl = document.querySelector('.card:nth-child(3) h4');
    if (presentTodayEl && summary.present_today) {
        presentTodayEl.textContent = summary.present_today;
    }
    
    // Update absent today counter
    const absentTodayEl = document.querySelector('.card:nth-child(4) h4');
    if (absentTodayEl && summary.absent_today) {
        absentTodayEl.textContent = summary.absent_today;
    }
}

/**
 * Setup quick action buttons
 */
function setupQuickActions() {
    // Quick scan button
    const quickScanBtn = document.getElementById('quickScanBtn');
    if (quickScanBtn) {
        quickScanBtn.addEventListener('click', () => {
            // Open RFID scan modal
            const scanModal = new bootstrap.Modal(document.getElementById('rfidScanModal'));
            scanModal.show();
            
            // Initialize RFID scan
            if (typeof initializeRFIDScan === 'function') {
                initializeRFIDScan();
            }
        });
    }
    
    // Quick add student button
    const quickAddStudentBtn = document.getElementById('quickAddStudentBtn');
    if (quickAddStudentBtn) {
        quickAddStudentBtn.addEventListener('click', () => {
            window.location.href = 'add-student.php';
        });
    }
}