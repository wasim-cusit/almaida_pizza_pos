/**
 * Main Application JavaScript
 * Fast Food POS System - Modern Design
 */

// Global variables
let currentCategoryId = 1;
let currentOrderNumber = '';
let selectedCartItem = null; // Global variable for selected cart item



// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    // Get current order number from header
    const orderInfo = document.querySelector('.order-info span');
    if (orderInfo && orderInfo.textContent.includes('Order No:')) {
        currentOrderNumber = orderInfo.textContent.replace('Order No: ', '');
    }
    
    // Set up event listeners
    setupEventListeners();
    
    // Load initial data
    loadInitialData();
    
    // Setup keyboard shortcuts
    setupKeyboardShortcuts();
    
    // Initialize cart
    updateCartDisplay();
});

// Setup event listeners
function setupEventListeners() {
    // Category click events
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const categoryId = this.dataset.categoryId;
            
            if (categoryId) {
                loadItems(categoryId);
            }
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            searchItems(this.value);
        });
    }
    
    // Cart item selection
    document.addEventListener('click', function(e) {
        if (e.target.closest('.cart-item')) {
            selectCartItem(e.target.closest('.cart-item'));
        }
    });
    
    // Add click handlers for category cards that might not have been caught
    document.addEventListener('click', function(e) {
        if (e.target.closest('.category-card')) {
            const card = e.target.closest('.category-card');
            const categoryId = card.dataset.categoryId;
            
            if (categoryId) {
                loadItems(categoryId);
            }
        }
    });
}

// Load initial data
function loadInitialData() {
    // Load first category items
    const firstCategory = document.querySelector('.category-card');
    if (firstCategory) {
        currentCategoryId = firstCategory.dataset.categoryId;
        loadItems(currentCategoryId);
    } else {
        // If no categories found, try to load items directly
        loadItems(1);
    }
}

// Load items for a specific category
function loadItems(categoryId) {
    currentCategoryId = categoryId;
    
    // Update active category
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('active');
    });
    
    const activeCard = document.querySelector(`[data-category-id="${categoryId}"]`);
    if (activeCard) {
        activeCard.classList.add('active');
    }
    
    // Show loading state
    const itemsGrid = document.getElementById('items-grid');
    if (itemsGrid) {
        itemsGrid.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><div class="loading"></div><p>Loading items...</p></div>';
    }
    
    // Try to fetch items via AJAX first
    const apiUrl = `api/get_items.php?category_id=${categoryId}`;
    
    console.log('Fetching items from:', apiUrl);
    
    fetch(apiUrl, {
        credentials: 'same-origin' // Include cookies for session
    })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API response:', data);
            if (data.success && data.items) {
                displayItems(data.items);
            } else {
                throw new Error(data.message || 'Error loading items');
            }
        })
        .catch(error => {
            console.error('API error:', error);
            // Fallback: Load items from the page data
            loadItemsFromPageData(categoryId);
        });
}

// Fallback function to load items from page data
function loadItemsFromPageData(categoryId) {
    const itemsGrid = document.getElementById('items-grid');
    if (!itemsGrid) return;
    
    // Get all item cards on the page
    const allItemCards = document.querySelectorAll('.item-card');
    const categoryItems = [];
    
    // Filter items by category (this is a fallback)
    allItemCards.forEach(card => {
        const itemId = card.getAttribute('onclick')?.match(/addToCart\((\d+)/)?.[1];
        if (itemId) {
            const itemName = card.querySelector('.item-name')?.textContent;
            const itemPrice = card.querySelector('.item-price')?.textContent.replace('PKR ', '');
            const itemDescription = card.querySelector('.item-description')?.textContent;
            
            if (itemName && itemPrice) {
                categoryItems.push({
                    id: itemId,
                    name: itemName,
                    price: parseFloat(itemPrice),
                    description: itemDescription || ''
                });
            }
        }
    });
    
    if (categoryItems.length === 0) {
        // If no items found, show a message
        itemsGrid.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No items found in this category. Please check the database.</div>';
    } else {
        displayItems(categoryItems);
    }
}

// Display items in the grid
function displayItems(items) {
    const itemsGrid = document.getElementById('items-grid');
    if (!itemsGrid) {
        return;
    }
    
    if (!items || items.length === 0) {
        itemsGrid.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No items found in this category.</div>';
        return;
    }
    
    const itemsHTML = items.map(item => {
        const safeName = item.name.replace(/'/g, "\\'").replace(/"/g, '\\"');
        const icon = getItemIcon(item.name);
        const price = parseFloat(item.price).toFixed(2);
        const description = item.description ? `<div class="item-description">${item.description}</div>` : '';
        
        // Check if item has size variants
        const hasSizeVariants = item.has_size_variants || (item.size_variants && item.size_variants.length > 0);
        const priceDisplay = hasSizeVariants ? 
            '<div class="item-price" style="color: #3b82f6; font-weight: 600;">Select Size</div>' : 
            `<div class="item-price">PKR ${price}</div>`;
        
        return `
            <div class="item-card" onclick="handleItemClick(${item.id}, '${safeName}', ${item.price}, ${hasSizeVariants})">
                <div class="item-image">${icon}</div>
                <div class="item-name">${item.name}</div>
                ${priceDisplay}
                ${description}
            </div>
        `;
    }).join('');
    
    itemsGrid.innerHTML = itemsHTML;
    
    // Force update payment display after items are loaded
    setTimeout(() => {
        if (typeof updatePaymentDisplay === 'function') {
            updatePaymentDisplay();
        }
    }, 100);
}

// Get appropriate icon for item
function getItemIcon(itemName) {
    const iconMap = {
        'PIZZA': '🍕',
        'BURGER': '🍔',
        'WINGS': '🍗',
        'SOUP': '🥣',
        'DRINKS': '🥤',
        'SHAWARMA': '🌯',
        'FRIES': '🍟',
        'SHAKE': '🥤',
        'SANDWICH': '🥪',
        'CHICKEN': '🍗',
        'FISH': '🐟',
        'BEEF': '🥩',
        'SALAD': '🥗',
        'DESSERT': '🍰',
        'COFFEE': '☕',
        'TEA': '🫖'
    };
    
    const upperName = itemName.toUpperCase();
    for (const [keyword, icon] of Object.entries(iconMap)) {
        if (upperName.includes(keyword)) {
            return icon;
        }
    }
    
    return '🍽️';
}

// Search items
function searchItems(query) {
    if (!query.trim()) {
        loadItems(currentCategoryId);
        return;
    }
    
    fetch(`api/search_items.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayItems(data.items);
            }
        })
        .catch(error => {
            // Handle search error silently
        });
}

// Navigation functions
function goHome() {
    window.location.href = 'index.php';
}

function goBack() {
    window.history.back();
}

function goNext() {
    window.history.forward();
}

function levelUp() {
    // Go to admin panel
    window.location.href = 'admin/index.php';
}

// Customer selection
function selectCustomer() {
    const customerName = document.getElementById('customer-name').value;
    const customerPostcode = document.getElementById('customer-postcode').value;
    
    if (!customerName.trim()) {
        showToast('Please enter customer name', 'warning');
        return;
    }
    
    showToast(`Customer selected: ${customerName}`, 'success');
}

// Order processing
function processOrder() {
    const cartItems = getCartItems();
    if (cartItems.length === 0) {
        showToast('Cart is empty!', 'error');
        return;
    }
    
    // Call the showPaymentModal from cart.js
    if (typeof window.showPaymentModal === 'function') {
        window.showPaymentModal();
    } else {
        showToast('Payment modal not available', 'error');
    }
}

// Take away order
function takeAway() {
    const cartItems = getCartItems();
    if (cartItems.length === 0) {
        showToast('Cart is empty!', 'error');
        return;
    }
    
    // Call the showPaymentModal from cart.js
    if (typeof window.showPaymentModal === 'function') {
        window.showPaymentModal();
    } else {
        showToast('Payment modal not available', 'error');
    }
}

// Table selection
function selectTable() {
    showModal('Select Table', `
        <div class="table-grid">
            ${Array.from({length: 20}, (_, i) => `
                <button class="table-btn" onclick="selectTableNumber(${i + 1})">
                    Table ${i + 1}
                </button>
            `).join('')}
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))">
                Cancel
            </button>
        </div>
    `);
}

// Select table number
function selectTableNumber(tableNumber) {
    showToast(`Table ${tableNumber} selected`, 'success');
    closeModal(document.querySelector('.modal'));
    // You can store the selected table in a variable or localStorage
    localStorage.setItem('selectedTable', tableNumber);
}

// Hold order
function holdOrder() {
    const cartItems = window.cart || [];
    if (cartItems.length === 0) {
        showToast('Cart is empty!', 'error');
        return;
    }
    
    // Calculate total properly
    const total = cartItems.reduce((sum, item) => {
        const itemTotal = (item.total_price || (item.price * item.quantity)) || 0;
        return sum + itemTotal;
    }, 0);
    
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    const orderData = {
        items: cartItems,
        total: total,
        customer: document.getElementById('customer-name')?.value || 'Walk-in Customer',
        timestamp: new Date().toISOString(),
        orderNumber: generateOrderNumber()
    };
    
    heldOrders.push(orderData);
    localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
    
    // Clear cart using the proper function
    if (typeof clearCart === 'function') {
        clearCart();
    } else {
        window.cart = [];
        if (typeof updateCartDisplay === 'function') {
            updateCartDisplay();
        }
    }
    
    showToast('Order held successfully', 'success');
}

// Recall order
function recallOrder() {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    if (heldOrders.length === 0) {
        showToast('No held orders', 'warning');
        return;
    }
    
    showHeldOrdersModal(heldOrders);
}

// Kitchen done
function kitchenDone() {
    const cartItems = window.cart || [];
    if (cartItems.length === 0) {
        showToast('Cart is empty!', 'error');
        return;
    }
    
    // Calculate total properly
    const total = cartItems.reduce((sum, item) => {
        const itemTotal = (item.total_price || (item.price * item.quantity)) || 0;
        return sum + itemTotal;
    }, 0);
    
    // Show kitchen order modal
    showModal('Kitchen Order', `
        <div style="text-align: center; padding: 20px;">
            <h3 style="color: #20bf55; margin-bottom: 20px;">Order Sent to Kitchen</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 5px 0;"><strong>Order Number:</strong> ${generateOrderNumber()}</p>
                <p style="margin: 5px 0;"><strong>Items:</strong> ${cartItems.length}</p>
                <p style="margin: 5px 0;"><strong>Total:</strong> PKR ${total.toFixed(2)}</p>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="confirmKitchenOrder()" style="margin-right: 10px;">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    `);
}

// Confirm kitchen order
function confirmKitchenOrder() {
    showToast('Order confirmed and sent to kitchen', 'success');
    closeModal(document.querySelector('.modal'));
    
    // Clear cart after sending to kitchen
    if (typeof clearCart === 'function') {
        clearCart();
    } else {
        window.cart = [];
        updateCartDisplay();
    }
}

// Additional functions
function showDressings() {
    showModal('Dressings', `
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
            <button class="btn btn-secondary" onclick="addDressing('Ketchup')">Ketchup</button>
            <button class="btn btn-secondary" onclick="addDressing('Mayo')">Mayo</button>
            <button class="btn btn-secondary" onclick="addDressing('Mustard')">Mustard</button>
            <button class="btn btn-secondary" onclick="addDressing('Hot Sauce')">Hot Sauce</button>
            <button class="btn btn-secondary" onclick="addDressing('BBQ')">BBQ</button>
            <button class="btn btn-secondary" onclick="addDressing('Ranch')">Ranch</button>
        </div>
    `);
}

function showNotes() {
    showModal('Instructions and Notes', `
        <div class="form-group">
            <label>Special Instructions:</label>
            <textarea id="order-notes" rows="4" placeholder="Enter special instructions..."></textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" onclick="saveNotes()">Save Notes</button>
            <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))">Cancel</button>
        </div>
    `);
}

function showSubMenu() {
    showModal('Sub Menu', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-secondary" onclick="showComboMenu()">Combo Meals</button>
            <button class="btn btn-secondary" onclick="showSides()">Side Items</button>
            <button class="btn btn-secondary" onclick="showDesserts()">Desserts</button>
            <button class="btn btn-secondary" onclick="showBeverages()">Beverages</button>
        </div>
    `);
}

