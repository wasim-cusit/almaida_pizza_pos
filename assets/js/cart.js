/**
 * Cart Management JavaScript
 * Fast Food POS System - Modern Design
 */

// Cart data structure
let cart = [];
let selectedItemIndex = -1;
// selectedCartItem will be declared globally in app.js to avoid conflicts

// Make cart globally accessible
window.cart = cart;

// Initialize cart from localStorage
document.addEventListener('DOMContentLoaded', function() {
    loadCartFromStorage();
    updateCartDisplay();
});

// Global variables for size selection
let selectedItemForSize = null;

// Handle item click - check if size selection is needed
function handleItemClick(itemId, itemName, price, hasSizeVariants) {
    if (hasSizeVariants) {
        // Show size selection modal
        selectedItemForSize = { id: itemId, name: itemName, price: price };
        showSizeSelectionModal(itemId, itemName);
    } else {
        // Add directly to cart
        addToCart(itemId, itemName, price, 1);
    }
}

// Show size selection modal
function showSizeSelectionModal(itemId, itemName) {
    // Fetch size variants from API
    fetch(`api/get_items.php?category_id=1`) // Assuming pizza is category 1
        .then(response => response.json())
        .then(data => {
            const item = data.items.find(item => item.id == itemId);
            if (item && item.size_variants && item.size_variants.length > 0) {
                displaySizeOptions(item);
            } else {
                // Fallback to direct add if no size variants found
                addToCart(itemId, itemName, 0, 1);
            }
        })
        .catch(error => {
            console.error('Error fetching size variants:', error);
            // Fallback to direct add
            addToCart(itemId, itemName, 0, 1);
        });
}

// Display size options in modal
function displaySizeOptions(item) {
    let modal = document.getElementById('size-modal');
    let title = document.getElementById('size-modal-title');
    let optionsContainer = document.getElementById('size-options');
    
    // If modal doesn't exist, create it dynamically
    if (!modal) {
        console.log('Creating size modal dynamically');
        const modalHTML = `
            <div id="size-modal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">
                <div class="modal-content" style="max-width: 500px; width: 90%; background-color: white; border-radius: 10px; position: relative; margin: 0;">
                    <div class="modal-header" style="background: #20bf55; color: white; padding: 15px; border-radius: 8px 8px 0 0;">
                        <h3 id="size-modal-title" style="margin: 0; font-size: 18px;">Select Size</h3>
                        <span class="close" onclick="closeSizeModal()" style="color: white; font-size: 24px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;">&times;</span>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <div id="size-options" style="display: grid; gap: 10px;">
                            <!-- Size options will be loaded here -->
                        </div>
                        <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                            <button onclick="closeSizeModal()" style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; margin-right: 10px;">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Get the newly created elements
        modal = document.getElementById('size-modal');
        title = document.getElementById('size-modal-title');
        optionsContainer = document.getElementById('size-options');
        
        // Add click-outside-to-close functionality
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeSizeModal();
            }
        });
        
        // Add ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'block') {
                closeSizeModal();
            }
        });
    }
    
    // Check if all required elements exist
    if (!modal || !title || !optionsContainer) {
        console.error('Size modal elements not found after creation:', {
            modal: !!modal,
            title: !!title,
            optionsContainer: !!optionsContainer
        });
        // Fallback to direct add
        addToCart(item.id, item.name, 0, 1);
        return;
    }
    
    title.textContent = `Select Size - ${item.name}`;
    
    // Clear previous options
    optionsContainer.innerHTML = '';
    
    // Add size options
    item.size_variants.forEach(size => {
        const sizeOption = document.createElement('div');
        sizeOption.className = 'size-option';
        sizeOption.style.cssText = `
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        
        sizeOption.innerHTML = `
            <div>
                <div style="font-weight: 600; font-size: 16px; color: #374151;">${size.size_name}</div>
                <div style="font-size: 14px; color: #6b7280;">${item.name}</div>
            </div>
            <div style="font-weight: 600; font-size: 18px; color: #20bf55;">
                PKR ${parseFloat(size.size_price).toFixed(2)}
            </div>
        `;
        
        sizeOption.onclick = () => {
            addToCartWithSize(item.id, item.name, size.size_name, size.size_price, 1);
            closeSizeModal();
        };
        
        sizeOption.onmouseenter = () => {
            sizeOption.style.borderColor = '#20bf55';
            sizeOption.style.backgroundColor = '#f0fdf4';
        };
        
        sizeOption.onmouseleave = () => {
            sizeOption.style.borderColor = '#e2e8f0';
            sizeOption.style.backgroundColor = 'transparent';
        };
        
        optionsContainer.appendChild(sizeOption);
    });
    
    // Show modal
    modal.style.display = 'block';
}

