CREATE TABLE repository_claim
(
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    github_id TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    wallet_address TEXT NOT NULL,
    user_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES pulsar.user_profile(id)
);
