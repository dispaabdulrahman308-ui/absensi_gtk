// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if(sidebar) sidebar.classList.toggle('open');
}

// Close modal on outside click
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modal');
    if(modal && event.target === modal) {
        closeModal();
    }
});

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    if(!form) return true;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if(!input.value.trim()) {
            input.style.borderColor = '#e53e3e';
            isValid = false;
        } else {
            input.style.borderColor = '#cbd5e0';
        }
    });
    
    return isValid;
}

// Number format helper
function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// Date helper
function formatDate(dateString) {
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
}

// Export table to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if(!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const rowData = [];
        const cols = row.querySelectorAll('td, th');
        cols.forEach(col => {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
}

// Print element
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if(!element) return;
    
    const originalTitle = document.title;
    document.title = 'Print - ' + originalTitle;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head><title>${document.title}</title>
        <link rel="stylesheet" href="assets/css/style.css">
        </head>
        <body>${element.outerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
    
    document.title = originalTitle;
}

// Camera handler
class CameraHandler {
    constructor(videoElement, canvasElement) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.stream = null;
    }
    
    async start() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: true });
            this.video.srcObject = this.stream;
            return true;
        } catch(err) {
            console.error('Camera error:', err);
            return false;
        }
    }
    
    capture() {
        if(!this.video.videoWidth) return null;
        
        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
        this.canvas.getContext('2d').drawImage(this.video, 0, 0);
        return this.canvas.toDataURL('image/jpeg', 0.8);
    }
    
    stop() {
        if(this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
    }
}

// Geolocation handler
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if(!navigator.geolocation) {
            reject('Geolocation not supported');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            position => resolve({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy
            }),
            error => reject(error.message)
        );
    });
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.maxWidth = '300px';
    toast.style.zIndex = '10000';
    toast.style.margin = '0';
    toast.innerHTML = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Confirmation dialog
function confirmAction(message, callback) {
    if(confirm(message)) {
        callback();
    }
}

// Loader show/hide
function showLoader() {
    let loader = document.getElementById('global-loader');
    if(!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        loader.innerHTML = '<div style="font-size: 24px;">⏳ Loading...</div>';
        document.body.appendChild(loader);
    }
    loader.style.display = 'flex';
}

function hideLoader() {
    const loader = document.getElementById('global-loader');
    if(loader) loader.style.display = 'none';
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if(!alert.classList.contains('alert-danger')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
    });
});