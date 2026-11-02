#!/usr/bin/env bash
# Regenerates nginx/cloudflare.conf from Cloudflare's published IP ranges.
set -euo pipefail
cd "$(dirname "$0")"
{
  echo "# Restore the visitor IP from Cloudflare. Refresh with docker/production/update-cloudflare-ips.sh"
  echo "real_ip_header CF-Connecting-IP;"
  for list in ips-v4 ips-v6; do
    curl -fsS "https://www.cloudflare.com/${list}" | sed 's/^/set_real_ip_from /; s/$/;/'
  done
} > nginx/cloudflare.conf.tmp
mv nginx/cloudflare.conf.tmp nginx/cloudflare.conf
echo "Updated nginx/cloudflare.conf"
