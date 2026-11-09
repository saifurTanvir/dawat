#!/usr/bin/env bash
# Sends one WhatsApp message to the owner through CallMeBot (free, personal use).
# Needs repository secrets WHATSAPP_NOTIFY_PHONE (8801XXXXXXXXX) and CALLMEBOT_APIKEY.
# Without them it does nothing, so deploys never fail because of a message.
set -u
MESSAGE="${1:?message required}"
if [ -z "${WHATSAPP_NOTIFY_PHONE:-}" ] || [ -z "${CALLMEBOT_APIKEY:-}" ]; then
  echo "WhatsApp secrets not set; skipping message."
  exit 0
fi
reply=$(curl -sS --max-time 30 --get "https://api.callmebot.com/whatsapp.php" \
  --data-urlencode "phone=${WHATSAPP_NOTIFY_PHONE}" \
  --data-urlencode "text=${MESSAGE}" \
  --data-urlencode "apikey=${CALLMEBOT_APIKEY}") || { echo "::warning::WhatsApp message could not be sent"; exit 0; }
if echo "$reply" | grep -qiE "queued|sent"; then
  echo "WhatsApp message queued."
else
  echo "::warning::CallMeBot did not accept the message: $(echo "$reply" | sed 's/<[^>]*>//g' | tr -s ' \n' ' ' | cut -c1-300)"
fi