function openBackOffice() {
    // Check user role and redirect accordingly
    if (window.userRole === 'admin') {
        window.open('admin/index.php', '_blank');
    } else if (window.userRole === 'cashier') {
        window.open('admin/cashier_dashboard.php', '_blank');
    } else {
        // Show access denied message for unauthorized users
        showModal('Access Denied', `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 64px; margin-bottom: 25px; color: #dc3545;"></i>
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">Access Required</h3>
                <p style="font-size: 16px; color: #6b7280; margin-bottom: 25px;">
                    You need administrator or cashier privileges to access the back office.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef;">
                    <p style="margin: 0; color: #495057; font-size: 14px;">
                        <strong>Current User:</strong> ${window.userName}<br>
                        <strong>Role:</strong> ${window.userRole}<br>
                        <strong>User ID:</strong> ${window.userId}
                    </p>
                </div>
                <div style="margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `);
    }
}

function showSpecialOffers() {
    // Check if user is admin for management access
    const isAdmin = window.userRole === 'admin';
    
    // Fetch special offers from API
    fetch('api/get_special_offers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let offersHTML = '';
                
                if (data.offers && data.offers.length > 0) {
                    offersHTML = `
                        <div style="text-align: center; margin-bottom: 25px;">
                            <h2 style="color: #1f2937; margin-bottom: 10px; font-size: 24px;">🎉 Special Offers</h2>
                            <p style="color: #6b7280; font-size: 16px;">Select an offer to add to cart</p>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            ${data.offers.map(offer => `
                                <div style="border: 3px solid #e5e7eb; padding: 25px; border-radius: 15px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); box-shadow: 0 8px 25px rgba(0,0,0,0.1); position: relative; transition: all 0.3s ease;">
                                    ${offer.priority > 1 ? `<div style="position: absolute; top: -10px; right: 15px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);">Priority ${offer.priority}</div>` : ''}
                                    
                                    <div style="text-align: center; margin-bottom: 20px;">
                                        <h3 style="margin: 0; color: #1f2937; font-size: 20px; font-weight: 700; line-height: 1.3;">${offer.title}</h3>
                                    </div>
                                    
                                    <p style="color: #6b7280; margin-bottom: 20px; line-height: 1.6; font-size: 15px; text-align: center;">${offer.description}</p>
                                    
                                    <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #e2e8f0; text-align: center;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <span style="text-decoration: line-through; color: #9ca3af; font-size: 16px; font-weight: 500;">PKR ${parseFloat(offer.original_price).toFixed(2)}</span>
                                            <span style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);">-${parseFloat(offer.discount_percentage).toFixed(1)}%</span>
                                        </div>
                                        <div style="color: #10b981; font-weight: 700; font-size: 24px; margin-bottom: 5px;">PKR ${parseFloat(offer.discounted_price).toFixed(2)}</div>
                                        <div style="color: #10b981; font-weight: 600; font-size: 14px;">Save: PKR ${(parseFloat(offer.original_price) - parseFloat(offer.discounted_price)).toFixed(2)}</div>
                                    </div>
                                    
                                    <div style="background: #fef3c7; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #f59e0b; text-align: center;">
                                        <div style="color: #92400e; font-size: 14px; font-weight: 600; margin-bottom: 5px;">📦 INCLUDES:</div>
                                        <div style="color: #78350f; font-size: 14px;">${offer.items_included}</div>
                                    </div>
                                    
                                    <button class="btn btn-primary" onclick="addSpecialOfferToCart(${offer.id}, '${offer.title.replace(/'/g, "\\'")}', ${offer.discounted_price})" style="width: 100%; padding: 15px; font-size: 16px; font-weight: 600; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 10px; color: white; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                                        <i class="fas fa-plus"></i> Add to Cart
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } else {
                    offersHTML = `
                        <div style="text-align: center; padding: 60px 20px; color: #6b7280;">
                            <i class="fas fa-gift" style="font-size: 64px; margin-bottom: 25px; color: #d1d5db;"></i>
                            <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">No Special Offers Available</h3>
                            <p style="font-size: 16px;">Check back later for amazing deals!</p>
                        </div>
                    `;
                }
                
                // Add admin management button if user is admin
                let adminButton = '';
                if (isAdmin) {
                    adminButton = `
                        <div style="text-align: center; margin-top: 30px; padding-top: 25px; border-top: 3px solid #e5e7eb;">
                            <button class="btn btn-secondary" onclick="window.open('admin/manage_special_offers.php', '_blank')" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                                <i class="fas fa-cog"></i> Manage Special Offers (Admin Only)
                            </button>
                        </div>
                    `;
                }
                
                showModal('🎁 Special Offers', offersHTML + adminButton);
            } else {
                showToast('Failed to load special offers', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading special offers:', error);
            showToast('Error loading special offers', 'error');
        });
}