// Close size selection modal
function closeSizeModal() {
    const modal = document.getElementById('size-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    selectedItemForSize = null;
}

// Add item to cart with size
function addToCartWithSize(itemId, itemName, sizeName, price, quantity = 1) {
    // Ensure price and quantity are numbers
    const safePrice = parseFloat(price) || 0;
    const safeQuantity = parseInt(quantity) || 1;
    
    // Create unique ID for item with size
    const uniqueId = `${itemId}_${sizeName}`;
    
    // Check if item with this size already exists in cart
    const existingItem = window.cart.find(item => 
        String(item.id) === String(itemId) && item.sizeName === sizeName
    );
    
    if (existingItem) {
        existingItem.quantity += safeQuantity;
        existingItem.totalPrice = existingItem.quantity * existingItem.price;
    } else {
        const newItem = {
            id: itemId,
            name: itemName,
            sizeName: sizeName,
            price: safePrice,
            quantity: safeQuantity,
            totalPrice: safePrice * safeQuantity
        };
        window.cart.push(newItem);
    }
    
    // Keep local cart in sync
    cart = window.cart;
    
    // Save to localStorage
    saveCartToStorage();
    
    // Update display
    updateCartDisplay();
    
    // Force update payment display after a short delay to ensure it updates
    setTimeout(() => {
        updatePaymentDisplay();
    }, 100);
    
    // Show success message
    showToast(`${itemName} (${sizeName}) added to cart`, 'success');
}

// Add item to cart (original function for non-size items)
function addToCart(itemId, itemName, price, quantity = 1) {
    // Ensure price and quantity are numbers
    const safePrice = parseFloat(price) || 0;
    const safeQuantity = parseInt(quantity) || 1;
    
    // Ensure itemId is treated as a string for comparison
    const stringItemId = String(itemId);
    
    // Check if item already exists in cart (for non-size items)
    const existingItem = window.cart.find(item => 
        String(item.id) === stringItemId && !item.sizeName
    );
    
    if (existingItem) {
        existingItem.quantity += safeQuantity;
        existingItem.totalPrice = existingItem.quantity * existingItem.price;
    } else {
        const newItem = {
            id: itemId,
            name: itemName,
            price: safePrice,
            quantity: safeQuantity,
            totalPrice: safePrice * safeQuantity
        };
        window.cart.push(newItem);
    }
    
    // Keep local cart in sync
    cart = window.cart;
    
    // Save to localStorage
    saveCartToStorage();
    
    // Update display
    updateCartDisplay();
    
    // Force update payment display after a short delay to ensure it updates
    setTimeout(() => {
        updatePaymentDisplay();
    }, 100);
    
    // Show success message
    showToast(`${itemName} added to cart`, 'success');
}

// Remove item from cart
function removeFromCart(itemId) {
    // Ensure itemId is treated as a string for comparison
    const stringItemId = String(itemId);
    
    // Find the item to get its name for the toast message
    const itemToRemove = window.cart.find(item => String(item.id) === stringItemId);
    const itemName = itemToRemove ? (itemToRemove.sizeName ? `${itemToRemove.name} (${itemToRemove.sizeName})` : itemToRemove.name) : 'Item';
    
    // Remove the item
    window.cart = window.cart.filter(item => String(item.id) !== stringItemId);
    
    // Keep local cart in sync
    cart = window.cart;
    saveCartToStorage();
    updateCartDisplay();
    showToast(`${itemName} removed from cart`, 'success');
}

// Update item quantity
function updateItemQuantity(itemId, newQuantity) {
    // Ensure itemId is treated as a string for comparison
    const stringItemId = String(itemId);
    const item = window.cart.find(item => String(item.id) === stringItemId);
    if (item) {
        const safeQuantity = Math.max(1, parseInt(newQuantity) || 1);
        item.quantity = safeQuantity;
        item.totalPrice = item.quantity * item.price;
        saveCartToStorage();
        updateCartDisplay();
    }
}

// Select cart item
function selectCartItem(element) {
    // Remove previous selection
    document.querySelectorAll('.cart-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Select new item
    if (element) {
        element.classList.add('selected');
        window.selectedCartItem = element; // Use global variable
        
        // Update the quantity display in controls
        const quantitySpan = element.querySelector('span');
        const quantityDisplay = document.getElementById('selected-quantity');
        if (quantityDisplay && quantitySpan) {
            quantityDisplay.textContent = quantitySpan.textContent;
        }
        
        // Add item ID to the element for reference
        const itemId = element.querySelector('.item-details').dataset.itemId;
        if (itemId) {
            element.dataset.itemId = itemId;
        }
    }
}

// Increase quantity of selected item
function increaseQuantity() {
    // Use selectedCartItem from the global scope
    if (window.selectedCartItem) {
        const selectedItemId = String(window.selectedCartItem.dataset.itemId);
        const item = window.cart.find(item => String(item.id) === selectedItemId);
        if (item) {
            item.quantity++;
            item.totalPrice = item.quantity * item.price;
            saveCartToStorage();
            updateCartDisplay();
            
            // Update quantity display
            document.getElementById('selected-quantity').textContent = item.quantity;
        } else {
            showToast('Item not found in cart', 'warning');
        }
    } else {
        showToast('Please select an item first', 'warning');
    }
}

// Decrease quantity of selected item
function decreaseQuantity() {
    // Use selectedCartItem from the global scope
    if (window.selectedCartItem) {
        const selectedItemId = String(window.selectedCartItem.dataset.itemId);
        const item = window.cart.find(item => String(item.id) === selectedItemId);
        if (item) {
            if (item.quantity > 1) {
                item.quantity--;
                item.totalPrice = item.quantity * item.price;
                saveCartToStorage();
                updateCartDisplay();
                
                // Update quantity display
                document.getElementById('selected-quantity').textContent = item.quantity;
            } else {
                // Remove item if quantity becomes 0
                removeFromCart(item.id);
                window.selectedCartItem = null;
                document.getElementById('selected-quantity').textContent = '1';
            }
        } else {
            showToast('Item not found in cart', 'warning');
        }
    } else {
        showToast('Please select an item first', 'warning');
    }
}

// Delete selected item
function deleteSelectedItem() {
    // Use selectedCartItem from the global scope
    if (window.selectedCartItem) {
        const selectedItemId = String(window.selectedCartItem.dataset.itemId);
        const item = window.cart.find(item => String(item.id) === selectedItemId);
        if (item) {
            removeFromCart(item.id);
            window.selectedCartItem = null;
            document.getElementById('selected-quantity').textContent = '1';
            
            // Remove selection from UI
            document.querySelectorAll('.cart-item').forEach(item => {
                item.classList.remove('selected');
            });
        } else {
            showToast('Item not found in cart', 'warning');
        }
    } else {
        showToast('Please select an item first', 'warning');
    }
}



// Update cart display
function updateCartDisplay() {
    
    const cartItemsContainer = document.getElementById('cart-items');
    const cartItemCount = document.getElementById('cart-item-count');
    const totalAmount = document.getElementById('total-amount');
    const totalItems = document.getElementById('total-items');
    
    if (!cartItemsContainer) {
        console.error('cartItemsContainer not found!');
        return;
    }
    
    // Fix cart data - ensure all items have correct totalPrice
    if (window.cart && window.cart.length > 0) {
        window.cart.forEach(item => {
            if (item.totalPrice === 0 || isNaN(item.totalPrice) || !item.totalPrice) {
                item.totalPrice = item.price * item.quantity;
            }
        });
        saveCartToStorage();
    } else {
        // If cart is empty, ensure localStorage is also cleared
        localStorage.removeItem('pos_cart');
    }
    
    // Clear container
    cartItemsContainer.innerHTML = '';
    
    if (window.cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="cart-empty" style="text-align: center; padding: 40px 20px; color: #64748b;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <i class="fas fa-shopping-cart" style="font-size: 32px; color: #94a3b8;"></i>
                </div>
                <h4 style="font-size: 18px; font-weight: 600; color: #475569; margin-bottom: 8px;">Your cart is empty</h4>
                <p style="font-size: 14px; color: #94a3b8; line-height: 1.5;">Add items from the menu to get started</p>
            </div>
        `;
        // Reset selected item
        window.selectedCartItem = null; // Use global variable
        updateSelectedQuantity();
    } else {
        // Add each item to cart display
        window.cart.forEach((item, index) => {
            const cartItem = document.createElement('div');
            cartItem.className = 'cart-item';
            cartItem.onclick = () => selectCartItem(cartItem);
            cartItem.dataset.itemId = item.id;
            
            // Create display name with size if available
            const itemDisplayName = item.sizeName ? `${item.name} (${item.sizeName})` : item.name;
            
            cartItem.innerHTML = `
                <div class="item-details" data-item-id="${item.id}" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: all 0.3s ease; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #20bf55, #01baef);"></div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div class="item-name" style="font-weight: 700; color: #1e293b; font-size: 15px; margin-bottom: 6px; letter-spacing: 0.3px;">${itemDisplayName}</div>
                            <div class="item-price" style="font-size: 13px; color: #64748b; font-weight: 500;">PKR ${item.price.toFixed(2)}</div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Total: PKR ${item.totalPrice.toFixed(2)}</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button onclick="event.stopPropagation(); updateItemQuantity('${item.id}', ${item.quantity - 1})" 
                                    style="width: 28px; height: 28px; border: 1px solid #d1d5db; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b; font-size: 14px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                -
                            </button>
                            <span style="min-width: 35px; text-align: center; font-weight: 700; color: #1e293b; font-size: 15px; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">${item.quantity}</span>
                            <button onclick="event.stopPropagation(); updateItemQuantity('${item.id}', ${item.quantity + 1})" 
                                    style="width: 28px; height: 28px; border: 1px solid #d1d5db; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b; font-size: 14px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                +
                            </button>
                            <button onclick="event.stopPropagation(); removeFromCart('${item.id}')" 
                                    style="width: 28px; height: 28px; border: 1px solid #ef4444; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-left: 6px; font-size: 14px; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(239,68,68,0.3);">
                                ×
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            cartItemsContainer.appendChild(cartItem);
        });
    }
    
    // Update counters - use total quantity of all items
    if (cartItemCount) {
        cartItemCount.textContent = getCartTotalQuantity();
    }
    
    // Update payment display with multiple attempts
    updatePaymentDisplay();
    
    // Update selected quantity display
    updateSelectedQuantity();
}

