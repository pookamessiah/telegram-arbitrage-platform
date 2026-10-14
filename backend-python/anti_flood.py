import asyncio
import random
import logging

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

async def human_delay(min_sec=45, max_sec=120):
    """
    Introduces a randomized pause between actions to emulate genuine human behavior.
    """
    delay = random.uniform(min_sec, max_sec)
    logging.info(f"Human Pacing Pause: Waiting {delay:.2f} seconds...")
    await asyncio.sleep(delay)

async def safe_execute(action_func, *args, **kwargs):
    """
    Executes a Pyrogram Telegram action with automated FloodWait handling and jitter buffers.
    """
    try:
        return await action_func(*args, **kwargs)
    except Exception as e:
        error_msg = str(e)
        if "FloodWait" in error_msg or "FLOOD_WAIT" in error_msg:
            # Extract required sleep time or default to a safe 15 minutes
            wait_seconds = getattr(e, "value", 900)
            jitter = random.randint(30, 180) # Adds dynamic variation to the wait time
            total_sleep = wait_seconds + jitter
            logging.warning(f"FloodWait encountered! Pausing execution for {total_sleep} seconds...")
            await asyncio.sleep(total_sleep)
            return await action_func(*args, **kwargs)
        else:
            logging.error(f"Execution Exception: {error_msg}")
            raise e