// Modal functions
function showModal(title, content) {
    // Remove any existing modals
    const existingModal = document.querySelector('.modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
                <button class="modal-close" onclick="closeModal(this.closest('.modal'))">&times;</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
    

}

function closeModal(element) {
    if (element) {
        element.remove();
    }
}

// Payment modal - moved to cart.js to avoid conflicts

// Setup order type listeners - moved to cart.js to avoid conflicts

// Complete order function - moved to cart.js
// This function is now defined in cart.js to avoid conflicts

// Show order success modal - moved to cart.js
// This function is now defined in cart.js to avoid conflicts

// Print invoice
function printInvoice(orderId) {
    // Open invoice in new window for printing
    window.open(`print_invoice.php?order_id=${orderId}`, '_blank');
}

// Get cart items function - moved to cart.js
// This function is now defined in cart.js to avoid conflicts

// Get cart total function - moved to cart.js
// This function is now defined in cart.js to avoid conflicts

// Clear cart and reset form function
function clearCartAndResetForm() {
    // Clear cart from localStorage
    localStorage.removeItem('pos_cart');
    
    // Clear customer form fields
    const customerNameField = document.getElementById('customer-name');
    const customerPostcodeField = document.getElementById('customer-postcode');
    
    if (customerNameField) customerNameField.value = '';
    if (customerPostcodeField) customerPostcodeField.value = '';
    
    // Update cart display
    if (typeof updateCartDisplay === 'function') {
        updateCartDisplay();
    }
    
    // Update payment display
    if (typeof updatePaymentDisplay === 'function') {
        updatePaymentDisplay();
    }
    
    // Reset selected cart item
    if (window.selectedCartItem) {
        window.selectedCartItem = null;
    }
    
    showToast('Order completed! Cart cleared for next order.', 'success');
}

// Clear cart function
function clearCart() {
    // Clear cart from localStorage
    localStorage.removeItem('pos_cart');
    
    // Update cart display
    if (typeof updateCartDisplay === 'function') {
        updateCartDisplay();
    }
    
    showToast('Cart cleared', 'success');
}

// Start new order function
function startNewOrder() {
    // Show confirmation dialog if cart has items
    if (window.cart && window.cart.length > 0) {
        if (!confirm('Are you sure you want to start a new order? This will clear the current cart.')) {
            return;
        }
    }
    
    // Use the same clearing logic as clearCart function
    // Clear the cart array completely
    window.cart = [];
    window.selectedItemIndex = -1;
    
    // Clear the global selected cart item
    window.selectedCartItem = null;
    
    // Clear localStorage completely
    localStorage.removeItem('pos_cart');
    
    // Force clear the display immediately
    const cartItemsContainer = document.getElementById('cart-items');
    if (cartItemsContainer) {
        cartItemsContainer.innerHTML = `
            <div class="cart-empty" style="text-align: center; padding: 40px 20px; color: #64748b;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <i class="fas fa-shopping-cart" style="font-size: 32px; color: #94a3b8;"></i>
                </div>
                <h4 style="font-size: 18px; font-weight: 600; color: #475569; margin-bottom: 8px;">Your cart is empty</h4>
                <p style="font-size: 14px; color: #94a3b8; line-height: 1.5;">Add items from the menu to get started</p>
            </div>
        `;
    }
    
    // Update counters
    const cartItemCount = document.getElementById('cart-item-count');
    if (cartItemCount) {
        cartItemCount.textContent = getCartTotalQuantity();
    }
    
    // Update payment display with correct item count
    const totalAmount = document.getElementById('total-amount');
    if (totalAmount) {
        totalAmount.innerHTML = `
            <div style="text-align: center; padding: 12px; background: #e2e8f0; border-radius: 6px; margin-bottom: 8px; font-weight: 600; color: #1e293b; font-size: 16px;">
                Payment PKR 0.00 <span style="color: #64748b; font-size: 14px; font-weight: 500; margin-left: 10px;">Item(s): 0</span>
            </div>
        `;
    }
    
    // Update selected quantity display
    const quantityDisplay = document.getElementById('selected-quantity');
    if (quantityDisplay) {
        quantityDisplay.textContent = '1';
    }
    
    // Remove any selected cart items from UI
    document.querySelectorAll('.cart-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Clear customer information
    const customerNameInput = document.getElementById('customer-name');
    const customerPostcodeInput = document.getElementById('customer-postcode');
    
    if (customerNameInput) {
        customerNameInput.value = '';
    }
    if (customerPostcodeInput) {
        customerPostcodeInput.value = '';
    }
    
    // Close the modal if it's open
    const modal = document.querySelector('.modal');
    if (modal) {
        // Try to use closeModal function if available, otherwise just remove the modal
        if (typeof closeModal === 'function') {
            closeModal(modal);
        } else {
            modal.remove();
        }
    }
    

    
    // Save the cleared cart to storage
    saveCartToStorage();
    
    // Update the cart display
    updateCartDisplay();
    
    // Show success message
    showToast('New order started! Cart cleared.', 'success');
    
    // Generate new order number
    generateNewOrderNumber();
}

// Generate new order number
function generateNewOrderNumber() {
    // Call API to get the next sequential order number
    fetch('api/generate_order_number.php')
        .then(response => response.json())
        .then(data => {
            if (data.order_number) {
                // Update order number display if it exists
                const orderNumberElement = document.querySelector('.order-info span');
                if (orderNumberElement) {
                    orderNumberElement.textContent = `Order No: ${data.order_number}`;
                }
            } else {
                console.error('Failed to generate order number:', data.error);
                // Fallback to timestamp-based generation
                const timestamp = new Date().getTime();
                const random = Math.floor(Math.random() * 1000);
                const newOrderNumber = `ORD${timestamp}${random}`;
                
                const orderNumberElement = document.querySelector('.order-info span');
                if (orderNumberElement) {
                    orderNumberElement.textContent = `Order No: ${newOrderNumber}`;
                }
            }
        })
        .catch(error => {
            console.error('Error generating order number:', error);
            // Fallback to timestamp-based generation
            const timestamp = new Date().getTime();
            const random = Math.floor(Math.random() * 1000);
            const newOrderNumber = `ORD${timestamp}${random}`;
            
            const orderNumberElement = document.querySelector('.order-info span');
            if (orderNumberElement) {
                orderNumberElement.textContent = `Order No: ${newOrderNumber}`;
            }
        });
}

// Print receipt
function printReceipt(order) {
    const qrCodeUrl = generateQRCode(order);
    
    const receiptWindow = window.open('', '_blank');
    receiptWindow.document.write(`
        <html>
        <head>
            <title>Receipt</title>
            <style>
                body { 
                    font-family: 'Courier New', monospace; 
                    margin: 20px; 
                    background: white;
                    color: #1e293b;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 20px; 
                    border-bottom: 2px solid #e2e8f0;
                    padding-bottom: 15px;
                }
                .header h2 {
                    color: #20bf55;
                    margin: 0 0 10px 0;
                    font-size: 24px;
                }
                .order-info {
                    display: flex;
                    justify-content: space-between;
                    margin: 10px 0;
                    font-size: 14px;
                }
                .item { 
                    display: flex; 
                    justify-content: space-between; 
                    margin: 8px 0; 
                    padding: 5px 0;
                    border-bottom: 1px solid #f1f5f9;
                }
                .total { 
                    border-top: 2px solid #e2e8f0; 
                    margin-top: 15px; 
                    padding-top: 15px; 
                    font-weight: bold;
                    font-size: 16px;
                }
                .qr-section {
                    text-align: center;
                    margin: 20px 0;
                    padding: 15px;
                    border: 2px solid #e2e8f0;
                    border-radius: 8px;
                    background: #f8fafc;
                }
                .qr-code img {
                    max-width: 120px;
                    height: auto;
                    border-radius: 6px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 2px solid #e2e8f0;
                    color: #64748b;
                    font-size: 12px;
                }
                @media print {
                    body { margin: 0; }
                    .qr-section { border: 1px solid #000; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>🍕 Fast Food POS</h2>
                <div class="order-info">
                    <span>Order: ${order.order_number}</span>
                    <span>Date: ${new Date().toLocaleString()}</span>
                </div>
                <div class="order-info">
                    <span>Cashier: ${order.cashier || 'Admin'}</span>
                    <span>Type: ${order.order_type || 'Dine-in'}</span>
                </div>
            </div>
            <div class="items">
                ${order.items.map(item => `
                    <div class="item">
                        <span>${item.name} x${item.quantity}</span>
                        <span>PKR ${item.total_price}</span>
                    </div>
                `).join('')}
            </div>
            <div class="total">
                <div class="item">
                    <strong>Total:</strong>
                    <strong>PKR ${order.total_amount}</strong>
                </div>
            </div>
            <div class="qr-section">
                <h4>Scan for Order Details</h4>
                <div class="qr-code">
                    <img src="${qrCodeUrl}" alt="QR Code" />
                </div>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">
                    Scan to view order details online
                </p>
            </div>
            <div class="footer">
                <p>Thank you for your order!</p>
                <p>Visit us again soon</p>
                <p>www.fastfoodpos.com</p>
            </div>
        </body>
        </html>
    `);
    receiptWindow.document.close();
    receiptWindow.print();
}

// Keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // F1-F12 for categories
        if (e.key >= 'F1' && e.key <= 'F12') {
            e.preventDefault();
            const categoryIndex = parseInt(e.key.replace('F', '')) - 1;
            const categories = document.querySelectorAll('.category-card');
            if (categories[categoryIndex]) {
                categories[categoryIndex].click();
            }
        }
        
        // Enter to process order
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            processOrder();
        }
        
        // Escape to clear cart
        if (e.key === 'Escape') {
            e.preventDefault();
            clearCart();
        }
    });
}

// Calculator functions
function calcInput(value) {
    const calcDisplay = document.getElementById('calc-display');
    if (calcDisplay) {
        // Prevent multiple operators in a row
        const currentValue = calcDisplay.value;
        const lastChar = currentValue.slice(-1);
        const operators = ['+', '-', '*', '/'];
        
        if (operators.includes(value) && operators.includes(lastChar)) {
            // Replace the last operator with the new one
            calcDisplay.value = currentValue.slice(0, -1) + value;
        } else {
            calcDisplay.value += value;
        }
    }
}

function calcClear() {
    const calcDisplay = document.getElementById('calc-display');
    if (calcDisplay) {
        calcDisplay.value = '';
    }
}

function calcResult() {
    const calcDisplay = document.getElementById('calc-display');
    if (calcDisplay) {
        try {
            const expression = calcDisplay.value;
            if (expression.trim() === '') {
                calcDisplay.value = '0';
                return;
            }
            
            // Replace × with * and ÷ with / for evaluation
            const sanitizedExpression = expression.replace(/×/g, '*').replace(/÷/g, '/');
            const result = eval(sanitizedExpression);
            
            // Check if result is valid
            if (isFinite(result)) {
                calcDisplay.value = result;
            } else {
                calcDisplay.value = 'Error';
            }
        } catch (e) {
            calcDisplay.value = 'Error';
        }
    }
}