// Clear cart
function clearCart() {
    // Show confirmation dialog
    if (window.cart && window.cart.length > 0) {
        if (confirm('Are you sure you want to clear all items from the cart?')) {
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
            showToast('All items cleared from cart', 'success');
        }
    } else {
        showToast('Cart is already empty', 'info');
    }
}

// Get cart total
function getCartTotal() {
    if (!Array.isArray(window.cart)) {
        return 0;
    }
    const total = window.cart.reduce((total, item) => {
        const itemPrice = parseFloat(item.totalPrice) || 0;
        return total + itemPrice;
    }, 0);
    // Ensure we return a number, not a string
    return parseFloat(total) || 0;
}

// Get cart item count (number of unique items)
function getCartItemCount() {
    return window.cart ? window.cart.length : 0;
}

// Get total quantity of all items
function getCartTotalQuantity() {
    if (!window.cart || !Array.isArray(window.cart)) {
        return 0;
    }
    return window.cart.reduce((total, item) => {
        return total + (parseInt(item.quantity) || 0);
    }, 0);
}

// Get cart items
function getCartItems() {
    return window.cart ? window.cart.map(item => ({
        id: item.id,
        name: item.name,
        sizeName: item.sizeName || '',
        price: item.price,
        quantity: item.quantity,
        total_price: item.totalPrice
    })) : [];
}

