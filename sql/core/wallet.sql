CREATE TABLE wallet
(
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    address TEXT UNIQUE NOT NULL,
    ens_name TEXT NULL DEFAULT NULL,
    ens_avatar TEXT NULL DEFAULT NULL,
    ens_data JSONB NULL DEFAULT NULL,
    user_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES pulsar.user_profile(id)
);

CREATE TABLE delegation
(
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    delegator_address TEXT NOT NULL,
    delegate_address TEXT NOT NULL,
    signature TEXT NOT NULL,
    nonce BIGINT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (delegator_address) REFERENCES wallet(address)
);

CREATE INDEX idx_delegations_delegator ON delegation(delegator_address);
CREATE INDEX idx_delegations_active_expired ON delegation(is_active, expires_at);