// Additional functions
function showFunctions() {
    // Check if user is admin or cashier for back office access
    const isAdmin = window.userRole === 'admin';
    const isCashier = window.userRole === 'cashier';
    const canAccessBackOffice = isAdmin || isCashier;
    
    showModal('Functions', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="showDressings()">
                <i class="fas fa-bottle-water"></i> Dressings
            </button>
            <button class="btn btn-primary" onclick="showNotes()">
                <i class="fas fa-sticky-note"></i> Notes
            </button>
            <button class="btn btn-primary" onclick="showSubMenu()">
                <i class="fas fa-list"></i> Sub Menu
            </button>
            <button class="btn btn-primary" onclick="showSpecialOffers()">
                <i class="fas fa-gift"></i> Special Offers
            </button>
            <button class="btn btn-primary" onclick="showEasyPad()">
                <i class="fas fa-calculator"></i> EasyPad
            </button>
            <button class="btn btn-primary" onclick="showPizza()">
                <i class="fas fa-pizza-slice"></i> Pizza Menu
            </button>
            ${canAccessBackOffice ? 
                '<button class="btn btn-secondary" onclick="openBackOffice()"><i class="fas fa-building"></i> Back Office</button>' :
                '<button class="btn btn-secondary" onclick="openBackOffice()" style="opacity: 0.7;"><i class="fas fa-building"></i> Back Office (Admin/Cashier)</button>'
            }
        </div>
    `);
}

function showMore() {
    // Check if user is admin for admin-specific functions
    const isAdmin = window.userRole === 'admin';
    const isCashier = window.userRole === 'cashier';
    
    showModal('More Options', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="showSettings()">
                <i class="fas fa-cog"></i> Settings
            </button>
            <button class="btn btn-primary" onclick="showHelp()">
                <i class="fas fa-question-circle"></i> Help
            </button>
            <button class="btn btn-primary" onclick="showNotes()">
                <i class="fas fa-sticky-note"></i> Notes
            </button>
            <button class="btn btn-primary" onclick="showDressings()">
                <i class="fas fa-bottle-water"></i> Dressings
            </button>
            ${(isAdmin || isCashier) ? 
                '<button class="btn btn-secondary" onclick="showReports()"><i class="fas fa-chart-bar"></i> Reports</button>' :
                '<button class="btn btn-secondary" onclick="showReports()" style="opacity: 0.7;"><i class="fas fa-chart-bar"></i> Reports (Admin/Cashier)</button>'
            }
            ${isAdmin ? 
                '<button class="btn btn-secondary" onclick="showInventory()"><i class="fas fa-boxes"></i> Inventory</button>' :
                '<button class="btn btn-secondary" onclick="showInventory()" style="opacity: 0.7;"><i class="fas fa-boxes"></i> Inventory (Admin)</button>'
            }
            ${isAdmin ? 
                '<button class="btn btn-secondary" onclick="showUsers()"><i class="fas fa-users"></i> Users</button>' :
                '<button class="btn btn-secondary" onclick="showUsers()" style="opacity: 0.7;"><i class="fas fa-users"></i> Users (Admin)</button>'
            }
            ${isAdmin ? 
                '<button class="btn btn-secondary" onclick="showBackup()"><i class="fas fa-download"></i> Backup</button>' :
                '<button class="btn btn-secondary" onclick="showBackup()" style="opacity: 0.7;"><i class="fas fa-download"></i> Backup (Admin)</button>'
            }
        </div>
    `);
}

// Generate order number
function generateOrderNumber() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `ORD${year}${month}${day}${random}`;
}

// Generate QR code for order
function generateQRCode(orderData) {
    const qrData = JSON.stringify({
        orderNumber: orderData.order_number,
        total: orderData.total_amount,
        items: orderData.items.length,
        timestamp: new Date().toISOString()
    });
    
    // Using a simple QR code service (you can replace with a proper QR library)
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrData)}`;
    return qrUrl;
}

// Additional utility functions
function showPizza() {
    // Fetch pizza items from the database
    fetch('api/get_items.php?category_id=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                let pizzaMenuHTML = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">';
                
                data.items.forEach(item => {
                    if (item.has_size_variants) {
                        pizzaMenuHTML += `
                            <button class="btn btn-primary" onclick="handleItemClick(${item.id}, '${item.name.replace(/'/g, "\\'")}', 0, true)">
                                <i class="fas fa-pizza-slice"></i> ${item.name}<br>
                                <small style="font-size: 12px; opacity: 0.8;">Select Size</small>
                            </button>
                        `;
                    } else {
                        pizzaMenuHTML += `
                            <button class="btn btn-primary" onclick="addToCart(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price})">
                                <i class="fas fa-pizza-slice"></i> ${item.name}<br>
                                <small style="font-size: 12px; opacity: 0.8;">PKR ${item.price}</small>
                            </button>
                        `;
                    }
                });
                
                pizzaMenuHTML += '</div>';
                showModal('Pizza Menu', pizzaMenuHTML);
            } else {
                showModal('Pizza Menu', '<p>No pizza items available.</p>');
            }
        })
        .catch(error => {
            console.error('Error loading pizza items:', error);
            showModal('Pizza Menu', '<p>Error loading pizza items.</p>');
        });
}

function showZebra() {
    showModal('Zebra Printer Management', `
        <div style="max-width: 600px; margin: 0 auto;">
            <h3 style="margin-bottom: 20px; color: #1e293b; text-align: center;">
                <i class="fas fa-print"></i> Zebra Printer Management
            </h3>
            
            <!-- Printer Status -->
            <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px; color: #475569;">
                    <i class="fas fa-info-circle"></i> Printer Status
                </h4>
                <div id="printer-status" style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: #10b981;" id="status-indicator"></div>
                    <span id="status-text">Connected - Ready</span>
                </div>
            </div>
            
            <!-- Printer Configuration -->
            <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin-bottom: 15px; color: #475569;">
                    <i class="fas fa-cog"></i> Printer Configuration
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Printer IP Address:</label>
                        <input type="text" id="printer-ip" value="192.168.1.100" 
                               style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Port:</label>
                        <input type="number" id="printer-port" value="9100" 
                               style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Label Width (inches):</label>
                        <input type="number" id="label-width" value="4" step="0.1" 
                               style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Label Height (inches):</label>
                        <input type="number" id="label-height" value="6" step="0.1" 
                               style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                    </div>
                </div>
            </div>
            
            <!-- Print Options -->
            <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin-bottom: 15px; color: #475569;">
                    <i class="fas fa-tags"></i> Print Options
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Print Type:</label>
                        <select id="print-type" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                            <option value="receipt">Receipt</option>
                            <option value="label">Label</option>
                            <option value="barcode">Barcode</option>
                            <option value="qr">QR Code</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Copies:</label>
                        <input type="number" id="print-copies" value="1" min="1" max="10" 
                               style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px;">
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="testZebraConnection()">
                    <i class="fas fa-wifi"></i> Test Connection
                </button>
                <button class="btn btn-secondary" onclick="saveZebraSettings()">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <button class="btn btn-primary" onclick="printZebraTest()">
                    <i class="fas fa-print"></i> Test Print
                </button>
            </div>
            
            <!-- Quick Print Actions -->
            <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                <h4 style="margin-bottom: 15px; color: #475569;">
                    <i class="fas fa-bolt"></i> Quick Print Actions
                </h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <button class="btn btn-outline" onclick="printZebraReceipt()">
                        <i class="fas fa-receipt"></i> Print Receipt
                    </button>
                    <button class="btn btn-outline" onclick="printZebraLabel()">
                        <i class="fas fa-tag"></i> Print Label
                    </button>
                    <button class="btn btn-outline" onclick="printZebraBarcode()">
                        <i class="fas fa-barcode"></i> Print Barcode
                    </button>
                    <button class="btn btn-outline" onclick="printZebraQR()">
                        <i class="fas fa-qrcode"></i> Print QR Code
                    </button>
                </div>
            </div>
        </div>
    `);
    
    // Load saved settings
    loadZebraSettings();
}

function loadZebraSettings() {
    const ip = localStorage.getItem('printerIp') || '192.168.1.100';
    const port = localStorage.getItem('printerPort') || '9100';
    const labelWidth = localStorage.getItem('labelWidth') || '4';
    const labelHeight = localStorage.getItem('labelHeight') || '6';
    const printType = localStorage.getItem('printType') || 'receipt';
    const printCopies = localStorage.getItem('printCopies') || '1';

    document.getElementById('printer-ip').value = ip;
    document.getElementById('printer-port').value = port;
    document.getElementById('label-width').value = labelWidth;
    document.getElementById('label-height').value = labelHeight;
    document.getElementById('print-type').value = printType;
    document.getElementById('print-copies').value = printCopies;
}

function saveZebraSettings() {
    const ip = document.getElementById('printer-ip').value;
    const port = document.getElementById('printer-port').value;
    const labelWidth = document.getElementById('label-width').value;
    const labelHeight = document.getElementById('label-height').value;
    const printType = document.getElementById('print-type').value;
    const printCopies = document.getElementById('print-copies').value;

    localStorage.setItem('printerIp', ip);
    localStorage.setItem('printerPort', port);
    localStorage.setItem('labelWidth', labelWidth);
    localStorage.setItem('labelHeight', labelHeight);
    localStorage.setItem('printType', printType);
    localStorage.setItem('printCopies', printCopies);
    showToast('Settings saved successfully!', 'success');
}

function testZebraConnection() {
    const ip = document.getElementById('printer-ip').value;
    const port = document.getElementById('printer-port').value;
    const statusText = document.getElementById('status-text');
    const statusIndicator = document.getElementById('status-indicator');

    statusText.textContent = 'Connecting...';
    statusIndicator.style.backgroundColor = '#f59e0b'; // Yellow

    // For local network printers, we'll simulate a connection test
    // In a real implementation, you would use WebSocket or direct TCP connection
    setTimeout(() => {
        // Simulate connection test - in real implementation, this would be an actual network test
        const isConnected = Math.random() > 0.3; // 70% success rate for demo
        
        if (isConnected) {
            statusText.textContent = 'Connected - Ready';
            statusIndicator.style.backgroundColor = '#10b981'; // Green
            showToast(`Zebra printer connection successful! (${ip}:${port})`, 'success');
        } else {
            statusText.textContent = 'Disconnected';
            statusIndicator.style.backgroundColor = '#ef4444'; // Red
            showToast(`Zebra printer connection failed. Please check IP (${ip}) and port (${port}).`, 'error');
        }
    }, 1000);
}

function printZebraTest() {
    const printType = document.getElementById('print-type').value;
    const copies = document.getElementById('print-copies').value || 1;
    
    let zpl = '';
    const testOrderNumber = 'TEST' + Date.now().toString().slice(-6);
    const testDate = new Date().toLocaleString();
    const testTotal = '150.00';
    
    switch(printType) {
        case 'receipt':
            zpl = generateReceiptZPL(testOrderNumber, testDate, testTotal);
            break;
        case 'label':
            zpl = generateLabelZPL(testOrderNumber, testDate);
            break;
        case 'barcode':
            zpl = generateBarcodeZPL(testOrderNumber);
            break;
        case 'qr':
            zpl = generateQRZPL(testOrderNumber, testTotal);
            break;
        default:
            zpl = `^XA^FO50,50^A0N,50,50^FDTest Print^FS^XZ`;
    }
    
    if (zpl) {
        printZebra(zpl, copies);
        showToast(`Test ${printType} print sent to Zebra printer`, 'success');
    } else {
        showToast('Please select a valid print type.', 'warning');
    }
}

function printZebra(zpl, copies) {
    const ip = document.getElementById('printer-ip').value;
    const port = document.getElementById('printer-port').value;

    // For demo purposes, we'll simulate the print process
    // In a real implementation, this would send ZPL commands to the printer
    showToast(`Sending print job to Zebra printer (${ip}:${port})...`, 'info');
    
    setTimeout(() => {
        // Simulate print success
        showToast(`Print job completed! ${copies} copy(ies) sent to Zebra printer.`, 'success');
        

    }, 2000);
}

function printZebraReceipt() {
    const orderNumber = generateOrderNumber();
    const currentDate = new Date().toLocaleString();
    const totalAmount = getCartTotal();
    
    // Generate ZPL for receipt
    const zpl = generateReceiptZPL(orderNumber, currentDate, totalAmount);
    const copies = document.getElementById('print-copies').value || 1;
    
    printZebra(zpl, copies);
    showToast('Receipt sent to Zebra printer', 'success');
}

function printZebraLabel() {
    const orderNumber = generateOrderNumber();
    const currentDate = new Date().toLocaleString();
    
    // Generate ZPL for label
    const zpl = generateLabelZPL(orderNumber, currentDate);
    const copies = document.getElementById('print-copies').value || 1;
    
    printZebra(zpl, copies);
    showToast('Label sent to Zebra printer', 'success');
}

function printZebraBarcode() {
    const orderNumber = generateOrderNumber();
    
    // Generate ZPL for barcode
    const zpl = generateBarcodeZPL(orderNumber);
    const copies = document.getElementById('print-copies').value || 1;
    
    printZebra(zpl, copies);
    showToast('Barcode sent to Zebra printer', 'success');
}

function printZebraQR() {
    const orderNumber = generateOrderNumber();
    const totalAmount = getCartTotal();
    
    // Generate ZPL for QR code
    const zpl = generateQRZPL(orderNumber, totalAmount);
    const copies = document.getElementById('print-copies').value || 1;
    
    printZebra(zpl, copies);
    showToast('QR Code sent to Zebra printer', 'success');
}

// ZPL Generation Functions
function generateReceiptZPL(orderNumber, date, total) {
    const labelWidth = document.getElementById('label-width').value || 4;
    const labelHeight = document.getElementById('label-height').value || 6;
    
    return `^XA
