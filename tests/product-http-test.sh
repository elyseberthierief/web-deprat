#!/usr/bin/env bash
set -eu
wp="php /tmp/wp-cli.phar"
wp_path="/tmp/deprat-wp"
base="http://127.0.0.1:8099"
product_id="$($wp post list --post_type=product --name=tube-de-test --field=ID --path="$wp_path")"
test -n "$product_id"

php -S 127.0.0.1:8099 -t "$wp_path" >/tmp/deprat-wp-server.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT
curl -fsS --retry 12 --retry-delay 1 --retry-connrefused "$base/?post_type=product&p=$product_id" -o /tmp/deprat-product.html
for expected in "deprat-product-breadcrumb" "Vue 3D" "Réf. :" "Informations techniques" "Acier galvanisé" "NF EN 12194" "Couple de destruction" "192 pièces" "Schéma dimensionnel personnalisé" "Notice complémentaire" "Télécharger la fiche technique PDF" "product-technical-front.css"; do
    grep -Fq "$expected" /tmp/deprat-product.html || { echo "Boutique : élément manquant : $expected" >&2; exit 1; }
done
echo "La page produit Astra contient les informations techniques."
curl -fsS "$base/wp-content/uploads/integration-test/fiche.pdf" -o /tmp/deprat-test.pdf
test "$(head -c 4 /tmp/deprat-test.pdf)" = "%PDF" || { echo "Le PDF téléchargé ne correspond pas au fichier attendu." >&2; exit 1; }
echo "Le PDF est téléchargeable."

status="$(curl -sS -b 'wordpress_test_cookie=WP%20Cookie%20check' -c /tmp/deprat-cookies -o /tmp/deprat-login.html -w '%{http_code}' \
    --data-urlencode 'log=deprat-test' --data-urlencode 'pwd=deprat-test' --data-urlencode 'wp-submit=Log In' \
    --data-urlencode "redirect_to=$base/wp-admin/" --data-urlencode 'testcookie=1' "$base/wp-login.php")"
if [ "$status" != 302 ]; then
    echo "Connexion au compte de test : réponse HTTP $status." >&2
    grep -E 'login_error|message|Erreur|error' /tmp/deprat-login.html | head -5 >&2 || true
    exit 1
fi
# L’assistant de première installation ne concerne pas un site déjà configuré.
$wp transient delete _wc_activation_redirect --path="$wp_path" >/dev/null 2>&1 || true
admin_status="$(curl -sS -b /tmp/deprat-cookies -D /tmp/deprat-admin-headers -o /tmp/deprat-admin.html -w '%{http_code}' "$base/wp-admin/post.php?post=$product_id&action=edit")"
if [ "$admin_status" != 200 ]; then
    echo "Page d’administration : réponse HTTP $admin_status." >&2
    grep -i '^location:' /tmp/deprat-admin-headers >&2 || true
    exit 1
fi
for expected in "Fiche technique DEPRAT" "Acier galvanisé" "Type de conditionnement" "Fiches techniques PDF" "Visualisation 3D" "product-technical-admin.js" "product-technical-admin.css"; do
    grep -Fq "$expected" /tmp/deprat-admin.html || { echo "Administration : élément manquant : $expected" >&2; exit 1; }
done
echo "Pages Astra de la boutique et de l’administration, et téléchargement du PDF vérifiés."
node "$GITHUB_WORKSPACE/tests/product-admin-real-browser.js" "$product_id"
