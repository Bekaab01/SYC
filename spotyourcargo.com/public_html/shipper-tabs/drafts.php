<?php
// Trust & Management Hub - Drafts & Pending Verification Tab

// Include document requirements configuration
require_once __DIR__ . '/../../private/document_requirements.php';

// Fetch Draft and Pending Verification loads for current shipper
$loads = [];
try {
    $shipper_id = $_SESSION['user_id'];
    echo "<!-- Debug: Starting query for shipper_id: $shipper_id -->";

    // First check total shipments for this shipper
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shipments WHERE shipper_id = ?");
    $check_stmt->execute([$shipper_id]);
    $total_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- Debug: Total shipments for this shipper: " . $total_result['total'] . " -->";

    // Check shipments by status
    $status_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM shipments WHERE shipper_id = ? GROUP BY status");
    $status_stmt->execute([$shipper_id]);
    $status_results = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- Debug: Status breakdown: " . json_encode($status_results) . " -->";

    $stmt = $pdo->prepare("
        SELECT s.id, s.load_ref, s.cargo_name, s.origin, s.destination,
               s.status, s.is_verified, s.is_boosted,
               (SELECT COUNT(DISTINCT doc_type) FROM load_documents ld WHERE ld.shipment_id = s.id) AS docs_count,
               (SELECT COUNT(*) FROM shipment_views sv WHERE sv.shipment_id = s.id) AS views_count,
               (SELECT COUNT(*) FROM quotations q WHERE q.shipment_id = s.id) AS bids_count,
               0 AS trust_score, -- Placeholder, will be calculated via API
               (SELECT COUNT(*) FROM load_documents WHERE shipment_id = s.id AND doc_type = 'Insurance') > 0 AS has_insurance
        FROM shipments s
        WHERE s.shipper_id = ? AND s.status IN ('Draft','Pending Verification')
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$shipper_id]);
    $loads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<!-- Debug: Query executed successfully. Found " . count($loads) . " loads -->";
    if (!empty($loads)) {
        echo "<!-- Debug: Load IDs: " . implode(', ', array_column($loads, 'id')) . " -->";
        echo "<!-- Debug: Load statuses: " . implode(', ', array_column($loads, 'status')) . " -->";
    }
} catch (PDOException $e) {
    error_log("Drafts data error: " . $e->getMessage());
    echo "<!-- Debug: Database error: " . $e->getMessage() . " -->";
}
?>

<div class="section-header">
    <h2><i class="fas fa-shield-alt"></i> Trust & Management Hub</h2>
    <div class="header-actions">
        <button class="btn btn-outline-primary" onclick="refreshDrafts()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Drafts Table -->