// Get cart data for order processing
function getCartData() {
    return {
        items: getCartItems(),
        total: getCartTotal(),
        itemCount: getCartItemCount(),
        customerName: document.getElementById('customer-name')?.value || '',
        customerPostcode: document.getElementById('customer-postcode')?.value || ''
    };
}

// Save cart to localStorage
function saveCartToStorage() {
    if (window.cart && window.cart.length > 0) {
        localStorage.setItem('pos_cart', JSON.stringify(window.cart));
    } else {
        localStorage.removeItem('pos_cart');
    }
    
    // Keep local reference in sync
    cart = window.cart;
}

// Load cart from localStorage
function loadCartFromStorage() {
    const savedCart = localStorage.getItem('pos_cart');
    
    if (savedCart) {
        try {
            const parsedCart = JSON.parse(savedCart);
            // Ensure cart is an array and validate each item
            if (Array.isArray(parsedCart)) {
                cart = parsedCart.map(item => {
                    const mappedItem = {
                        id: item.id,
                        name: item.name || '',
                        sizeName: item.sizeName || '',
                        price: parseFloat(item.price) || 0,
                        quantity: parseInt(item.quantity) || 1,
                        totalPrice: parseFloat(item.totalPrice) || 0
                    };
                    // Recalculate totalPrice if it's 0 or invalid
                    if (mappedItem.totalPrice === 0 || isNaN(mappedItem.totalPrice)) {
                        mappedItem.totalPrice = mappedItem.price * mappedItem.quantity;
                    }
                    return mappedItem;
                });
            } else {
                cart = [];
            }
        } catch (e) {
            cart = [];
        }
    } else {
        cart = [];
    }
    
    // Keep global reference in sync
    window.cart = cart;
    
    // Ensure local cart is also in sync
    cart = window.cart;
}

