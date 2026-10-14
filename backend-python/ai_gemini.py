import os
import google.generativeai as genai
from config import Config

# Configure Gemini API
genai.configure(api_key=Config.GEMINI_API_KEY)

class GeminiAI:
    def __init__(self):
        # Using Gemini Flash for fast, real-time chat evaluation and generation
        self.model = genai.GenerativeModel('gemini-2.5-flash')

    def generate_chat_response(self, group_context, peer_message, tone_scale, target_interests):
        """
        Generates a human-like response tailored to a specific tone scale (1-10) 
        and aligned with target interests.
        """
        tone_desc = "casual, friendly, and conversational" if tone_scale <= 5 else "professional, authoritative, and formal"
        
        prompt = f"""
        You are participating in a Telegram group chat. Your goal is to hold a natural, human-like conversation without sounding like a bot.
        
        Group Context / Discussion: {group_context}
        Latest Message to Reply To: "{peer_message}"
        Desired Communication Tone: Scale {tone_scale}/10 ({tone_desc}).
        Target Interests to subtly steer towards: {target_interests}
        
        Instructions:
        - Keep the response concise, engaging, and indistinguishable from a real human Telegram user.
        - Do not immediately pitch or spam links; build rapport first.
        - Reply in the natural language of the user's message.
        """
        
        response = self.model.generate_content(prompt)
        return response.text.strip()

    def evaluate_prospect_interest(self, conversation_history, target_interests):
        """
        Analyzes group chat messages to score a prospect's interest and summarize their discussion.
        """
        prompt = f"""
        Analyze the following conversation history for a specific user and evaluate their interest score relative to these target interests: {target_interests}.
        
        Conversation History:
        {conversation_history}
        
        Return your evaluation strictly in the following format:
        Score: [Integer from 0 to 100]
        Summary: [1-2 sentences summarizing their needs, pain points, and discussion topic]
        """
        
        response = self.model.generate_content(prompt)
        return response.text.strip()
