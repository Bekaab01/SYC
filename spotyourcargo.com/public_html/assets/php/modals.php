<?php
// Reusable modals for Terms of Service and Privacy Policy
?>

<!-- Modal Styles -->
<style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        z-index: 2000;
        opacity: 0;
        transition: opacity 0.3s ease;
        backdrop-filter: blur(5px);
    }

    .modal.active {
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 1;
    }

    .modal-content {
        background-color: var(--white);
        border-radius: 15px;
        width: 90%;
        max-width: 700px;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        transform: translateY(20px);
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .modal.active .modal-content {
        transform: translateY(0);
    }

    .modal-header {
        padding: 20px 25px;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
        color: var(--white);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--white);
        font-size: 28px;
        cursor: pointer;
        line-height: 1;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.3s;
    }

    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-body h4 {
        color: var(--primary-blue);
        margin: 20px 0 10px;
        font-size: 18px;
        font-weight: 600;
    }

    .modal-body h4:first-child {
        margin-top: 0;
    }

    .modal-body p {
        margin-bottom: 15px;
        line-height: 1.6;
        color: var(--dark-gray);
    }

    .modal-body ul {
        margin: 10px 0 15px 20px;
    }

    .modal-body li {
        margin-bottom: 8px;
        line-height: 1.5;
    }

    /* Responsive Design for Modals */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            max-height: 90vh;
        }

        .modal-header {
            padding: 15px 20px;
        }

        .modal-body {
            padding: 20px;
        }
    }

    @media (max-width: 480px) {
        .modal-header h3 {
            font-size: 20px;
        }

        .modal-body {
            padding: 15px;
        }
    }
</style>

<!-- Terms of Service Modal -->
<div class="modal" id="termsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Terms of Service</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <h4>1. Acceptance of Terms</h4>
            <p>By accessing and using the SYC platform, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>

            <h4>2. User Responsibilities</h4>
            <p>Users are responsible for maintaining the confidentiality of their account and password and for restricting access to their computer. You agree to accept responsibility for all activities that occur under your account or password.</p>

            <h4>3. Service Modifications</h4>
            <p>SYC reserves the right to modify or discontinue, temporarily or permanently, the service with or without notice. You agree that SYC shall not be liable to you or to any third party for any modification, suspension or discontinuance of the service.</p>

            <h4>4. User Conduct</h4>
            <p>You agree not to use the service to:</p>
            <ul>
                <li>Post false or misleading information</li>
                <li>Violate any applicable laws or regulations</li>
                <li>Infringe upon the rights of others</li>
                <li>Transmit harmful or malicious code</li>
                <li>Attempt to gain unauthorized access to our systems</li>
            </ul>

            <h4>5. Limitation of Liability</h4>
            <p>SYC shall not be liable for any indirect, incidental, special, consequential or punitive damages resulting from your use of the service. In no event shall SYC's total liability to you for all damages exceed the amount paid by you for the service.</p>

            <h4>6. Governing Law</h4>
            <p>These terms shall be interpreted and governed by the laws of Ethiopia, without regard to conflict of law provisions. Any disputes arising from these terms shall be subject to the exclusive jurisdiction of the courts of Ethiopia.</p>

            <h4>7. Contact Information</h4>
            <p>If you have any questions about these Terms of Service, please contact us at support@syc.com.</p>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal" id="privacyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Privacy Policy</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <h4>1. Information We Collect</h4>
            <p>We collect information you provide directly to us, such as when you create an account, use our services, or communicate with us. This includes personal information like your name, email address, phone number, and business details.</p>

            <h4>2. How We Use Your Information</h4>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide, maintain, and improve our services</li>
                <li>Send you technical notices and support messages</li>
                <li>Respond to your comments and questions</li>
                <li>Process transactions and send related information</li>
                <li>Send you marketing communications (with your consent)</li>
            </ul>

            <h4>3. Information Sharing</h4>
            <p>We do not sell, trade, or otherwise transfer your personally identifiable information to outside parties without your consent, except as described in this policy. We may share your information with trusted third parties who assist us in operating our website, conducting our business, or servicing you.</p>

            <h4>4. Data Security</h4>
            <p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet is 100% secure.</p>

            <h4>5. Cookies and Tracking</h4>
            <p>We use cookies and similar tracking technologies to enhance your experience on our platform. You can control cookie settings through your browser preferences.</p>

            <h4>6. Your Rights</h4>
            <p>You have the right to access, correct, or delete your personal information at any time through your account settings. You may also opt out of marketing communications at any time.</p>

            <h4>7. Changes to This Policy</h4>
            <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date.</p>

            <h4>8. Contact Us</h4>
            <p>If you have any questions about this Privacy Policy, please contact us at privacy@syc.com.</p>
        </div>
    </div>
</div>

<script>
    // Modal functionality
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Terms and Privacy modals
    document.addEventListener('DOMContentLoaded', function() {
        const termsLink = document.getElementById('termsLink');
        const privacyLink = document.getElementById('privacyLink');

        if (termsLink) {
            termsLink.addEventListener('click', function(e) {
                e.preventDefault();
                openModal('termsModal');
            });
        }

        if (privacyLink) {
            privacyLink.addEventListener('click', function(e) {
                e.preventDefault();
                openModal('privacyModal');
            });
        }

        // Close modals
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal.id);
            });
        });

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    });
</script>