// Show toast notification
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create new toast
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    // Add to page
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // Remove toast after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// Update payment display with multiple attempts
function updatePaymentDisplay() {
    const totalAmount = document.getElementById('total-amount');
    
    if (totalAmount) {
        const total = getCartTotal();
        // Convert to number and handle any type issues
        const safeTotal = parseFloat(total) || 0;
        
        // Update payment display with payment info and item count on the same line
        totalAmount.innerHTML = `
            <div style="text-align: center; padding: 12px; background: #e2e8f0; border-radius: 6px; margin-bottom: 8px; font-weight: 600; color: #1e293b; font-size: 16px;">
                Payment PKR ${safeTotal.toFixed(2)} <span style="color: #64748b; font-size: 14px; font-weight: 500; margin-left: 10px;">Item(s): ${getCartTotalQuantity()}</span>
            </div>
        `;
        
        // Also update the cart item count in the header
        const cartItemCount = document.getElementById('cart-item-count');
        if (cartItemCount) {
            cartItemCount.textContent = getCartTotalQuantity();
        }
    } else {
        console.error('totalAmount element not found!');
    }
}

// Update selected quantity display
function updateSelectedQuantity() {
    const quantityDisplay = document.getElementById('selected-quantity');
    // Use selectedCartItem from the global scope
    if (window.selectedCartItem) {
        // Find the quantity span within the selected cart item
        const quantitySpan = window.selectedCartItem.querySelector('span');
        if (quantitySpan) {
            quantityDisplay.textContent = quantitySpan.textContent;
        } else {
            quantityDisplay.textContent = '1';
        }
    } else {
        quantityDisplay.textContent = '1';
    }
}

// Process payment with enhanced functionality
function processPayment(method, amount) {
    const paymentData = {
        method: method,
        amount: amount,
        items: getCartItems(),
        customer: document.getElementById('customer-name')?.value || '',
        customer_postcode: document.getElementById('customer-postcode')?.value || '',
        customer_phone: document.getElementById('customer-phone')?.value || '',
        customer_email: document.getElementById('customer-email')?.value || '',
        table_number: localStorage.getItem('selectedTable') || '',
        notes: localStorage.getItem('orderNotes') || '',
        order_type: localStorage.getItem('orderType') || 'dine_in'
    };
    
    // Show processing message
    showToast('Processing payment...', 'info');
    
    // Send to server
    fetch('api/process_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Order processed successfully!', 'success');
            
            // Clear cart
            clearCart();
            
            // Print receipt with QR code
            printEnhancedReceipt(data.print_data, data.qr_code_url);
            
            // Show success modal with order details
            showOrderSuccessModal(data.order, data.qr_code_url);
            
        } else {
            showToast(data.message || 'Payment failed', 'error');
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        showToast('Payment failed', 'error');
    });
    
    closeModal(document.querySelector('.modal'));
}

