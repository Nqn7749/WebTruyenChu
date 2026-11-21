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
