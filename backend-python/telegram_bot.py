import asyncio
import random
from pyrogram import Client, filters
from pyrogram.types import Message
from logger import setup_logger
from anti_flood import human_delay, safe_execute
from ai_gemini import GeminiAI
from database import fetch_campaigns_for_account, save_or_update_prospect

logger = setup_logger("TelegramBotModule")

class TelegramWorkerNode:
    def __init__(self, account_data):
        self.account_id = account_data['id']
        self.api_id = account_data['api_id']
        self.api_hash = account_data['api_hash']
        self.session_string = account_data['session_string']
        self.proxy = account_data.get('proxy_server')
        
        proxy_config = None
        if self.proxy and "://" in self.proxy:
            # Parse proxy format if provided (socks5://user:pass@host:port)
            parts = self.proxy.replace("://", ":").split(":")
            if len(parts) >= 4:
                proxy_config = {
                    "scheme": parts[0],
                    "hostname": parts[2],
                    "port": int(parts[3]),
                    "username": parts[4] if len(parts) > 4 else None,
                    "password": parts[5] if len(parts) > 5 else None
                }

        self.client = Client(
            name=f"session_{self.account_id}",
            api_id=self.api_id,
            api_hash=self.api_hash,
            session_string=self.session_string,
            proxy=proxy_config,
            in_memory=True
        )
        self.ai = GeminiAI()

    async def start(self):
        @self.client.on_message(filters.group & ~filters.bot)
        async def handle_message(client: Client, message: Message):
            await self.process_group_message(client, message)

        async with self.client:
            logger.info(f"Telegram client started for Account ID #{self.account_id}")
            await asyncio.Event().wait()

    async def process_group_message(self, client: Client, message: Message):
        if not message.from_user or message.from_user.is_self:
            return

        chat_id = message.chat.id
        user_id = message.from_user.id
        username = message.from_user.username
        full_name = f"{message.from_user.first_name or ''} {message.from_user.last_name or ''}".strip()
        text = message.text or message.caption or ""

        if not text:
            return

        # 1. Check if user is an administrator of the group
        try:
            member = await client.get_chat_member(chat_id, user_id)
            if member.status in ["administrator", "creator"]:
                return # Skip group admins
        except Exception:
            pass

        # 2. Fetch campaigns for this account
        campaigns = fetch_campaigns_for_account(self.account_id)
        if not campaigns:
            return

        for camp in campaigns:
            target_interests = camp['interests']
            tone_scale = camp['tone_scale']
            arbitrage_mode = camp['arbitrage_mode']

            # 3. Evaluate prospect interest using Gemini AI
            eval_result = self.ai.evaluate_prospect_interest(text, target_interests)
            
            score = 50
            summary = "Engaged in discussion."
            try:
                for line in eval_result.split("\n"):
                    if "Score:" in line:
                        score = int(''.join(filter(str.isdigit, line)))
                    elif "Summary:" in line:
                        summary = line.replace("Summary:", "").strip()
            except Exception:
                pass

            # 4. Save prospect data
            save_or_update_prospect(self.account_id, str(chat_id), str(user_id), username, full_name, score, summary)

            # 5. Engage if score is high (>= 70)
            if score >= 70:
                await human_delay(35, 95)
                reply_text = self.ai.generate_chat_response(text, text, tone_scale, target_interests, arbitrage_mode)

                async def send_reply():
                    await client.send_message(chat_id, reply_text, reply_to_message_id=message.id)

                await safe_execute(send_reply)
                logger.info(f"Engaged user @{username} in chat {chat_id} with score {score}.")
