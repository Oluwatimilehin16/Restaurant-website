let selectedSpace = null;
let selectedTable = null;
let selectedDate = null;
let selectedTime = null;
let availableTables = [];
let reservedTables = [];

const spaceData = {
    indoor: {
        name: 'Indoor Dining',
        tables: [
            { id: 'I1', capacity: 2 },
            { id: 'I2', capacity: 2 },
            { id: 'I3', capacity: 4 },
            { id: 'I4', capacity: 4 },
            { id: 'I5', capacity: 4 },
            { id: 'I6', capacity: 3 },
            { id: 'I7', capacity: 2 },
            { id: 'I8', capacity: 4 }
        ]
    },
    outdoor: {
        name: 'Outdoor Terrace',
        tables: [
            { id: 'O1', capacity: 4 },
            { id: 'O2', capacity: 4 },
            { id: 'O3', capacity: 6 },
            { id: 'O4', capacity: 2 },
            { id: 'O5', capacity: 4 },
            { id: 'O6', capacity: 6 },
            { id: 'O7', capacity: 2 },
            { id: 'O8', capacity: 4 },
            { id: 'O9', capacity: 6 },
            { id: 'O10', capacity: 4 }
        ]
    },
    lounge: {
        name: 'Work Lounge',
        tables: [
            { id: 'L1', capacity: 6 },
            { id: 'L2', capacity: 8 },
            { id: 'L3', capacity: 4 },
            { id: 'L4', capacity: 6 },
            { id: 'L5', capacity: 8 },
            { id: 'L6', capacity: 4 }
        ]
    }
};

// Set minimum date to tomorrow
const dateInput = document.getElementById('reservationDate');
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
dateInput.min = tomorrow.toISOString().split('T')[0];

function goToStep(step) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    
    document.getElementById(`step${step}`).classList.add('active');
    document.querySelector(`[data-step="${step}"]`).classList.add('active');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function selectSpace(space) {
    selectedSpace = space;
    goToStep(2);
}

async function loadTableAvailability() {
    const date = document.getElementById('reservationDate').value;
    const time = document.getElementById('reservationTime').value;
    
    const continueBtn = document.getElementById('continueToTables');
    
    if (date && time) {
        selectedDate = date;
        selectedTime = time;
        
        // Fetch real-time availability from database
        try {
            showToast('Checking availability...');
            const response = await fetch(`check_availability.php?space=${selectedSpace}&date=${date}&time=${time}`);
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to debug
            const text = await response.text();
            console.log('Raw response:', text);
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                availableTables = data.available;
                reservedTables = data.reserved;
                continueBtn.disabled = false;
                continueBtn.style.opacity = '1';
                showToast('✅ Availability loaded');
            } else {
                showToast('❌ Error checking availability: ' + data.message, true);
                continueBtn.disabled = true;
                continueBtn.style.opacity = '0.5';
            }
        } catch (error) {
            console.error('Full error details:', error);
            showToast('❌ Connection error: ' + error.message, true);
            continueBtn.disabled = true;
            continueBtn.style.opacity = '0.5';
        }
    } else {
        continueBtn.disabled = true;
        continueBtn.style.opacity = '0.5';
    }
}

function validateStep2() {
    const date = document.getElementById('reservationDate').value;
    const time = document.getElementById('reservationTime').value;
    
    if (!date) {
        showToast('⚠️ Please select a date for your reservation', true);
        return false;
    }
    
    if (!time) {
        showToast('⚠️ Please select a time for your reservation', true);
        return false;
    }
    
    return true;
}

function validateStep3() {
    if (!selectedTable) {
        showToast('⚠️ Please select a table to continue', true);
        return false;
    }
    return true;
}

function isTableReserved(tableId) {
    return reservedTables.some(table => table.id === tableId);
}

function renderTables() {
    const space = spaceData[selectedSpace];
    const tablesGrid = document.getElementById('tablesGrid');
    document.getElementById('spaceNameDisplay').textContent = `${space.name} on ${selectedDate} at ${selectedTime}`;
    
    if (availableTables.length === 0 && reservedTables.length === 0) {
        tablesGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">Loading tables...</div>';
        return;
    }
    
    tablesGrid.innerHTML = space.tables.map(table => {
        const reserved = isTableReserved(table.id);
        const statusClass = reserved ? 'reserved' : 'available';
        
        return `
            <div class="table-card ${statusClass}" onclick="selectTable('${table.id}', ${table.capacity}, ${reserved})" id="table-${table.id}">
                <div class="table-icon">🪑</div>
                <div class="table-number">Table ${table.id}</div>
                <div class="table-capacity">${table.capacity} Seats</div>
                ${reserved ? '<div class="table-status-badge">Reserved</div>' : '<div class="table-status-badge available">Available</div>'}
            </div>
        `;
    }).join('');
}

