import os

class Config:
    DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
    DB_USER = os.getenv("DB_USER", "root")
    DB_PASS = os.getenv("DB_PASS", "")
    DB_NAME = os.getenv("DB_NAME", "telegram_platform")
    DB_PORT = int(os.getenv("DB_PORT", 3306))
    
    GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
    ENCRYPTION_KEY = os.getenv("ENCRYPTION_KEY", "default-secret-key-change-me")
