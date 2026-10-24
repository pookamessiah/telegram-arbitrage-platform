#!/usr/bin/env python3
import sys
import json
import asyncio
from pyrogram import Client
from pyrogram.errors import SessionPasswordNeeded, PhoneCodeInvalid, PhoneCodeExpired, FloodWait, ApiIdInvalid, PhoneNumberInvalid

async def main():
    try:
        input_data = json.loads(sys.stdin.read())
        action = input_data.get("action")
        api_id = int(input_data.get("api_id"))
        api_hash = input_data.get("api_hash")
        phone_number = input_data.get("phone_number")
        
        if action == "send_code":
            async with Client(":memory:", api_id=api_id, api_hash=api_hash, in_memory=True) as app:
                sent_code = await app.send_code(phone_number)
                session_str = await app.export_session_string()
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
            
            async with Client(
                "verify_session", 
                api_id=api_id, 
                api_hash=api_hash, 
                session_string=session_string if session_string else None,
                in_memory=True if not session_string else False
            ) as app:
                try:
                    await app.sign_in(phone_number, phone_code_hash, code)
                    final_session = await app.export_session_string()
                    print(json.dumps({"status": "success", "session_string": final_session}))
                except SessionPasswordNeeded:
                    if password:
                        await app.check_password(password)
                        final_session = await app.export_session_string()
                        print(json.dumps({"status": "success", "session_string": final_session}))
                    else:
                        print(json.dumps({"status": "password_required", "message": "Two-step verification password required."}))
        else:
            print(json.dumps({"status": "error", "message": "Invalid action specified."}))
            
    except Exception as e:
        # Return the exact exception class name and message for debugging
        error_msg = f"{type(e).__name__}: {str(e)}"
        print(json.dumps({"status": "error", "message": error_msg}))

if __name__ == "__main__":
    asyncio.run(main())
