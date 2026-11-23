# Authorized HTML crawler

The admin crawler only accepts HTTPS sources on one configured host. Add a source only when the site owner/licensor permits automated collection and local storage of the selected metadata and chapter text.

## Setup

1. Run `php artisan migrate`.
2. Run a queue worker: `php artisan queue:work`.
3. Run the Laravel scheduler every minute: `php artisan schedule:work` (or configure the server cron for `php artisan schedule:run`).
4. Open Admin → Crawler nguồn truyện.
5. Add the HTTPS base URL and listing URL, set the XPath expressions for story links, story title, optional author/description, chapter links, chapter title and chapter content.
6. Confirm permission and enable the source, then use **Chạy ngay** to test.

Each run is limited to 20 stories and 50 chapters per story. New stories and chapters are imported as unpublished drafts. An administrator must review and publish them manually. Imported story/chapter source URLs are tracked to prevent repeated imports. Enabled, permission-confirmed sources are scheduled every six hours.

## XPath example

For HTML such as `<a class="story" href="/story/one">...</a>`, use `//a[contains(concat(" ", normalize-space(@class), " "), " story ")]`. For chapter text, target a stable container such as `//div[@id="chapter-content"]`.

The crawler does not follow redirects, rejects non-HTTPS URLs, other hosts, private/reserved IPs, oversized pages and invalid XPath. It does not bypass login, paywalls, anti-bot controls or access restrictions.

## Shared allowlist with "Nhập truyện từ URL"

The crawler and the URL importer share one allowlist and one hardened HTTP client.

- When `STORY_IMPORT_REQUIRE_ALLOWLIST=true`, a crawler source's host must be listed in `config/story_import.php` (`sources`). The form refuses other hosts, and a run (manual or scheduled) for a host that is not listed, or was removed later, is cancelled before any request is sent.
- Pages are fetched with the importer's client: every resolved IPv4/IPv6 address must be public, the validated IP is pinned for the request (needs `ext-curl`), redirects are refused, only HTML/XML is accepted and pages are capped at 2 MB.
- The crawler keeps its own rule that every URL must stay on the source's configured host.
- With the allowlist off (default) behaviour is unchanged except for the stronger network checks above.
