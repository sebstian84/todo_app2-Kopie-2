## 2026-04-25 - [High] Secure Password Storage & Random Session Tokens
**Vulnerability:** Legacy plaintext password storage and predictable session tokens (username-based).
**Learning:** The application initially used simple string concatenation for tokens and stored passwords directly in an encrypted JSON file without hashing. While encryption protected the data at rest, any compromise of the encryption key or the active token would lead to full account takeover.
**Prevention:** Always use `password_hash()`/`password_verify()` for credentials and `random_bytes()` for session identifiers. Implement server-side session invalidation by tracking active tokens.
