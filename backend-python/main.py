import asyncio
import logging
import os
import pymysql
from datetime import datetime
from pyrogram import Client, filters
from pyrogram.types import Message
from config import Config
from anti_flood import human_delay, safe_execute
from ai_gemini import GeminiAI

logging.basicConfig(level=logging.INFO, format="%(asctime)s - [%(levelname)s] - %(message)s")

def get_db_connection():
    return pymysql.connect(
        host=Config.DB_HOST,
        user=Config.DB_USER,
        password=Config.DB_PASS,
        database=Config.DB_NAME,
        port=Config.DB_PORT,
        cursorclass=pymysql.cursors.DictCursor
    )

async def process_incoming_message(client: Client, message: Message, account_id: int):
    """
    Monitors incoming group messages, checks admin/bot safety, evaluates prospect interest,
    and triggers AI-powered conversation handling.
    """
    if not message.from_user or message.from_user.is_self:
        return

    chat_id = message.chat.id
    user_id = message.from_user.id
    username = message.from_user.username
    full_name = f"{message.from_user.first_name or ''} {message.from_user.last_name or ''}".strip()
    text = message.text or message.caption or ""

    if not text:
        return

    db = get_db_connection()
    try:
        with db.cursor() as cursor:
            # 1. Check if user is a group administrator (avoid texting admins)
            try:
                member = await client.get_chat_member(chat_id, user_id)
                if member.status in ["administrator", "creator"]:
                    logging.info(f"Skipping user @{username} as they are an admin of chat {chat_id}.")
                    return
            except Exception:
                pass # If unable to fetch member status, proceed cautiously

            # 2. Fetch campaign configuration for this account and group
            cursor.execute("""
                SELECT cg.*, t.tone_scale, t.interests, t.endpoint_type, t.endpoint_value, t.arbitrage_mode
                FROM campaign_groups cg
                JOIN templates t ON cg.template_id = t.id
                WHERE cg.telegram_account_id = %s AND cg.is_active = 1
            """, (account_id,))
            campaigns = cursor.fetchall()

            for camp in campaigns:
                target_interests = camp['interests']
                tone_scale = camp['tone_scale']

                # 3. Evaluate prospect interest using Gemini AI
                ai = GeminiAI()
                eval_result = ai.evaluate_prospect_interest(text, target_interests)
                
                # Simple parsing of AI evaluation output
                score = 50 # Default fallback score
                summary = "Engaged in discussion."
                try:
                    for line in eval_result.split("\n"):
                        if "Score:" in line:
                            score = int(''.filter(str.isdigit, line))
                        elif "Summary:" in line:
                            summary = line.replace("Summary:", "").strip()
                except Exception:
                    pass

                # 4. Save or update prospect record in database
                cursor.execute("""
                    INSERT INTO prospects (telegram_account_id, group_identifier, prospect_tg_id, prospect_username, prospect_name, interest_score, discussion_summary, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, 'discovered')
                    ON DUPLICATE KEY UPDATE 
                        interest_score = VALUES(interest_score),
                        discussion_summary = VALUES(discussion_summary),
                        updated_at = CURRENT_TIMESTAMP
                """, (account_id, str(chat_id), str(user_id), username, full_name, score, summary))
                db.commit()

                # 5. If interest score is high enough, engage in conversation
                if score >= 70:
                    await human_delay(30, 90) # Pacing to avoid bans
                    reply_text = ai.generate_chat_response(text, text, tone_scale, target_interests)
                    
                    async def send_reply():
                        await client.send_message(chat_id, reply_text, reply_to_message_id=message.id)

                    await safe_execute(send_reply)
                    logging.info(f"Engaged prospect @{username} in chat {chat_id} with score {score}.")

    except Exception as e:
        logging.error(f"Error processing message: {e}")
    finally:
        db.close()

async def run_telegram_account(account):
    """
    Initializes a Pyrogram client for a specific user Telegram account and attaches event handlers.
    """
    account_id = account['id']
    api_id = account['api_id']
    api_hash = account['api_hash']
    session_string = account['session_string']

    if not session_string:
        logging.warning(f"Account ID {account_id} has no session string. Skipping.")
        return

    # Initialize Pyrogram client (using session string for persistent login)
    app = Client(
        name=f"session_{account_id}",
        api_id=api_id,
        api_hash=api_hash,
        session_string=session_string,
        in_memory=True
    )

    @app.on_message(filters.group & ~filters.bot)
    async def handle_group_message(client, message):
        await process_incoming_message(client, message, account_id)

    async with app:
        logging.info(f"Telegram client started successfully for Account ID: {account_id}")
        await asyncio.Event().wait() # Keep running indefinitely

async def main():
    """
    Main background loop checking active accounts and scheduling workers.
    """
    logging.info("Starting Telegram Arbitrage Automation Worker...")
    while True:
        db = get_db_connection()
        try:
            with db.cursor() as cursor:
                cursor.execute("SELECT * FROM telegram_accounts WHERE status = 'active' AND session_string IS NOT NULL")
                accounts = cursor.fetchall()
            
            if accounts:
                # Run all active Telegram accounts concurrently
                await asyncio.gather(*(run_telegram_account(acc) for acc in accounts), return_exceptions=True)
            else:
                logging.info("No active Telegram accounts found in database. Retrying in 60 seconds...")
        except Exception as e:
            logging.error(f"Main loop error: {e}")
        finally:
            db.close()

        await asyncio.sleep(60)

if __name__ == "__main__":
    asyncio.run(main())