// Enhanced receipt printing with QR codes
function printEnhancedReceipt(printData, qrCodeURL) {
    const receiptWindow = window.open('print_receipt.php', '_blank');
    
    // Create form data to send to print page
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'print_receipt.php';
    form.target = '_blank';
    
    // Add order data
    const orderDataInput = document.createElement('input');
    orderDataInput.type = 'hidden';
    orderDataInput.name = 'order_data';
    orderDataInput.value = JSON.stringify(printData);
    form.appendChild(orderDataInput);
    
    // Add QR code URL
    const qrCodeInput = document.createElement('input');
    qrCodeInput.type = 'hidden';
    qrCodeInput.name = 'qr_code_url';
    qrCodeInput.value = qrCodeURL;
    form.appendChild(qrCodeInput);
    
    // Add QR data
    const qrDataInput = document.createElement('input');
    qrDataInput.type = 'hidden';
    qrDataInput.name = 'qr_data';
    qrDataInput.value = printData.qr_data;
    form.appendChild(qrDataInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Show order success modal
function showOrderSuccessModal(order, qrCodeURL) {
    const modalContent = `
        <div style="text-align: center; max-width: 500px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; border-radius: 15px 15px 0 0; margin: -20px -20px 20px -20px;">
                <i class="fas fa-check-circle" style="font-size: 4em; margin-bottom: 15px;"></i>
                <h2 style="margin: 0; font-size: 2em;">Order Successful!</h2>
                <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1em;">Your order has been processed successfully</p>
            </div>
            
            <div style="background: #f8fafc; padding: 25px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
                <h3 style="margin: 0 0 15px 0; color: #1e293b;">Order Details</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left;">
                    <div>
                        <strong>Order Number:</strong><br>
                        <span style="color: #20bf55; font-weight: 600;">${order.order_number}</span>
                    </div>
                    <div>
                        <strong>Total Amount:</strong><br>
                        <span style="color: #20bf55; font-weight: 600;">PKR ${parseFloat(order.total_amount).toFixed(2)}</span>
                    </div>
                    <div>
                        <strong>Payment Method:</strong><br>
                        <span style="text-transform: capitalize;">${order.payment_method.replace('_', ' ')}</span>
                    </div>
                    <div>
                        <strong>Items:</strong><br>
                        <span>${order.items.length} items</span>
                    </div>
                </div>
            </div>
            
            ${qrCodeURL ? `
            <div style="background: #f8fafc; padding: 25px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0; text-align: center;">
                <h4 style="margin: 0 0 15px 0; color: #1e293b;">📱 QR Code</h4>
                <img src="${qrCodeURL}" alt="QR Code" style="max-width: 150px; border-radius: 8px; margin: 10px 0;">
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">Scan to view order details online</p>
            </div>
            ` : ''}
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 25px;">
                <button class="btn btn-primary" onclick="printEnhancedReceipt(${JSON.stringify(order)}, '${qrCodeURL}')" style="width: 100%;">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" style="width: 100%;">
                    <i class="fas fa-check"></i> Done
                </button>
            </div>
        </div>
    `;
    
    showModal('🎉 Order Completed!', modalContent);
}

// Enhanced payment modal with order completion functionality
function showPaymentModal(cartData) {
    const total = getCartTotal();
    const items = getCartItems();
    
    if (items.length === 0) {
        showToast('Cart is empty', 'error');
        return;
    }
    
    // Get customer information from the payment display
    const customerName = document.getElementById('customer-name')?.value || '';
    const customerPostcode = document.getElementById('customer-postcode')?.value || '';
    const orderType = document.getElementById('order-type-select')?.value || 'dine_in';
    
    const modalContent = `
        <div style="text-align: center; margin-bottom: 20px;">
            <h3 style="color: #1e293b; margin-bottom: 10px;">Complete Order</h3>
            <h2 style="color: #20bf55; font-size: 28px; font-weight: 700;">PKR ${total.toFixed(2)}</h2>
        </div>
        
        <!-- Order Type Selection -->
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
            <h4 style="margin: 0 0 15px 0; color: #374151;"><i class="fas fa-utensils"></i> Order Type</h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="orderType" value="dine_in" ${orderType === 'dine_in' ? 'checked' : ''} style="margin-right: 8px;">
                    <span><i class="fas fa-chair"></i> Dine In</span>
                </label>
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="orderType" value="takeaway" ${orderType === 'takeaway' ? 'checked' : ''} style="margin-right: 8px;">
                    <span><i class="fas fa-shopping-bag"></i> Takeaway</span>
                </label>
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="orderType" value="delivery" ${orderType === 'delivery' ? 'checked' : ''} style="margin-right: 8px;">
                    <span><i class="fas fa-truck"></i> Delivery</span>
                </label>
            </div>
            
            <!-- Table Number (for dine-in) -->
            <div id="table-section" style="margin-top: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Table Number:</label>
                <input type="number" id="tableNumber" min="1" max="50" placeholder="Enter table number" 
                       style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
            </div>
        </div>
        
        <!-- Customer Information -->
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
            <h4 style="margin: 0 0 15px 0; color: #374151;"><i class="fas fa-user"></i> Customer Information</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Customer Name:</label>
                    <input type="text" id="customerName" placeholder="Enter customer name" value="${customerName}"
                           style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Contact Number:</label>
                    <input type="tel" id="customerContact" placeholder="Enter phone number" 
                           style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div style="grid-column: span 2;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Delivery Address:</label>
                    <input type="text" id="customerAddress" placeholder="Enter delivery address (for delivery orders)" 
                           style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Postcode:</label>
                    <input type="text" id="customerPostcode" placeholder="Enter postcode" value="${customerPostcode}"
                           style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                </div>
            </div>
        </div>
        
        <!-- Payment Method -->
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
            <h4 style="margin: 0 0 15px 0; color: #374151;"><i class="fas fa-credit-card"></i> Payment Method</h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="paymentMethod" value="cash" checked style="margin-right: 8px;">
                    <span><i class="fas fa-money-bill-wave"></i> Cash</span>
                </label>
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="paymentMethod" value="card" style="margin-right: 8px;">
                    <span><i class="fas fa-credit-card"></i> Card</span>
                </label>
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="paymentMethod" value="online" style="margin-right: 8px;">
                    <span><i class="fas fa-globe"></i> Online</span>
                </label>
                <label style="display: flex; align-items: center; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white;">
                    <input type="radio" name="paymentMethod" value="mobile" style="margin-right: 8px;">
                    <span><i class="fas fa-mobile-alt"></i> Mobile</span>
                </label>
            </div>
        </div>
        
        <!-- Notes -->
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
            <h4 style="margin: 0 0 15px 0; color: #374151;"><i class="fas fa-sticky-note"></i> Order Notes</h4>
            <textarea id="orderNotes" placeholder="Add any special instructions or notes..." 
                      style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; height: 80px; resize: vertical;"></textarea>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-secondary" onclick="closeModal(document.querySelector('.modal'))" 
                    style="margin-right: 10px; padding: 12px 24px; border: none; border-radius: 8px; background: #64748b; color: white; cursor: pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn btn-primary" onclick="completeOrder()" 
                    style="padding: 12px 24px; border: none; border-radius: 8px; background: linear-gradient(135deg, #20bf55, #01baef); color: white; cursor: pointer; font-weight: 600;">
                <i class="fas fa-check"></i> Complete Order
            </button>
        </div>
    `;
    
    showModal('Complete Order', modalContent);
    
    // Add event listeners for order type changes
    setupOrderTypeListeners();
}

function setupOrderTypeListeners() {
    const orderTypeInputs = document.querySelectorAll('input[name="orderType"]');
    const tableSection = document.getElementById('table-section');
    const customerAddress = document.getElementById('customerAddress');
    
    orderTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
            const orderType = this.value;
            
            // Show/hide table section for dine-in
            if (orderType === 'dine_in') {
                tableSection.style.display = 'block';
                customerAddress.placeholder = 'Enter delivery address (optional)';
                customerAddress.required = false;
            } else if (orderType === 'takeaway') {
                tableSection.style.display = 'none';
                customerAddress.placeholder = 'Enter delivery address (optional)';
                customerAddress.required = false;
            } else if (orderType === 'delivery') {
                tableSection.style.display = 'none';
                customerAddress.placeholder = 'Enter delivery address (required)';
                customerAddress.required = true;
            }
        });
    });
}

