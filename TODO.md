### 1. Fix DocumentVerificationService.php key mismatch
- [x] Change `$trustScore['score']` to `$trustScore['trust_score']` in processDocument method

### 2. Create new API endpoint for trust scores
- [x] Create `/api/get_trust_score.php` that calls TrustScorer for shipment documents
