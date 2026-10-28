#!/bin/bash
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
cd "$DIR"

# Stop any running instances on port 6020
fuser -k 6020/tcp 2>/dev/null || true

# Start gunicorn with uvicorn workers
exec ./venv/bin/gunicorn -k uvicorn.workers.UvicornWorker app:app -b 127.0.0.1:6020 -w 2

