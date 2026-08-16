#!/bin/bash
npx concurrently \
  -n "SERVE,VITE,MAILPIT,STRIPE,QUEUE,SCHEDULE" \
  -c "blue,green,yellow,magenta,cyan,white" \
  "php artisan serve" \
  "npm run dev" \
  "mailpit" \
  # "stripe listen --forward-to localhost:8000/webhooks/stripe" \
#   "php artisan queue:work" \
#   "php artisan schedule:work"