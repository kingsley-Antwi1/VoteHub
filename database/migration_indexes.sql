-- Add missing indexes for performance
ALTER TABLE elections ADD INDEX idx_elections_institution_id (institution_id);
ALTER TABLE elections ADD INDEX idx_elections_status (status);
ALTER TABLE positions ADD INDEX idx_positions_election_id (election_id);
ALTER TABLE candidates ADD INDEX idx_candidates_position_id (position_id);
ALTER TABLE votes ADD INDEX idx_votes_election_id (election_id);
ALTER TABLE votes ADD INDEX idx_votes_voter_id (voter_id);
ALTER TABLE votes ADD INDEX idx_votes_candidate_id (candidate_id);
ALTER TABLE votes ADD INDEX idx_votes_position_id (position_id);
ALTER TABLE payments ADD INDEX idx_payments_institution_id (institution_id);
ALTER TABLE payments ADD INDEX idx_payments_status (status);
ALTER TABLE subscriptions ADD INDEX idx_subscriptions_institution_id (institution_id);
ALTER TABLE subscriptions ADD INDEX idx_subscriptions_status (status);
ALTER TABLE otp_codes ADD INDEX idx_otp_voter_code (voter_id, code, expires_at);
