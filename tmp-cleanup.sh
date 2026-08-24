#!/bin/bash
set -euo pipefail
cd /home/marke/code/projecten/finance
find resources/js/sparen -name '*.tsx' -print -delete
# Fix type import if it pointed at tsx module resolution
ls resources/js/sparen/components/*.vue | wc -l
ls resources/js/sparen/*.tsx 2>/dev/null || echo 'no root tsx left'
grep -n "KpiBreakdownModal" resources/js/sparen/kpiBreakdown.ts
grep -n react package.json vite.config.ts || true
