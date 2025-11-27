// Reservation data - will be fetched from database
let reservations = [];
let blockedTables = [];

let currentFilter = 'all';
let currentSpaceFilter = 'all';
let searchQuery = '';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setCurrentDate();
    setDefaultWalkInDate();
    loadReservations();
    loadBlockedTables();
    
    // Add listeners for date/time changes to update available tables
    document.getElementById('walkInDate').addEventListener('change', loadAvailableTables);
    document.getElementById('walkInTime').addEventListener('change', loadAvailableTables);
});

// Set current date display
function setCurrentDate() {
    const today = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('currentDate').textContent = today.toLocaleDateString('en-US', options);
}

// Set default date for walk-in form
function setDefaultWalkInDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('walkInDate').value = today;
    document.getElementById('blockDate').value = today;
}

// Load blocked tables from database
async function loadBlockedTables() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const response = await fetch(`get_blocked_tables.php?date=${today}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            blockedTables = data.blockedTables;
            console.log('Loaded blocked tables:', blockedTables);
            updateBlockedTablesDisplay();
        }
        
    } catch (error) {
        console.error('Error loading blocked tables:', error);
    }
}

// Update blocked tables display - NOW WITH ACTUAL UI
function updateBlockedTablesDisplay() {
    const blockedSection = document.getElementById('blockedTablesSection');
    
    if (!blockedSection) {
        // Create the blocked tables section if it doesn't exist
        const contentArea = document.querySelector('.content-area');
        const newSection = document.createElement('div');
        newSection.className = 'blocked-tables-section';
        newSection.id = 'blockedTablesSection';
        newSection.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">
                    <span>🚫</span> Blocked Tables
                </h2>
            </div>
            <div class="blocked-tables-grid" id="blockedTablesGrid"></div>
        `;
        
        // Insert before reservations section
        const reservationsSection = document.querySelector('.reservations-section');
        contentArea.insertBefore(newSection, reservationsSection);
    }
    
    const grid = document.getElementById('blockedTablesGrid');
    
    if (blockedTables.length === 0) {
        grid.innerHTML = `
            <div class="empty-state small">
                <div class="empty-state-icon">✅</div>
                <p>No blocked tables</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = blockedTables.map(block => `
        <div class="blocked-table-card">
            <div class="blocked-header">
                <div class="space-badge ${getSpaceClass(block.space_type)}">
                    ${getSpaceIcon(block.space_type)} ${block.space_type.toUpperCase()}
                </div>
                <button class="unblock-btn" onclick="unblockTable(${block.id})">
                    ✓ Unblock
                </button>
            </div>
            <div class="blocked-info">
                <div class="blocked-table-id">Table ${block.table_id}</div>
                <div class="blocked-time">
                    📅 ${formatDate(block.block_date)}
                    <br>
                    🕒 ${formatTime(block.block_start_time)} - ${formatTime(block.block_end_time)}
                </div>
                ${block.reason ? `<div class="blocked-reason">📝 ${block.reason}</div>` : ''}
            </div>
        </div>
    `).join('');
}

// Helper functions for blocked tables display
function getSpaceClass(space) {
    const map = {
        'indoor': 'space-indoor',
        'outdoor': 'space-outdoor',
        'lounge': 'space-lounge'
    };
    return map[space] || 'space-indoor';
}

function getSpaceIcon(space) {
    const map = {
        'indoor': '🏠',
        'outdoor': '🌳',
        'lounge': '💼'
    };
    return map[space] || '🏠';
}

function formatTime(timeStr) {
    // Convert 24h to 12h format
    const [hours, minutes] = timeStr.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayHour = h > 12 ? h - 12 : (h === 0 ? 12 : h);
    return `${displayHour}:${minutes} ${ampm}`;
}

// Unblock table function
async function unblockTable(blockId) {
    if (!confirm('Are you sure you want to unblock this table?')) {
        return;
    }
    
    try {
        showToast('Unblocking table...');
        
        const response = await fetch('unblock_table.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ blockId: blockId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            loadBlockedTables(); // Reload blocked tables
            showToast('✅ Table unblocked successfully');
        } else {
            throw new Error(result.message || 'Failed to unblock table');
        }
        
    } catch (error) {
        console.error('Error unblocking table:', error);
        showToast('❌ Failed to unblock table: ' + error.message, 'error');
    }
}

// Load and display reservations FROM DATABASE
async function loadReservations() {
    // Animate refresh icon
    const refreshIcon = document.getElementById('refreshIcon');
    refreshIcon.style.transform = 'rotate(360deg)';
    refreshIcon.style.transition = 'transform 0.5s ease';
    setTimeout(() => {
        refreshIcon.style.transform = 'rotate(0deg)';
    }, 500);

    try {
        showToast('Loading reservations...');
        
        // Fetch from database
        const response = await fetch('get_reservation.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            // Convert to frontend format
            reservations = data.reservations.map(r => ({
                id: r.id,
                customerName: r.customerName,
                phone: r.customerPhone,
                email: r.customerEmail,
                date: r.date,
                time: r.time,
                guests: r.tableCapacity,
                space: r.spaceType,
                tableId: r.tableId,
                status: r.status,
                specialRequests: '',
                depositAmount: r.depositAmount,
                paymentStatus: r.paymentStatus,
                createdAt: r.createdAt
            }));
            
            updateStats();
            displayReservations();
            showToast(`✅ Loaded ${reservations.length} reservations`);
        } else {
            throw new Error(data.message || 'Failed to load reservations');
        }
        
    } catch (error) {
        console.error('Error loading reservations:', error);
        showToast('❌ Error loading reservations: ' + error.message, 'error');
        // Show empty state
        displayReservations();
    }
}

// Update statistics
function updateStats() {
    const today = new Date().toISOString().split('T')[0];
    
    const todayReservations = reservations.filter(r => r.date === today);
    const confirmed = reservations.filter(r => r.status === 'confirmed');
    const seated = reservations.filter(r => r.status === 'seated');
    const upcoming = reservations.filter(r => new Date(r.date) > new Date() && r.status === 'confirmed');
    
    document.getElementById('todayReservations').textContent = todayReservations.length;
    document.getElementById('confirmedCount').textContent = confirmed.length;
    document.getElementById('seatedCount').textContent = seated.length;
    document.getElementById('upcomingCount').textContent = upcoming.length;
    document.getElementById('notificationBadge').textContent = seated.length;
}

// Display reservations
function displayReservations() {
    const grid = document.getElementById('reservationsGrid');
    let filteredReservations = getFilteredReservations();
    
    if (filteredReservations.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Reservations Found</h3>
                <p>Try adjusting your filters or add a new reservation</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = filteredReservations.map(reservation => createReservationCard(reservation)).join('');
}

// Get filtered reservations
function getFilteredReservations() {
    const today = new Date().toISOString().split('T')[0];
    
    return reservations.filter(reservation => {
        // Filter by status/date
        let matchesFilter = true;
        if (currentFilter === 'today') {
            matchesFilter = reservation.date === today;
        } else if (currentFilter === 'upcoming') {
            matchesFilter = new Date(reservation.date) > new Date();
        } else if (currentFilter !== 'all') {
            matchesFilter = reservation.status === currentFilter;
        }
        
        // Filter by space
        const matchesSpace = currentSpaceFilter === 'all' || reservation.space === currentSpaceFilter;
        
        // Filter by search query
        const matchesSearch = !searchQuery || 
            reservation.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
            reservation.phone.includes(searchQuery);
        
        return matchesFilter && matchesSpace && matchesSearch;
    });
}

// Create reservation card HTML
function createReservationCard(reservation) {
    const spaceInfo = getSpaceInfo(reservation.space);
    const statusInfo = getStatusInfo(reservation.status);
    
    return `
        <div class="reservation-card">
            <div class="reservation-header">
                <div class="reservation-id">${reservation.id}</div>
                <div class="reservation-status ${statusInfo.class}">${statusInfo.label}</div>
            </div>
            <div class="reservation-body">
                <div class="space-badge ${spaceInfo.class}">
                    ${spaceInfo.icon} ${spaceInfo.name}
                </div>
                
                <div class="reservation-info">
                    <div class="info-row">
                        <span class="info-label">Customer:</span>
                        <span class="info-value">${reservation.customerName}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">${reservation.phone}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date & Time:</span>
                        <span class="info-value">${formatDate(reservation.date)} at ${reservation.time}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Guests:</span>
                        <span class="info-value">${reservation.guests} people</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Table:</span>
                        <span class="info-value">${reservation.tableId}</span>
                    </div>
                    ${reservation.specialRequests ? `
                    <div class="info-row">
                        <span class="info-label">Notes:</span>
                        <span class="info-value">${reservation.specialRequests}</span>
                    </div>
                    ` : ''}
                </div>
                
                <div class="reservation-actions">
                    ${getActionButtons(reservation)}
                </div>
            </div>
        </div>
    `;
}

// Get space information
function getSpaceInfo(space) {
    const spaceMap = {
        indoor: { name: 'Indoor Dining', icon: '🏠', class: 'space-indoor' },
        outdoor: { name: 'Outdoor Terrace', icon: '🌳', class: 'space-outdoor' },
        lounge: { name: 'Work Lounge', icon: '💼', class: 'space-lounge' }
    };
    return spaceMap[space] || spaceMap.indoor;
}

// Get status information
function getStatusInfo(status) {
    const statusMap = {
        pending: { label: 'Pending', class: 'status-pending' },
        confirmed: { label: 'Confirmed', class: 'status-confirmed' },
        seated: { label: 'Seated', class: 'status-seated' },
        completed: { label: 'Completed', class: 'status-completed' },
        cancelled: { label: 'Cancelled', class: 'status-cancelled' },
        no_show: { label: 'No Show', class: 'status-cancelled' }
    };
    return statusMap[status] || statusMap.pending;
}

// Get action buttons based on status
function getActionButtons(reservation) {
    switch(reservation.status) {
        case 'confirmed':
            return `
                <button class="btn btn-success" onclick="updateReservationStatus('${reservation.id}', 'seated')">
                    🪑 Mark Seated
                </button>
                <button class="btn btn-danger" onclick="updateReservationStatus('${reservation.id}', 'cancelled')">
                    ❌ Cancel
                </button>
            `;
        case 'seated':
            return `
                <button class="btn btn-primary" onclick="updateReservationStatus('${reservation.id}', 'completed')">
                    ✅ Complete
                </button>
            `;
        case 'pending':
            return `
                <button class="btn btn-success" onclick="updateReservationStatus('${reservation.id}', 'confirmed')">
                    ✅ Confirm
                </button>
                <button class="btn btn-danger" onclick="updateReservationStatus('${reservation.id}', 'cancelled')">
                    ❌ Decline
                </button>
            `;
        case 'completed':
        case 'cancelled':
            return `
                <button class="btn btn-secondary" onclick="deleteReservation('${reservation.id}')">
                    🗑️ Delete
                </button>
            `;
        default:
            return '';
    }
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// Filter reservations
function filterReservations(filter) {
    currentFilter = filter;
    
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    displayReservations();
}

// Filter by space
function filterBySpace() {
    currentSpaceFilter = document.getElementById('spaceFilter').value;
    displayReservations();
}

// Search reservations
function searchReservations() {
    searchQuery = document.getElementById('searchInput').value;
    displayReservations();
}

// Update reservation status via API
async function updateReservationStatus(reservationId, newStatus) {
    try {
        showToast('Updating reservation...');
        
        const response = await fetch('update_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservationId: reservationId,
                action: 'update_status',
                status: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update local data
            const reservation = reservations.find(r => r.id === reservationId);
            if (reservation) {
                reservation.status = newStatus;
            }
            
            loadReservations(); // Reload to reflect changes
            showToast(`✅ Reservation ${newStatus}`);
        } else {
            throw new Error(result.message || 'Update failed');
        }
        
    } catch (error) {
        console.error('Error updating reservation:', error);
        showToast('❌ Failed to update: ' + error.message, 'error');
    }
}

// Reservation actions (legacy - now use updateReservationStatus)
function markAsSeated(id) {
    updateReservationStatus(id, 'seated');
}

function completeReservation(id) {
    updateReservationStatus(id, 'completed');
}

function confirmReservation(id) {
    updateReservationStatus(id, 'confirmed');
}

function cancelReservation(id) {
    if (confirm('Are you sure you want to cancel this reservation?')) {
        updateReservationStatus(id, 'cancelled');
    }
}

function deleteReservation(id) {
    if (confirm('Are you sure you want to permanently delete this reservation?')) {
        reservations = reservations.filter(r => r.id !== id);
        loadReservations();
        showToast('Reservation deleted', 'error');
    }
}

// Modal functions
function openWalkInModal() {
    document.getElementById('walkInModal').style.display = 'flex';
    loadAvailableTables(); // Load tables when opening modal
}

function closeWalkInModal() {
    document.getElementById('walkInModal').style.display = 'none';
    document.getElementById('walkInForm').reset();
    setDefaultWalkInDate();
}

function openBlockTableModal() {
    document.getElementById('blockTableModal').style.display = 'flex';
}

function closeBlockTableModal() {
    document.getElementById('blockTableModal').style.display = 'none';
    document.getElementById('blockTableForm').reset();
    setDefaultWalkInDate();
}

// Load available tables based on space and check against blocked tables
async function loadAvailableTables() {
    const space = document.getElementById('walkInSpace').value;
    const tableSelect = document.getElementById('walkInTable');
    const date = document.getElementById('walkInDate').value;
    const time = document.getElementById('walkInTime').value;
    
    if (!space) {
        tableSelect.innerHTML = '<option value="">Select space first...</option>';
        return;
    }
    
    if (!date || !time) {
        // If date/time not selected, show all tables
        const allTables = {
            indoor: ['I1', 'I2', 'I3', 'I4', 'I5', 'I6', 'I7', 'I8'],
            outdoor: ['O1', 'O2', 'O3', 'O4', 'O5', 'O6', 'O7', 'O8', 'O9', 'O10'],
            lounge: ['L1', 'L2', 'L3', 'L4', 'L5', 'L6']
        };
        
        const options = allTables[space].map(table => 
            `<option value="${table}">Table ${table}</option>`
        ).join('');
        
        tableSelect.innerHTML = '<option value="">Select Table</option>' + options;
        return;
    }
    
    // Check availability via API
    try {
        showToast('Checking table availability...');
        
        const response = await fetch(`check_availability.php?space=${space}&date=${date}&time=${time}`);
        const data = await response.json();
        
        if (data.success && data.available) {
            if (data.available.length === 0) {
                tableSelect.innerHTML = '<option value="">⚠️ No tables available at this time</option>';
                showToast('⚠️ No tables available for selected time', 'error');
            } else {
                const options = data.available.map(table => 
                    `<option value="${table.id}">Table ${table.id} (${table.capacity} seats)</option>`
                ).join('');
                
                tableSelect.innerHTML = '<option value="">Select Table</option>' + options;
                showToast(`✅ ${data.available.length} table(s) available`);
            }
        } else {
            throw new Error(data.message || 'Failed to check availability');
        }
        
    } catch (error) {
        console.error('Error checking availability:', error);
        showToast('❌ Error checking availability', 'error');
        
        // Fallback to showing all tables
        const allTables = {
            indoor: ['I1', 'I2', 'I3', 'I4', 'I5', 'I6', 'I7', 'I8'],
            outdoor: ['O1', 'O2', 'O3', 'O4', 'O5', 'O6', 'O7', 'O8', 'O9', 'O10'],
            lounge: ['L1', 'L2', 'L3', 'L4', 'L5', 'L6']
        };
        
        const options = allTables[space].map(table => 
            `<option value="${table}">Table ${table}</option>`
        ).join('');
        
        tableSelect.innerHTML = '<option value="">Select Table</option>' + options;
    }
}

// Handle walk-in form submission
document.getElementById('walkInForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const tableSelect = document.getElementById('walkInTable');
    const selectedOption = tableSelect.options[tableSelect.selectedIndex];
    const tableText = selectedOption.text;
    
    // Extract capacity from "Table I1 (2 seats)" format
    const capacityMatch = tableText.match(/\((\d+) seats?\)/);
    const capacity = capacityMatch ? parseInt(capacityMatch[1]) : 2;
    
    const customerName = document.getElementById('walkInName').value;
    const customerPhone = document.getElementById('walkInPhone').value;
    const customerEmail = document.getElementById('walkInEmail').value || '';
    
    console.log('Form values:', { customerName, customerPhone, customerEmail });
    
    const walkInData = {
        space: document.getElementById('walkInSpace').value,
        tableId: document.getElementById('walkInTable').value,
        tableCapacity: capacity,
        date: document.getElementById('walkInDate').value,
        time: document.getElementById('walkInTime').value,
        customerName: customerName,
        customerPhone: customerPhone,
        customerEmail: customerEmail,
        depositAmount: 0,
        paymentStatus: 'not_required',
        bookingSource: 'walk-in',
        status: 'confirmed'
    };
    
    try {
        showToast('Creating walk-in reservation...');
        
        console.log('Sending data to server:', walkInData);
        
        const response = await fetch('save_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(walkInData)
        });
        
        const responseText = await response.text();
        console.log('Server response:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Invalid response from server: ' + responseText);
        }
        
        if (result.success) {
            closeWalkInModal();
            loadReservations();
            showToast(`✅ Walk-in reservation created for ${customerName}`);
            
            // Automatically mark as seated
            setTimeout(() => {
                updateReservationStatus(result.reservationId, 'seated');
            }, 500);
        } else {
            throw new Error(result.message || 'Failed to create reservation');
        }
        
    } catch (error) {
        console.error('Error creating walk-in:', error);
        showToast('❌ Failed to create reservation: ' + error.message, 'error');
    }
});

// Handle block table form submission
document.getElementById('blockTableForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const blockData = {
        spaceType: document.getElementById('blockSpace').value,
        tableId: document.getElementById('blockTableId').value,
        blockDate: document.getElementById('blockDate').value,
        blockStartTime: document.getElementById('blockStartTime').value,
        blockEndTime: document.getElementById('blockEndTime').value,
        reason: document.getElementById('blockReason').value || 'Blocked by admin'
    };
    
    try {
        showToast('Blocking table...');
        
        const response = await fetch('block_table.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(blockData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeBlockTableModal();
            loadBlockedTables(); // Reload blocked tables
            showToast(`✅ Table ${blockData.tableId} blocked from ${blockData.blockStartTime} to ${blockData.blockEndTime}`);
        } else {
            throw new Error(result.message || 'Failed to block table');
        }
        
    } catch (error) {
        console.error('Error blocking table:', error);
        showToast('❌ Failed to block table: ' + error.message, 'error');
    }
});

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast show';
    
    if (type === 'error') {
        toast.classList.add('error');
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Close modals on outside click
window.onclick = function(event) {
    const walkInModal = document.getElementById('walkInModal');
    const blockTableModal = document.getElementById('blockTableModal');
    
    if (event.target === walkInModal) {
        closeWalkInModal();
    }
    if (event.target === blockTableModal) {
        closeBlockTableModal();
    }
}