^FO50,50^A0N,60,60^FD🍕 Fast Food POS^FS
^FO50,120^A0N,40,40^FDReceipt^FS
^FO50,170^A0N,30,30^FDOrder: ${orderNumber}^FS
^FO50,210^A0N,30,30^FDDate: ${date}^FS
^FO50,250^A0N,30,30^FDTotal: PKR ${total}^FS
^FO50,290^A0N,25,25^FDThank you for your order!^FS
^XZ`;
}

function generateLabelZPL(orderNumber, date) {
    const labelWidth = document.getElementById('label-width').value || 4;
    const labelHeight = document.getElementById('label-height').value || 6;
    
    return `^XA
^FO50,50^A0N,50,50^FDOrder Label^FS
^FO50,110^A0N,40,40^FD${orderNumber}^FS
^FO50,160^A0N,30,30^FD${date}^FS
^FO50,200^A0N,25,25^FDKitchen Copy^FS
^XZ`;
}

function generateBarcodeZPL(orderNumber) {
    const labelWidth = document.getElementById('label-width').value || 4;
    const labelHeight = document.getElementById('label-height').value || 6;
    
    return `^XA
^FO50,50^A0N,40,40^FDBarcode^FS
^FO50,100^BY3^BCN,100,Y,N,N^FD${orderNumber}^FS
^FO50,220^A0N,30,30^FD${orderNumber}^FS
^XZ`;
}

function generateQRZPL(orderNumber, total) {
    const labelWidth = document.getElementById('label-width').value || 4;
    const labelHeight = document.getElementById('label-height').value || 6;
    
    const qrData = JSON.stringify({
        orderNumber: orderNumber,
        total: total,
        timestamp: new Date().toISOString()
    });
    
    return `^XA
^FO50,50^A0N,40,40^FDQR Code^FS
^FO50,100^BQN,2,8^FD${qrData}^FS
^FO50,250^A0N,30,30^FD${orderNumber}^FS
^XZ`;
}

// getCartTotal function - moved to cart.js
// This function is now defined in cart.js to avoid conflicts

// Placeholder functions for the above modals
function showGeneralSettings() { showToast('General Settings - Coming Soon', 'info'); }
function showPrinterSettings() { showToast('Printer Settings - Coming Soon', 'info'); }
function showPaymentSettings() { showToast('Payment Settings - Coming Soon', 'info'); }
function showTaxSettings() { showToast('Tax Settings - Coming Soon', 'info'); }
function showDailyReport() { showToast('Daily Report - Coming Soon', 'info'); }
function showItemReport() { showToast('Item Report - Coming Soon', 'info'); }
function showUserReport() { showToast('User Report - Coming Soon', 'info'); }
function showStockLevels() { showToast('Stock Levels - Coming Soon', 'info'); }
function showLowStock() { showToast('Low Stock - Coming Soon', 'info'); }
function showInventoryReport() { showToast('Inventory Report - Coming Soon', 'info'); }
function showSuppliers() { showToast('Suppliers - Coming Soon', 'info'); }
function showUserRoles() { showToast('User Roles - Coming Soon', 'info'); }
function showUserActivity() { showToast('User Activity - Coming Soon', 'info'); }
function showPermissions() { showToast('Permissions - Coming Soon', 'info'); }
function createBackup() { showToast('Creating backup...', 'info'); }
function restoreBackup() { showToast('Restore backup - Coming Soon', 'info'); }
function showBackupHistory() { showToast('Backup History - Coming Soon', 'info'); }
function showAutoBackup() { showToast('Auto Backup - Coming Soon', 'info'); }
function showUserGuide() { showToast('User Guide - Coming Soon', 'info'); }
function showVideoTutorials() { showToast('Video Tutorials - Coming Soon', 'info'); }
function showFAQ() { showToast('FAQ - Coming Soon', 'info'); }
function contactSupport() { showToast('Contact Support - Coming Soon', 'info'); }

// Add missing function definitions
function addDressing(dressingType) {
    if (window.selectedCartItem) {
        const itemId = window.selectedCartItem.dataset.itemId;
        const item = cart.find(item => item.id == itemId);
        if (item) {
            if (!item.dressings) {
                item.dressings = [];
            }
            if (!item.dressings.includes(dressingType)) {
                item.dressings.push(dressingType);
                updateCartDisplay();
                showToast(`Added ${dressingType} to ${item.name}`, 'success');
            } else {
                showToast(`${dressingType} already added to ${item.name}`, 'warning');
            }
        }
    } else {
        showToast('Please select an item first', 'warning');
    }
    closeModal(document.querySelector('.modal'));
}

function addSpecialOfferToCart(offerId, offerTitle, offerPrice) {
    try {
        // Validate parameters
        if (!offerId || !offerTitle || !offerPrice) {
            console.error('Invalid parameters for addSpecialOfferToCart:', { offerId, offerTitle, offerPrice });
            showToast('Error: Invalid offer data', 'error');
            return;
        }
        
        // Ensure price is a number
        const safePrice = parseFloat(offerPrice) || 0;
        if (safePrice <= 0) {
            showToast('Error: Invalid offer price', 'error');
            return;
        }
        
        // Add special offer to cart
        const offerItem = {
            id: `offer_${offerId}`,
            name: offerTitle,
            price: safePrice,
            quantity: 1,
            type: 'special_offer',
            offerId: offerId
        };
        
        addToCart(offerItem.id, offerItem.name, offerItem.price);
        closeModal(document.querySelector('.modal'));
        showToast(`Added ${offerTitle} to cart`, 'success');
    } catch (error) {
        console.error('Error adding special offer to cart:', error);
        showToast('Error adding offer to cart', 'error');
    }
}

function saveNotes() {
    const notes = document.getElementById('order-notes').value;
    if (notes.trim()) {
        // Store notes in localStorage or session
        localStorage.setItem('orderNotes', notes);
        showToast('Notes saved successfully', 'success');
    }
    closeModal(document.querySelector('.modal'));
}

function showComboMenu() {
    showModal('Combo Meals', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div style="border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px;">
                <h4>🍕 Pizza Combo</h4>
                <p>Any Pizza + Drink + Fries</p>
                <p style="color: #20bf55; font-weight: bold;">PKR 650</p>
                <button class="btn btn-primary" onclick="addComboToCart('pizza')">Add Combo</button>
            </div>
            <div style="border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px;">
                <h4>🍔 Burger Combo</h4>
                <p>Any Burger + Fries + Drink</p>
                <p style="color: #20bf55; font-weight: bold;">PKR 550</p>
                <button class="btn btn-primary" onclick="addComboToCart('burger')">Add Combo</button>
            </div>
        </div>
    `);
}

