import sys
import json
import asyncio
from pyrogram import Client
from pyrogram.errors import SessionPasswordNeeded

async def handle_action():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No action specified."}))
        return

    action = sys.argv[1]

    if action == "send_code":
        # Args: api_id, api_hash, phone_number
        if len(sys.argv) < 5:
            print(json.dumps({"success": False, "error": "Missing arguments for send_code."}))
            return
        
        api_id = int(sys.argv[2])
        api_hash = sys.argv[3]
        phone_number = sys.argv[4]

        # Use an in-memory or temporary session name
        app = Client(f"session_{phone_number}", api_id=api_id, api_hash=api_hash, in_memory=True)
        try:
            await app.connect()
            sent_code = await app.send_code(phone_number)
            print(json.dumps({
                "success": True,
                "phone_code_hash": sent_code.phone_code_hash
            }))
        except Exception as e:
            print(json.dumps({"success": False, "error": str(e)}))
        finally:
            if app.is_connected:
                await app.disconnect()

    elif action == "verify_code":
        # Args: api_id, api_hash, phone_number, phone_code_hash, code, [password]
        if len(sys.argv) < 7:
            print(json.dumps({"success": False, "error": "Missing arguments for verify_code."}))
            return

        api_id = int(sys.argv[2])
        api_hash = sys.argv[3]
        phone_number = sys.argv[4]
        phone_code_hash = sys.argv[5]
        code = sys.argv[6]
        password = sys.argv[7] if len(sys.argv) > 8 and sys.argv[7] else None

        app = Client(f"session_{phone_number}", api_id=api_id, api_hash=api_hash, in_memory=True)
        try:
            await app.connect()
            try:
                await app.sign_in(phone_number, phone_code_hash, code)
            except SessionPasswordNeeded:
                if password:
                    await app.check_password(password)
                else:
                    print(json.dumps({"success": False, "error": "2FA_REQUIRED"}))
                    return

            session_string = await app.export_session_string()
            print(json.dumps({
                "success": True,
                "session_string": session_string
            }))
        except Exception as e:
            print(json.dumps({"success": False, "error": str(e)}))
        finally:
            if app.is_connected:
                await app.disconnect()

if __name__ == "__main__":
    asyncio.run(handle_action())