<div class="table-container">
    <table class="data-table" id="drafts-table">
        <thead>
            <tr>
                <th>Load Ref</th>
                <th>Cargo Name</th>
                <th>Route</th>
                <th>Status</th>
                <th>Trust Score</th>
                <th>Market Heat</th>
                <th>Cargo Protection</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="drafts-tbody">
            <?php if (!empty($loads)): ?>
                <?php foreach ($loads as $load): ?>
                    <?php
                    $trust_score = $load['trust_score'];
                    $has_insurance = $load['has_insurance'];
                    $is_boosted = $load['is_boosted'];

                    $status = $load['status'];
                    $status_class = 'grey';
                    $status_text = 'Draft';
                    if ($status === 'Pending Verification') {
                        $status_class = 'amber pulsing';
                        $status_text = 'LIVE – Awaiting Bids';
                    }
                    if ($load['is_verified']) {
                        $status_class = 'green';
                        $status_text = 'Verified';
                    }
                    ?>
                    <tr data-shipment-id="<?php echo $load['id']; ?>">
                        <td>
                            <button class="shipment-id-btn" onclick="viewShipmentDetails(<?php echo $load['id']; ?>)">
                                <?php echo htmlspecialchars($load['load_ref']); ?>
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars($load['cargo_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(($load['origin'] ?? 'N/A') . ' → ' . ($load['destination'] ?? 'N/A')); ?></td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($status_text); ?>
                            </span>
                        </td>
                        <td>
                            <div class="trust-container">
                                <div class="trust-bar">
                                    <div class="progress" style="width: <?php echo $trust_score; ?>%; background: <?php echo $trust_score >= 67 ? '#28a745' : ($trust_score >= 34 ? '#ffc107' : '#6c757d'); ?>;"></div>
                                    <span class="trust-text"><?php echo $trust_score; ?>%</span>
                                </div>
                                <div class="trust-status" id="status-<?php echo $load['id']; ?>">
                                    <span class="status-badge unknown">Loading...</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="market-heat">
                                👁 <?php echo $load['views_count']; ?> Views | 💼 <?php echo $load['bids_count']; ?> Bids
                            </div>
                        </td>
                        <td>
                            <div class="cargo-protection">
                                <?php if ($has_insurance): ?>
                                    <i class="fas fa-shield-alt text-success" title="Cargo Insured"></i>
                                <?php else: ?>
                                    <i class="fas fa-shield-alt text-muted" title="No Insurance"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($status === 'Draft'): ?>
                                    <button class="action-btn resume-btn" onclick="resumePosting(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-play"></i> Resume
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteDraft(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php elseif ($status === 'Pending Verification'): ?>
                                    <button class="action-btn manage-btn" onclick="manageDocuments(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-file-alt"></i> Manage Docs
                                    </button>
                                    <button class="action-btn analytics-btn" onclick="viewAnalytics(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-chart-bar"></i> Analytics
                                    </button>
                                    <button class="action-btn withdraw-btn" onclick="withdrawLoad(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-times"></i> Withdraw
                                    </button>
                                    <button class="action-btn boost-btn" onclick="boostLoad(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-rocket"></i> Boost
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn analytics-btn" onclick="viewAnalytics(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-chart-bar"></i> Analytics
                                    </button>
                                    <button class="action-btn withdraw-btn" onclick="withdrawLoad(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-times"></i> Withdraw
                                    </button>
                                    <button class="action-btn boost-btn" onclick="boostLoad(<?php echo $load['id']; ?>)">
                                        <i class="fas fa-rocket"></i> Boost
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Empty State -->
<?php if (empty($loads)): ?>
    <div class="empty-state" id="empty-state">
        <i class="fas fa-edit"></i>
        <h3>No Drafts</h3>
        <p>You don't have any draft shipments or pending verification loads.</p>
        <button class="btn btn-primary" onclick="loadTabContent('post_load')">+ Post Your First Load</button>
    </div>
<?php endif; ?>

<!-- Off-canvas Document Drawer -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="documentDrawer" aria-labelledby="documentDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="documentDrawerLabel">Manage Documents</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="documentDrawerBody">
        <!-- Documents will be loaded here -->
    </div>
</div>

<!-- Loading State -->
<div class="loading-state" id="loading-state" style="display: none;">
    <i class="fas fa-spinner fa-spin"></i>
    <p>Loading drafts...</p>
</div>

// Global variables for document requirements
let documentRequirements = <?php echo json_encode(DocumentRequirements::getAll()); ?>;
let documentTypes = <?php echo json_encode(DocumentRequirements::getAllTypes()); ?>;

<script>
// Global variables
let loadsData = <?php echo json_encode($loads); ?>;

// Load verification UI with progress ring and document slots
function loadVerificationUI(shipmentId, documents) {
    // Make function globally available
    window.loadVerificationUI = loadVerificationUI;
    const uploadedDocs = [...new Set(documents.map(doc => doc.doc_type))];
    const uploadedCount = uploadedDocs.length;
    const totalDocs = <?php echo DocumentRequirements::getTotalCount(); ?>;
    const requiredCount = <?php echo DocumentRequirements::getRequiredCount(); ?>;
    const progressPercent = (uploadedCount / totalDocs) * 100;

    // Fetch real trust score from API
    fetchTrustScoreForDrawer(shipmentId).then(trustData => {
        const trustScore = trustData.success ? trustData.trust_score : 0;
        const status = trustData.status || 'unknown';
        const notes = trustData.notes || [];
        updateDrawerTrustScore(trustScore, status, notes);
    });

    let html = `
        <div class="verification-header">
            <div class="progress-ring-container">
                <div class="progress-ring" style="--progress: ${progressPercent}">
                    <div class="progress-ring-circle">
                        <span class="progress-text">${uploadedCount}/${totalDocs}</span>
                    </div>
                </div>
                <div class="progress-label">Documents Uploaded</div>
            </div>
            <div class="trust-score-display">
                <div class="trust-score-value" id="drawer-trust-score">Loading...</div>
                <div class="trust-score-label">Trust Score</div>
            </div>
        </div>

        <div class="document-slots">
    `;

    // Document slots - dynamically generated from requirements
    const docTypes = documentTypes.map(docType => ({
        key: docType,
        label: documentRequirements.required[docType] ? documentRequirements.required[docType].label : documentRequirements.optional[docType].label,
        required: documentRequirements.required[docType] ? true : false
    }));

    docTypes.forEach(docType => {
        const isUploaded = uploadedDocs.includes(docType.key);
        const uploadedDoc = documents.find(doc => doc.doc_type === docType.key);
        const uploadDate = uploadedDoc ? new Date(uploadedDoc.uploaded_at).toLocaleDateString() : '';

        // Processing status information
        const ocrStatus = uploadedDoc ? uploadedDoc.ocr_status : 'pending';
        const verificationStatus = uploadedDoc ? uploadedDoc.verification_status : 'pending';
        const ocrConfidence = uploadedDoc ? uploadedDoc.ocr_confidence : 0;
        const trustScore = uploadedDoc ? uploadedDoc.trust_score : 0;

        // Determine overall processing status
        let processingStatus = 'pending';
        let processingStatusText = 'Not Started';
        let processingStatusClass = 'processing-pending';

        if (isUploaded) {
            if (ocrStatus === 'completed' && verificationStatus === 'completed') {
                processingStatus = 'completed';
                processingStatusText = 'Verified';
                processingStatusClass = 'processing-completed';
            } else if (ocrStatus === 'failed' || verificationStatus === 'failed') {
                processingStatus = 'failed';
                processingStatusText = 'Failed';
                processingStatusClass = 'processing-failed';
            } else if (ocrStatus === 'processing' || verificationStatus === 'processing') {
                processingStatus = 'processing';
                processingStatusText = 'Processing...';
                processingStatusClass = 'processing-active';
            } else {
                processingStatus = 'pending';
                processingStatusText = 'Queued';
                processingStatusClass = 'processing-pending';
            }
        }

        html += `
            <div class="document-slot ${isUploaded ? 'uploaded' : 'missing'} ${docType.required ? 'required' : 'optional'}">
                <div class="slot-header">
                    <div class="slot-icon">
                        <i class="fas ${getDocIcon(docType.key)}"></i>
                    </div>
                    <div class="slot-info">
                        <div class="slot-title">${docType.label}</div>
                        <div class="slot-status">
                            ${isUploaded ?
                                `<span class="status-uploaded">✓ Uploaded (${uploadDate})</span>` :
                                `<span class="status-missing">${docType.required ? 'Required' : 'Optional'}</span>`
                            }
                        </div>
                        ${isUploaded ? `
                            <div class="processing-status">
                                <div class="processing-indicator ${processingStatusClass}">
                                    <i class="fas ${getProcessingIcon(processingStatus)}"></i>
                                    <span>${processingStatusText}</span>
                                </div>
                                ${processingStatus === 'completed' ? `
                                    <div class="processing-details">
                                        <div class="detail-item">
                                            <span class="detail-label">OCR:</span>
                                            <span class="detail-value ${ocrStatus === 'completed' ? 'success' : ocrStatus === 'failed' ? 'error' : 'warning'}">
                                                ${ocrStatus === 'completed' ? `${ocrConfidence}%` : ocrStatus}
                                            </span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">AI:</span>
                                            <span class="detail-value ${verificationStatus === 'completed' ? 'success' : verificationStatus === 'failed' ? 'error' : 'warning'}">
                                                ${verificationStatus === 'completed' ? `${trustScore}%` : verificationStatus}
                                            </span>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="slot-actions">
                        ${isUploaded ?
                            `<button class="btn btn-sm btn-outline-danger" data-action="remove" data-doc-type="${docType.key}" data-shipment-id="${shipmentId}">
                                <i class="fas fa-trash"></i>
                            </button>` :
                            `<button class="btn btn-sm btn-outline-primary" data-action="upload" data-doc-type="${docType.key}" data-shipment-id="${shipmentId}">
                                <i class="fas fa-upload"></i>
                            </button>`
                        }
                    </div>
                </div>
                ${!isUploaded ? `
                    <input type="file" id="file-${docType.key.replace(/ /g, '_')}" style="position: absolute; left: -9999px;" accept=".pdf,.jpg,.jpeg,.png,.gif"
                           data-doc-type="${docType.key}" data-shipment-id="${shipmentId}">
                    <label class="upload-area" for="file-${docType.key.replace(/ /g, '_')}" data-doc-type="${docType.key}" data-shipment-id="${shipmentId}">
                        <div class="upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Click to upload ${docType.label.toLowerCase()}</div>
                            <small>PDF, JPG, PNG up to 10MB</small>
                        </div>
                    </label>
                ` : ''}
            </div>
        `;
    });

    html += `
        </div>

        <div class="verification-actions">
            <button class="btn btn-success w-100" onclick="markAsVerified(${shipmentId})" ${uploadedCount < 3 ? 'disabled' : ''}>
                <i class="fas fa-check"></i> Mark as Verified
            </button>
            ${uploadedCount >= 3 ? '<small class="text-muted">Minimum 3 documents required for verification</small>' : '<small class="text-warning">Upload at least 3 documents to proceed</small>'}
        </div>
    `;

    document.getElementById('documentDrawerBody').innerHTML = html;

    // Re-initialize upload listeners for dynamically added elements
    initializeUploadListeners();
}

// Calculate current trust score based on uploaded documents
function calculateCurrentTrustScore(documents) {
    const docScores = {
        'Invoice': 40,
        'SAD': 30,
        'Warehouse Release': 30,
        'Insurance': 20
    };

    let score = 0;
    documents.forEach(doc => {
        if (docScores[doc.doc_type]) {
            score += docScores[doc.doc_type];
        }
    });

    return Math.min(100, score);
}

// Get document label for display
function getDocLabel(docType) {
    const labels = {
        'Invoice': 'Commercial Invoice',
        'Warehouse Release': 'Warehouse Release',
        'SAD': 'Packing List/SAD',
        'Insurance': 'Cargo Insurance'
    };
    return labels[docType] || docType;
}

// Helper function to get document icons
function getDocIcon(docType) {
    const icons = {
        'Invoice': 'fa-file-invoice',
        'Warehouse Release': 'fa-file-contract',
        'SAD': 'fa-file-signature',
        'Insurance': 'fa-shield-alt'
    };
    return icons[docType] || 'fa-file';
}

// Helper function to get processing status icons
function getProcessingIcon(status) {
    const icons = {
        'pending': 'fa-clock',
        'processing': 'fa-spinner fa-spin',
        'completed': 'fa-check-circle',
        'failed': 'fa-exclamation-triangle'
    };
    return icons[status] || 'fa-question-circle';
}

window.handleFileUpload = function(shipmentId, docType, inputElement) {
    const file = inputElement.files[0];
    if (!file) return;

    // Show loading state - the label is the next sibling of the input
    const uploadArea = inputElement.nextElementSibling;
    if (uploadArea && uploadArea.classList.contains('upload-area')) {
        uploadArea.innerHTML = '<i class="fas fa-spinner fa-spin"></i><div>Uploading...</div>';
    }

    // Create FormData
    const formData = new FormData();
    formData.append('shipment_id', shipmentId);
    formData.append('doc_type', docType);
    formData.append('document', file);

    // Upload via AJAX
    fetch('api/upload_verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            // Reload the verification UI
            fetch(`?action=fetch_documents&shipment_id=${shipmentId}`)
                .then(response => response.json())
                .then(documents => {
                    loadVerificationUI(shipmentId, documents);
                });
        } else {
            showErrorMessage(data.message || 'Upload failed');
            // Reset upload area
            uploadArea.innerHTML = `
                <i class="fas fa-cloud-upload-alt"></i>
                <div>Click to upload ${getDocLabel(docType).toLowerCase()}</div>
                <small>PDF, JPG, PNG up to 10MB</small>
            `;
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showErrorMessage('Upload failed. Please try again.');
        // Reset upload area
        uploadArea.innerHTML = `
            <i class="fas fa-cloud-upload-alt"></i>
            <div>Click to upload ${getDocLabel(docType).toLowerCase()}</div>
            <small>PDF, JPG, PNG up to 10MB</small>
        `;
    });
};

// Load trust scores for all shipments via API
function loadTrustScores() {
    const rows = document.querySelectorAll('#drafts-tbody tr[data-shipment-id]');
    rows.forEach(row => {
        const shipmentId = row.getAttribute('data-shipment-id');
        if (shipmentId) {
            fetchTrustScore(shipmentId);
        }
    });
}

// Fetch trust score specifically for the drawer
function fetchTrustScoreForDrawer(shipmentId) {
    return fetch(`api/trust_score.php?shipment_id=${shipmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.trust_score !== undefined) {
                return data;
            } else {
                console.error('Trust score API error for drawer shipment', shipmentId, ':', data.message);
                return { success: false, trust_score: 0 };
            }
        })
        .catch(error => {
            console.error('Error fetching trust score for drawer shipment', shipmentId, ':', error);
            return { success: false, trust_score: 0 };
        });
}

// Update the trust score display in the drawer
function updateDrawerTrustScore(trustScore, status = 'unknown', notes = []) {
    const trustScoreElement = document.getElementById('drawer-trust-score');
    if (trustScoreElement) {
        trustScoreElement.textContent = `${trustScore}%`;

        // Add status badge next to trust score
        let statusBadge = '';
        if (status === 'trusted') {
            statusBadge = '<span class="status-badge green small">Trusted</span>';
        } else if (status === 'review_required') {
            statusBadge = '<span class="status-badge amber small">Review Required</span>';
        } else if (status === 'rejected') {
            statusBadge = '<span class="status-badge error small">Rejected</span>';
        } else if (status === 'no_documents') {
            statusBadge = '<span class="status-badge grey small">No Documents</span>';
        } else {
            statusBadge = '<span class="status-badge unknown small">Unknown</span>';
        }

        // Update the trust score display with badge
        const trustScoreDisplay = trustScoreElement.closest('.trust-score-display');
        if (trustScoreDisplay) {
            trustScoreDisplay.innerHTML = `
                <div class="trust-score-value" id="drawer-trust-score">${trustScore}%</div>
                <div class="trust-score-label">Trust Score ${statusBadge}</div>
            `;
        }
    }

    // Display verification notes if available
    if (notes && notes.length > 0) {
        const drawerBody = document.getElementById('documentDrawerBody');
        if (drawerBody) {
            // Find the verification header and add notes after it
            const verificationHeader = drawerBody.querySelector('.verification-header');
            if (verificationHeader) {
                // Remove existing notes section if present
                const existingNotes = drawerBody.querySelector('.verification-notes');
                if (existingNotes) {
                    existingNotes.remove();
                }

                // Create notes section
                const notesSection = document.createElement('div');
                notesSection.className = 'verification-notes';
                notesSection.innerHTML = `
                    <h6 class="notes-title"><i class="fas fa-info-circle"></i> Verification Details</h6>
                    <div class="notes-list">
                        ${notes.map(note => `<div class="note-item ${note.type || 'info'}">${note.message}</div>`).join('')}
                    </div>
                `;

                // Insert after verification header
                verificationHeader.insertAdjacentElement('afterend', notesSection);
            }
        }
    }
}

// Fetch trust score for a single shipment
function fetchTrustScore(shipmentId) {
    fetch(`api/trust_score.php?shipment_id=${shipmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.trust_score !== undefined) {
                updateTrustScoreDisplay(shipmentId, data.trust_score, data.status, data.document_count, data.message);
            } else {
                // Handle API error - show error status
                updateTrustScoreDisplay(shipmentId, 0, 'error', 0, data.message || 'Failed to calculate trust score');
                console.error('Trust score API error for shipment', shipmentId, ':', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching trust score for shipment', shipmentId, ':', error);
            // Handle network error - show error status
            updateTrustScoreDisplay(shipmentId, 0, 'error', 0, 'Network error - unable to calculate trust score');
        });
}

// Update the trust score display for a shipment
function updateTrustScoreDisplay(shipmentId, trustScore, status = 'success', documentCount = 0, message = '') {
    const row = document.querySelector(`tr[data-shipment-id="${shipmentId}"]`);
    if (row) {
        const trustBar = row.querySelector('.trust-bar');
        const progressDiv = trustBar.querySelector('.progress');
        const trustText = trustBar.querySelector('.trust-text');
        const statusDiv = row.querySelector(`#status-${shipmentId}`);

        // Update progress bar width
        progressDiv.style.width = `${trustScore}%`;

        // Update color based on score
        if (trustScore >= 67) {
            progressDiv.style.background = '#28a745'; // Green
        } else if (trustScore >= 34) {
            progressDiv.style.background = '#ffc107'; // Yellow
        } else {
            progressDiv.style.background = '#6c757d'; // Grey
        }

        // Update text
        trustText.textContent = `${trustScore}%`;

        // Update status badge
        if (statusDiv) {
            let statusHtml = '';
            if (status === 'success') {
                statusHtml = `<span class="status-badge green">Verified (${documentCount} docs)</span>`;
            } else if (status === 'error') {
                statusHtml = `<span class="status-badge error">Error</span>`;
            } else {
                statusHtml = `<span class="status-badge unknown">Calculating...</span>`;
            }
            statusDiv.innerHTML = statusHtml;
        }
    }
}

// Initialize when tab loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Load real trust scores for all shipments
    loadTrustScores();

    // Auto-refresh every 60 seconds
    setInterval(refreshDrafts, 60000);

    // Initialize upload event listeners
    initializeUploadListeners();
});

// Initialize upload event listeners
function initializeUploadListeners() {
    // Add click listeners to action buttons
    document.addEventListener('click', function(e) {
        const button = e.target.closest('button[data-action]');
        if (button) {
            const action = button.getAttribute('data-action');
            const docType = button.getAttribute('data-doc-type');
            const shipmentId = button.getAttribute('data-shipment-id');

            if (action === 'upload' && docType) {
                window.triggerFileUpload(docType);
            } else if (action === 'remove' && docType && shipmentId) {
                removeDocument(shipmentId, docType);
            }
        }
    });

    // Add change listeners to file inputs
    document.addEventListener('change', function(e) {
        if (e.target.matches('input[type="file"][data-doc-type]')) {
            const input = e.target;
            const docType = input.getAttribute('data-doc-type');
            const shipmentId = input.getAttribute('data-shipment-id');
            if (docType && shipmentId) {
                window.handleFileUpload(shipmentId, docType, input);
            }
        }
    });
}

// Refresh drafts
function refreshDrafts() {
    showLoadingState();
    location.reload(); // Simple refresh for now
}

// Make functions globally available
window.refreshDrafts = refreshDrafts;
window.viewShipmentDetails = viewShipmentDetails;
window.resumePosting = resumePosting;
window.deleteDraft = deleteDraft;
window.viewAnalytics = viewAnalytics;

// View shipment details (placeholder)
function viewShipmentDetails(shipmentId) {
    // Placeholder - implement shipment details view
    console.log('View shipment details:', shipmentId);
    dashboardModalSystem.showInfoModal('Shipment details view for ' + shipmentId + ' will be implemented');
}

// Resume posting (redirect to post_load tab)
function resumePosting(shipmentId) {
    loadTabContent('post_load');
}

// Delete draft
function deleteDraft(shipmentId) {
    if (confirm('Are you sure you want to delete this draft?')) {
        performAction('delete', shipmentId);
    }
}

// Manage documents (open off-canvas drawer)
window.manageDocuments = function(shipmentId) {
    // Load documents for this shipment
    fetchDocuments(shipmentId);
    const drawer = new bootstrap.Offcanvas(document.getElementById('documentDrawer'));
    drawer.show();
};

// View analytics (placeholder)
function viewAnalytics(shipmentId) {
    console.log('View analytics for shipment:', shipmentId);
    dashboardModalSystem.showInfoModal('Analytics view for shipment ' + shipmentId + ' will be implemented');
}

// Withdraw load
window.withdrawLoad = function(shipmentId) {
    if (confirm('Are you sure you want to withdraw this load?')) {
        performAction('withdraw', shipmentId);
    }
};

// Boost load
window.boostLoad = function(shipmentId) {
    if (confirm('Are you sure you want to boost this load? This will increase visibility to carriers.')) {
        performAction('boost', shipmentId);
    }
};

// Load verification UI for drawer
function fetchDocuments(shipmentId) {
    document.getElementById('documentDrawerBody').innerHTML = '<p>Loading verification...</p>';

    fetch(`?action=fetch_documents&shipment_id=${shipmentId}`)
        .then(response => response.json())
        .then(documents => {
            loadVerificationUI(shipmentId, documents);
        })
        .catch(error => {
            console.error('Error fetching documents:', error);
            document.getElementById('documentDrawerBody').innerHTML = '<p>Error loading verification</p>';
        });
}



// Get document label for display
function getDocLabel(docType) {
    const labels = {
        'Invoice': 'Commercial Invoice',
        'Warehouse Release': 'Warehouse Release',
        'SAD': 'Packing List/SAD',
        'Insurance': 'Cargo Insurance'
    };
    return labels[docType] || docType;
}

// Upload document (legacy function - now triggers file input)
window.uploadDocument = function(shipmentId, docType) {
    triggerFileUpload(docType);
};

// Remove document
function removeDocument(shipmentId, docType) {
    dashboardModalSystem.showConfirmationModal(
        `Are you sure you want to remove the ${getDocLabel(docType)}?`,
        `(() => {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: \`action=remove_document&shipment_id=${shipmentId}&doc_type=${docType}\`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message);
                    // Reload the verification UI
                    fetch(\`?action=fetch_documents&shipment_id=${shipmentId}\`)
                        .then(response => response.json())
                        .then(documents => {
                            loadVerificationUI(${shipmentId}, documents);
                        });
                } else {
                    showErrorMessage(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('An error occurred');
            });
        })()`
    );
}

// Helper function to get document icons
function getDocIcon(docType) {
    const icons = {
        'Invoice': 'fa-file-invoice',
        'Warehouse Release': 'fa-file-contract',
        'SAD': 'fa-file-signature',
        'Insurance': 'fa-shield-alt'
    };
    return icons[docType] || 'fa-file';
}

// Mark as verified
function markAsVerified(shipmentId) {
    performAction('verify', shipmentId);
    const drawer = bootstrap.Offcanvas.getInstance(document.getElementById('documentDrawer'));
    drawer.hide();
}

// Perform AJAX action
function performAction(action, shipmentId) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&shipment_id=${shipmentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            refreshDrafts();
        } else {
            showErrorMessage(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred');
    });
}

// Utility functions
function showLoadingState() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('drafts-table').style.display = 'none';
}

function hideLoadingState() {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('drafts-table').style.display = 'table';
}

function showSuccessMessage(message) {
    // Use SpotYourCargo modal popup instead of browser alert
    dashboardModalSystem.showSuccessModal(message);
}

function showErrorMessage(message) {
    // Use SpotYourCargo modal popup instead of browser alert
    dashboardModalSystem.showErrorModal(message);
}
</script>

<style>
/* Status Badges */
.status-badge.grey {
    background: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.amber {
    background: #ffc107;
    color: #212529;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Verified Check */
.verified-check {
    color: #28a745;
    font-size: 16px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.continue-btn {
    background: var(--primary-blue);
    color: white;
}

.continue-btn:hover {
    background: var(--secondary-blue);
}

.verify-btn {
    background: #28a745;
    color: white;
}

.verify-btn:hover {
    background: #1e7e34;
}

.verify-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

/* Shipment ID Button */
.shipment-id-btn {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-weight: 600;
    text-decoration: underline;
    padding: 0;
}

.shipment-id-btn:hover {
    color: #0056b3;
}

/* Table Styles */
.table-container {
    background: var(--card-bg, #fff);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--secondary-bg, #f8f9fa);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary, #333);
    border-bottom: 2px solid var(--border-color, #dee2e6);
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    vertical-align: middle;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #666);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: var(--primary-blue);
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--text-primary, #333);
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #666);
}

.loading-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: var(--primary-blue);
}

/* Trust Score Progress Bar */
.trust-bar {
    position: relative;
    width: 100px;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.trust-bar .progress {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}

.trust-text {
    position: absolute;
    font-size: 10px;
    font-weight: 600;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Market Heat */
.market-heat {
    font-size: 12px;
    color: #666;
}

.market-heat i {
    margin-right: 2px;
}

/* Cargo Protection */
.cargo-protection {
    display: flex;
    gap: 5px;
}

.cargo-protection i {
    font-size: 16px;
}

/* Pulsing Status Badge */
.status-badge.pulsing {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Green Status Badge */
.status-badge.green {
    background: #28a745;
    color: white;
}

/* Error Status Badge */
.status-badge.error {
    background: #dc3545;
    color: white;
}

/* Unknown Status Badge */
.status-badge.unknown {
    background: #6c757d;
    color: white;
}

/* Action Button Variants - SpotYourCargo Brand Theme */
.resume-btn {
    background: var(--primary-blue, #003366);
    color: white;
}

.resume-btn:hover {
    background: var(--secondary-blue, #1E4D8F);
}

.delete-btn {
    background: #dc3545;
    color: white;
}

.delete-btn:hover {
    background: #c82333;
}

.manage-btn {
    background: var(--primary-yellow, #FFD700);
    color: var(--primary-blue, #003366);
    font-weight: 600;
}

.manage-btn:hover {
    background: #E6C200;
}

.analytics-btn {
    background: var(--secondary-blue, #1E4D8F);
    color: white;
}

.analytics-btn:hover {
    background: #0F3460;
}

.withdraw-btn {
    background: #6c757d;
    color: white;
}

.withdraw-btn:hover {
    background: #545b62;
}

.boost-btn {
    background: var(--primary-yellow, #FFD700);
    color: var(--primary-blue, #003366);
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(255, 215, 0, 0.3);
}

.boost-btn:hover {
    background: #E6C200;
    box-shadow: 0 4px 8px rgba(255, 215, 0, 0.4);
}

/* Off-canvas Document Drawer */
.offcanvas {
    width: 400px !important;
}

.document-list {
    margin-bottom: 20px;
}

.document-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 10px;
    background: #f8f9fa;
}

.document-item i {
    margin-right: 10px;
    color: #6c757d;
}

/* Verification UI Styles */
.verification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.progress-ring-container {
    text-align: center;
}

.progress-ring {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 10px;
}

.progress-ring-circle {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: conic-gradient(
        #28a745 calc(var(--progress) * 1%),
        #e9ecef calc(var(--progress) * 1%)
    );
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress-ring-circle::before {
    content: '';
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: white;
    position: absolute;
}

.progress-text {
    position: relative;
    z-index: 1;
    font-weight: 600;
    color: #28a745;
    font-size: 14px;
}

.progress-label {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.trust-score-display {
    text-align: center;
}

.trust-score-value {
    font-size: 24px;
    font-weight: 700;
    color: #003366;
    display: block;
}

.trust-score-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.document-slots {
    margin-bottom: 20px;
}

.document-slot {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.document-slot.uploaded {
    border-color: #28a745;
    background: linear-gradient(135deg, #f8fff8 0%, #f0f8f0 100%);
}

.document-slot.missing.required {
    border-color: #ffc107;
    background: linear-gradient(135deg, #fffef8 0%, #fefcf0 100%);
}

.document-slot.missing.optional {
    border-color: #6c757d;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.document-slot:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.slot-header {
    display: flex;
    align-items: center;
    padding: 15px;
    background: rgba(255,255,255,0.8);
}

.slot-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 18px;
}

.document-slot.uploaded .slot-icon {
    background: #28a745;
    color: white;
}

.document-slot.missing .slot-icon {
    background: #6c757d;
    color: white;
}

.slot-info {
    flex: 1;
}

.slot-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
    font-size: 14px;
}

.slot-status {
    font-size: 12px;
}

.status-uploaded {
    color: #28a745;
    font-weight: 500;
}

.status-missing {
    color: #6c757d;
    font-weight: 500;
}

.slot-actions {
    margin-left: 15px;
}

.upload-area {
    padding: 20px;
    text-align: center;
    cursor: pointer;
    background: rgba(255,255,255,0.6);
    border-top: 1px solid rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.upload-area:hover {
    background: rgba(0,123,255,0.1);
}

.upload-placeholder i {
    font-size: 24px;
    color: #6c757d;
    margin-bottom: 8px;
    display: block;
}

.upload-placeholder div {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
}

.upload-placeholder small {
    color: #666;
    font-size: 11px;
}

.verification-actions {
    margin-top: 20px;
}

.verification-actions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .data-table th, .data-table td {
        padding: 10px;
        font-size: 14px;
    }

    .action-buttons {
        flex-direction: column;
        gap: 3px;
    }

    .action-btn {
        padding: 4px 8px;
        font-size: 11px;
    }

    .trust-bar {
        width: 80px;
        height: 16px;
    }

    .trust-text {
        font-size: 9px;
    }

    .market-heat {
        font-size: 11px;
    }

    .cargo-protection i {
        font-size: 14px;
    }

    .offcanvas {
        width: 100% !important;
    }

    .verification-header {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }

    .progress-ring {
        width: 60px;
        height: 60px;
    }

    .progress-ring-circle::before {
        width: 50px;
        height: 50px;
    }

    .progress-text {
        font-size: 12px;
    }

    .trust-score-value {
        font-size: 20px;
    }

    .slot-header {
        padding: 12px;
    }

    .slot-icon {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }

    .upload-area {
        padding: 15px;
    }
}
</style>