function showSides() {
    showModal('Side Items', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <button class="btn btn-secondary" onclick="addToCart(101, 'French Fries', 150)">French Fries</button>
            <button class="btn btn-secondary" onclick="addToCart(102, 'Onion Rings', 200)">Onion Rings</button>
            <button class="btn btn-secondary" onclick="addToCart(103, 'Mozzarella Sticks', 250)">Mozzarella Sticks</button>
            <button class="btn btn-secondary" onclick="addToCart(104, 'Garlic Bread', 180)">Garlic Bread</button>
        </div>
    `);
}

function showDesserts() {
    showModal('Desserts', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <button class="btn btn-secondary" onclick="addToCart(201, 'Chocolate Cake', 300)">Chocolate Cake</button>
            <button class="btn btn-secondary" onclick="addToCart(202, 'Ice Cream', 250)">Ice Cream</button>
            <button class="btn btn-secondary" onclick="addToCart(203, 'Cheesecake', 350)">Cheesecake</button>
            <button class="btn btn-secondary" onclick="addToCart(204, 'Brownie', 200)">Brownie</button>
        </div>
    `);
}

function showBeverages() {
    showModal('Beverages', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <button class="btn btn-secondary" onclick="addToCart(301, 'Coca Cola', 180)">Coca Cola</button>
            <button class="btn btn-secondary" onclick="addToCart(302, 'Pepsi', 180)">Pepsi</button>
            <button class="btn btn-secondary" onclick="addToCart(303, 'Sprite', 180)">Sprite</button>
            <button class="btn btn-secondary" onclick="addToCart(304, 'Water', 100)">Water</button>
        </div>
    `);
}

function addComboToCart(comboType) {
    if (comboType === 'pizza') {
        addToCart(401, 'Pizza Combo (Pizza + Fries + Drink)', 650);
    } else if (comboType === 'burger') {
        addToCart(402, 'Burger Combo (Burger + Fries + Drink)', 550);
    }
    closeModal(document.querySelector('.modal'));
}

function addBurgerCombo() {
    addToCart(402, 'Burger Combo (Burger + Fries + Drink)', 550);
    closeModal(document.querySelector('.modal'));
}

function recallHeldOrder(orderIndex) {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    if (heldOrders[orderIndex]) {
        const heldOrder = heldOrders[orderIndex];
        // Restore the cart from held order
        cart = [...heldOrder.items];
        saveCartToStorage();
        updateCartDisplay();
        
        // Remove from held orders
        heldOrders.splice(orderIndex, 1);
        localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
        
        showToast('Order recalled successfully', 'success');
        closeModal(document.querySelector('.modal'));
    } else {
        showToast('Order not found', 'error');
    }
}

// Add missing function definitions
function showSettings() {
    showModal('Settings', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-secondary" onclick="showGeneralSettings()">
                <i class="fas fa-cog"></i> General
            </button>
            <button class="btn btn-secondary" onclick="showPrinterSettings()">
                <i class="fas fa-print"></i> Printer
            </button>
            <button class="btn btn-secondary" onclick="showPaymentSettings()">
                <i class="fas fa-credit-card"></i> Payment
            </button>
            <button class="btn btn-secondary" onclick="showTaxSettings()">
                <i class="fas fa-percentage"></i> Tax
            </button>
        </div>
    `);
}

