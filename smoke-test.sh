#!/usr/bin/env bash
set -euo pipefail
python3 "$(dirname "$0")/smoke-test.py" "${1:-http://localhost:15001}"
