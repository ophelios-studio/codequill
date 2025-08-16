-- ##################################################################################################################
-- SESSION
-- ##################################################################################################################
CREATE TABLE session
(
    session_id VARCHAR(255) PRIMARY KEY,
    access INT NOT NULL, -- Last access time for the session
    data TEXT NOT NULL, -- Data of the session
    expire INT NOT NULL, -- Expiration time is last access + configured session lifetime
    ip_address VARCHAR(25) NULL DEFAULT NULL,
    user_agent JSONB NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);