<?php
// Shared functions and variables for shipper dashboard

function generateCargoId($pdo) {
    // Get the last cargo ID
    $stmt = $pdo->query("SELECT cargo_id FROM cargo ORDER BY id DESC LIMIT 1");
    $last_cargo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_cargo && preg_match('/SYC-CG-(\d+)/', $last_cargo['cargo_id'], $matches)) {
        $last_number = (int)$matches[1];
        $new_number = $last_number + 1;
    } else {
        // If no SpotYourCargo-CG IDs exist yet, check if we have numeric IDs to convert
        $stmt = $pdo->query("SELECT cargo_id FROM cargo WHERE cargo_id REGEXP '^[0-9]+$' ORDER BY id DESC LIMIT 1");
        $numeric_id = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($numeric_id && is_numeric($numeric_id['cargo_id'])) {
            $new_number = (int)$numeric_id['cargo_id'] + 1;
        } else {
            $new_number = 1;
        }
    }
    
    return 'SYC-CG-' . str_pad($new_number, 6, '0', STR_PAD_LEFT);
}

function generateTruckId($pdo) {
    // Check if trucks table has truck_id column
    $check_column = $pdo->query("SHOW COLUMNS FROM trucks LIKE 'truck_id'");
    
    if ($check_column->rowCount() > 0) {
        $stmt = $pdo->query("SELECT truck_id FROM trucks ORDER BY id DESC LIMIT 1");
        $last_truck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_truck && preg_match('/SYC-TR-(\d+)/', $last_truck['truck_id'], $matches)) {
            $last_number = (int)$matches[1];
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
    } else {
        // If no truck_id column, just use sequential numbering
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM trucks");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_number = $result['max_id'] ? $result['max_id'] + 1 : 1;
    }
    
    return 'SYC-TR-' . str_pad($new_number, 6, '0', STR_PAD_LEFT);
}

function calculateMatchScore($cargo, $truck) {
    $score = 0;
    $max_score = 100;
    
    // Capacity match (40% of total score)
    $cargo_weight = floatval($cargo['weight'] ?? 0);
    $truck_capacity = floatval($truck['capacity'] ?? 0);
    
    if ($truck_capacity > 0) {
        $capacity_ratio = $cargo_weight / $truck_capacity;
        if ($capacity_ratio <= 1.0) {
            // Perfect match or truck has extra capacity
            $capacity_score = 40 * (1 - abs($capacity_ratio - 0.8) / 0.8); // Best at 80% capacity utilization
        } else {
            // Truck too small
            $capacity_score = 40 * (1 / $capacity_ratio);
        }
        $score += max(0, min(40, $capacity_score));
    }
    
    // Location match (30% of total score)
    $cargo_pickup = strtolower($cargo['pickup_location'] ?? '');
    $truck_location = strtolower($truck['current_location'] ?? '');
    
    if ($cargo_pickup && $truck_location) {
        if (strpos($truck_location, $cargo_pickup) !== false || 
            strpos($cargo_pickup, $truck_location) !== false ||
            levenshtein($cargo_pickup, $truck_location) <= 3) {
            $score += 30; // Same location
        } elseif (strpos($truck['operating_route'] ?? '', $cargo_pickup) !== false) {
            $score += 20; // On operating route
        } else {
            $score += 10; // Different location
        }
    }
    
    // Cargo type compatibility (20% of total score)
    $cargo_type = strtolower($cargo['cargo_type'] ?? 'general');
    $score += 20; // Base score - all trucks can handle general cargo
    
    // Special requirements (10% of total score)
    $score += 10;
    
    return min(100, max(0, round($score)));
}

function getMatchReasons($cargo, $truck, $score) {
    $reasons = [];
    
    // Capacity reason
    $cargo_weight = floatval($cargo['weight'] ?? 0);
    $truck_capacity = floatval($truck['capacity'] ?? 0);
    
    if ($truck_capacity > 0) {
        $utilization = ($cargo_weight / $truck_capacity) * 100;
        if ($utilization <= 80) {
            $reasons[] = "Perfect capacity fit (" . round($utilization) . "% utilization)";
        } elseif ($utilization <= 100) {
            $reasons[] = "Good capacity match (" . round($utilization) . "% utilization)";
        } else {
            $reasons[] = "Adequate capacity (" . round($utilization) . "% utilization)";
        }
    }
    
    // Location reason
    $cargo_pickup = $cargo['pickup_location'] ?? '';
    $truck_location = $truck['current_location'] ?? '';
    
    if ($cargo_pickup && $truck_location) {
        if (strpos(strtolower($truck_location), strtolower($cargo_pickup)) !== false) {
            $reasons[] = "Same location: " . $truck_location;
        } elseif (strpos(strtolower($truck['operating_route'] ?? ''), strtolower($cargo_pickup)) !== false) {
            $reasons[] = "On operating route through " . $cargo_pickup;
        } else {
            $reasons[] = "Available in " . $truck_location;
        }
    }
    
    // High score reasons
    if ($score >= 80) {
        $reasons[] = "Excellent overall match";
    } elseif ($score >= 60) {
        $reasons[] = "Good overall compatibility";
    }
    
    return $reasons;
}

function calculateTruckMatches($cargo, $pdo) {
    $matches = [];
    
    try {
        // Get all active trucks
        $trucks_stmt = $pdo->prepare("
            SELECT t.*, 
                   c.company_name as carrier_company,
                   c.email as carrier_email,
                   c.phone as carrier_phone,
                   u.full_name as carrier_contact
            FROM trucks t
            LEFT JOIN carriers c ON t.carrier_id = c.id
            LEFT JOIN users u ON c.syc_id = u.syc_id
            WHERE t.status = 'active'
        ");
        $trucks_stmt->execute();
        $all_trucks = $trucks_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_trucks as $truck) {
            $match_score = calculateMatchScore($cargo, $truck);
            
            // Only include trucks with reasonable match score (50%+)
            if ($match_score >= 50) {
                $matches[] = [
                    'truck' => $truck,
                    'match_score' => $match_score,
                    'match_reasons' => getMatchReasons($cargo, $truck, $match_score)
                ];
            }
        }
        
        // Sort by match score (highest first)
        usort($matches, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });
        
    } catch (PDOException $e) {
        error_log("Match calculation error: " . $e->getMessage());
    }
    
    return $matches;
}
?>