<?php
// Include database connection
include_once __DIR__ . '/../../../private/db.php';

// Handle testimonial submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $review = trim($_POST['review'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($name && $review && $rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("INSERT INTO testimonials (name, position, review, rating, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $position, $review, $rating]);
            $success_message = "Thank you for your review!";
        } catch (PDOException $e) {
            error_log("Error inserting testimonial: " . $e->getMessage());
            $error_message = "Sorry, there was an error submitting your review.";
        }
    } else {
        $error_message = "Please fill in all required fields correctly.";
    }
}

// Handle testimonial edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_testimonial'])) {
    $id = (int)($_POST['edit_id'] ?? 0);
    $name = trim($_POST['edit_name'] ?? '');
    $position = trim($_POST['edit_position'] ?? '');
    $review = trim($_POST['edit_review'] ?? '');
    $rating = (int)($_POST['edit_rating'] ?? 0);

    if ($id && $name && $review && $rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("UPDATE testimonials SET name = ?, position = ?, review = ?, rating = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $position, $review, $rating, $id]);
            $success_message = "Testimonial updated successfully!";
        } catch (PDOException $e) {
            error_log("Error updating testimonial: " . $e->getMessage());
            $error_message = "Sorry, there was an error updating the testimonial.";
        }
    } else {
        $error_message = "Please fill in all required fields correctly.";
    }
}

// Handle testimonial remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_testimonial'])) {
    $id = (int)($_POST['remove_id'] ?? 0);

    if ($id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "Testimonial removed successfully!";
        } catch (PDOException $e) {
            error_log("Error removing testimonial: " . $e->getMessage());
            $error_message = "Sorry, there was an error removing the testimonial.";
        }
    }
}

