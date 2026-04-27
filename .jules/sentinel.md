## 2025-05-15 - Predictable Auth Tokens and Plaintext Passwords
**Vulnerability:** The application used a predictable authentication token (prefix + username) and stored passwords in plaintext (though encrypted at rest).
**Learning:** Predictable tokens allow easy impersonation if usernames are known. Plaintext passwords, even if stored in an encrypted file, are a major risk if the encryption key is compromised or the file is accessed in memory.
**Prevention:** Always use cryptographically secure random tokens (`random_bytes`) and industry-standard hashing algorithms (`password_hash` with Argon2 or bcrypt) for credentials. Implement constant-time comparison (`hash_equals`) for all security tokens.