function selectTable(tableId, capacity, isReserved) {
    if (isReserved) {
        showToast('❌ This table is already reserved. Please select another table.', true);
        return;
    }

    // Remove previous selection
    document.querySelectorAll('.table-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Add selection to clicked table
    const tableElement = document.getElementById(`table-${tableId}`);
    tableElement.classList.add('selected');

    selectedTable = { id: tableId, capacity: capacity };

    // Show selected table info
    const infoDiv = document.getElementById('selectedTableInfo');
    const detailsDiv = document.getElementById('selectedTableDetails');
    infoDiv.style.display = 'block';
    detailsDiv.textContent = `Table ${tableId} - ${capacity} Seats`;

    const continueBtn = document.getElementById('continueToConfirm');
    continueBtn.disabled = false;
    continueBtn.style.opacity = '1';
}

// Update step 3 to render tables
const originalGoToStep = goToStep;
goToStep = function(step) {
    // Validate before moving to next step
    if (step === 3) {
        if (!validateStep2()) {
            return;
        }
        renderTables();
    } else if (step === 4) {
        if (!validateStep3()) {
            return;
        }
        updateSummary();
    }
    originalGoToStep(step);
}

function updateSummary() {
    document.getElementById('summarySpace').textContent = spaceData[selectedSpace].name;
    document.getElementById('summaryTable').textContent = `Table ${selectedTable.id}`;
    document.getElementById('summaryDate').textContent = new Date(selectedDate).toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    document.getElementById('summaryTime').textContent = selectedTime;
    document.getElementById('summaryCapacity').textContent = `${selectedTable.capacity} Seats`;
}

document.getElementById('reservationForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Validate all form fields
    const fullName = document.getElementById('fullName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const email = document.getElementById('email').value.trim();
    const agreeTerms = document.getElementById('agreeTerms').checked;

    if (!fullName) {
        showToast('⚠️ Please enter your full name', true);
        document.getElementById('fullName').focus();
        return;
    }

    if (!phone) {
        showToast('⚠️ Please enter your phone number', true);
        document.getElementById('phone').focus();
        return;
    }

    if (!email) {
        showToast('⚠️ Please enter your email address', true);
        document.getElementById('email').focus();
        return;
    }

    if (!agreeTerms) {
        showToast('⚠️ Please agree to the terms and conditions', true);
        document.getElementById('agreeTerms').focus();
        return;
    }

    // Prepare reservation data
    const reservationData = {
        space: selectedSpace,
        tableId: selectedTable.id,
        tableCapacity: selectedTable.capacity,
        date: selectedDate,
        time: selectedTime,
        fullName: fullName,
        phone: phone,
        email: email,
        deposit: 5000,
        bookingSource: 'online'
    };

    // Disable submit button to prevent double submission
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    try {
        showToast('Processing your reservation...');

        const response = await fetch(`save_reservation.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(reservationData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('✅ Reservation confirmed!');
            
            // Show success message
            setTimeout(() => {
                alert(`✅ Reservation Confirmed!\n\n` +
                    `Reservation ID: ${result.reservationId}\n` +
                    `Space: ${spaceData[selectedSpace].name}\n` +
                    `Table: ${selectedTable.id} (${selectedTable.capacity} seats)\n` +
                    `Name: ${fullName}\n` +
                    `Date: ${selectedDate}\n` +
                    `Time: ${selectedTime}\n\n` +
                    `Your table is now reserved!\n\n` +
                    `A confirmation has been sent to ${email}`);
                
                // Reset form and go back to step 1
                document.getElementById('reservationForm').reset();
                selectedSpace = null;
                selectedTable = null;
                selectedDate = null;
                selectedTime = null;
                availableTables = [];
                reservedTables = [];
                goToStep(1);
            }, 1000);
        } else {
            showToast('❌ ' + result.message, true);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Pay & Reserve →';
            
            // If table is no longer available, go back to step 3
            if (result.message.includes('no longer available')) {
                setTimeout(() => {
                    goToStep(3);
                }, 2000);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('❌ Connection error. Please try again.', true);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Pay & Reserve →';
    }
});

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    
    if (isError) {
        toast.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
    } else {
        toast.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}