// Fetch testimonials from database
try {
    $stmt = $pdo->query("SELECT id, name, position, review, rating FROM testimonials ORDER BY created_at DESC LIMIT 6");
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching testimonials: " . $e->getMessage());
    $testimonials = []; // Fallback to empty array
}
?>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="container">
        <div class="section-title">
            <h2>What Our Clients Say</h2>
            <p>Hear from businesses that have transformed their logistics with SYC</p>
        </div>

        <div class="testimonials-container">
            <?php if (!empty($testimonials)): ?>
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-options">
                            <i class="fas fa-ellipsis-v options-icon"></i>
                            <div class="options-dropdown">
                                <button class="edit-btn" data-id="<?php echo $testimonial['id']; ?>" data-name="<?php echo htmlspecialchars($testimonial['name']); ?>" data-position="<?php echo htmlspecialchars($testimonial['position'] ?? ''); ?>" data-review="<?php echo htmlspecialchars($testimonial['review']); ?>" data-rating="<?php echo $testimonial['rating']; ?>">Edit</button>
                                <form method="post" class="remove-form" style="display: inline;">
                                    <input type="hidden" name="remove_id" value="<?php echo $testimonial['id']; ?>">
                                    <button type="submit" name="remove_testimonial" onclick="return confirm('Are you sure you want to remove this testimonial?')">Remove</button>
                                </form>
                            </div>
                        </div>
                        <div class="rating" data-rating="<?php echo $testimonial['rating']; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?php echo $i <= $testimonial['rating'] ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="testimonial-content">
                            <p><?php echo htmlspecialchars($testimonial['review']); ?></p>
                        </div>
                        <div class="testimonial-author">
                            <div class="author-info">
                                <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                                <p><?php echo htmlspecialchars($testimonial['position'] ?? 'Client'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-testimonials">
                    <p>Be the first to rate SYC!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Testimonial Submission Form -->
        <div class="testimonial-form">
            <h3>Share Your Experience</h3>
            <?php if (isset($success_message)): ?>
                <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position">
                </div>
                <div class="form-group">
                    <label for="rating">Rating *</label>
                    <div class="star-rating" id="star-rating">
                        <input type="hidden" id="rating" name="rating" required>
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="review">Review *</label>
                    <textarea id="review" name="review" rows="4" required></textarea>
                </div>
                <button type="submit" name="submit_testimonial" class="btn">Submit Review</button>
            </form>
        </div>
    </div>
</section>

<!-- Edit Testimonial Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Testimonial</h3>
        <form method="post">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="form-group">
                <label for="edit_name">Name *</label>
                <input type="text" id="edit_name" name="edit_name" required>
            </div>
            <div class="form-group">
                <label for="edit_position">Position</label>
                <input type="text" id="edit_position" name="edit_position">
            </div>
            <div class="form-group">
                <label for="edit_rating">Rating *</label>
                <div class="star-rating" id="edit-star-rating">
                    <input type="hidden" id="edit_rating" name="edit_rating" required>
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
            </div>
            <div class="form-group">
                <label for="edit_review">Review *</label>
                <textarea id="edit_review" name="edit_review" rows="4" required></textarea>
            </div>
            <button type="submit" name="edit_testimonial" class="btn">Update Review</button>
        </form>
    </div>
</div>

<style>
:root {
    --primary-blue: #003366;
    --primary-yellow: #FFD700;
    --secondary-blue: #1E4D8F;
    --light-gray: #F5F5F5;
    --dark-gray: #333333;
    --white: #FFFFFF;
    --text-dark: #1d1d1f;
    --text-light: #86868b;
    --card-bg: rgba(255, 255, 255, 0.8);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

html {
    scroll-behavior: smooth;
}

body {
    overflow-x: hidden;
    color: var(--dark-gray);
    background-color: #ffffff;
    line-height: 1.6;
    font-weight: 400;
}

/* General Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-title {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
}

.section-title h2 {
    font-size: 2.8rem;
    color: var(--primary-blue);
    margin-bottom: 20px;
    font-weight: 700;
    letter-spacing: -0.5px;
    position: relative;
    display: inline-block;
}

.section-title h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background-color: var(--primary-yellow);
}

.section-title p {
    font-size: 1.2rem;
    color: var(--dark-gray);
    max-width: 700px;
    margin: 0 auto;
    font-weight: 400;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    background-color: var(--primary-blue);
    color: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.btn:hover {
    background-color: var(--secondary-blue);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 51, 102, 0.3);
}

/* Testimonials Section */
.testimonials {
    padding: 80px 0;
    text-align: center;
    background-color: var(--white);
}

.testimonials-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.testimonial-card {
    background-color: var(--white);
    padding: 30px;
    border-radius: 15px;
    box-shadow: var(--card-shadow);
    position: relative;
    transition: var(--transition);
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.testimonial-card::before {
    content: '"';
    position: absolute;
    top: 20px;
    left: 20px;
    font-size: 60px;
    color: rgba(0, 51, 102, 0.1);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1;
}

.testimonial-content {
    position: relative;
    z-index: 1;
    margin-bottom: 20px;
}

.rating {
    margin-bottom: 15px;
}

.rating i {
    color: var(--primary-yellow);
    margin: 0 2px;
}

.rating .fas.fa-star {
    color: var(--primary-yellow);
}

.rating .far.fa-star {
    color: #ddd;
}

.testimonial-content p {
    font-size: 16px;
    line-height: 1.6;
    color: var(--dark-gray);
    font-style: italic;
}

.testimonial-author {
    display: flex;
    align-items: center;
    gap: 15px;
}

.author-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-yellow);
}

.author-info h4 {
    color: var(--primary-blue);
    margin-bottom: 5px;
    font-size: 18px;
}

.author-info p {
    color: #666;
    font-size: 14px;
}

.no-testimonials {
    text-align: center;
    padding: 50px;
    background-color: var(--white);
    border-radius: 15px;
    box-shadow: var(--card-shadow);
}

.no-testimonials p {
    font-size: 18px;
    color: var(--primary-blue);
    margin: 0;
}

.testimonial-form {
    margin-top: 60px;
    text-align: center;
}

.testimonial-form h3 {
    color: var(--primary-blue);
    margin-bottom: 20px;
    font-size: 28px;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--dark-gray);
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: var(--transition);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 2px rgba(0, 51, 102, 0.1);
}

.success-message {
    color: green;
    margin-bottom: 20px;
    font-weight: 500;
}

.error-message {
    color: red;
    margin-bottom: 20px;
    font-weight: 500;
}

.star-rating {
    display: flex;
    gap: 5px;
}

.star-rating i {
    font-size: 24px;
    color: #ddd;
    cursor: pointer;
    transition: var(--transition);
}

.star-rating i.active {
    color: var(--primary-yellow);
}

.star-rating i.hover {
    color: var(--primary-yellow);
}

/* Testimonial Options */
.testimonial-options {
    position: absolute;
    top: 10px;
    right: 10px;
    cursor: pointer;
}

.options-icon {
    font-size: 18px;
    color: var(--dark-gray);
    transition: var(--transition);
}

