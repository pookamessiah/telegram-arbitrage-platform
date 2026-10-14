import logging
import sys

def setup_logger(name="TelegramWorker"):
    """Sets up and returns a centralized logger instance."""
    logger = logging.getLogger(name)
    if not logger.handlers:
        logger.setLevel(logging.INFO)
        handler = logging.StreamHandler(sys.stdout)
        formatter = logging.Formatter('%(asctime)s - [%(levelname)s] - %(name)s - %(message)s')
        handler.setFormatter(formatter)
        logger.addHandler(handler)
    return logger
