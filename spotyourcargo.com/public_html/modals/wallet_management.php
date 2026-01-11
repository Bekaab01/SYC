<?php
// Wallet Management Modal
// This modal shows wallet balance, transaction history, and top-up options

require_once '../includes/config.php';

// Get wallet data for the current shipper
$wallet_data = [];
$transactions = [];
$shipper_id = $_SESSION['shipper_id'] ?? null;

if ($shipper_id) {
    try {
        // Get wallet balance
        $stmt = $pdo->prepare("
            SELECT balance, currency, last_updated
            FROM wallets
            WHERE user_id = ? AND user_type = 'shipper'
        ");
        $stmt->execute([$shipper_id]);
        $wallet_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get recent transactions (last 20)
        $stmt = $pdo->prepare("
            SELECT id, type, amount, currency, description, status,
                   reference_id, created_at
            FROM wallet_transactions
            WHERE user_id = ? AND user_type = 'shipper'
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$shipper_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Wallet data query error: " . $e->getMessage());
    }
}
?>

<div class="modal active" id="wallet-management-modal">
    <div class="modal-content wallet-management-modal">
        <div class="modal-header">
            <h3><i class="fas fa-wallet"></i> Wallet Management</h3>
            <button class="modal-close" onclick="closeModal('wallet-management-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <!-- Wallet Balance Section -->
            <div class="wallet-balance-section">
                <div class="balance-card">
                    <div class="balance-header">
                        <h4>Current Balance</h4>
                        <small>Last updated: <?php echo $wallet_data ? date('M j, Y H:i', strtotime($wallet_data['last_updated'])) : 'Never'; ?></small>
                    </div>
                    <div class="balance-amount">
                        <span class="amount"><?php echo number_format($wallet_data['balance'] ?? 0, 2); ?></span>
                        <span class="currency"><?php echo htmlspecialchars($wallet_data['currency'] ?? 'USD'); ?></span>
                    </div>
                    <div class="quick-actions">
                        <button class="quick-topup-btn" onclick="showTopupSection()">
                            <i class="fas fa-plus"></i>
                            Top Up
                        </button>
                        <button class="quick-withdraw-btn" onclick="showWithdrawSection()">
                            <i class="fas fa-minus"></i>
                            Withdraw
                        </button>
                    </div>
                </div>
            </div>

            <!-- Top-up Section (Hidden by default) -->
            <div class="topup-section" id="topup-section" style="display: none;">
                <h4><i class="fas fa-credit-card"></i> Top Up Your Wallet</h4>
                <div class="topup-options">
                    <div class="preset-amounts">
                        <button class="amount-btn" data-amount="50" data-bonus="2">
                            <div class="amount">$50</div>
                            <div class="bonus">+ $2 bonus</div>
                        </button>
                        <button class="amount-btn" data-amount="100" data-bonus="5">
                            <div class="amount">$100</div>
                            <div class="bonus">+ $5 bonus</div>
                        </button>
                        <button class="amount-btn" data-amount="250" data-bonus="15">
                            <div class="amount">$250</div>
                            <div class="bonus">+ $15 bonus</div>
                        </button>
                        <button class="amount-btn" data-amount="500" data-bonus="35">
                            <div class="amount">$500</div>
                            <div class="bonus">+ $35 bonus</div>
                        </button>
                    </div>
                    <div class="custom-amount">
                        <label for="custom-amount">Custom Amount ($)</label>
                        <input type="number" id="custom-amount" min="10" step="0.01" placeholder="Enter amount">
                        <button class="btn btn-primary" onclick="processTopup()">Top Up</button>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="transaction-history">
                <div class="section-header">
                    <h4><i class="fas fa-history"></i> Recent Transactions</h4>
                    <button class="view-all-btn" onclick="viewAllTransactions()">
                        <i class="fas fa-eye"></i>
                        View All
                    </button>
                </div>

                <?php if (!empty($transactions)): ?>
                    <div class="transactions-list">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item <?php echo htmlspecialchars($transaction['type']); ?>">
                                <div class="transaction-icon">
                                    <?php
                                    $icon_class = 'fas fa-question';
                                    switch ($transaction['type']) {
                                        case 'credit':
                                            $icon_class = 'fas fa-plus-circle';
                                            break;
                                        case 'debit':
                                            $icon_class = 'fas fa-minus-circle';
                                            break;
                                        case 'refund':
                                            $icon_class = 'fas fa-undo';
                                            break;
                                        case 'fee':
                                            $icon_class = 'fas fa-dollar-sign';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-description">
                                        <?php echo htmlspecialchars($transaction['description']); ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <span class="transaction-date"><?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?></span>
                                        <?php if ($transaction['reference_id']): ?>
                                        <span class="transaction-ref">Ref: <?php echo htmlspecialchars($transaction['reference_id']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="transaction-amount">
                                    <span class="amount <?php echo $transaction['type']; ?>">
                                        <?php
                                        $prefix = '';
                                        if ($transaction['type'] === 'credit' || $transaction['type'] === 'refund') {
                                            $prefix = '+';
                                        } elseif ($transaction['type'] === 'debit' || $transaction['type'] === 'fee') {
                                            $prefix = '-';
                                        }
                                        echo $prefix . number_format($transaction['amount'], 2) . ' ' . htmlspecialchars($transaction['currency']);
                                        ?>
                                    </span>
                                    <span class="status <?php echo htmlspecialchars($transaction['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($transaction['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-transactions">
                        <i class="fas fa-receipt"></i>
                        <h5>No Transactions Yet</h5>
                        <p>Your transaction history will appear here once you start using your wallet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.wallet-management-modal .modal-content {
    max-width: 800px;
    max-height: 90vh;
}

.wallet-balance-section {
    margin-bottom: 30px;
}

.balance-card {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
}

.balance-header h4 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
}

.balance-header small {
    opacity: 0.8;
    font-size: 12px;
}

.balance-amount {
    margin: 20px 0;
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 10px;
}

.balance-amount .amount {
    font-size: 48px;
    font-weight: 700;
    line-height: 1;
}

.balance-amount .currency {
    background: rgba(255, 255, 255, 0.2);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.quick-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
}

.quick-topup-btn, .quick-withdraw-btn {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.quick-topup-btn {
    background: var(--primary-yellow);
    color: var(--primary-blue);
}

.quick-topup-btn:hover {
    background: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(251, 191, 36, 0.3);
}

.quick-withdraw-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.quick-withdraw-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.topup-section {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.topup-section h4 {
    margin: 0 0 20px 0;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    gap: 8px;
}

.preset-amounts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.amount-btn {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    cursor: pointer;
    transition: var(--transition);
    text-align: center;
}

.amount-btn:hover, .amount-btn.selected {
    border-color: var(--primary-blue);
    background: rgba(0, 51, 102, 0.05);
    transform: translateY(-2px);
}

.amount-btn .amount {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-blue);
    margin-bottom: 5px;
}

.amount-btn .bonus {
    font-size: 12px;
    color: #4CAF50;
    font-weight: 500;
}

.custom-amount {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.custom-amount label {
    font-weight: 500;
    color: var(--dark-gray);
}

.custom-amount input {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
}

.transaction-history {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
}

.transaction-history .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.transaction-history h4 {
    margin: 0;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    gap: 8px;
}

.transactions-list {
    max-height: 400px;
    overflow-y: auto;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.transaction-item.credit .transaction-icon {
    background: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
}

.transaction-item.debit .transaction-icon {
    background: rgba(244, 67, 54, 0.1);
    color: #F44336;
}

.transaction-item.refund .transaction-icon {
    background: rgba(255, 152, 0, 0.1);
    color: #FF9800;
}

.transaction-item.fee .transaction-icon {
    background: rgba(156, 39, 176, 0.1);
    color: #9C27B0;
}

.transaction-details {
    flex: 1;
}

.transaction-description {
    font-weight: 600;
    color: var(--dark-gray);
    margin-bottom: 4px;
}

.transaction-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: var(--text-light);
}

.transaction-amount {
    text-align: right;
}

.transaction-amount .amount {
    display: block;
    font-weight: 700;
    font-size: 16px;
    margin-bottom: 4px;
}

.transaction-amount .amount.credit,
.transaction-amount .amount.refund {
    color: #4CAF50;
}

.transaction-amount .amount.debit,
.transaction-amount .amount.fee {
    color: #F44336;
}

.transaction-amount .status {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    font-weight: 500;
}

.status.completed {
    background: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
}

.status.pending {
    background: rgba(255, 152, 0, 0.1);
    color: #FF9800;
}

.status.failed {
    background: rgba(244, 67, 54, 0.1);
    color: #F44336;
}

.empty-transactions {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.empty-transactions i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-transactions h5 {
    margin: 0 0 10px 0;
    color: var(--dark-gray);
}

.view-all-btn {
    background: none;
    border: 1px solid var(--secondary-blue);
    color: var(--secondary-blue);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 6px;
}

.view-all-btn:hover {
    background: var(--secondary-blue);
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .balance-amount .amount {
        font-size: 36px;
    }

    .quick-actions {
        flex-direction: column;
        align-items: center;
    }

    .preset-amounts {
        grid-template-columns: repeat(2, 1fr);
    }

    .transaction-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .transaction-amount {
        text-align: left;
        align-self: flex-end;
    }

    .transaction-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<script>
// Wallet management modal functionality
function openWalletManagementModal() {
    const modal = document.getElementById('wallet-management-modal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeWalletManagementModal() {
    const modal = document.getElementById('wallet-management-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Top-up section toggle
function showTopupSection() {
    const topupSection = document.getElementById('topup-section');
    if (topupSection) {
        topupSection.style.display = topupSection.style.display === 'none' ? 'block' : 'none';
    }
}

// Withdraw section toggle (placeholder)
function showWithdrawSection() {
    dashboardModalSystem.showErrorModal('Withdraw functionality coming soon!');
}

// Preset amount selection
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('amount-btn') || e.target.closest('.amount-btn')) {
        const button = e.target.classList.contains('amount-btn') ? e.target : e.target.closest('.amount-btn');

        // Remove selected class from all buttons
        document.querySelectorAll('.amount-btn').forEach(btn => btn.classList.remove('selected'));

        // Add selected class to clicked button
        button.classList.add('selected');

        const amount = button.dataset.amount;
        const bonus = button.dataset.bonus;

        // Update custom amount input
        const customInput = document.getElementById('custom-amount');
        if (customInput) {
            customInput.value = amount;
        }
    }
});

// Process top-up
function processTopup() {
    const customInput = document.getElementById('custom-amount');
    const amount = customInput ? parseFloat(customInput.value) : 0;

    if (!amount || amount < 10) {
        dashboardModalSystem.showErrorModal('Please enter a valid amount (minimum $10)');
        return;
    }

    // Here you would integrate with payment gateway
    dashboardModalSystem.showSuccessModal(`Processing top-up of $${amount}. Payment integration coming soon!`);
}

// View all transactions
function viewAllTransactions() {
    // Load full transaction history modal or redirect
    dashboardModalSystem.showErrorModal('Full transaction history view coming soon!');
}
</script>