.options-icon:hover {
    color: var(--primary-blue);
}

.options-dropdown {
    display: none;
    position: absolute;
    top: 30px;
    right: 0;
    background-color: var(--white);
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: var(--card-shadow);
    z-index: 10;
    min-width: 100px;
}

.options-dropdown button {
    display: block;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    font-size: 14px;
    color: var(--dark-gray);
    transition: var(--transition);
}

.options-dropdown button:hover {
    background-color: var(--light-gray);
    color: var(--primary-blue);
}

.remove-form button {
    background: none;
    border: none;
    padding: 8px 12px;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-size: 14px;
    color: red;
    transition: var(--transition);
}

.remove-form button:hover {
    background-color: var(--light-gray);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--white);
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%;
    max-width: 500px;
    border-radius: 15px;
    box-shadow: var(--card-shadow);
    position: relative;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    top: 10px;
    right: 15px;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .testimonials-container {
        grid-template-columns: 1fr;
    }

    .testimonial-form h3 {
        font-size: 24px;
    }

    .form-group {
        max-width: 100%;
    }

    .section-title h2 {
        font-size: 2.2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Star rating for submission form
    const starContainer = document.getElementById('star-rating');
    const stars = starContainer.querySelectorAll('i');
    const ratingInput = document.getElementById('rating');

    function highlightStars(rating, className) {
        stars.forEach((star, index) => {
            const starRating = parseInt(star.getAttribute('data-rating'));
            if (starRating <= rating) {
                star.classList.add(className);
            } else {
                star.classList.remove(className);
            }
        });
    }

    function resetHoverStars() {
        const currentRating = parseInt(ratingInput.value) || 0;
        stars.forEach(star => star.classList.remove('hover'));
        highlightStars(currentRating, 'active');
    }

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            ratingInput.value = rating;
            highlightStars(rating, 'active');
        });

        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            highlightStars(rating, 'hover');
        });

        star.addEventListener('mouseout', function() {
            resetHoverStars();
        });
    });

    // Options dropdown toggle
    document.querySelectorAll('.testimonial-options').forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.querySelector('.options-dropdown');
            // Hide other dropdowns
            document.querySelectorAll('.options-dropdown').forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.options-dropdown').forEach(d => d.style.display = 'none');
    });

    // Edit modal
    const modal = document.getElementById('edit-modal');
    const closeBtn = document.querySelector('.close');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const position = this.getAttribute('data-position');
            const review = this.getAttribute('data-review');
            const rating = this.getAttribute('data-rating');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_review').value = review;
            document.getElementById('edit_rating').value = rating;

            // Set active stars for edit
            const editStars = document.querySelectorAll('#edit-star-rating i');
            const editRatingInput = document.getElementById('edit_rating');
            editStars.forEach((star, index) => {
                if (index < parseInt(rating)) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });

            modal.style.display = 'block';
        });
    });

    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Star rating for edit modal
    const editStarContainer = document.getElementById('edit-star-rating');
    const editStars = editStarContainer.querySelectorAll('i');
    const editRatingInput = document.getElementById('edit_rating');

    function highlightEditStars(rating, className) {
        editStars.forEach((star, index) => {
            const starRating = parseInt(star.getAttribute('data-rating'));
            if (starRating <= rating) {
                star.classList.add(className);
            } else {
                star.classList.remove(className);
            }
        });
    }

    function resetEditHoverStars() {
        const currentRating = parseInt(editRatingInput.value) || 0;
        editStars.forEach(star => star.classList.remove('hover'));
        highlightEditStars(currentRating, 'active');
    }

    editStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            editRatingInput.value = rating;
            highlightEditStars(rating, 'active');
        });

        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            highlightEditStars(rating, 'hover');
        });

        star.addEventListener('mouseout', function() {
            resetEditHoverStars();
        });
    });

    // Hover effect for testimonial display stars
    document.querySelectorAll('.rating[data-rating]').forEach(ratingEl => {
        const stars = ratingEl.querySelectorAll('i');
        const originalRating = parseInt(ratingEl.dataset.rating);
        
        stars.forEach((star, index) => {
            star.addEventListener('mouseover', () => {
                stars.forEach((s, i) => {
                    if (i <= index) {
                        s.style.color = 'var(--primary-yellow)';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
            
            star.addEventListener('mouseout', () => {
                stars.forEach((s, i) => {
                    if (i < originalRating) {
                        s.style.color = 'var(--primary-yellow)';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });
    });
});
</script>