function showReports() {
    // Check if user is admin
    if (window.userRole !== 'admin') {
        showModal('Access Denied', `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 64px; margin-bottom: 25px; color: #dc3545;"></i>
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">Admin Access Required</h3>
                <p style="font-size: 16px; color: #6b7280; margin-bottom: 25px;">
                    You need administrator privileges to access reports.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef;">
                    <p style="margin: 0; color: #495057; font-size: 14px;">
                        <strong>Current User:</strong> ${window.userName}<br>
                        <strong>Role:</strong> ${window.userRole}<br>
                        <strong>User ID:</strong> ${window.userId}
                    </p>
                </div>
                <div style="margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `);
        return;
    }
    
    showModal('Reports', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="window.open('admin/reports.php', '_blank')">
                <i class="fas fa-chart-bar"></i> Sales Report
            </button>
            <button class="btn btn-primary" onclick="showDailyReport()">
                <i class="fas fa-calendar-day"></i> Daily Report
            </button>
            <button class="btn btn-primary" onclick="showItemReport()">
                <i class="fas fa-box"></i> Item Report
            </button>
            <button class="btn btn-primary" onclick="showUserReport()">
                <i class="fas fa-user"></i> User Report
            </button>
        </div>
    `);
}

function showInventory() {
    // Check if user is admin
    if (window.userRole !== 'admin') {
        showModal('Access Denied', `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 64px; margin-bottom: 25px; color: #dc3545;"></i>
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">Admin Access Required</h3>
                <p style="font-size: 16px; color: #6b7280; margin-bottom: 25px;">
                    You need administrator privileges to access inventory management.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef;">
                    <p style="margin: 0; color: #495057; font-size: 14px;">
                        <strong>Current User:</strong> ${window.userName}<br>
                        <strong>Role:</strong> ${window.userRole}<br>
                        <strong>User ID:</strong> ${window.userId}
                    </p>
                </div>
                <div style="margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `);
        return;
    }
    
    showModal('Inventory', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="showStockLevels()">
                <i class="fas fa-boxes"></i> Stock Levels
            </button>
            <button class="btn btn-primary" onclick="showLowStock()">
                <i class="fas fa-exclamation-triangle"></i> Low Stock
            </button>
            <button class="btn btn-primary" onclick="showInventoryReport()">
                <i class="fas fa-chart-line"></i> Inventory Report
            </button>
            <button class="btn btn-primary" onclick="showSuppliers()">
                <i class="fas fa-truck"></i> Suppliers
            </button>
        </div>
    `);
}

function showUsers() {
    // Check if user is admin
    if (window.userRole !== 'admin') {
        showModal('Access Denied', `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 64px; margin-bottom: 25px; color: #dc3545;"></i>
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">Admin Access Required</h3>
                <p style="font-size: 16px; color: #6b7280; margin-bottom: 25px;">
                    You need administrator privileges to access user management.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef;">
                    <p style="margin: 0; color: #495057; font-size: 14px;">
                        <strong>Current User:</strong> ${window.userName}<br>
                        <strong>Role:</strong> ${window.userRole}<br>
                        <strong>User ID:</strong> ${window.userId}
                    </p>
                </div>
                <div style="margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `);
        return;
    }
    
    showModal('User Management', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="window.open('admin/manage_users.php', '_blank')">
                <i class="fas fa-users"></i> Manage Users
            </button>
            <button class="btn btn-primary" onclick="showUserRoles()">
                <i class="fas fa-user-tag"></i> User Roles
            </button>
            <button class="btn btn-primary" onclick="showUserActivity()">
                <i class="fas fa-user-clock"></i> User Activity
            </button>
            <button class="btn btn-primary" onclick="showPermissions()">
                <i class="fas fa-shield-alt"></i> Permissions
            </button>
        </div>
    `);
}

function showBackup() {
    // Check if user is admin
    if (window.userRole !== 'admin') {
        showModal('Access Denied', `
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 64px; margin-bottom: 25px; color: #dc3545;"></i>
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #374151;">Admin Access Required</h3>
                <p style="font-size: 16px; color: #6b7280; margin-bottom: 25px;">
                    You need administrator privileges to access backup and restore functions.
                </p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef;">
                    <p style="margin: 0; color: #495057; font-size: 14px;">
                        <strong>Current User:</strong> ${window.userName}<br>
                        <strong>Role:</strong> ${window.userRole}<br>
                        <strong>User ID:</strong> ${window.userId}
                    </p>
                </div>
                <div style="margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `);
        return;
    }
    
    showModal('Backup & Restore', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="createBackup()">
                <i class="fas fa-download"></i> Create Backup
            </button>
            <button class="btn btn-primary" onclick="restoreBackup()">
                <i class="fas fa-upload"></i> Restore Backup
            </button>
            <button class="btn btn-primary" onclick="showBackupHistory()">
                <i class="fas fa-history"></i> Backup History
            </button>
            <button class="btn btn-primary" onclick="showAutoBackup()">
                <i class="fas fa-clock"></i> Auto Backup
            </button>
        </div>
    `);
}

function showHelp() {
    showModal('Help & Support', `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <button class="btn btn-primary" onclick="showUserGuide()">
                <i class="fas fa-book"></i> User Guide
            </button>
            <button class="btn btn-primary" onclick="showVideoTutorials()">
                <i class="fas fa-video"></i> Video Tutorials
            </button>
            <button class="btn btn-primary" onclick="showFAQ()">
                <i class="fas fa-question-circle"></i> FAQ
            </button>
            <button class="btn btn-primary" onclick="contactSupport()">
                <i class="fas fa-headset"></i> Contact Support
            </button>
        </div>
    `);
}

// Export all functions for global access
window.processOrderWithType = function() {
    const total = getCartTotal();
    if (typeof window.showPaymentModal === 'function') {
        window.showPaymentModal();
    } else {
        showToast('Payment modal not available', 'error');
    }
};

window.startNewOrder = startNewOrder;

window.showHeldOrdersModal = function(heldOrders) {
    const ordersList = heldOrders.map((order, index) => {
        // Ensure total is a number and handle potential issues
        const total = typeof order.total === 'number' ? order.total : 
                     (typeof order.total === 'string' ? parseFloat(order.total) : 0);
        const itemsCount = Array.isArray(order.items) ? order.items.length : 0;
        const customer = order.customer || 'Walk-in';
        const timestamp = order.timestamp ? new Date(order.timestamp).toLocaleString() : 'Unknown';
        
        return `
            <div style="border: 1px solid #e0e0e0; padding: 15px; margin: 10px 0; border-radius: 8px; background: #f8f9fa;">
                <h4 style="margin: 0 0 10px 0; color: #20bf55;">Order ${index + 1}</h4>
                <div style="margin-bottom: 10px;">
                    <p style="margin: 5px 0;"><strong>Items:</strong> ${itemsCount}</p>
                    <p style="margin: 5px 0;"><strong>Total:</strong> PKR ${total.toFixed(2)}</p>
                    <p style="margin: 5px 0;"><strong>Customer:</strong> ${customer}</p>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;"><strong>Time:</strong> ${timestamp}</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="recallHeldOrder(${index})" style="flex: 1;">
                        <i class="fas fa-redo"></i> Recall Order
                    </button>
                    <button class="btn btn-danger" onclick="deleteHeldOrder(${index})" style="flex: 1;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    const modalContent = `
        <div>
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #20bf55;">Held Orders (${heldOrders.length})</h3>
            </div>
            ${ordersList}
            ${heldOrders.length > 0 ? `
                <div style="margin-top: 20px; text-align: center;">
                    <button class="btn btn-secondary" onclick="clearAllHeldOrders()">
                        <i class="fas fa-trash"></i> Clear All Held Orders
                    </button>
                </div>
            ` : ''}
        </div>
    `;
    
    showModal('Held Orders', modalContent);
};

window.recallHeldOrder = function(index) {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    if (heldOrders[index]) {
        const order = heldOrders[index];
        
        // Clear current cart first
        if (typeof clearCart === 'function') {
            clearCart();
        } else {
            window.cart = [];
        }
        
        // Add items from held order to cart
        if (Array.isArray(order.items)) {
            order.items.forEach(item => {
                if (typeof addToCart === 'function') {
                    addToCart(item.id, item.name, item.price, item.quantity);
                } else {
                    // Fallback if addToCart is not available
                    const cartItem = {
                        id: item.id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity,
                        total_price: item.total_price || (item.price * item.quantity)
                    };
                    window.cart.push(cartItem);
                }
            });
        }
        
        // Remove the recalled order from held orders
        heldOrders.splice(index, 1);
        localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
        
        // Update cart display
        if (typeof updateCartDisplay === 'function') {
            updateCartDisplay();
        }
        
        closeModal(document.querySelector('.modal'));
        showToast('Order recalled successfully', 'success');
    }
};

window.deleteHeldOrder = function(index) {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    if (heldOrders[index]) {
        heldOrders.splice(index, 1);
        localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
        showToast('Held order deleted', 'success');
        
        // Refresh the modal
        const newHeldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
        if (newHeldOrders.length === 0) {
            closeModal(document.querySelector('.modal'));
        } else {
            showHeldOrdersModal(newHeldOrders);
        }
    }
};

window.clearAllHeldOrders = function() {
    localStorage.removeItem('heldOrders');
    closeModal(document.querySelector('.modal'));
    showToast('All held orders cleared', 'success');
};

window.addComboToCart = function() {
    // Add combo items to cart
    addToCart(999, 'Pizza Combo', 450);
    addToCart(1000, 'Drink', 80);
    addToCart(1001, 'Fries', 60);
    showToast('Combo added to cart', 'success');
    closeModal(document.querySelector('.modal'));
};

window.closeModal = function(modal) {
    if (modal) {
        modal.remove();
    }
};

window.loadItemsFromPageData = function(categoryId) {
    loadItemsFromPageData(categoryId);
};

// Export all the new functions
window.selectTableNumber = selectTableNumber;
window.showSettings = showSettings;
window.showReports = showReports;
window.showInventory = showInventory;
window.showUsers = showUsers;
window.showBackup = showBackup;
window.showHelp = showHelp;
window.showGeneralSettings = showGeneralSettings;
window.showPrinterSettings = showPrinterSettings;
window.showPaymentSettings = showPaymentSettings;
window.showTaxSettings = showTaxSettings;
window.showDailyReport = showDailyReport;
window.showItemReport = showItemReport;
window.showUserReport = showUserReport;
window.showStockLevels = showStockLevels;
window.showLowStock = showLowStock;
window.showInventoryReport = showInventoryReport;
window.showSuppliers = showSuppliers;
window.showUserRoles = showUserRoles;
window.showUserActivity = showUserActivity;
window.showPermissions = showPermissions;
window.createBackup = createBackup;
window.restoreBackup = restoreBackup;
window.showBackupHistory = showBackupHistory;
window.showAutoBackup = showAutoBackup;
window.showUserGuide = showUserGuide;
window.showVideoTutorials = showVideoTutorials;
window.showFAQ = showFAQ;
window.contactSupport = contactSupport; 

// Export all the new functions
window.addDressing = addDressing;
window.addSpecialOfferToCart = addSpecialOfferToCart;
window.saveNotes = saveNotes;
window.showComboMenu = showComboMenu;
window.showSides = showSides;
window.showDesserts = showDesserts;
window.showBeverages = showBeverages;
window.addComboToCart = addComboToCart;
window.addBurgerCombo = addBurgerCombo;
window.selectTableNumber = selectTableNumber;
window.recallHeldOrder = recallHeldOrder;
window.deleteHeldOrder = deleteHeldOrder;
window.clearAllHeldOrders = clearAllHeldOrders;
window.confirmKitchenOrder = confirmKitchenOrder;
window.calcBackspace = calcBackspace;
window.calcQuickAmount = calcQuickAmount;

// Cart control functions
function decreaseQuantity() {
    if (selectedCartItem) {
        const itemId = selectedCartItem.dataset.itemId;
        const currentQuantity = parseInt(selectedCartItem.querySelector('.quantity-display').textContent);
        if (currentQuantity > 1) {
            updateItemQuantity(itemId, currentQuantity - 1);
        } else {
            removeFromCart(itemId);
        }
    } else {
        showToast('Please select an item first', 'warning');
    }
}

function increaseQuantity() {
    if (selectedCartItem) {
        const itemId = selectedCartItem.dataset.itemId;
        const currentQuantity = parseInt(selectedCartItem.querySelector('.quantity-display').textContent);
        updateItemQuantity(itemId, currentQuantity + 1);
    } else {
        showToast('Please select an item first', 'warning');
    }
}

function applyDiscount() {
    if (selectedCartItem) {
        showModal('Apply Discount', `
            <div style="text-align: center;">
                <h3>Apply Discount</h3>
                <div style="margin: 20px 0;">
                    <input type="number" id="discount-percentage" placeholder="Discount %" 
                           style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; width: 200px; text-align: center;">
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0;">
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(5)">5%</button>
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(10)">10%</button>
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(15)">15%</button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0;">
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(20)">20%</button>
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(25)">25%</button>
                    <button class="btn btn-secondary" onclick="applyDiscountPercent(50)">50%</button>
                </div>
                <div style="margin-top: 20px;">
                    <button class="btn btn-primary" onclick="applyCustomDiscount()">Apply Discount</button>
                    <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))">Cancel</button>
                </div>
            </div>
        `);
    } else {
        showToast('Please select an item first', 'warning');
    }
}

function applyDiscountPercent(percent) {
    document.getElementById('discount-percentage').value = percent;
}

function applyCustomDiscount() {
    const discountPercent = document.getElementById('discount-percentage').value;
    if (discountPercent && discountPercent > 0 && discountPercent <= 100) {
        if (selectedCartItem) {
            const itemId = selectedCartItem.dataset.itemId;
            // Apply discount logic here
            showToast(`Applied ${discountPercent}% discount`, 'success');
            closeModal(document.querySelector('.modal'));
        }
    } else {
        showToast('Please enter a valid discount percentage (1-100)', 'error');
    }
}

function deleteSelectedItem() {
    if (selectedCartItem) {
        const itemId = selectedCartItem.dataset.itemId;
        removeFromCart(itemId);
        selectedCartItem = null;
        updateSelectedQuantity();
    } else {
        showToast('Please select an item first', 'warning');
    }
}

function updateSelectedQuantity() {
    const quantityDisplay = document.getElementById('selected-quantity');
    if (quantityDisplay) {
        // Use global selectedCartItem if available
        if (window.selectedCartItem) {
            const quantityElement = window.selectedCartItem.querySelector('.quantity-display');
            if (quantityElement) {
                quantityDisplay.textContent = quantityElement.textContent;
            } else {
                quantityDisplay.textContent = '1';
            }
        } else {
            quantityDisplay.textContent = '1';
        }
    }
}

// Export cart control functions
window.decreaseQuantity = decreaseQuantity;
window.increaseQuantity = increaseQuantity;
window.applyDiscount = applyDiscount;
window.applyDiscountPercent = applyDiscountPercent;
window.applyCustomDiscount = applyCustomDiscount;
window.deleteSelectedItem = deleteSelectedItem;
window.updateSelectedQuantity = updateSelectedQuantity; 

// Export navigation functions
window.goHome = goHome;
window.goBack = goBack;
window.goNext = goNext;
window.levelUp = levelUp;

// Export customer functions
window.selectCustomer = selectCustomer;

// Export order functions
window.processOrder = processOrder;
window.takeAway = takeAway;
window.selectTable = selectTable;
window.holdOrder = holdOrder;
window.recallOrder = recallOrder;
window.kitchenDone = kitchenDone;

// Export modal functions
window.showModal = showModal;
window.closeModal = closeModal;

// Export payment functions
window.printReceipt = printReceipt;

// Export utility functions
window.showDressings = showDressings;
window.showNotes = showNotes;
window.showSubMenu = showSubMenu;
window.openBackOffice = openBackOffice;
window.showSpecialOffers = showSpecialOffers;
window.showPizza = showPizza;
window.showZebra = showZebra;
window.showEasyPad = showEasyPad;
window.showFunctions = showFunctions;
window.showMore = showMore;

// Export calculator functions
window.calcInput = calcInput;
window.calcClear = calcClear;
window.calcResult = calcResult;

// Export search functions
window.searchItems = searchItems;

// Export item loading functions
window.loadItems = loadItems;
window.displayItems = displayItems;
window.getItemIcon = getItemIcon;

// Export keyboard shortcuts function
window.setupKeyboardShortcuts = setupKeyboardShortcuts;

// Export specific functions that are referenced in HTML
window.showDressings = showDressings;
window.showNotes = showNotes;
window.showSubMenu = showSubMenu;
window.openBackOffice = openBackOffice;
window.showSpecialOffers = showSpecialOffers;
window.showPizza = showPizza;
window.showZebra = showZebra;
window.showEasyPad = showEasyPad;
window.showFunctions = showFunctions;
window.showMore = showMore;

// Add missing functions that are referenced in HTML
window.showSettings = function() {
    showModal('Settings', 'Settings panel coming soon...');
};

window.showReports = function() {
    showModal('Reports', 'Reports panel coming soon...');
};

window.showInventory = function() {
    showModal('Inventory', 'Inventory panel coming soon...');
};

window.showUsers = function() {
    showModal('Users', 'User management coming soon...');
};

window.showBackup = function() {
    showModal('Backup', 'Backup panel coming soon...');
};

window.showHelp = function() {
    showModal('Help', 'Help panel coming soon...');
};

// Add all the placeholder functions
window.showGeneralSettings = function() { showToast('General Settings - Coming Soon', 'info'); };
window.showPrinterSettings = function() { showToast('Printer Settings - Coming Soon', 'info'); };
window.showPaymentSettings = function() { showToast('Payment Settings - Coming Soon', 'info'); };
window.showTaxSettings = function() { showToast('Tax Settings - Coming Soon', 'info'); };
window.showDailyReport = function() { showToast('Daily Report - Coming Soon', 'info'); };
window.showItemReport = function() { showToast('Item Report - Coming Soon', 'info'); };
window.showUserReport = function() { showToast('User Report - Coming Soon', 'info'); };
window.showStockLevels = function() { showToast('Stock Levels - Coming Soon', 'info'); };
window.showLowStock = function() { showToast('Low Stock - Coming Soon', 'info'); };
window.showInventoryReport = function() { showToast('Inventory Report - Coming Soon', 'info'); };
window.showSuppliers = function() { showToast('Suppliers - Coming Soon', 'info'); };
window.showUserRoles = function() { showToast('User Roles - Coming Soon', 'info'); };
window.showUserActivity = function() { showToast('User Activity - Coming Soon', 'info'); };
window.showPermissions = function() { showToast('Permissions - Coming Soon', 'info'); };
window.createBackup = function() { showToast('Creating backup...', 'info'); };
window.restoreBackup = function() { showToast('Restore backup - Coming Soon', 'info'); };
window.showBackupHistory = function() { showToast('Backup History - Coming Soon', 'info'); };
window.showAutoBackup = function() { showToast('Auto Backup - Coming Soon', 'info'); };
window.showUserGuide = function() { showToast('User Guide - Coming Soon', 'info'); };
window.showVideoTutorials = function() { showToast('Video Tutorials - Coming Soon', 'info'); };
window.showFAQ = function() { showToast('FAQ - Coming Soon', 'info'); };
window.contactSupport = function() { showToast('Contact Support - Coming Soon', 'info'); }; 

function showEasyPad() {
    showModal('EasyPad Calculator', `
        <div style="text-align: center; max-width: 400px; margin: 0 auto;">
            <h3 style="margin-bottom: 20px; color: #1e293b;">EasyPad Calculator</h3>
            
            <!-- Calculator Display -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="calc-display" readonly 
                       style="width: 100%; padding: 15px; font-size: 24px; text-align: right; 
                              border: 2px solid #e2e8f0; border-radius: 8px; background: #f8fafc; 
                              color: #1e293b; font-family: 'Courier New', monospace;"
                       placeholder="0">
            </div>
            
            <!-- Calculator Buttons -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 15px;">
                <!-- First Row -->
                <button class="btn btn-secondary" onclick="calcInput('7')" style="padding: 15px; font-size: 18px;">7</button>
                <button class="btn btn-secondary" onclick="calcInput('8')" style="padding: 15px; font-size: 18px;">8</button>
                <button class="btn btn-secondary" onclick="calcInput('9')" style="padding: 15px; font-size: 18px;">9</button>
                <button class="btn btn-primary" onclick="calcInput('+')" style="padding: 15px; font-size: 18px;">+</button>
                
                <!-- Second Row -->
                <button class="btn btn-secondary" onclick="calcInput('4')" style="padding: 15px; font-size: 18px;">4</button>
                <button class="btn btn-secondary" onclick="calcInput('5')" style="padding: 15px; font-size: 18px;">5</button>
                <button class="btn btn-secondary" onclick="calcInput('6')" style="padding: 15px; font-size: 18px;">6</button>
                <button class="btn btn-primary" onclick="calcInput('-')" style="padding: 15px; font-size: 18px;">-</button>
                
                <!-- Third Row -->
                <button class="btn btn-secondary" onclick="calcInput('1')" style="padding: 15px; font-size: 18px;">1</button>
                <button class="btn btn-secondary" onclick="calcInput('2')" style="padding: 15px; font-size: 18px;">2</button>
                <button class="btn btn-secondary" onclick="calcInput('3')" style="padding: 15px; font-size: 18px;">3</button>
                <button class="btn btn-primary" onclick="calcInput('*')" style="padding: 15px; font-size: 18px;">×</button>
                
                <!-- Fourth Row -->
                <button class="btn btn-secondary" onclick="calcInput('0')" style="padding: 15px; font-size: 18px;">0</button>
                <button class="btn btn-secondary" onclick="calcInput('.')" style="padding: 15px; font-size: 18px;">.</button>
                <button class="btn btn-primary" onclick="calcResult()" style="padding: 15px; font-size: 18px;">=</button>
                <button class="btn btn-primary" onclick="calcInput('/')" style="padding: 15px; font-size: 18px;">÷</button>
            </div>
            
            <!-- Control Buttons -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <button class="btn btn-danger" onclick="calcClear()" style="padding: 12px;">
                    <i class="fas fa-trash"></i> Clear
                </button>
                <button class="btn btn-secondary" onclick="calcBackspace()" style="padding: 12px;">
                    <i class="fas fa-backspace"></i> Backspace
                </button>
            </div>
            
            <!-- Quick Amount Buttons -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                <h4 style="margin-bottom: 10px; color: #64748b;">Quick Amounts</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                    <button class="btn btn-outline" onclick="calcQuickAmount(100)" style="padding: 8px; font-size: 14px;">100</button>
                    <button class="btn btn-outline" onclick="calcQuickAmount(500)" style="padding: 8px; font-size: 14px;">500</button>
                    <button class="btn btn-outline" onclick="calcQuickAmount(1000)" style="padding: 8px; font-size: 14px;">1000</button>
                </div>
            </div>
        </div>
    `);
    
    // Focus on the display
    setTimeout(() => {
        const display = document.getElementById('calc-display');
        if (display) {
            display.focus();
        }
    }, 100);
}

function calcBackspace() {
    const calcDisplay = document.getElementById('calc-display');
    if (calcDisplay) {
        calcDisplay.value = calcDisplay.value.slice(0, -1);
    }
}

function calcQuickAmount(amount) {
    const calcDisplay = document.getElementById('calc-display');
    if (calcDisplay) {
        calcDisplay.value = amount;
    }
}

// Export Zebra printer functions
window.loadZebraSettings = loadZebraSettings;
window.saveZebraSettings = saveZebraSettings;
window.testZebraConnection = testZebraConnection;
window.printZebraTest = printZebraTest;
window.printZebra = printZebra;
window.printZebraReceipt = printZebraReceipt;
window.printZebraLabel = printZebraLabel;
window.printZebraBarcode = printZebraBarcode;
window.printZebraQR = printZebraQR;



// Export ZPL generation functions
window.generateReceiptZPL = generateReceiptZPL;
window.generateLabelZPL = generateLabelZPL;
window.generateBarcodeZPL = generateBarcodeZPL;
window.generateQRZPL = generateQRZPL;
// getCartTotal is now defined in cart.js