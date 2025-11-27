// Complete Orders Dashboard JavaScript - Fixed Version
let allOrders = [];
let filteredOrders = [];
let currentFilter = 'all';
let currentPeriod = 'today';
let soundEnabled = true;
let lastOrderCount = 0;

// Load orders when page loads
document.addEventListener('DOMContentLoaded', () => {
    console.log('Dashboard loaded, fetching orders...');
    loadOrders();
    // Auto-refresh every 10 seconds
    setInterval(loadOrders, 10000);
});

async function loadOrders() {
    const refreshIcon = document.getElementById('refreshIcon');
    if (refreshIcon) {
        refreshIcon.style.animation = 'spin 1s linear';
    }
    
    try {
        const response = await fetch(`get_orders.php?period=${currentPeriod}`);
        const text = await response.text();
        console.log('Raw response:', text);
        
        const data = JSON.parse(text);
        console.log('Parsed data:', data);
        
        if (data.success) {
            allOrders = data.orders || [];
            console.log('Orders loaded:', allOrders.length);
            
            // Check for new orders
            if (allOrders.length > lastOrderCount && lastOrderCount > 0) {
                playNotificationSound();
                showToast('New order received!', 'success');
            }
            lastOrderCount = allOrders.length;
            
            // Update analytics
            updateAnalytics();
            
            // Apply current filter
            filterOrders(currentFilter);
        } else {
            console.error('Failed to load orders:', data.message);
            showToast('Failed to load orders: ' + data.message, 'error');
            displayOrders([]); // Show empty state
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        showToast('Error loading orders. Check console.', 'error');
        displayOrders([]); // Show empty state
    }
    
    if (refreshIcon) {
        setTimeout(() => {
            refreshIcon.style.animation = '';
        }, 1000);
    }
}

function updateAnalytics() {
    const periodOrders = filterOrdersByPeriod(allOrders, currentPeriod);
    
    const totalRevenue = periodOrders.reduce((sum, order) => sum + parseFloat(order.total || 0), 0);
    const totalOrdersCount = periodOrders.length;
    const pendingCount = periodOrders.filter(o => o.status === 'pending').length;
    const completedCount = periodOrders.filter(o => o.status === 'completed').length;
    
    document.getElementById('totalRevenue').textContent = `₦${totalRevenue.toLocaleString()}`;
    document.getElementById('totalOrders').textContent = totalOrdersCount;
    document.getElementById('pendingOrders').textContent = pendingCount;
    document.getElementById('completedOrders').textContent = completedCount;
    document.getElementById('notificationBadge').textContent = pendingCount;
    
    const periodText = {
        'today': "Today's",
        'week': "This week's",
        'month': "This month's",
        'all': 'Total'
    };
    
    document.getElementById('revenueSubtext').textContent = `${periodText[currentPeriod]} earnings`;
}

function filterOrdersByPeriod(orders, period) {
    const now = new Date();
    
    return orders.filter(order => {
        const orderDate = new Date(order.timestamp);
        
        switch(period) {
            case 'today':
                return orderDate.toDateString() === now.toDateString();
            case 'week':
                const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                return orderDate >= weekAgo;
            case 'month':
                return orderDate.getMonth() === now.getMonth() && 
                       orderDate.getFullYear() === now.getFullYear();
            case 'all':
            default:
                return true;
        }
    });
}

function filterByPeriod(period) {
    currentPeriod = period;
    
    document.querySelectorAll('.time-filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.period === period) {
            btn.classList.add('active');
        }
    });
    
    updateAnalytics();
    filterOrders(currentFilter);
}

function filterOrders(filter) {
    currentFilter = filter;
    
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.filter === filter) {
            tab.classList.add('active');
        }
    });
    
    if (filter === 'all') {
        filteredOrders = [...allOrders];
    } else if (filter === 'dinein' || filter === 'delivery') {
        filteredOrders = allOrders.filter(o => o.type === filter);
    } else {
        filteredOrders = allOrders.filter(o => o.status === filter);
    }
    
    const searchTerm = document.getElementById('searchInput').value;
    if (searchTerm) {
        searchOrders();
    } else {
        displayOrders(filteredOrders);
    }
}

