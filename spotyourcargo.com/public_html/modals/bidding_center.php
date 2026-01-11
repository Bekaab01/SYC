<?php
// Bidding Center Modal
// This modal shows all pending quotations/bids for the shipper's loads

require_once '../includes/config.php';

// Get pending bids for the current shipper
$pending_bids = [];
$shipper_id = $_SESSION['shipper_id'] ?? null;

if ($shipper_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                b.id as bid_id, b.amount, b.currency, b.message, b.status,
                b.created_at, b.valid_until,
                c.id as cargo_id, c.cargo_id as cargo_ref, c.description,
                c.weight, c.pickup_location, c.dropoff_location,
                a.id as association_id, a.company_name, a.contact_person,
                a.phone as association_phone, a.email as association_email,
                TIMESTAMPDIFF(HOUR, b.created_at, NOW()) as hours_since_bid
            FROM bids b
            JOIN cargo c ON b.cargo_id = c.id
            JOIN associations a ON b.association_id = a.id
            WHERE c.shipper_user_id = ?
            AND b.status = 'pending'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$shipper_id]);
        $pending_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Pending bids query error: " . $e->getMessage());
    }
}
?>

<div class="modal active" id="bidding-center-modal">
    <div class="modal-content bidding-center-modal">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice-dollar"></i> Bidding Center</h3>
            <button class="modal-close" onclick="closeModal('bidding-center-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <?php if (!empty($pending_bids)): ?>
                <div class="bids-summary">
                    <div class="summary-stat">
                        <span class="stat-number"><?php echo count($pending_bids); ?></span>
                        <span class="stat-label">Pending Quotations</span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-number"><?php echo count(array_unique(array_column($pending_bids, 'cargo_id'))); ?></span>
                        <span class="stat-label">Active Loads</span>
                    </div>
                </div>

                <div class="bids-container">
                    <?php
                    $current_cargo_id = null;
                    foreach ($pending_bids as $bid):
                        if ($current_cargo_id !== $bid['cargo_id']):
                            if ($current_cargo_id !== null) echo '</div>'; // Close previous cargo group
                            $current_cargo_id = $bid['cargo_id'];
                    ?>
                        <div class="cargo-bids-group">
                            <div class="cargo-header">
                                <div class="cargo-info">
                                    <h4><?php echo htmlspecialchars($bid['cargo_ref']); ?></h4>
                                    <p><?php echo htmlspecialchars($bid['description']); ?></p>
                                    <div class="cargo-route">
                                        <i class="fas fa-route"></i>
                                        <?php echo htmlspecialchars($bid['pickup_location']); ?> → <?php echo htmlspecialchars($bid['dropoff_location']); ?>
                                    </div>
                                </div>
                                <div class="cargo-details">
                                    <span class="cargo-weight"><?php echo htmlspecialchars($bid['weight']); ?> kg</span>
                                </div>
                            </div>

                            <div class="bids-list">
                    <?php endif; ?>

                                <div class="bid-card" data-bid-id="<?php echo $bid['bid_id']; ?>">
                                    <div class="bid-header">
                                        <div class="bidder-info">
                                            <div class="bidder-avatar">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div class="bidder-details">
                                                <h5><?php echo htmlspecialchars($bid['company_name']); ?></h5>
                                                <small><?php echo htmlspecialchars($bid['contact_person']); ?></small>
                                            </div>
                                        </div>
                                        <div class="bid-amount">
                                            <div class="amount"><?php echo number_format($bid['amount'], 2); ?> <small><?php echo htmlspecialchars($bid['currency']); ?></small></div>
                                            <div class="bid-time"><?php echo $bid['hours_since_bid']; ?>h ago</div>
                                        </div>
                                    </div>

                                    <?php if ($bid['message']): ?>
                                    <div class="bid-message">
                                        <p><?php echo htmlspecialchars($bid['message']); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="bid-actions">
                                        <button class="btn btn-success accept-bid" data-bid-id="<?php echo $bid['bid_id']; ?>" data-association-id="<?php echo $bid['association_id']; ?>">
                                            <i class="fas fa-check"></i>
                                            Accept
                                        </button>
                                        <button class="btn btn-secondary negotiate-bid" data-bid-id="<?php echo $bid['bid_id']; ?>">
                                            <i class="fas fa-comments"></i>
                                            Negotiate
                                        </button>
                                        <button class="btn btn-outline reject-bid" data-bid-id="<?php echo $bid['bid_id']; ?>">
                                            <i class="fas fa-times"></i>
                                            Reject
                                        </button>
                                    </div>

                                    <div class="bid-meta">
                                        <small>
                                            <i class="fas fa-clock"></i>
                                            Valid until: <?php echo date('M j, Y H:i', strtotime($bid['valid_until'])); ?>
                                        </small>
                                        <a href="tel:<?php echo htmlspecialchars($bid['association_phone']); ?>" class="contact-link">
                                            <i class="fas fa-phone"></i>
                                            Call
                                        </a>
                                    </div>
                                </div>

                    <?php endforeach; ?>
                            </div>
                        </div>
                    <?php if (!empty($pending_bids)) echo '</div>'; // Close last cargo group ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h3>No Pending Quotations</h3>
                    <p>You don't have any pending bids to review at the moment.</p>
                    <button class="btn btn-primary" onclick="closeModal('bidding-center-modal'); loadTabContent('drafts');">
                        <i class="fas fa-eye"></i>
                        View My Loads
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.bidding-center-modal .modal-content {
    max-width: 1200px;
    max-height: 85vh;
}