function completeOrder() {
    const total = getCartTotal();
    const items = getCartItems();
    
    if (items.length === 0) {
        showToast('Cart is empty', 'error');
        return;
    }
    
    // Get form values
    const orderTypeElement = document.querySelector('input[name="orderType"]:checked');
    const orderType = orderTypeElement ? orderTypeElement.value : 'dine_in';
    
    const tableNumber = document.getElementById('tableNumber')?.value || null;
    
    const paymentMethodElement = document.querySelector('input[name="paymentMethod"]:checked');
    const paymentMethod = paymentMethodElement ? paymentMethodElement.value : 'cash';
    
    const customerName = document.getElementById('customerName')?.value || '';
    const customerContact = document.getElementById('customerContact')?.value || '';
    const customerAddress = document.getElementById('customerAddress')?.value || '';
    const customerPostcode = document.getElementById('customerPostcode')?.value || '';
    const notes = document.getElementById('orderNotes')?.value || '';
    
    // Validate delivery address for delivery orders
    if (orderType === 'delivery' && !customerAddress.trim()) {
        showToast('Delivery address is required for delivery orders', 'error');
        return;
    }
    
    // Prepare order data
    const orderData = {
        items: items,
        customer: {
            name: customerName,
            contact: customerContact,
            address: customerAddress,
            postcode: customerPostcode
        },
        order_type: orderType,
        table_number: tableNumber,
        payment_method: paymentMethod,
        notes: notes
    };
    
    // Show loading
    showToast('Processing order...', 'info');
    
    // Send order to server
    fetch('api/complete_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            closeModal(document.querySelector('.modal'));
            
            // Show success modal with invoice
            showOrderSuccessModal(data.order);
            
            // Note: Cart will be cleared when user clicks "Start New Order"
            // Don't clear cart here to allow user to review order before starting new order
            
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error processing order', 'error');
    });
}

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