function searchOrders() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    if (!searchTerm) {
        displayOrders(filteredOrders);
        return;
    }
    
    const searchResults = filteredOrders.filter(order => {
        return order.id.toLowerCase().includes(searchTerm) ||
               (order.customerName && order.customerName.toLowerCase().includes(searchTerm)) ||
               (order.tableNumber && order.tableNumber.toString().includes(searchTerm)) ||
               (order.phone && order.phone.includes(searchTerm));
    });
    
    displayOrders(searchResults);
}

function displayOrders(orders) {
    const grid = document.getElementById('ordersGrid');
    
    if (!orders || orders.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Orders Found</h3>
                <p>Orders will appear here in real-time</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = orders.map(order => createOrderCard(order)).join('');
}

function createOrderCard(order) {
    const statusColors = {
        pending: '#ff9800',
        preparing: '#2196f3',
        ready: '#4caf50',
        completed: '#00bcd4',
        cancelled: '#f44336'
    };
    
    const statusIcons = {
        pending: '⏳',
        preparing: '👨‍🍳',
        ready: '✅',
        completed: '🎉',
        cancelled: '❌'
    };
    
    const orderTypeIcon = order.type === 'dinein' ? '🍽️' : '🚗';
    const orderTypeLabel = order.type === 'dinein' ? 'Dine-In' : 'Delivery';
    
    const itemsList = order.items.map(item => {
        const customizations = item.customizations && item.customizations.length > 0
            ? `<div class="item-customizations">${item.customizations.join(', ')}</div>`
            : '';
        return `
            <div class="order-item">
                <span class="item-name">${item.name} x${item.quantity}</span>
                ${customizations}
                <span class="item-price">₦${(item.price * item.quantity).toLocaleString()}</span>
            </div>
        `;
    }).join('');
    
    const customerInfo = order.type === 'dinein'
        ? `<div class="order-info"><strong>Table:</strong> ${order.tableNumber}</div>`
        : `
            <div class="order-info"><strong>Customer:</strong> ${order.customerName}</div>
            <div class="order-info"><strong>Phone:</strong> ${order.phone}</div>
            <div class="order-info"><strong>Address:</strong> ${order.address}</div>
        `;
    
    const waiterBadge = order.requestedWaiter
        ? '<span class="waiter-badge">👨‍💼 Waiter Requested</span>'
        : '';
    
    const paymentBadge = `<span class="payment-badge ${order.paymentStatus}">${order.paymentStatus === 'paid' ? '💳 Paid' : '💵 Unpaid'}</span>`;
    
    return `
        <div class="order-card" data-order-id="${order.id}" data-status="${order.status}">
            <div class="order-header">
                <div class="order-number">
                    <span class="order-type-icon">${orderTypeIcon}</span>
                    <strong>#${order.id}</strong>
                    <span class="order-type-label">${orderTypeLabel}</span>
                </div>
                <div class="order-badges">
                    ${waiterBadge}
                    ${paymentBadge}
                </div>
            </div>
            
            <div class="order-status" style="background-color: ${statusColors[order.status]}">
                ${statusIcons[order.status]} ${order.status.toUpperCase()}
            </div>
            
            <div class="order-details">
                ${customerInfo}
                <div class="order-time">
                    <strong>Time:</strong> ${formatTime(order.timestamp)}
                </div>
            </div>
            
            <div class="order-items">
                ${itemsList}
            </div>
            
            <div class="order-total">
                <strong>Total:</strong> ₦${parseFloat(order.total).toLocaleString()}
            </div>
            
            <div class="order-actions">
                ${getOrderActions(order)}
            </div>
        </div>
    `;
}

function getOrderActions(order) {
    switch(order.status) {
        case 'pending':
            return `
                <button class="btn-action btn-accept" onclick="updateOrderStatus('${order.id}', 'preparing')">
                    Accept Order
                </button>
                <button class="btn-action btn-cancel" onclick="updateOrderStatus('${order.id}', 'cancelled')">
                    Cancel
                </button>
            `;
        case 'preparing':
            return `
                <button class="btn-action btn-ready" onclick="updateOrderStatus('${order.id}', 'ready')">
                    Mark as Ready
                </button>
            `;
        case 'ready':
            return `
                <button class="btn-action btn-complete" onclick="updateOrderStatus('${order.id}', 'completed')">
                    Complete Order
                </button>
            `;
        case 'completed':
            return `<div class="completed-message">Order completed ✓</div>`;
        case 'cancelled':
            return `<div class="cancelled-message">Order cancelled ✗</div>`;
        default:
            return '';
    }
}

async function updateOrderStatus(orderId, newStatus) {
    try {
        const response = await fetch('update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                orderId: orderId,
                status: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Order ${orderId} updated to ${newStatus}`, 'success');
            loadOrders();
        } else {
            showToast('Failed to update order', 'error');
        }
    } catch (error) {
        console.error('Error updating order:', error);
        showToast('Error updating order', 'error');
    }
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000 / 60); // minutes
    
    if (diff < 1) return 'Just now';
    if (diff < 60) return `${diff} min ago`;
    if (diff < 1440) return `${Math.floor(diff / 60)} hours ago`;
    
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function toggleSound() {
    soundEnabled = !soundEnabled;
    const btn = document.getElementById('soundToggle');
    btn.textContent = soundEnabled ? '🔔 Sound ON' : '🔕 Sound OFF';
}

function playNotificationSound() {
    if (!soundEnabled) return;
    const audio = document.getElementById('notificationSound');
    if (audio) {
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Add CSS for spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .order-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 16px;
    }
    
    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .order-number {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
    }
    
    .order-type-icon {
        font-size: 24px;
    }
    
    .order-type-label {
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    .order-badges {
        display: flex;
        gap: 8px;
    }
    
    .waiter-badge, .payment-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .waiter-badge {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .payment-badge.paid {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .payment-badge.unpaid {
        background: #fff3e0;
        color: #f57c00;
    }
    
    .order-status {
        padding: 8px 16px;
        border-radius: 6px;
        text-align: center;
        color: white;
        font-weight: bold;
        margin-bottom: 16px;
    }
    
    .order-details {
        margin-bottom: 16px;
    }
    
    .order-info {
        padding: 6px 0;
        color: #666;
    }
    
    .order-items {
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
        padding: 12px 0;
        margin: 12px 0;
    }
    
    .order-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
    }
    
    .item-customizations {
        font-size: 12px;
        color: #888;
        margin-top: 4px;
    }
    
    .order-total {
        font-size: 18px;
        font-weight: bold;
        margin: 16px 0;
        text-align: right;
    }
    
    .order-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
    }
    
    .btn-action {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-accept {
        background: #4caf50;
        color: white;
    }
    
    .btn-accept:hover {
        background: #45a049;
    }
    
    .btn-ready {
        background: #2196f3;
        color: white;
    }
    
    .btn-ready:hover {
        background: #0b7dda;
    }
    
    .btn-complete {
        background: #00bcd4;
        color: white;
    }
    
    .btn-complete:hover {
        background: #00acc1;
    }
    
    .btn-cancel {
        background: #f44336;
        color: white;
    }
    
    .btn-cancel:hover {
        background: #da190b;
    }
    
    .completed-message, .cancelled-message {
        text-align: center;
        padding: 12px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .completed-message {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .cancelled-message {
        background: #ffebee;
        color: #c62828;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 80px;
        margin-bottom: 20px;
    }
`;
document.head.appendChild(style);