#!/usr/bin/env python3
"""
Agentic Banking AI Assistant — concurrent HTTP sidecar that routes WhatsApp
conversations to the Cursor Agent CLI with per-phone session isolation.

Speed optimizations:
  - Pre-fetched DB context in prompt (eliminates tool-use round trips)
  - Concise prompt tuning (less output = less generation time)
  - Reduced timeout (120s vs 600s)
  - Timing logged per request

Architecture:
  - ThreadingHTTPServer handles multiple requests concurrently.
  - Per-phone threading locks serialize messages from the same guest,
    while different guests are processed in parallel.
  - Each request spawns a subprocess to the Agent CLI.

Listens on port 8101 (configurable via AI_PORT env var).

POST /ask body:
    {
        "prompt": "...",               # Required. The user message / full prompt.
        "system_prompt": "...",        # Optional. Prepended to prompt for role instructions.
        "max_chars": 3800,             # Optional. Appended as a length constraint hint.
        "phone_number": "+2557..."     # Optional. Session key for per-phone locking.
    }

GET /health  -> {"status": "ok", "active_sessions": N}
"""

import json
import os
import re
import subprocess
import sys
import threading
import time
from http.server import HTTPServer, BaseHTTPRequestHandler
from socketserver import ThreadingMixIn

PORT = int(os.environ.get("AI_PORT", 8101))
AGENT_BIN = os.environ.get("AGENT_CLI_PATH", "/Users/andrewmashamba/.local/bin/agent")
PROJECT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RULES_FILE = os.path.join(PROJECT_DIR, ".cursor", "rules", "whatsapp-banker.mdc")
# Align with Cursor Agent CLI / Claude 200k-token context: 200k tokens × ~4 chars/token ≈ 800k chars
# Overridable via AI_MAX_PROMPT_LEN (e.g. 800000). See docs/smart-context-and-prompting.md
MAX_PROMPT_LEN = int(os.environ.get("AI_MAX_PROMPT_LEN", 800_000))
# Max HTTP body size for POST /ask (must allow JSON of MAX_PROMPT_LEN; 2MB covers 800k chars + overhead)
MAX_BODY_BYTES = int(os.environ.get("AI_MAX_BODY_BYTES", 2_000_000))
TIMEOUT = 120  # Reduced — pre-fetched data means fewer tool calls

# ── Cached rules content ────────────────────────────────────────────
_banker_rules: str | None = None


def load_banker_rules() -> str:
    """Load and cache the banker rules from .cursor/rules/whatsapp-banker.mdc"""
    global _banker_rules
    if _banker_rules is not None:
        return _banker_rules

    try:
        with open(RULES_FILE, "r", encoding="utf-8") as f:
            content = f.read()
        # Strip YAML frontmatter (between --- markers)
        match = re.match(r"^---\n.*?\n---\n", content, re.DOTALL)
        if match:
            _banker_rules = content[match.end():].strip()
        else:
            _banker_rules = content.strip()
        print(f"[AI] Loaded banker rules ({len(_banker_rules)} chars)", file=sys.stderr)
    except FileNotFoundError:
        print(f"[WARN] Rules file not found: {RULES_FILE}", file=sys.stderr)
        _banker_rules = ""
    except Exception as e:
        print(f"[WARN] Failed to load rules: {e}", file=sys.stderr)
        _banker_rules = ""

    return _banker_rules

# ── Per-phone session locks ──────────────────────────────────────────
_phone_locks: dict[str, threading.Lock] = {}
_phone_locks_guard = threading.Lock()
_active_count = 0
_active_count_lock = threading.Lock()


def get_phone_lock(phone: str) -> threading.Lock:
    """Get or create a lock for a specific phone number."""
    with _phone_locks_guard:
        if phone not in _phone_locks:
            _phone_locks[phone] = threading.Lock()
        return _phone_locks[phone]


def cleanup_stale_locks():
    """Remove locks for phones that are no longer active."""
    with _phone_locks_guard:
        stale = [p for p, lock in _phone_locks.items() if not lock.locked()]
        for p in stale:
            del _phone_locks[p]


class ThreadingHTTPServer(ThreadingMixIn, HTTPServer):
    """HTTP server that handles each request in a new thread."""
    daemon_threads = True
    allow_reuse_address = True


