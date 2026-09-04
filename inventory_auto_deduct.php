<?php
// inventory_auto_deduct.php - SIMPLIFIED VERSION

include 'db_connect.php';

/**
 * Deduct inventory when add-ons are selected
 */
function deductAddonInventory($conn, $booking_id, $add_ons_string, $laundry_weight = 0) {
    if (empty($add_ons_string) || $add_ons_string == 'None' || $add_ons_string == '') {
        return ['success' => true, 'message' => 'No add-ons selected', 'deductions' => []];
    }
    
    $deductions = [];
    $errors = [];
    $addons = array_map('trim', explode(',', $add_ons_string));
    
    foreach ($addons as $addon) {
        $addon_lower = strtolower($addon);
        $item_search = '';
        $deduct_quantity = 0;
        $item_type = '';
        
        // SIMPLIFIED MAPPING
        if (strpos($addon_lower, 'liquid detergent') !== false || strpos($addon_lower, 'detergent') !== false) {
            $item_search = 'Liquid Detergent';
            $deduct_quantity = 0.050; // 50ml
            $item_type = 'detergent';
        }
        elseif (strpos($addon_lower, 'fabric conditioner') !== false || strpos($addon_lower, 'fabcon') !== false || strpos($addon_lower, 'conditioner') !== false) {
            $item_search = 'Fabric Conditioner';
            $deduct_quantity = 0.030; // 30ml
            $item_type = 'fabcon';
        }
        elseif (strpos($addon_lower, 'extra dry') !== false) {
            $deductions[] = ['type' => 'extra-dry', 'status' => 'no_deduction', 'message' => '⚡ Extra Dry - no inventory deduction'];
            continue;
        }
        else {
            continue; // Skip unknown add-ons
        }
        
        // Find item by exact name
        $item_query = "SELECT * FROM inventory_items WHERE item_name = '$item_search' AND status != 'Out of Stock' LIMIT 1";
        $item_result = $conn->query($item_query);
        
        if ($item_result && $item_result->num_rows > 0) {
            $item = $item_result->fetch_assoc();
            $item_id = $item['item_id'];
            $current_stock = $item['current_stock'];
            
            if ($current_stock < $deduct_quantity) {
                $errors[] = "❌ Insufficient $item_search. Available: {$current_stock} {$item['unit']}";
                continue;
            }
            
            $new_stock = $current_stock - $deduct_quantity;
            $conn->query("UPDATE inventory_items SET current_stock = $new_stock WHERE item_id = $item_id");
            
            $notes = "🧴 Add-on: $addon (Booking #$booking_id)";
            $conn->query("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, transaction_date) 
                          VALUES ($item_id, 'Usage', $deduct_quantity, $current_stock, $new_stock, 'Booking', $booking_id, '$notes', NOW())");
            
            $deductions[] = ['item' => $item_search, 'quantity' => $deduct_quantity, 'unit' => $item['unit']];
            
            // Low stock alert
            if ($new_stock <= $item['min_stock_level']) {
                $msg = "⚠️ $item_search running low! Current: $new_stock {$item['unit']}";
                $conn->query("INSERT INTO notifications_admin (booking_id, user_id, message, status, created_at) VALUES ($booking_id, 1, '$msg', 'pending', NOW())");
            }
        } else {
            $errors[] = "❌ $item_search not found in inventory";
        }
    }
    
    return ['success' => empty($errors), 'deductions' => $deductions, 'errors' => $errors];
}

/**
 * Deduct items ALWAYS used per booking
 */
function deductAlwaysPerBooking($conn, $booking_id) {
    $deductions = [];
    
    // Plastic Bag - 1 piece per booking
    $item_query = "SELECT * FROM inventory_items WHERE item_name = 'Plastic Bag' AND status != 'Out of Stock' LIMIT 1";
    $item_result = $conn->query($item_query);
    
    if ($item_result && $item_result->num_rows > 0) {
        $item = $item_result->fetch_assoc();
        $item_id = $item['item_id'];
        $current_stock = $item['current_stock'];
        $deduct_quantity = 1;
        
        if ($current_stock >= $deduct_quantity) {
            $new_stock = $current_stock - $deduct_quantity;
            $conn->query("UPDATE inventory_items SET current_stock = $new_stock WHERE item_id = $item_id");
            $conn->query("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, transaction_date) 
                          VALUES ($item_id, 'Usage', $deduct_quantity, $current_stock, $new_stock, 'Booking', $booking_id, '🛍️ Per booking (Booking #$booking_id)', NOW())");
            $deductions[] = ['item' => 'Plastic Bag', 'quantity' => $deduct_quantity, 'unit' => $item['unit']];
        }
    }
    
    return ['success' => true, 'deductions' => $deductions];
}

/**
 * Deduct detergent based on laundry weight
 */
function deductDetergentByWeight($conn, $booking_id, $laundry_weight) {
    if ($laundry_weight <= 0) return ['success' => true, 'message' => 'No weight specified'];
    
    $usage_per_kg = 0.050; // 50ml per kg
    $deduct_quantity = $laundry_weight * $usage_per_kg;
    
    $item_query = "SELECT * FROM inventory_items WHERE item_name = 'Liquid Detergent' AND status != 'Out of Stock' LIMIT 1";
    $item_result = $conn->query($item_query);
    
    if ($item_result && $item_result->num_rows > 0) {
        $item = $item_result->fetch_assoc();
        $item_id = $item['item_id'];
        $current_stock = $item['current_stock'];
        
        if ($current_stock >= $deduct_quantity) {
            $new_stock = $current_stock - $deduct_quantity;
            $conn->query("UPDATE inventory_items SET current_stock = $new_stock WHERE item_id = $item_id");
            $conn->query("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, transaction_date) 
                          VALUES ($item_id, 'Usage', $deduct_quantity, $current_stock, $new_stock, 'Booking', $booking_id, '🧼 Detergent for {$laundry_weight}kg (Booking #$booking_id)', NOW())");
            
            if ($new_stock <= $item['min_stock_level']) {
                $msg = "⚠️ Liquid Detergent running low! Current: $new_stock {$item['unit']}";
                $conn->query("INSERT INTO notifications_admin (booking_id, user_id, message, status, created_at) VALUES ($booking_id, 1, '$msg', 'pending', NOW())");
            }
            
            return ['success' => true, 'item' => 'Liquid Detergent', 'quantity' => $deduct_quantity, 'unit' => $item['unit']];
        }
    }
    return ['success' => false, 'message' => 'Detergent not found'];
}

/**
 * Get stock status for dashboard
 */
function getInventoryStockStatus($conn) {
    $stats = ['total_items' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'low_stock_items' => []];
    $result = $conn->query("SELECT COUNT(*) as cnt FROM inventory_items");
    $stats['total_items'] = $result->fetch_assoc()['cnt'] ?? 0;
    $result = $conn->query("SELECT COUNT(*) as cnt FROM inventory_items WHERE status = 'Low Stock'");
    $stats['low_stock'] = $result->fetch_assoc()['cnt'] ?? 0;
    $result = $conn->query("SELECT COUNT(*) as cnt FROM inventory_items WHERE status = 'Out of Stock'");
    $stats['out_of_stock'] = $result->fetch_assoc()['cnt'] ?? 0;
    $result = $conn->query("SELECT item_id, item_name, current_stock, min_stock_level, unit FROM inventory_items WHERE status IN ('Low Stock', 'Out of Stock') ORDER BY current_stock ASC LIMIT 5");
    while ($row = $result->fetch_assoc()) { $stats['low_stock_items'][] = $row; }
    return $stats;
}
?>