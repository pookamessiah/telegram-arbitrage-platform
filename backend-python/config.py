import os

class Config:
    DB_HOST = os.getenv("DB_HOST", "iriguchi.proxy.rlwy.net")
    DB_USER = os.getenv("DB_USER", "root")
    DB_PASS = os.getenv("DB_PASS", "KQTStEeMXresazDMKuCKkWtPLxfeGGsL")
    DB_NAME = os.getenv("DB_NAME", "railway")
    DB_PORT = int(os.getenv("DB_PORT", 34558))
    
    GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
    ENCRYPTION_KEY = os.getenv("ENCRYPTION_KEY", "default-secret-key-change-me")
