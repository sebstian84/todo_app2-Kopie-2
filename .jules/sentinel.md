# Sentinel Security Journal

## 2026-04-26 - Secure Authentication Overhaul
**Vulnerability:** Predictable authentication tokens and plaintext password storage.
**Learning:** The application used a hardcoded base string concatenated with the username to generate "secure" tokens. This allowed an attacker knowing a username to easily forge a valid authentication token. Additionally, passwords were stored in plaintext within an encrypted file, but the encryption key was hardcoded, providing limited protection if the file was accessed.
**Prevention:** Use `bin2hex(random_bytes(32))` for cryptographically secure random token generation and persist these tokens. Implement `password_hash()` and `password_verify()` for all credential storage and verification. Use `hash_equals()` for constant-time comparison of security tokens to mitigate timing attacks.
