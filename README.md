# Cantonese Coach (PHP + Bootstrap + SQLite + Qwen APIs)

A mobile-responsive web app for Cantonese speaking practice:
- Record your voice in Cantonese
- Transcribe with Qwen ASR
- Generate tutor reply with Qwen chat model
- Optionally include a short English explanation
- Show Yale romanization for Cantonese Chinese text
- Convert Cantonese reply to speech with Qwen TTS
- Save recent turns in SQLite

## 1) Configure API

1. Copy `config.example.php` to `config.php`
2. Set `dashscope_api_key`
3. Optionally adjust model IDs, ASR language (`yue`), and voice settings

## 2) Run locally

```bash
php -S 127.0.0.1:8080
```

Open `http://127.0.0.1:8080`.

## 3) Deploy on InfinityFree

1. Upload all files to your site root (`htdocs`)
2. Ensure `config.php` exists on server with your API key
3. Ensure `data/` is writable so SQLite file can be created
4. Visit your domain and allow microphone permission
5. If you use this repo's GitHub Action deploy job, `config.php` is excluded from delete-sync by default, so it stays on server.

## Notes

- This implementation uses short recorded audio upload (not real-time websocket streaming) for compatibility with shared hosting + PHP.
- For Cantonese TTS, prefer `tts_voice` = `Rocky` or `Kiki` in `config.php`.
- For stronger Cantonese control, use `tts_model` = `qwen3-tts-instruct-flash` with `tts_instructions`.
- If your account/region has different model IDs or voice names, update `config.php`.
- If Cantonese language code is rejected, try `zh` in `asr_language`.