class AiHandler(BaseHTTPRequestHandler):
    """Handle POST /ask requests with per-phone session isolation."""

    def do_POST(self):
        if self.path != "/ask":
            self._json_response(404, {"success": False, "message": "Not found"})
            return

        content_length = int(self.headers.get("Content-Length", 0))
        if content_length == 0 or content_length > MAX_BODY_BYTES:
            self._json_response(400, {
                "success": False,
                "message": f"Invalid request body (max {MAX_BODY_BYTES} bytes).",
            })
            return

        try:
            body = json.loads(self.rfile.read(content_length))
        except (json.JSONDecodeError, UnicodeDecodeError):
            self._json_response(400, {"success": False, "message": "Invalid JSON."})
            return

        prompt = (body.get("prompt") or "").strip()
        if not prompt:
            self._json_response(400, {"success": False, "message": "prompt is required."})
            return

        system_prompt = (body.get("system_prompt") or "").strip()
        max_chars = body.get("max_chars")
        phone_number = (body.get("phone_number") or "").strip()

        # Build the full prompt with banker rules injected
        banker_rules = load_banker_rules()
        full_prompt = ""
        if banker_rules:
            full_prompt += banker_rules + "\n\n---\n\n"
        if system_prompt:
            full_prompt += system_prompt + "\n\n"
        full_prompt += "CLIENT MESSAGE:\n" + prompt
        if max_chars and isinstance(max_chars, int) and max_chars > 0:
            full_prompt += f"\n\nIMPORTANT: Keep your response under {max_chars} characters. Be concise — under 200 words."

        if len(full_prompt) > MAX_PROMPT_LEN:
            self._json_response(400, {
                "success": False,
                "message": f"Combined prompt exceeds {MAX_PROMPT_LEN:,} characters (Cursor Agent CLI–aligned limit).",
            })
            return

        # ── Acquire per-phone lock ────────────────────────────────
        phone_lock = get_phone_lock(phone_number) if phone_number else None

        if phone_lock:
            if not phone_lock.acquire(timeout=5):
                self._json_response(429, {
                    "success": False,
                    "message": "A message from this guest is already being processed.",
                    "retry_after": 30,
                })
                return

        global _active_count
        with _active_count_lock:
            _active_count += 1
            current = _active_count

        log_prefix = f"[{phone_number or 'no-phone'}]"
        t_start = time.monotonic()
        print(f"[AI] {log_prefix} Processing (active: {current})", file=sys.stderr)

        try:
            result = self._call_agent(full_prompt)
            elapsed = time.monotonic() - t_start
            self._handle_agent_result(result, log_prefix, elapsed)
        finally:
            with _active_count_lock:
                _active_count -= 1
            if phone_lock:
                phone_lock.release()

    def _call_agent(self, full_prompt: str):
        """Call the Agent CLI subprocess."""
        # Use current user's HOME directory for agent to write config/cache
        env = os.environ.copy()

        cmd = [
            AGENT_BIN, "-p",
            "--output-format", "text",
            "--model", "auto",
            "--force",
            "--trust",
            "--approve-mcps",
            "--workspace", PROJECT_DIR,
            full_prompt,
        ]

        try:
            return subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=TIMEOUT,
                cwd=PROJECT_DIR,
                env=env,
            )
        except subprocess.TimeoutExpired:
            self._json_response(504, {
                "success": False,
                "message": "Request timed out. Please try a simpler question.",
            })
            return None
        except Exception as e:
            print(f"[ERROR] subprocess failed: {e}", file=sys.stderr)
            self._json_response(503, {
                "success": False,
                "message": "AI assistant is temporarily unavailable.",
            })
            return None

    def _handle_agent_result(self, result, log_prefix: str, elapsed: float):
        """Process the agent subprocess result."""
        if result is None:
            return

        if result.returncode != 0:
            stderr_snippet = result.stderr[:300] if result.stderr else "(empty)"
            print(f"[ERROR] {log_prefix} agent exit {result.returncode}: {stderr_snippet}",
                  file=sys.stderr)
            self._json_response(503, {
                "success": False,
                "message": "AI assistant is temporarily unavailable.",
            })
            return

        answer = result.stdout.strip()
        if not answer:
            self._json_response(422, {
                "success": False,
                "message": "No response generated. Please rephrase your question.",
            })
            return

        print(f"[AI] {log_prefix} Done in {elapsed:.1f}s ({len(answer)} chars)",
              file=sys.stderr)
        self._json_response(200, {"success": True, "data": {"answer": answer}})

    def do_GET(self):
        """Health check with active session count."""
        if self.path == "/health":
            with _phone_locks_guard:
                active_phones = sum(1 for lock in _phone_locks.values() if lock.locked())
            self._json_response(200, {
                "status": "ok",
                "active_sessions": active_phones,
                "active_workers": _active_count,
            })
            return
        self._json_response(404, {"success": False, "message": "Not found"})

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors_headers()
        self.end_headers()

    def _json_response(self, status, data):
        body = json.dumps(data).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self._cors_headers()
        self.end_headers()
        self.wfile.write(body)

    def _cors_headers(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")

    def log_message(self, fmt, *args):
        print(f"[AI] {self.client_address[0]} - {fmt % args}", file=sys.stderr)


def main():
    os.chdir(PROJECT_DIR)

    # Pre-load banker rules at startup
    rules = load_banker_rules()
    rules_status = f"loaded ({len(rules)} chars)" if rules else "NOT FOUND"

    server = ThreadingHTTPServer(("127.0.0.1", PORT), AiHandler)
    print(f"[AI] Agentic Banking AI Assistant listening on 127.0.0.1:{PORT}")
    print(f"[AI] Project dir:  {PROJECT_DIR}")
    print(f"[AI] Rules file:   {RULES_FILE}")
    print(f"[AI] Rules status: {rules_status}")
    print(f"[AI] Agent bin:    {AGENT_BIN}")
    print(f"[AI] Model:        auto")
    print(f"[AI] Timeout:      {TIMEOUT}s | Max prompt: {MAX_PROMPT_LEN:,} chars | Max body: {MAX_BODY_BYTES:,} bytes")
    print(f"[AI] Threading:    enabled (per-phone session isolation)")

    def _cleanup_loop():
        while True:
            time.sleep(300)
            cleanup_stale_locks()

    cleanup_thread = threading.Thread(target=_cleanup_loop, daemon=True)
    cleanup_thread.start()

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[AI] Shutting down.")
        server.server_close()


if __name__ == "__main__":
    main()
