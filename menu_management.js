let menuItems = [];
let currentCategory = '';
let itemToDelete = null;

document.addEventListener('DOMContentLoaded', () => {
    loadMenuItems();
    setupFilters();
    setupSearch();
});

function setupFilters() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            filterAndDisplay();
        });
    });
}

function setupSearch() {
    document.getElementById('searchInput').addEventListener('input', function() {
        filterAndDisplay();
    });
}

function filterAndDisplay() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    let filtered = menuItems;
    
    if (currentCategory) {
        filtered = filtered.filter(item => item.category === currentCategory);
    }
    
    if (searchTerm) {
        filtered = filtered.filter(item => 
            item.itemName.toLowerCase().includes(searchTerm) ||
            (item.shortDescription && item.shortDescription.toLowerCase().includes(searchTerm))
        );
    }
    
    displayMenuItems(filtered);
}

async function loadMenuItems() {
    try {
        const response = await fetch('get_menu_items.php');
        const data = await response.json();
        
        if (data.success) {
            menuItems = data.items;
            displayMenuItems(menuItems);
        } else {
            document.getElementById('menuGrid').innerHTML = '<div class="loading">Failed to load menu items</div>';
        }
    } catch (error) {
        console.error('Error loading menu:', error);
        document.getElementById('menuGrid').innerHTML = '<div class="loading">Error loading menu items</div>';
    }
}

function displayMenuItems(items) {
    const grid = document.getElementById('menuGrid');
    
    if (items.length === 0) {
        grid.innerHTML = '<div class="loading">No menu items found</div>';
        return;
    }
    
    let html = '';
    
    items.forEach(item => {
        html += `
            <div class="menu-card">
                <img src="${item.imagePath}" alt="${item.itemName}" class="card-image">
                <div class="card-content">
                    <div class="card-header">
                        <h3 class="card-title">${item.itemName}</h3>
                        <span class="card-category">${item.category}</span>
                    </div>
                    <div class="card-price">₦${item.basePrice.toLocaleString()}</div>
                    <p class="card-description">${item.shortDescription || ''}</p>
                    <div class="card-actions">
                        <button class="btn btn-edit" onclick="editItem(${item.id})">Edit</button>
                        <button class="btn btn-danger" onclick="openDeleteModal(${item.id})">Delete</button>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

function editItem(itemId) {
    window.location.href = `edit_menu_item.html?id=${itemId}`;
}

function openDeleteModal(itemId) {
    itemToDelete = itemId;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    itemToDelete = null;
    document.getElementById('deleteModal').classList.remove('active');
}

async function confirmDelete() {
    if (!itemToDelete) return;
    
    try {
        const response = await fetch('delete_menu_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: itemToDelete })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Menu item deleted successfully');
            closeDeleteModal();
            loadMenuItems();
        } else {
            showToast(result.message || 'Failed to delete item', 'error');
        }
    } catch (error) {
        console.error('Error deleting item:', error);
        showToast('Error deleting item', 'error');
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}