function showOrderSuccessModal(order) {
    const modalContent = `
        <div style="text-align: center; padding: 40px 20px;">
            <div style="background: #d1fae5; color: #065f46; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-check-circle" style="font-size: 64px; margin-bottom: 20px;"></i>
                <h3 style="font-size: 24px; margin-bottom: 10px;">Order Completed Successfully!</h3>
                <p style="font-size: 16px; margin-bottom: 0;">Order #${order.order_number}</p>
            </div>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 2px solid #e2e8f0;">
                <h4 style="margin: 0 0 15px 0; color: #374151;">Order Details</h4>
                <div style="text-align: left;">
                    <p><strong>Order Number:</strong> ${order.order_number}</p>
                    <p><strong>Total Amount:</strong> PKR ${order.total_amount.toFixed(2)}</p>
                    <p><strong>Order Type:</strong> ${order.order_type.replace('_', ' ').toUpperCase()}</p>
                    ${order.table_number ? `<p><strong>Table:</strong> ${order.table_number}</p>` : ''}
                    <p><strong>Payment Method:</strong> ${order.payment_method.toUpperCase()}</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="printInvoice(${order.id})" 
                        style="padding: 12px 24px; border: none; border-radius: 8px; background: linear-gradient(135deg, #20bf55, #01baef); color: white; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button class="btn btn-secondary" onclick="startNewOrder()" 
                        style="padding: 12px 24px; border: none; border-radius: 8px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-plus"></i> Start New Order
                </button>
            </div>
        </div>
    `;
    
    showModal('Order Success', modalContent);
}

function printInvoice(orderId) {
    // Open invoice in new window for printing
    window.open(`print_invoice.php?order_id=${orderId}`, '_blank');
}

// Simple modal close function
function closeModal(modal) {
    if (modal && modal.parentNode) {
        modal.remove();
    }
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
    

    
    // Show success message
    showToast('New order started! Cart cleared.', 'success');
    
    // Generate new order number
    generateNewOrderNumber();
}

// Export all functions for global access
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.updateItemQuantity = updateItemQuantity;
window.getCartItems = getCartItems;
window.getCartTotal = getCartTotal;
window.getCartTotalQuantity = getCartTotalQuantity;
window.clearCart = clearCart;
window.selectCartItem = selectCartItem;
window.increaseQuantity = increaseQuantity;
window.decreaseQuantity = decreaseQuantity;
window.deleteSelectedItem = deleteSelectedItem;
window.updateSelectedQuantity = updateSelectedQuantity;
window.updateCartDisplay = updateCartDisplay;
window.updatePaymentDisplay = updatePaymentDisplay;
window.showToast = showToast;
window.showPaymentModal = showPaymentModal;
window.completeOrder = completeOrder;
window.printInvoice = printInvoice;
window.showOrderSuccessModal = showOrderSuccessModal;
window.startNewOrder = startNewOrder;
window.closeModal = closeModal; 