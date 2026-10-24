#!/usr/bin/env python3
import sys
import json
import asyncio
import traceback
from pyrogram import Client

async def main():
    try:
        raw_input = sys.stdin.read()
        if not raw_input:
            print(json.dumps({"status": "error", "message": "No input received from PHP."}))
            return

        input_data = json.loads(raw_input)
        action = input_data.get("action")
        
        # Safely validate and cast api_id to a strict integer
        raw_api_id = input_data.get("api_id")
        try:
            api_id = int(raw_api_id) if raw_api_id is not None else 0
        except (ValueError, TypeError):
            print(json.dumps({"status": "error", "message": f"Invalid API ID format received: {raw_api_id}. Must be an integer."}))
            return

        api_hash = str(input_data.get("api_hash", ""))
        phone_number = str(input_data.get("phone_number", ""))
        
        if action == "send_code":
            app = Client(
                name="temp_auth_session",
                api_id=api_id,
                api_hash=api_hash,
                in_memory=True,
                no_updates=True
            )
            await app.connect()
            sent_code = await app.send_code(phone_number)
            session_str = await app.export_session_string()
            await app.disconnect()
            
            print(json.dumps({
                "status": "success",
                "phone_code_hash": sent_code.phone_code_hash,
                "session_string": session_str
            }))
        
        elif action == "verify_code":
            phone_code_hash = input_data.get("phone_code_hash")
            code = input_data.get("code")
            session_string = input_data.get("session_string", "")
            password = input_data.get("password", None)
            
            app = Client(
                name="verify_session", 
                api_id=api_id, 
                api_hash=api_hash, 
                session_string=session_string if session_string else None,
                in_memory=True,
                no_updates=True
            )
            await app.connect()
            
            try:
                await app.sign_in(phone_number, phone_code_hash, code)
                final_session = await app.export_session_string()
                await app.disconnect()
                print(json.dumps({"status": "success", "session_string": final_session}))
            except Exception as sign_in_error:
                error_type = type(sign_in_error).__name__
                if error_type == "SessionPasswordNeeded":
                    if password:
                        await app.check_password(password)
                        final_session = await app.export_session_string()
                        await app.disconnect()
                        print(json.dumps({"status": "success", "session_string": final_session}))
                    else:
                        await app.disconnect()
                        print(json.dumps({"status": "password_required", "message": "Two-step verification password required."}))
                else:
                    await app.disconnect()
                    print(json.dumps({"status": "error", "message": f"{error_type}: {str(sign_in_error)}"}))
        else:
            print(json.dumps({"status": "error", "message": "Invalid action specified."}))
            
    except Exception as e:
        tb = traceback.format_exc()
        error_msg = f"{type(e).__name__}: {str(e)}"
        print(json.dumps({"status": "error", "message": error_msg}))

if __name__ == "__main__":
    asyncio.run(main())
