#!/bin/sh
set -eu

cd /var/www/html

remove_hot_file() {
    rm -f public/hot
}

trap remove_hot_file EXIT INT TERM
remove_hot_file

package_lock_hash="$(sha256sum package-lock.json | awk '{print $1}')"
package_marker="node_modules/.package-lock-hash"
if [ ! -f "$package_marker" ] || [ "$(cat "$package_marker")" != "$package_lock_hash" ]; then
    npm ci
    printf '%s\n' "$package_lock_hash" > "$package_marker"
fi

exec npm run dev -- --host 0.0.0.0 --port "${VITE_PORT:-5173}"