.bids-summary {
    display: flex;
    gap: 30px;
    margin-bottom: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
}

.summary-stat {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-blue);
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: var(--text-light);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cargo-bids-group {
    margin-bottom: 30px;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
}

.cargo-header {
    background: var(--primary-blue);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cargo-header h4 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
}

.cargo-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.cargo-route {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    opacity: 0.8;
}

.cargo-weight {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.bids-list {
    padding: 20px;
    background: white;
}

.bid-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    transition: var(--transition);
}

.bid-card:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border-color: var(--secondary-blue);
}

.bid-card:last-child {
    margin-bottom: 0;
}

.bid-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.bidder-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bidder-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-yellow);
    color: var(--primary-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.bidder-details h5 {
    margin: 0 0 2px 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--dark-gray);
}

.bidder-details small {
    color: var(--text-light);
    font-size: 12px;
}

.bid-amount {
    text-align: right;
}

.bid-amount .amount {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-blue);
    line-height: 1;
}

.bid-amount .amount small {
    font-size: 14px;
    font-weight: 500;
}

.bid-time {
    font-size: 12px;
    color: var(--text-light);
    margin-top: 4px;
}

.bid-message {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
    border-left: 3px solid var(--secondary-blue);
}

.bid-message p {
    margin: 0;
    font-size: 14px;
    color: var(--dark-gray);
    line-height: 1.4;
}

.bid-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.bid-actions .btn {
    flex: 1;
    font-size: 14px;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-success {
    background: #4CAF50;
    color: white;
}

.btn-success:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-outline {
    background: transparent;
    color: #dc3545;
    border: 1px solid #dc3545;
}

.btn-outline:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
}

.bid-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--text-light);
}

.contact-link {
    color: var(--secondary-blue);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: var(--transition);
}

.contact-link:hover {
    color: var(--primary-blue);
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .bids-summary {
        flex-direction: column;
        gap: 15px;
    }

    .cargo-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .bid-header {
        flex-direction: column;
        gap: 15px;
    }

    .bid-amount {
        text-align: left;
    }

    .bid-actions {
        flex-direction: column;
    }

    .bid-meta {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}
</style>

<script>
// Bidding center modal functionality
function openBiddingCenterModal() {
    const modal = document.getElementById('bidding-center-modal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeBiddingCenterModal() {
    const modal = document.getElementById('bidding-center-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Accept bid functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('accept-bid') || e.target.closest('.accept-bid')) {
        const button = e.target.classList.contains('accept-bid') ? e.target : e.target.closest('.accept-bid');
        const bidId = button.dataset.bidId;
        const associationId = button.dataset.associationId;

        if (confirm('Are you sure you want to accept this bid? This will assign the load to this carrier.')) {
            acceptBid(bidId, associationId);
        }
    }
});

// Negotiate bid functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('negotiate-bid') || e.target.closest('.negotiate-bid')) {
        const button = e.target.classList.contains('negotiate-bid') ? e.target : e.target.closest('.negotiate-bid');
        const bidId = button.dataset.bidId;

        // Open negotiation modal or form
        alert('Negotiation feature for bid ID: ' + bidId + ' (Coming soon)');
    }
});

// Reject bid functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('reject-bid') || e.target.closest('.reject-bid')) {
        const button = e.target.classList.contains('reject-bid') ? e.target : e.target.closest('.reject-bid');
        const bidId = button.dataset.bidId;

        if (confirm('Are you sure you want to reject this bid?')) {
            rejectBid(bidId);
        }
    }
});

// AJAX functions for bid actions
function acceptBid(bidId, associationId) {
    fetch('ajax/accept_bid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            bid_id: bidId,
            association_id: associationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            dashboardModalSystem.showSuccessModal('Bid accepted successfully!');
            closeBiddingCenterModal();
            // Refresh dashboard data
            loadTabContent('dashboard');
        } else {
            dashboardModalSystem.showErrorModal('Error accepting bid: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        dashboardModalSystem.showErrorModal('An error occurred while accepting the bid.');
    });
}

function rejectBid(bidId) {
    fetch('ajax/reject_bid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            bid_id: bidId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            dashboardModalSystem.showSuccessModal('Bid rejected successfully!');
            // Remove the bid card from UI
            const bidCard = document.querySelector(`[data-bid-id="${bidId}"]`);
            if (bidCard) {
                bidCard.remove();
            }
        } else {
            dashboardModalSystem.showErrorModal('Error rejecting bid: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        dashboardModalSystem.showErrorModal('An error occurred while rejecting the bid.');
    });
}
</script>
