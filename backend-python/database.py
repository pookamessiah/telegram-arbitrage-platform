import pymysql
import logging
from config import Config

def get_db_connection():
    """Returns a new MySQL database connection using PDO/PyMySQL DictCursor."""
    return pymysql.connect(
        host=Config.DB_HOST,
        user=Config.DB_USER,
        password=Config.DB_PASS,
        database=Config.DB_NAME,
        port=Config.DB_PORT,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

def fetch_active_accounts():
    """Retrieves all active Telegram accounts configured with a session string."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT * FROM telegram_accounts 
                WHERE status = 'active' AND session_string IS NOT NULL AND session_string != ''
            """)
            return cursor.fetchall()
    finally:
        conn.close()

def fetch_campaigns_for_account(account_id):
    """Retrieves all active group campaigns and their behavior templates for a given Telegram account."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT cg.*, t.tone_scale, t.interests, t.endpoint_type, t.endpoint_value, t.arbitrage_mode
                FROM campaign_groups cg
                JOIN templates t ON cg.template_id = t.id
                WHERE cg.telegram_account_id = %s AND cg.is_active = 1
            """, (account_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def save_or_update_prospect(account_id, group_identifier, tg_id, username, full_name, score, summary):
    """Inserts a newly discovered prospect or updates their existing record in the database."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO prospects (
                    telegram_account_id, group_identifier, prospect_tg_id, 
                    prospect_username, prospect_name, interest_score, 
                    discussion_summary, status
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, 'discovered')
                ON DUPLICATE KEY UPDATE 
                    interest_score = VALUES(interest_score),
                    discussion_summary = VALUES(discussion_summary),
                    updated_at = CURRENT_TIMESTAMP
            """, (account_id, str(group_identifier), str(tg_id), username, full_name, score, summary))
    finally:
        conn.close()
